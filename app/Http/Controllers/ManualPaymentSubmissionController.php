<?php

namespace App\Http\Controllers;

use App\Models\ManualPaymentOtp;
use App\Models\ManualPaymentSubmission;
use App\Models\SubscriptionOrder;
use App\Services\ManualPaymentAdminNotifier;
use App\Services\ManualPaymentProofStorage;
use App\Services\ManualPaymentReadiness;
use App\Services\SubscriptionOrderQuoteValidator;
use App\Support\EmailDeliveryReadiness;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ManualPaymentSubmissionController extends Controller
{
    public function store(
        Request $request,
        SubscriptionOrder $order,
        SubscriptionOrderQuoteValidator $quotes,
        ManualPaymentProofStorage $proofs,
        ManualPaymentReadiness $readiness,
        EmailDeliveryReadiness $emailReadiness,
        ManualPaymentAdminNotifier $notifier,
    ): RedirectResponse {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        if (!$readiness->isReady()) {
            throw ValidationException::withMessages([
                'proof' => 'Le paiement manuel est temporairement indisponible : la configuration de sécurité du serveur doit être vérifiée par l’administrateur.',
            ]);
        }

        $methods = (array) config('payments.manual_methods', []);
        $data = $request->validate([
            'method' => ['required', 'string', Rule::in(array_keys($methods))],
            'manual_payment_otp' => ['required', 'digits:8'],
            'transaction_reference' => ['required', 'string', 'min:5', 'max:191'],
            'declared_amount' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'transaction_occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'proof' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf'],
            'payment_acknowledgement' => ['accepted'],
        ]);

        $reference = trim($data['transaction_reference']);
        if ($data['method'] === 'usdt_bep20') {
            if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $reference)) {
                throw ValidationException::withMessages([
                    'transaction_reference' => 'Pour USDT BEP20, indiquez le hash de transaction complet (0x suivi de 64 caractères hexadécimaux).',
                ]);
            }
            $reference = strtolower($reference);
        } else {
            if (!preg_match('/^[A-Za-z0-9._-]{5,191}$/', $reference)) {
                throw ValidationException::withMessages([
                    'transaction_reference' => 'La référence de transaction contient des caractères non acceptés.',
                ]);
            }
            $reference = strtoupper($reference);
        }

        $declaredCents = $this->amountToCents((string) $data['declared_amount']);
        if ($declaredCents === null || $declaredCents <= 0) {
            throw ValidationException::withMessages([
                'declared_amount' => 'Indiquez un montant positif avec au plus deux décimales.',
            ]);
        }
        $declaredAmount = $this->formatCents($declaredCents);
        $storedProof = null;

        try {
            $result = DB::transaction(function () use (
                $request,
                $order,
                $quotes,
                $data,
                $reference,
                $declaredCents,
                $declaredAmount,
                $proofs,
                &$storedProof,
            ): array {
                $lockedOrder = SubscriptionOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $lockedOrder->user_id === (int) $request->user()->id
                    && in_array($lockedOrder->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED'], true)
                    && $quotes->canPayNow($lockedOrder), 409,
                    'Cette commande doit être recalculée avant le paiement manuel.');
                abort_if($lockedOrder->payments()->where('status', 'PENDING')->exists(), 409, 'Un paiement sécurisé est déjà en attente pour cette commande.');
                abort_if($lockedOrder->manualPaymentSubmissions()->where('status', 'PENDING')->exists(), 409, 'Une transaction est déjà en cours de vérification pour cette commande.');

                $otp = ManualPaymentOtp::where('subscription_order_id', $lockedOrder->id)
                    ->where('user_id', $request->user()->id)
                    ->where('method', $data['method'])
                    ->whereNull('used_at')
                    ->whereNull('invalidated_at')
                    ->where('expires_at', '>', now())
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

                if (!$otp) {
                    return ['otp_error' => 'expired'];
                }
                if ($otp->failed_attempts >= 5) {
                    return ['otp_error' => 'locked'];
                }
                if (!Hash::check((string) $data['manual_payment_otp'], $otp->code_hash)) {
                    $otp->increment('failed_attempts');

                    return ['otp_error' => 'invalid'];
                }

                $orderAmount = $otp->currency === 'USDT'
                    ? (string) $lockedOrder->amount_usd
                    : (string) $lockedOrder->amount_xof;
                $orderAmountCents = $this->amountToCents($orderAmount);
                $otpAmountCents = $this->amountToCents((string) $otp->expected_amount);
                if ($orderAmountCents === null || $otpAmountCents !== $orderAmountCents) {
                    throw ValidationException::withMessages([
                        'manual_payment' => 'Le code OTP ne correspond plus au montant enregistré sur cette commande. Générez un nouveau code.',
                    ]);
                }

                if (ManualPaymentSubmission::where('method', $data['method'])
                    ->whereRaw('LOWER(transaction_reference) = ?', [strtolower($reference)])->exists()) {
                    throw ValidationException::withMessages([
                        'transaction_reference' => 'Cette référence de transaction a déjà été soumise.',
                    ]);
                }

                $methods = (array) config('payments.manual_methods', []);
                $method = $methods[$data['method']];
                $currency = strtoupper((string) ($method['currency'] ?? ''));
                abort_unless($currency === $otp->currency
                    && hash_equals((string) $method['recipient'], (string) $otp->recipient_snapshot), 409);

                $storedProof = $proofs->store($data['proof']);
                $submission = ManualPaymentSubmission::create([
                    'subscription_order_id' => $lockedOrder->id,
                    'user_id' => $request->user()->id,
                    'manual_payment_otp_id' => $otp->id,
                    'method' => $data['method'],
                    'recipient_snapshot' => $otp->recipient_snapshot,
                    'expected_amount' => $otp->expected_amount,
                    'declared_amount' => $declaredAmount,
                    'currency' => $otp->currency,
                    'transaction_reference' => $reference,
                    'transaction_occurred_at' => CarbonImmutable::parse((string) $data['transaction_occurred_at'], config('app.timezone', 'UTC'))->utc(),
                    'proof_path' => $storedProof['path'],
                    'proof_sha256' => $storedProof['sha256'],
                    'status' => 'PENDING',
                    'admin_email_status' => 'BLOCKED',
                    'customer_acknowledged_at' => now(),
                    'customer_terms_version' => (string) config('legal.manual_payment_terms_version', 'manual-payment-v1'),
                ]);

                $otp->update(['used_at' => now()]);

                DB::table('audit_logs')->insert([
                    'user_id' => $request->user()->id,
                    'action' => 'manual_payment.submitted',
                    'entity_type' => ManualPaymentSubmission::class,
                    'entity_id' => $submission->id,
                    'old_values' => null,
                    'new_values' => json_encode([
                        'status' => 'PENDING',
                        'subscription_order_id' => $lockedOrder->id,
                        'otp_id' => $otp->id,
                        'method' => $data['method'],
                        'expected_amount' => $otp->expected_amount,
                        'declared_amount' => $declaredAmount,
                        'currency' => $otp->currency,
                        'transaction_reference' => $reference,
                        'transaction_occurred_at' => $submission->transaction_occurred_at?->toIso8601String(),
                        'proof_sha256' => $storedProof['sha256'],
                        'customer_acknowledged_at' => $submission->customer_acknowledged_at?->toIso8601String(),
                        'customer_terms_version' => $submission->customer_terms_version,
                    ], JSON_THROW_ON_ERROR),
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return ['submission' => $submission];
            });

            if (isset($result['otp_error'])) {
                $message = match ($result['otp_error']) {
                    'locked' => 'Ce code OTP a atteint sa limite d’essais. Générez un nouveau code de transaction.',
                    'invalid' => 'Le code OTP est incorrect. Vérifiez le code affiché ou générez-en un nouveau.',
                    default => 'Le code OTP a expiré ou a déjà été utilisé. Générez un nouveau code de transaction.',
                };

                return back()->withErrors(['manual_payment_otp' => $message])
                    ->withInput($request->except('manual_payment_otp'));
            }
        } catch (Throwable $exception) {
            if (is_array($storedProof) && is_string($storedProof['path'] ?? null)) {
                $proofs->delete($storedProof['path']);
            }

            if (!$exception instanceof QueryException) {
                throw $exception;
            }

            if (ManualPaymentSubmission::where('method', $data['method'])
                ->whereRaw('LOWER(transaction_reference) = ?', [strtolower($reference)])->exists()) {
                throw ValidationException::withMessages([
                    'transaction_reference' => 'Cette référence de transaction a déjà été soumise.',
                ]);
            }

            throw $exception;
        }

        /** @var ManualPaymentSubmission $submission */
        $submission = $result['submission'];
        $emailSent = $notifier->send($submission, $emailReadiness);
        $message = $emailSent
            ? 'Demande enregistrée et e-mail envoyé à l’administrateur. Le paiement reste en attente de vérification réelle.'
            : 'Demande enregistrée dans la file sécurisée. L’e-mail à l’administrateur n’a pas été confirmé; aucun abonnement ne sera activé avant la vérification des fonds.';

        return redirect()->route('subscriptions.orders.show', $order)->with('status', $message);
    }

    private function amountToCents(string $amount): ?int
    {
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $amount, $parts)) {
            return null;
        }

        return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    private function formatCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
