<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\Payments\CreateCheckoutRequest;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SubscriptionCheckoutService
{
    public function start(
        User $user,
        SubscriptionPlan $plan,
        string $idempotencyKey,
        PaymentProviderInterface $provider,
        ?SubscriptionOrder $order = null,
        ?SubscriptionOrderQuoteValidator $quoteValidator = null,
    ): Payment
    {
        if (!$plan->status || $user->status !== 'ACTIVE') {
            abort(404);
        }
        if ($order && ((int) $order->user_id !== (int) $user->id || (int) $order->subscription_plan_id !== (int) $plan->id
            || !in_array($order->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED', 'PAYMENT_PENDING'], true)
            || !($quoteValidator ?? app(SubscriptionOrderQuoteValidator::class))->canPayNow($order)
            || $order->quote_expires_at->isPast())) {
            throw new RuntimeException('This order does not have a valid, unexpired XOF quote.');
        }

        if (!$order) {
            throw new RuntimeException('A server-priced order with a verified exchange-rate quote is required.');
        }
        $paymentAmount = (string) $order->amount_xof;
        $paymentCurrency = 'XOF';

        if (!$provider->supportsCheckout($paymentCurrency, $paymentAmount)) {
            throw new RuntimeException('The selected payment provider does not support this offer currency or amount.');
        }

        $payment = DB::transaction(function () use ($user, $plan, $idempotencyKey, $provider, $order, $paymentAmount, $paymentCurrency, $quoteValidator) {
            $existing = Payment::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->user_id === $user->id
                    && $existing->subscription?->subscription_plan_id === $plan->id
                    && (!$order || $existing->subscription_order_id === $order->id), 409);
                abort_unless($existing->status === 'PENDING', 409, 'Ce paiement ne peut plus être repris.');

                return $existing;
            }

            if ($order) {
                $lockedOrder = SubscriptionOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if (!in_array($lockedOrder->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED'], true)
                    || !($quoteValidator ?? app(SubscriptionOrderQuoteValidator::class))->canPayNow($lockedOrder)) {
                    abort(409, 'Cette commande doit être actualisée avec le taux fixe de 600 XOF par USD avant paiement.');
                }
                $order = $lockedOrder;
                $paymentAmount = (string) $lockedOrder->amount_xof;
                if (!$provider->supportsCheckout('XOF', $paymentAmount)) {
                    throw new RuntimeException('The selected payment provider does not support this amount.');
                }
            }

            $subscription = Subscription::where('user_id', $user->id)
                ->where('subscription_plan_id', $plan->id)
                ->where('status', 'ACTIVE')
                ->where(fn ($query) => $query->whereNull('admin_override')->orWhere('admin_override', '!=', 'DISABLED'))
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->orderByDesc('ends_at')->lockForUpdate()->first();
            $isRenewal = $subscription !== null;

            if (!$subscription) {
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $plan->id,
                    'status' => 'PENDING',
                    'plan_name_snapshot' => $order?->plan_name_snapshot ?? $plan->name,
                    'plan_price_snapshot' => $order?->amount_usd ?? $plan->price,
                    'plan_currency_snapshot' => $order ? 'USD' : strtoupper($plan->currency ?: config('payments.currency', 'USD')),
                    'plan_duration_days_snapshot' => $order?->duration_days_snapshot ?? $plan->duration_days,
                    'plan_max_mt5_accounts_snapshot' => $order?->max_mt5_accounts_snapshot ?? $plan->max_mt5_accounts,
                    'plan_capital_required_snapshot' => $order?->capital_required_snapshot ?? $plan->capital_required,
                ]);
            } else {
                // Reuse an unfinished checkout for the same entitlement instead of
                // opening multiple concurrent renewal invoices.
                $pendingRenewal = Payment::where('subscription_id', $subscription->id)
                    ->where('status', 'PENDING')->latest('id')->lockForUpdate()->first();
                if ($pendingRenewal) {
                    if ($order && (int) $pendingRenewal->subscription_order_id !== (int) $order->id) {
                        abort(409, 'Un autre paiement de renouvellement est déjà en attente pour cet abonnement.');
                    }
                    return $pendingRenewal;
                }
            }

            return Payment::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'provider' => $provider->name(),
                'amount' => $paymentAmount,
                'currency' => $paymentCurrency,
                'status' => 'PENDING',
                'subscription_order_id' => $order?->id,
                'idempotency_key' => $idempotencyKey,
                'is_renewal' => $isRenewal,
                'metadata' => $order ? [
                    'order_id' => $order->id,
                    'reference_usd_amount' => $order->amount_usd,
                    'fx_rate_usd_xof' => $order->fx_rate,
                    'fx_rate_as_of' => $order->fx_rate_as_of?->toDateString(),
                    'fx_rate_updated_at' => $order->fx_rate_updated_at?->toIso8601String(),
                    'fx_provider' => $order->fx_provider,
                    'fx_source_url' => $order->fx_source_url,
                ] : null,
            ]);
        });

        if ($order && $order->status !== 'PAYMENT_PENDING') {
            $order->update(['status' => 'PAYMENT_PENDING']);
        }

        if ($payment->checkout_url) {
            return $payment;
        }

        try {
            $session = $provider->createCheckout(new CreateCheckoutRequest(
                idempotencyKey: (string) ($payment->idempotency_key ?: $idempotencyKey),
                amount: (string) $payment->amount,
                currency: $payment->currency,
                successUrl: $order ? route('subscriptions.orders.show', $order) : config('payments.success_url'),
                cancelUrl: $order
                    ? route('subscriptions.orders.show', $order).'?checkout=cancelled'
                    : config('payments.cancel_url'),
                metadata: ['payment_id' => $payment->id, 'subscription_id' => $payment->subscription_id],
                customerName: trim($user->first_name.' '.$user->last_name),
                customerEmail: $user->email,
                customerPhone: $user->phone,
            ));

            $scheme = parse_url($session->redirectUrl, PHP_URL_SCHEME);
            if (!$session->transactionId || !filter_var($session->redirectUrl, FILTER_VALIDATE_URL) || $scheme !== 'https') {
                throw new RuntimeException('The payment provider returned an invalid checkout session.');
            }

            $payment->forceFill([
                'transaction_id' => $session->transactionId,
                'checkout_url' => $session->redirectUrl,
            ])->save();
        } catch (Throwable $exception) {
            DB::transaction(function () use ($payment, $order) {
                $payment->refresh();
                if ($payment->status === 'PENDING') {
                    $payment->update(['status' => 'FAILED']);
                    $payment->subscription()->where('status', 'PENDING')->update(['status' => 'CANCELLED']);
                }
                if ($order && $order->fresh()->status === 'PAYMENT_PENDING') {
                    $order->update(['status' => 'PAYMENT_FAILED']);
                }
            });

            throw $exception;
        }

        return $payment->fresh();
    }

}
