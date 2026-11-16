<?php

namespace App\Services;

use App\Mail\ManualPaymentConfirmed;
use App\Models\ManualPaymentSubmission;
use App\Support\EmailDeliveryReadiness;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class ManualPaymentCustomerNotifier
{
    public function send(
        ManualPaymentSubmission $submission,
        EmailDeliveryReadiness $readiness,
        bool $allowResend = false,
    ): bool {
        $submission = ManualPaymentSubmission::with(['user', 'order'])->findOrFail($submission->id);
        if ($submission->status !== 'APPROVED'
            || ($submission->customer_email_status === 'SENT' && !$allowResend)) {
            return false;
        }

        if (!$readiness->canSend()) {
            $submission->update([
                'customer_email_status' => 'BLOCKED',
                'customer_email_last_attempt_at' => now(),
            ]);

            return false;
        }

        $reserved = DB::transaction(function () use ($submission, $allowResend): bool {
            $locked = ManualPaymentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $staleSending = $locked->customer_email_status === 'SENDING'
                && ($locked->customer_email_last_attempt_at === null
                    || $locked->customer_email_last_attempt_at->lte(now()->subMinutes(10)));
            if ($locked->status !== 'APPROVED'
                || ($locked->customer_email_status === 'SENT' && !$allowResend)
                || ($locked->customer_email_status === 'SENDING' && !$staleSending)) {
                return false;
            }

            $locked->update([
                'customer_email_status' => 'SENDING',
                'customer_email_last_attempt_at' => now(),
            ]);

            return true;
        });

        if (!$reserved) {
            return false;
        }

        try {
            Mail::to((string) $submission->user?->email)->send(new ManualPaymentConfirmed($submission));
            $submission->update([
                'customer_email_status' => 'SENT',
                'customer_email_sent_at' => now(),
            ]);

            return true;
        } catch (Throwable $exception) {
            ManualPaymentSubmission::whereKey($submission->id)->update(['customer_email_status' => 'FAILED']);
            Log::warning('Manual payment customer confirmation failed.', [
                'submission_id' => $submission->id,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }
}
