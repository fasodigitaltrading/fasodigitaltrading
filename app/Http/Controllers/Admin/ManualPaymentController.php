<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManualPaymentSubmission;
use App\Services\ManualPaymentAdminNotifier;
use App\Services\ManualPaymentCustomerNotifier;
use App\Services\ManualPaymentReadiness;
use App\Services\ManualPaymentProofStorage;
use App\Services\ManualPaymentReviewService;
use App\Services\SubscriptionOrderQuoteValidator;
use App\Support\EmailDeliveryReadiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ManualPaymentController extends Controller
{
    public function index(Request $request, ManualPaymentReadiness $readiness): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['PENDING', 'INFO_REQUIRED', 'APPROVED', 'REJECTED'])],
        ]);

        return view('admin.manual-payments.index', [
            'submissions' => ManualPaymentSubmission::with([
                'user:id,first_name,last_name,email',
                'order:id,plan_name_snapshot,amount_usd,amount_xof,fx_rate,fx_provider,fx_rate_as_of,fx_source_url',
                'payment:id,status,provider,transaction_id',
                'reviewer:id,first_name,last_name',
                'otp:id,code_ciphertext',
            ])
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->orderByRaw("CASE WHEN status = 'PENDING' AND created_at <= ? THEN 0 ELSE 1 END", [now()->subMinutes(30)])
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString(),
            'selectedStatus' => $filters['status'] ?? '',
            'methodLabels' => collect(config('payments.manual_methods', []))
                ->mapWithKeys(fn (array $method, string $key) => [$key => $method['label'] ?? $key])
                ->all(),
            'canApprove' => $readiness->isReady(),
            'canNotify' => app(EmailDeliveryReadiness::class)->canSend(),
            'crypto' => config('payments.crypto'),
            'emailReadinessStatus' => app(EmailDeliveryReadiness::class)->status(),
        ]);
    }

    public function notify(
        ManualPaymentSubmission $submission,
        ManualPaymentAdminNotifier $notifier,
        EmailDeliveryReadiness $readiness,
        ManualPaymentProofStorage $proofs,
    ): RedirectResponse {
        abort_unless($submission->status === 'PENDING', 409);
        $sent = $notifier->send($submission, $readiness, $proofs, allowResend: true);
        $submission->refresh();

        DB::table('audit_logs')->insert([
            'user_id' => request()->user()->id,
            'action' => 'manual_payment.admin_email_retry',
            'entity_type' => ManualPaymentSubmission::class,
            'entity_id' => $submission->id,
            'old_values' => null,
            'new_values' => json_encode([
                'email_status' => $submission->admin_email_status,
                'transport_accepted' => $sent,
            ], JSON_THROW_ON_ERROR),
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 2000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.manual-payments.index')
            ->with($sent ? 'status' : 'email_status', $sent
                ? 'L’e-mail de transaction a été accepté par le transport configuré. Vérifiez la boîte administrateur.'
                : 'L’e-mail n’a pas été envoyé. La demande reste dans cette file de vérification.');
    }

    public function proof(
        ManualPaymentSubmission $submission,
        ManualPaymentProofStorage $proofs,
    ) {
        try {
            abort_unless($proofs->matchesHash($submission->proof_path, $submission->proof_sha256), 404);
            $contents = $proofs->contents($submission->proof_path);
        } catch (\RuntimeException) {
            abort(404);
        }

        $extension = pathinfo($submission->proof_path, PATHINFO_EXTENSION);

        return response()->streamDownload(static function () use ($contents): void {
            echo $contents;
        }, 'payment-proof-'.$submission->id.'.'.$extension, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function approve(
        Request $request,
        ManualPaymentSubmission $submission,
        ManualPaymentReviewService $reviews,
        ManualPaymentProofStorage $proofs,
        ManualPaymentReadiness $readiness,
        SubscriptionOrderQuoteValidator $quotes,
        ManualPaymentCustomerNotifier $customerNotifier,
        EmailDeliveryReadiness $emailReadiness,
    ): RedirectResponse {
        $data = $request->validate([
            'verified_amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'verified_transaction_hash' => [Rule::requiredIf($submission->method === 'usdt_bep20'), 'nullable', 'regex:/^0x[a-fA-F0-9]{64}$/'],
            'verified_token_contract' => [Rule::requiredIf($submission->method === 'usdt_bep20'), 'nullable', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'verified_recipient' => [Rule::requiredIf($submission->method === 'usdt_bep20'), 'nullable', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'verified_confirmations' => [Rule::requiredIf($submission->method === 'usdt_bep20'), 'nullable', 'integer', 'min:0', 'max:999999'],
            'confirm_token_contract' => [Rule::requiredIf($submission->method === 'usdt_bep20'), 'nullable', Rule::in(['yes'])],
            'confirm_recipient' => [Rule::requiredIf($submission->method === 'usdt_bep20'), 'nullable', Rule::in(['yes'])],
            'confirm_funds' => ['required', Rule::in(['yes'])],
            'review_note' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $reviews->approve(
            $submission->id,
            $request->user(),
            (string) $data['verified_amount'],
            isset($data['verified_transaction_hash']) ? (string) $data['verified_transaction_hash'] : null,
            isset($data['verified_token_contract']) ? (string) $data['verified_token_contract'] : null,
            isset($data['verified_recipient']) ? (string) $data['verified_recipient'] : null,
            isset($data['verified_confirmations']) ? (int) $data['verified_confirmations'] : null,
            trim($data['review_note']),
            $request->ip(),
            $request->userAgent(),
            $proofs,
            $readiness,
            $quotes,
        );

        $emailSent = $customerNotifier->send($submission->fresh(), $emailReadiness);

        return redirect()->route('admin.manual-payments.index')
            ->with('status', $emailSent
                ? 'Paiement confirmé, abonnement et licence activés; le courriel client a été accepté par le transport.'
                : 'Paiement confirmé, abonnement et licence activés. Le courriel client est en attente ou a échoué; vous pouvez le renvoyer après configuration SMTP.');
    }

    public function requestMoreInfo(
        Request $request,
        ManualPaymentSubmission $submission,
        ManualPaymentReviewService $reviews,
    ): RedirectResponse {
        $data = $request->validate(['review_note' => ['required', 'string', 'min:10', 'max:500']]);
        $reviews->requestMoreInfo(
            $submission->id,
            $request->user(),
            trim($data['review_note']),
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()->route('admin.manual-payments.index')
            ->with('status', 'Une information ou une nouvelle capture a été demandée au client. L’historique est conservé.');
    }

    public function notifyCustomer(
        Request $request,
        ManualPaymentSubmission $submission,
        ManualPaymentCustomerNotifier $notifier,
        EmailDeliveryReadiness $readiness,
    ): RedirectResponse {
        abort_unless($submission->status === 'APPROVED', 409);
        $sent = $notifier->send($submission, $readiness, allowResend: true);
        $submission->refresh();

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'manual_payment.customer_email_retry',
            'entity_type' => ManualPaymentSubmission::class,
            'entity_id' => $submission->id,
            'old_values' => null,
            'new_values' => json_encode([
                'email_status' => $submission->customer_email_status,
                'transport_accepted' => $sent,
            ], JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.manual-payments.index')
            ->with($sent ? 'status' : 'email_status', $sent
                ? 'Le courriel de confirmation a été accepté par le transport configuré.'
                : 'Le courriel client n’a pas été envoyé; la confirmation du paiement reste enregistrée.');
    }

    public function reject(
        Request $request,
        ManualPaymentSubmission $submission,
        ManualPaymentReviewService $reviews,
    ): RedirectResponse {
        $data = $request->validate([
            'review_note' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $reviews->reject(
            $submission->id,
            $request->user(),
            trim($data['review_note']),
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()->route('admin.manual-payments.index')
            ->with('status', 'Justificatif refusé. Le client peut transmettre un nouveau paiement.');
    }
}
