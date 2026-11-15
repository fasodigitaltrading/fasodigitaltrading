<?php

namespace App\Http\Controllers;

use App\Models\ManualPaymentOtp;
use App\Models\SubscriptionOrder;
use App\Services\ManualPaymentReadiness;
use App\Services\SubscriptionOrderQuoteValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ManualPaymentOtpController extends Controller
{
    public function store(
        Request $request,
        SubscriptionOrder $order,
        SubscriptionOrderQuoteValidator $quotes,
        ManualPaymentReadiness $readiness,
    ): RedirectResponse {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        if (!$readiness->isReady()) {
            throw ValidationException::withMessages([
                'manual_payment' => 'Le paiement manuel est temporairement indisponible : la configuration sécurisée du serveur doit être vérifiée.',
            ]);
        }

        $data = $request->validate([
            'method' => ['required', 'string', Rule::in(array_keys((array) config('payments.manual_methods', [])))],
        ]);

        $methods = (array) config('payments.manual_methods', []);
        $method = $methods[$data['method']];
        $currency = strtoupper((string) ($method['currency'] ?? ''));
        $code = str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);

        $otp = DB::transaction(function () use ($request, $order, $quotes, $method, $currency, $data, $code): ManualPaymentOtp {
            $lockedOrder = SubscriptionOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            abort_unless((int) $lockedOrder->user_id === (int) $request->user()->id
                && in_array($lockedOrder->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED'], true)
                && $quotes->canPayNow($lockedOrder), 409,
                'Cette commande doit être actualisée avant de générer un code de paiement.');
            abort_if($lockedOrder->payments()->where('status', 'PENDING')->exists(), 409, 'Un paiement est déjà en attente pour cette commande.');
            abort_if($lockedOrder->manualPaymentSubmissions()->where('status', 'PENDING')->exists(), 409, 'Une transaction est déjà en cours de vérification.');

            $amountSource = $currency === 'USDT'
                ? (string) $lockedOrder->amount_usd
                : (string) $lockedOrder->amount_xof;
            $amountCents = $this->amountToCents($amountSource);
            abort_unless($amountCents !== null && $currency !== '', 409, 'Le montant ou la devise de cette commande est invalide.');
            $amount = intdiv($amountCents, 100).'.'.str_pad((string) ($amountCents % 100), 2, '0', STR_PAD_LEFT);

            ManualPaymentOtp::where('subscription_order_id', $lockedOrder->id)
                ->whereNull('used_at')
                ->whereNull('invalidated_at')
                ->update(['invalidated_at' => now()]);

            $otp = ManualPaymentOtp::create([
                'subscription_order_id' => $lockedOrder->id,
                'user_id' => $request->user()->id,
                'method' => $data['method'],
                'recipient_snapshot' => (string) $method['recipient'],
                'expected_amount' => $amount,
                'currency' => $currency,
                'code_hash' => Hash::make($code),
                'code_ciphertext' => Crypt::encryptString($code),
                'expires_at' => now()->addMinutes(30),
            ]);

            DB::table('audit_logs')->insert([
                'user_id' => $request->user()->id,
                'action' => 'manual_payment.otp_issued',
                'entity_type' => ManualPaymentOtp::class,
                'entity_id' => $otp->id,
                'old_values' => null,
                'new_values' => json_encode([
                    'subscription_order_id' => $lockedOrder->id,
                    'method' => $data['method'],
                    'expected_amount' => $amount,
                    'currency' => $currency,
                    'expires_at' => $otp->expires_at?->toIso8601String(),
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $otp;
        });

        return redirect()->route('subscriptions.orders.show', $order)
            ->with('manual_payment_otp', Crypt::encryptString(json_encode([
                'order_id' => $order->id,
                'otp_id' => $otp->id,
                'code' => $code,
            ], JSON_THROW_ON_ERROR)))
            ->with('status', 'Code OTP de transaction généré. Il est valable 30 minutes et ne sert qu’à associer votre demande à la commande.');
    }

    private function amountToCents(string $amount): ?int
    {
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $amount, $parts)) {
            return null;
        }

        return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
    }
}
