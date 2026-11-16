<?php

namespace App\Services;

use App\Mail\ManualPaymentReceivedAlert;
use App\Models\ManualPaymentSubmission;
use App\Support\EmailDeliveryReadiness;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class ManualPaymentAdminNotifier
{
    public function send(
        ManualPaymentSubmission $submission,
        EmailDeliveryReadiness $readiness,
        ManualPaymentProofStorage $proofs,
        bool $allowResend = false,
    ): bool
    {
        $submission = ManualPaymentSubmission::with(['user', 'order', 'otp'])->findOrFail($submission->id);
        $codeCiphertext = $submission->otp?->code_ciphertext;
        if ($submission->status !== 'PENDING'
            || ($submission->admin_email_status === 'SENT' && !$allowResend)) {
            return false;
        }

        if (!$readiness->canSend()) {
            $submission->update([
                'admin_email_status' => 'BLOCKED',
                'admin_email_last_attempt_at' => now(),
            ]);

            return false;
        }

        $reserved = DB::transaction(function () use ($submission, $allowResend): bool {
            $locked = ManualPaymentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $staleSending = $locked->admin_email_status === 'SENDING'
                && ($locked->admin_email_last_attempt_at === null
                    || $locked->admin_email_last_attempt_at->lte(now()->subMinutes(10)));
            if ($locked->status !== 'PENDING'
                || ($locked->admin_email_status === 'SENT' && !$allowResend)
                || ($locked->admin_email_status === 'SENDING' && !$staleSending)
                ) {
                return false;
            }

            $locked->update([
                'admin_email_status' => 'SENDING',
                'admin_email_last_attempt_at' => now(),
            ]);

            return true;
        });

        if (!$reserved) {
            return false;
        }

        try {
            if (!$proofs->matchesHash((string) $submission->proof_path, (string) $submission->proof_sha256)) {
                throw new \RuntimeException('The stored payment proof is missing or has changed.');
            }
            $proofContents = $proofs->contents((string) $submission->proof_path);
            $code = $codeCiphertext ? \Illuminate\Support\Facades\Crypt::decryptString($codeCiphertext) : null;
            Mail::to((string) config('admin.email'))->send(new ManualPaymentReceivedAlert($submission, $code, $proofContents));

            DB::transaction(function () use ($submission): void {
                $locked = ManualPaymentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
                $locked->update([
                    'admin_email_status' => 'SENT',
                    'admin_email_sent_at' => now(),
                ]);

                if ($locked->otp()->where('expires_at', '<=', now())->exists()) {
                    $locked->otp()->update(['code_ciphertext' => null]);
                }
            });

            return true;
        } catch (Throwable $exception) {
            ManualPaymentSubmission::whereKey($submission->id)->update(['admin_email_status' => 'FAILED']);
            Log::warning('Manual payment admin notification failed.', [
                'submission_id' => $submission->id,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }
}
