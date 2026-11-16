<?php

namespace App\Http\Controllers;

use App\Models\ManualPaymentSubmission;
use App\Models\SubscriptionOrder;
use App\Services\ManualPaymentAdminNotifier;
use App\Services\ManualPaymentProofStorage;
use App\Services\ManualPaymentReadiness;
use App\Services\SubscriptionOrderQuoteValidator;
use App\Support\EmailDeliveryReadiness;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
                'proof' => 'Le paiement crypto est temporairement indisponible : la configuration sécurisée du serveur doit être vérifiée.',
            ]);
        }

        $methods = (array) config('payments.manual_methods', []);
        $data = $request->validate([
            'method' => ['required', 'string', Rule::in(['usdt_bep20']), Rule::in(array_keys($methods))],
            'proof' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png'],
            'payment_acknowledgement' => ['accepted'],
        ]);

        $method = $methods[$data['method']] ?? null;
        $contract = (string) config('payments.crypto.token_contract');
        abort_unless(is_array($method)
            && strtoupper((string) ($method['currency'] ?? '')) === 'USDT'
            && preg_match('/^0x[a-fA-F0-9]{40}$/', $contract) === 1
            && preg_match('/^0x[a-fA-F0-9]{40}$/', (string) ($method['recipient'] ?? '')) === 1, 503,
            'La configuration du jeton ou du portefeuille de réception est invalide.');

        $storedProof = null;

        try {
            $submission = DB::transaction(function () use ($request, $order, $quotes, $data, $method, $proofs, &$storedProof): ManualPaymentSubmission {
                $lockedOrder = SubscriptionOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $lockedOrder->user_id === (int) $request->user()->id
                    && in_array($lockedOrder->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED'], true)
                    && $quotes->canPayNow($lockedOrder), 409,
                    'Cette commande doit être recalculée avant le paiement crypto.');
                abort_if($lockedOrder->payments()->where('status', 'PENDING')->exists(), 409,
                    'Un paiement sécurisé est déjà en attente pour cette commande.');
                abort_if($lockedOrder->manualPaymentSubmissions()->where('status', 'PENDING')->exists(), 409,
                    'Une transaction est déjà en cours de vérification pour cette commande.');

                $amount = (string) $lockedOrder->amount_usd;
                if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $amount) || (float) $amount <= 0) {
                    throw ValidationException::withMessages(['payment' => 'Le montant USDT de la commande est invalide.']);
                }

                $storedProof = $proofs->store($data['proof']);
                $reference = 'REQ-'.Str::upper((string) Str::uuid());
                $submission = ManualPaymentSubmission::create([
                    'subscription_order_id' => $lockedOrder->id,
                    'user_id' => $request->user()->id,
                    'method' => $data['method'],
                    'recipient_snapshot' => (string) $method['recipient'],
                    'expected_amount' => $amount,
                    'currency' => 'USDT',
                    'transaction_reference' => $reference,
                    'proof_path' => $storedProof['path'],
                    'proof_sha256' => $storedProof['sha256'],
                    'status' => 'PENDING',
                    'admin_email_status' => 'BLOCKED',
                    'customer_email_status' => 'NOT_SENT',
                    'customer_acknowledged_at' => now(),
                    'customer_terms_version' => (string) config('legal.manual_payment_terms_version', 'manual-payment-v2-crypto'),
                ]);

                DB::table('audit_logs')->insert([
                    'user_id' => $request->user()->id,
                    'action' => 'manual_payment.submitted',
                    'entity_type' => ManualPaymentSubmission::class,
                    'entity_id' => $submission->id,
                    'old_values' => null,
                    'new_values' => json_encode([
                        'status' => 'PENDING',
                        'subscription_order_id' => $lockedOrder->id,
                        'method' => $data['method'],
                        'expected_amount' => $amount,
                        'currency' => 'USDT',
                        'request_reference' => $reference,
                        'proof_sha256' => $storedProof['sha256'],
                        'customer_acknowledged_at' => $submission->customer_acknowledged_at?->toIso8601String(),
                        'customer_terms_version' => $submission->customer_terms_version,
                    ], JSON_THROW_ON_ERROR),
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $submission;
            });
        } catch (Throwable $exception) {
            if (is_array($storedProof) && is_string($storedProof['path'] ?? null)) {
                $proofs->delete($storedProof['path']);
            }

            if ($exception instanceof QueryException) {
                throw ValidationException::withMessages([
                    'proof' => 'La demande ne peut pas être enregistrée pour le moment. Réessayez une fois, sans effectuer un second transfert.',
                ]);
            }

            throw $exception;
        }

        $emailSent = $notifier->send($submission, $emailReadiness, $proofs);
        $message = $emailSent
            ? 'Capture reçue. La demande est en attente de vérification réelle sur BNB Smart Chain; le courriel a été accepté par le transport administrateur.'
            : 'Capture reçue dans la file sécurisée. L’e-mail administrateur est en attente ou a échoué; aucun abonnement ne sera activé avant vérification réelle.';

        return redirect()->route('subscriptions.orders.show', $order)->with('status', $message);
    }
}
