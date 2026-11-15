<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManualPaymentSubmission;
use App\Services\ManualPaymentAdminNotifier;
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
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ManualPaymentController extends Controller
{
    public function index(Request $request, ManualPaymentReadiness $readiness): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['PENDING', 'APPROVED', 'REJECTED'])],
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
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString(),
            'selectedStatus' => $filters['status'] ?? '',
            'methodLabels' => collect(config('payments.manual_methods', []))
                ->mapWithKeys(fn (array $method, string $key) => [$key => $method['label'] ?? $key])
                ->all(),
            'canApprove' => $readiness->isReady(),
            'canNotify' => app(EmailDeliveryReadiness::class)->canSend(),
            'emailReadinessStatus' => app(EmailDeliveryReadiness::class)->status(),
        ]);
    }

    public function notify(
        ManualPaymentSubmission $submission,
        ManualPaymentAdminNotifier $notifier,
        EmailDeliveryReadiness $readiness,
    ): RedirectResponse {
        abort_unless($submission->status === 'PENDING', 409);
        $sent = $notifier->send($submission, $readiness, allowResend: true);
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
    ): BinaryFileResponse {
        try {
            abort_unless($proofs->matchesHash($submission->proof_path, $submission->proof_sha256), 404);
            $path = $proofs->downloadPath($submission->proof_path);
        } catch (\RuntimeException) {
            abort(404);
        }

        $extension = pathinfo($submission->proof_path, PATHINFO_EXTENSION);

        return response()->download($path, 'payment-proof-'.$submission->id.'.'.$extension, [
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
    ): RedirectResponse {
        $data = $request->validate([
            'verified_amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'confirm_funds' => ['required', Rule::in(['yes'])],
            'review_note' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $reviews->approve(
            $submission->id,
            $request->user(),
            (string) $data['verified_amount'],
            trim($data['review_note']),
            $request->ip(),
            $request->userAgent(),
            $proofs,
            $readiness,
            $quotes,
        );

        return redirect()->route('admin.manual-payments.index')
            ->with('status', 'Paiement vérifié et abonnement mis à jour. L’action est inscrite au journal d’audit.');
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
