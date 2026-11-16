<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentProviderUnavailable;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Services\Fx\FixedUsdXofRateService;
use App\Services\ManualPaymentReadiness;
use App\Services\SubscriptionOrderQuoteValidator;
use App\Services\PaymentProviderFactory;
use App\Services\SubscriptionCheckoutService;
use App\Services\SubscriptionOrderCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class SubscriptionOrderController extends Controller
{
    public function store(
        Request $request,
        SubscriptionPlan $plan,
        SubscriptionOrderCreator $orders,
        PaymentProviderFactory $providers,
        SubscriptionCheckoutService $checkout,
    ): RedirectResponse {
        $data = $request->validate(['idempotency_key' => ['required', 'uuid']]);
        $plan = SubscriptionPlan::whereKey($plan->id)->where('status', true)
            ->whereIn('slug', SubscriptionPlan::PUBLIC_CATALOGUE_SLUGS)->firstOrFail();
        if (strtoupper($plan->currency) !== 'USD' || (float) $plan->price <= 0) {
            throw ValidationException::withMessages([
                'order' => 'Cette offre n’est pas publiable en commande : le tarif de référence doit être un montant USD positif.',
            ]);
        }

        $existing = SubscriptionOrder::where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) {
            abort_unless((int) $existing->user_id === (int) $request->user()->id
                && (int) $existing->subscription_plan_id === (int) $plan->id, 409);

            return $this->startCheckoutOrSummary($request, $existing, $data['idempotency_key'], $providers, $checkout);
        }

        $order = DB::transaction(fn () => $orders->create(
            $request->user(),
            $plan,
            $data['idempotency_key'],
        ));

        return $this->startCheckoutOrSummary($request, $order, $data['idempotency_key'], $providers, $checkout);
    }

    public function refreshQuote(
        Request $request,
        SubscriptionOrder $order,
        FixedUsdXofRateService $rates,
    ): RedirectResponse {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        abort_unless(in_array($order->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED'], true), 409);

        $rate = $rates->refresh();
        $amountXof = $rate?->amountXof((string) $order->amount_usd);
        $usable = $rate?->canBeUsedForCheckout() && $amountXof !== null;

        $order->update([
            'amount_xof' => $amountXof,
            'fx_rate' => $rate?->rate,
            'fx_rate_as_of' => $rate?->asOf->toDateString(),
            'fx_rate_updated_at' => $rate?->updatedAt,
            'fx_provider' => $rate?->provider,
            'fx_source_url' => $rate?->sourceUrl !== '' ? $rate?->sourceUrl : null,
            'fx_fetched_at' => $rate?->fetchedAt,
            'quote_expires_at' => $usable
                ? now()->addMinutes(max(1, (int) config('fx.quote_valid_minutes', 30)))
                : null,
        ]);

        return redirect()->route('subscriptions.orders.show', $order)
            ->with($usable ? 'status' : 'fx_quote_unavailable', $usable
                ? 'Le montant a été recalculé avec le taux fixe de 600 XOF pour 1 USD.'
                : 'Le montant ne peut pas être converti avec le taux fixe configuré; aucun paiement ne peut être lancé.');
    }

    public function prepareBeforeEmailVerification(
        Request $request,
        SubscriptionPlan $plan,
        SubscriptionOrderCreator $orders,
    ): RedirectResponse {
        abort_unless($request->user()->role === 'INVESTOR', 403);
        abort_if($request->user()->hasVerifiedEmail(), 409, 'Votre adresse est déjà vérifiée. Choisissez la formule depuis le catalogue.');

        $plan = SubscriptionPlan::whereKey($plan->id)->where('status', true)
            ->whereIn('slug', SubscriptionPlan::PUBLIC_CATALOGUE_SLUGS)->firstOrFail();
        if (strtoupper((string) $plan->currency) !== 'USD' || (float) $plan->price <= 0) {
            throw ValidationException::withMessages([
                'order' => 'Cette formule ne peut pas être commandée en ligne pour le moment.',
            ]);
        }

        $order = DB::transaction(function () use ($request, $plan, $orders): SubscriptionOrder {
            $existing = SubscriptionOrder::where('user_id', $request->user()->id)
                ->where('subscription_plan_id', $plan->id)
                ->where('status', 'AWAITING_PAYMENT')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            return $existing ?: $orders->create($request->user(), $plan);
        });

        $request->session()->put('checkout.order_id', $order->id);

        return redirect()->route('verification.notice')
            ->with('status', 'Formule retenue. Confirmez votre adresse e-mail pour ouvrir le récapitulatif du paiement; aucun paiement n’a été effectué.');
    }

    public function show(
        Request $request,
        SubscriptionOrder $order,
        PaymentProviderFactory $providers,
        ManualPaymentReadiness $manualPaymentReadiness,
        SubscriptionOrderQuoteValidator $quotes,
    ): View {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        $order->load(['plan', 'latestPayment', 'manualPaymentSubmissions']);

        $providerAvailable = false;
        $providerName = trim((string) config('payments.default_provider'));
        if ($quotes->canPayNow($order) && $providerName !== '') {
            try {
                $providerAvailable = $providers->make($providerName)
                    ->supportsCheckout('XOF', (string) $order->amount_xof);
            } catch (PaymentProviderUnavailable) {
                $providerAvailable = false;
            }
        }

        $latestManualSubmission = $order->manualPaymentSubmissions->sortByDesc('id')->first();
        $manualPaymentReady = $manualPaymentReadiness->isReady();
        $manualPaymentAvailable = in_array($order->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED'], true)
            && $quotes->canPayNow($order)
            && $manualPaymentReady
            && !$order->payments()->where('status', 'PENDING')->exists()
            && $latestManualSubmission?->status !== 'PENDING';

        return view('public.order-summary', [
            'order' => $order,
            'providerAvailable' => $providerAvailable,
            'providerName' => $providerName,
            'paymentAttempt' => $order->latestPayment,
            'manualPaymentMethods' => config('payments.manual_methods', []),
            'manualPaymentAvailable' => $manualPaymentAvailable,
            'manualPaymentReady' => $manualPaymentReady,
            'latestManualSubmission' => $latestManualSubmission,
            'crypto' => config('payments.crypto'),
        ]);
    }

    public function checkout(
        Request $request,
        SubscriptionOrder $order,
        PaymentProviderFactory $providers,
        SubscriptionCheckoutService $checkout,
    ): RedirectResponse {
        $data = $request->validate(['idempotency_key' => ['required', 'uuid']]);
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);

        try {
            $providerName = trim((string) config('payments.default_provider'));
            if ($providerName === '') {
                throw new PaymentProviderUnavailable('No payment provider is configured.');
            }
            $provider = $providers->make($providerName);
            $payment = $checkout->start(
                $request->user(),
                $order->plan()->firstOrFail(),
                $data['idempotency_key'],
                $provider,
                $order,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'payment' => 'Le paiement ne peut pas être lancé : vérifiez le taux fixe enregistré, la commande et la configuration réelle du compte marchand.',
            ]);
        }

        return redirect()->away($payment->checkout_url);
    }

    private function startCheckoutOrSummary(
        Request $request,
        SubscriptionOrder $order,
        string $idempotencyKey,
        PaymentProviderFactory $providers,
        SubscriptionCheckoutService $checkout,
    ): RedirectResponse {
        try {
            $providerName = trim((string) config('payments.default_provider'));
            if ($providerName === '') {
                throw new PaymentProviderUnavailable('No payment provider is configured.');
            }

            $provider = $providers->make($providerName);
            $payment = $checkout->start(
                $request->user(),
                $order->plan()->firstOrFail(),
                $idempotencyKey,
                $provider,
                $order,
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            throw $exception;
        } catch (RuntimeException) {
            return redirect()->route('subscriptions.orders.show', $order)
                ->with('payment_unavailable', 'Le paiement sécurisé n’est pas disponible pour le moment. Votre commande est conservée et aucun débit n’a été lancé.');
        }

        return redirect()->away($payment->checkout_url);
    }
}
