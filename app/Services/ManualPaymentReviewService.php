<?php

namespace App\Services;

use App\Models\ManualPaymentSubmission;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\Payments\VerifiedPaymentEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Illuminate\Support\Str;

final class ManualPaymentReviewService
{
    public function approve(
        int $submissionId,
        User $reviewer,
        string $verifiedAmount,
        ?string $verifiedTransactionHash,
        ?string $verifiedTokenContract,
        ?string $verifiedRecipient,
        ?int $verifiedConfirmations,
        string $reviewNote,
        ?string $ipAddress,
        ?string $userAgent,
        ManualPaymentProofStorage $proofStorage,
        ManualPaymentReadiness $readiness,
        SubscriptionOrderQuoteValidator $quotes,
    ): void {
        DB::transaction(function () use ($submissionId, $reviewer, $verifiedAmount, $verifiedTransactionHash, $verifiedTokenContract, $verifiedRecipient, $verifiedConfirmations, $reviewNote, $ipAddress, $userAgent, $proofStorage, $readiness, $quotes): void {
            $submission = ManualPaymentSubmission::whereKey($submissionId)->lockForUpdate()->firstOrFail();
            abort_unless($submission->status === 'PENDING' && $submission->payment_id === null, 409);
            if (!$proofStorage->matchesHash($submission->proof_path, $submission->proof_sha256)) {
                throw ValidationException::withMessages([
                    'manual_payment' => 'Le justificatif est absent ou a été modifié; le paiement ne peut pas être confirmé.',
                ]);
            }

            $expectedCents = $this->amountToCents((string) $submission->expected_amount);
            $verifiedCents = $this->amountToCents($verifiedAmount);
            if ($expectedCents === null || $verifiedCents === null || $expectedCents !== $verifiedCents) {
                throw ValidationException::withMessages([
                    'verified_amount' => 'Le montant réellement reçu doit correspondre exactement au montant attendu.',
                ]);
            }

            if (!$readiness->isReady()) {
                throw ValidationException::withMessages([
                    'manual_payment' => 'La configuration de sécurité du serveur doit être vérifiée avant de valider un paiement.',
                ]);
            }

            $order = SubscriptionOrder::whereKey($submission->subscription_order_id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($submission->user_id)->lockForUpdate()->firstOrFail();
            abort_unless($order->user_id === $user->id
                && $user->status === 'ACTIVE'
                && $user->email_verified_at !== null
                && in_array($order->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED'], true)
                && $quotes->hasValidSnapshot($order), 409);
            abort_if($order->payments()->where('status', 'PENDING')->exists(), 409, 'Un autre paiement est déjà en attente pour cette commande.');
            abort_if($order->payments()->where('status', 'PAID')->exists(), 409, 'Cette commande a déjà un paiement confirmé.');

            $expectedOrderAmount = $submission->currency === 'USDT'
                ? (string) $order->amount_usd
                : (string) $order->amount_xof;
            if ($this->amountToCents($expectedOrderAmount) !== $expectedCents) {
                throw ValidationException::withMessages([
                    'manual_payment' => 'Le montant du justificatif ne correspond plus au montant enregistré sur la commande.',
                ]);
            }

            $transactionHashToStore = null;
            $tokenContractToStore = null;
            $recipientToStore = null;
            $confirmationsToStore = null;
            if ($submission->method === 'usdt_bep20') {
                $transactionHashToStore = strtolower(trim((string) $verifiedTransactionHash));
                $tokenContractToStore = strtolower(trim((string) $verifiedTokenContract));
                $recipientToStore = strtolower(trim((string) $verifiedRecipient));
                $confirmationsToStore = $verifiedConfirmations;
                $expectedContract = strtolower((string) config('payments.crypto.token_contract'));
                $expectedRecipient = strtolower((string) config('payments.manual_methods.usdt_bep20.recipient'));
                $minimumConfirmations = max(1, (int) config('payments.crypto.minimum_confirmations', 15));

                if (preg_match('/^0x[a-f0-9]{64}$/', $transactionHashToStore) !== 1) {
                    throw ValidationException::withMessages(['verified_transaction_hash' => 'Le hash BSC doit contenir 0x suivi de 64 caractères hexadécimaux.']);
                }
                if ($expectedContract === '' || !hash_equals($expectedContract, $tokenContractToStore)) {
                    throw ValidationException::withMessages(['verified_token_contract' => 'Le contrat vérifié ne correspond pas au jeton BSC-USD accepté par la plateforme.']);
                }
                if ($expectedRecipient === '' || !hash_equals($expectedRecipient, $recipientToStore)
                    || !hash_equals(strtolower((string) $submission->recipient_snapshot), $recipientToStore)) {
                    throw ValidationException::withMessages(['verified_recipient' => 'Le destinataire de la transaction ne correspond pas au portefeuille de cette commande.']);
                }
                if ($confirmationsToStore === null || $confirmationsToStore < $minimumConfirmations) {
                    throw ValidationException::withMessages(['verified_confirmations' => 'Le nombre de confirmations doit atteindre au moins '.$minimumConfirmations.'.']);
                }

                $duplicateHash = ManualPaymentSubmission::where('verified_transaction_hash', $transactionHashToStore)
                    ->where('id', '!=', $submission->id)->exists()
                    || Payment::where('provider', 'manual-transfer')
                        ->whereRaw('LOWER(transaction_id) = ?', [$transactionHashToStore])->exists();
                if ($duplicateHash) {
                    throw ValidationException::withMessages(['verified_transaction_hash' => 'Ce hash BSC a déjà été utilisé pour une autre demande.']);
                }
            }

            $paymentTransactionId = $transactionHashToStore ?? $submission->transaction_reference;

            if (Payment::where('provider', 'manual-transfer')
                ->whereRaw('LOWER(transaction_id) = ?', [strtolower($paymentTransactionId)])->exists()) {
                throw ValidationException::withMessages([
                    'transaction_reference' => 'Cette référence a déjà été enregistrée sur un paiement confirmé.',
                ]);
            }

            $activeSubscription = Subscription::where('user_id', $user->id)
                ->where('subscription_plan_id', $order->subscription_plan_id)
                ->where('status', 'ACTIVE')
                ->where(fn ($query) => $query->whereNull('admin_override')->orWhere('admin_override', '!=', 'DISABLED'))
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->orderByDesc('ends_at')->lockForUpdate()->first();
            $isRenewal = $activeSubscription !== null;

            if (!$activeSubscription) {
                $activeSubscription = Subscription::create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $order->subscription_plan_id,
                    'status' => 'PENDING',
                    'plan_name_snapshot' => $order->plan_name_snapshot,
                    'plan_price_snapshot' => $order->amount_usd,
                    'plan_currency_snapshot' => 'USD',
                    'plan_duration_days_snapshot' => $order->duration_days_snapshot,
                    'plan_max_mt5_accounts_snapshot' => $order->max_mt5_accounts_snapshot,
                    'plan_capital_required_snapshot' => $order->capital_required_snapshot,
                ]);
            }

            $payment = Payment::create([
                'user_id' => $user->id,
                'subscription_id' => $activeSubscription->id,
                'subscription_order_id' => $order->id,
                'provider' => 'manual-transfer',
                'transaction_id' => $paymentTransactionId,
                'amount' => $submission->expected_amount,
                'currency' => $submission->currency,
                'status' => 'PENDING',
                'idempotency_key' => (string) Str::uuid(),
                'is_renewal' => $isRenewal,
                'metadata' => [
                    'manual_submission_id' => $submission->id,
                    'method' => $submission->method,
                    'recipient' => $submission->recipient_snapshot,
                    'proof_sha256' => $submission->proof_sha256,
                    'bsc_transaction_hash' => $transactionHashToStore,
                    'token_contract' => $tokenContractToStore,
                    'verified_recipient' => $recipientToStore,
                    'confirmations' => $confirmationsToStore,
                    'usd_xof_rate' => $order->fx_rate,
                    'fx_provider' => $order->fx_provider,
                    'fx_rate_as_of' => $order->fx_rate_as_of?->toDateString(),
                    'fx_source_url' => $order->fx_source_url,
                ],
            ]);

            $submission->update([
                'payment_id' => $payment->id,
                'status' => 'APPROVED',
                'verified_transaction_hash' => $transactionHashToStore,
                'verified_token_contract' => $tokenContractToStore,
                'verified_recipient' => $recipientToStore,
                'verified_confirmations' => $confirmationsToStore,
                'review_note' => $reviewNote,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
            $submission->otp()->update(['code_ciphertext' => null]);

            $event = new VerifiedPaymentEvent(
                eventId: 'manual-payment-submission-'.$submission->id.'-approved',
                eventType: 'manual_transfer.confirmed_by_admin',
                transactionId: $paymentTransactionId,
                status: 'PAID',
                amount: $submission->expected_amount,
                currency: $submission->currency,
            );
            $payload = json_encode([
                'submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id,
                'method' => $submission->method,
                'transaction_reference' => $submission->transaction_reference,
                'verified_transaction_hash' => $transactionHashToStore,
                'verified_token_contract' => $tokenContractToStore,
                'verified_recipient' => $recipientToStore,
                'verified_confirmations' => $confirmationsToStore,
                'amount' => $submission->expected_amount,
                'currency' => $submission->currency,
                'proof_sha256' => $submission->proof_sha256,
                'review_note' => $reviewNote,
            ], JSON_THROW_ON_ERROR);

            $result = app(PaymentWebhookProcessor::class)->process('manual-transfer', $event, $payload);
            if (($result['processed'] ?? false) !== true || ($result['rejected'] ?? false) === true) {
                throw new RuntimeException('The manually verified payment could not be applied.');
            }

            DB::table('audit_logs')->insert([
                'user_id' => $reviewer->id,
                'action' => 'manual_payment.approved',
                'entity_type' => ManualPaymentSubmission::class,
                'entity_id' => $submission->id,
                'old_values' => json_encode(['status' => 'PENDING'], JSON_THROW_ON_ERROR),
                'new_values' => json_encode([
                    'status' => 'APPROVED',
                    'payment_id' => $payment->id,
                    'expected_amount' => $submission->expected_amount,
                    'verified_amount' => intdiv($verifiedCents, 100).'.'.str_pad((string) ($verifiedCents % 100), 2, '0', STR_PAD_LEFT),
                    'currency' => $submission->currency,
                    'method' => $submission->method,
                    'transaction_reference' => $submission->transaction_reference,
                    'verified_transaction_hash' => $transactionHashToStore,
                    'verified_token_contract' => $tokenContractToStore,
                    'verified_recipient' => $recipientToStore,
                    'verified_confirmations' => $confirmationsToStore,
                    'proof_sha256' => $submission->proof_sha256,
                    'review_note' => $reviewNote,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $ipAddress,
                'user_agent' => mb_substr((string) $userAgent, 0, 2000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function requestMoreInfo(
        int $submissionId,
        User $reviewer,
        string $reviewNote,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        DB::transaction(function () use ($submissionId, $reviewer, $reviewNote, $ipAddress, $userAgent): void {
            $submission = ManualPaymentSubmission::whereKey($submissionId)->lockForUpdate()->firstOrFail();
            abort_unless($submission->status === 'PENDING' && $submission->payment_id === null, 409);

            $submission->update([
                'status' => 'INFO_REQUIRED',
                'review_note' => $reviewNote,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
            $submission->otp()->update(['code_ciphertext' => null]);

            DB::table('audit_logs')->insert([
                'user_id' => $reviewer->id,
                'action' => 'manual_payment.info_requested',
                'entity_type' => ManualPaymentSubmission::class,
                'entity_id' => $submission->id,
                'old_values' => json_encode(['status' => 'PENDING'], JSON_THROW_ON_ERROR),
                'new_values' => json_encode([
                    'status' => 'INFO_REQUIRED',
                    'method' => $submission->method,
                    'request_reference' => $submission->transaction_reference,
                    'proof_sha256' => $submission->proof_sha256,
                    'review_note' => $reviewNote,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $ipAddress,
                'user_agent' => mb_substr((string) $userAgent, 0, 2000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function reject(
        int $submissionId,
        User $reviewer,
        string $reviewNote,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        DB::transaction(function () use ($submissionId, $reviewer, $reviewNote, $ipAddress, $userAgent): void {
            $submission = ManualPaymentSubmission::whereKey($submissionId)->lockForUpdate()->firstOrFail();
            abort_unless($submission->status === 'PENDING' && $submission->payment_id === null, 409);

            $submission->update([
                'status' => 'REJECTED',
                'review_note' => $reviewNote,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
            $submission->otp()->update(['code_ciphertext' => null]);

            DB::table('audit_logs')->insert([
                'user_id' => $reviewer->id,
                'action' => 'manual_payment.rejected',
                'entity_type' => ManualPaymentSubmission::class,
                'entity_id' => $submission->id,
                'old_values' => json_encode(['status' => 'PENDING'], JSON_THROW_ON_ERROR),
                'new_values' => json_encode([
                    'status' => 'REJECTED',
                    'method' => $submission->method,
                    'transaction_reference' => $submission->transaction_reference,
                    'proof_sha256' => $submission->proof_sha256,
                    'review_note' => $reviewNote,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $ipAddress,
                'user_agent' => mb_substr((string) $userAgent, 0, 2000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function amountToCents(string $amount): ?int
    {
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $amount, $parts)) {
            return null;
        }

        return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
    }
}
