<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Payment;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;

class ReferralCommissionService
{
    public function accrueFor(Payment $payment): ?Commission
    {
        if ($payment->status !== 'PAID' || $payment->paid_at === null) {
            return null;
        }

        return DB::transaction(function () use ($payment) {
            $referral = Referral::where('user_id', $payment->user_id)->first();
            $partner = $referral?->partner;
            $partnerOwner = $partner?->user;
            if (!$partner || $partner->status !== 'ACTIVE' || !$partnerOwner
                || $partnerOwner->status !== 'ACTIVE' || !in_array($partnerOwner->role, ['INVESTOR', 'PARTNER'], true)) {
                return null;
            }

            $rate = (string) $partner->commission_rate;
            if (!preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', $rate, $rateParts)) {
                return null;
            }
            $rateBasisPoints = ((int) $rateParts[1] * 100) + (int) str_pad($rateParts[2] ?? '', 2, '0');
            if ($rateBasisPoints <= 0 || $rateBasisPoints > 10000) {
                return null;
            }

            $amountParts = [];
            if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', (string) $payment->getRawOriginal('amount'), $amountParts)) {
                return null;
            }
            $paymentCents = ((int) $amountParts[1] * 100) + (int) str_pad($amountParts[2] ?? '', 2, '0');
            $commissionCents = intdiv(($paymentCents * $rateBasisPoints) + 5000, 10000);

            return Commission::firstOrCreate(
                ['payment_id' => $payment->id],
                [
                    'partner_id' => $partner->id,
                    'user_id' => $payment->user_id,
                    'amount' => intdiv($commissionCents, 100).'.'.str_pad((string) ($commissionCents % 100), 2, '0', STR_PAD_LEFT),
                    'currency' => strtoupper($payment->currency),
                    'percentage' => intdiv($rateBasisPoints, 100).'.'.str_pad((string) ($rateBasisPoints % 100), 2, '0', STR_PAD_LEFT),
                    'status' => 'PENDING',
                    'available_at' => now()->addDays((int) config('referrals.commission_hold_days', 14)),
                ],
            );
        });
    }
}
