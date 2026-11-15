<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\License;
use App\Models\Commission;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Services\Payments\VerifiedPaymentEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentWebhookProcessor
{
    private const ALLOWED_STATUSES = ['PAID', 'FAILED', 'CANCELLED', 'REFUNDED'];

    public function process(string $provider, VerifiedPaymentEvent $event, string $payload): array
    {
        if ($event->eventId === '' || strlen($event->eventId) > 191
            || $event->eventType === '' || strlen($event->eventType) > 100
            || !in_array($event->status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('The provider returned an invalid normalized payment event.');
        }

        if ($event->status === 'PAID'
            && (strlen((string) config('license.binding_key')) < 32 || strlen((string) config('app.key')) < 20)) {
            throw new \RuntimeException('License issuance is not configured; the confirmed payment event must be retried.');
        }

        $payloadHash = hash('sha256', $payload);

        try {
            return DB::transaction(function () use ($provider, $event, $payloadHash) {
                $existing = PaymentWebhookEvent::where('provider', $provider)
                    ->where('event_id', $event->eventId)->lockForUpdate()->first();

                if ($existing) {
                    return ['duplicate' => true, 'conflict' => !hash_equals($existing->payload_hash, $payloadHash)];
                }

                $record = PaymentWebhookEvent::create([
                    'provider' => $provider,
                    'event_id' => $event->eventId,
                    'event_type' => $event->eventType,
                    'payload_hash' => $payloadHash,
                    'processing_status' => 'RECEIVED',
                ]);

                $payment = Payment::where('provider', $provider)
                    ->where('transaction_id', $event->transactionId)
                    ->lockForUpdate()->first();

                if (!$payment) {
                    $record->update(['processing_status' => 'IGNORED', 'processed_at' => now()]);

                    return ['duplicate' => false, 'ignored' => true];
                }

                if (!$this->amountMatches((string) $payment->amount, $event->amount)
                    || strtoupper($payment->currency) !== strtoupper($event->currency)) {
                    $record->update([
                        'payment_id' => $payment->id,
                        'processing_status' => 'REJECTED',
                        'processed_at' => now(),
                    ]);

                    return ['duplicate' => false, 'rejected' => true];
                }

                $record->payment()->associate($payment);
                $record->processing_status = 'PROCESSED';
                $record->processed_at = now();

                if ($payment->status === 'PENDING') {
                    $payment->status = $event->status;
                    $payment->paid_at = $event->status === 'PAID' ? now() : null;
                    $payment->save();

                    if ($payment->subscription_order_id) {
                        $orderStatus = match ($event->status) {
                            'PAID' => 'PAID',
                            'FAILED' => 'PAYMENT_FAILED',
                            'CANCELLED' => 'CANCELLED',
                            'REFUNDED' => 'REFUNDED',
                        };
                        SubscriptionOrder::whereKey($payment->subscription_order_id)
                            ->lockForUpdate()->update(['status' => $orderStatus, 'updated_at' => now()]);
                    }

                    $subscription = $payment->subscription;
                    if ($subscription && $event->status === 'PAID' && $subscription->status === 'PENDING') {
                        $startsAt = now();
                        $subscription->update([
                            'status' => 'ACTIVE',
                            'starts_at' => $startsAt,
                            'ends_at' => $startsAt->copy()->addDays(
                                $subscription->plan_duration_days_snapshot ?: $subscription->plan->duration_days,
                            ),
                        ]);
                    } elseif ($subscription && $event->status === 'PAID' && $payment->is_renewal
                        && in_array($subscription->status, ['ACTIVE', 'EXPIRED'], true)) {
                        $startsAt = $subscription->ends_at && $subscription->ends_at->isFuture()
                            ? $subscription->ends_at->copy()
                            : now();
                        $subscription->update([
                            'status' => 'ACTIVE',
                            'ends_at' => $startsAt->addDays(
                                $subscription->plan_duration_days_snapshot ?: $subscription->plan->duration_days,
                            ),
                        ]);
                    } elseif ($subscription && $subscription->status === 'PENDING'
                        && in_array($event->status, ['FAILED', 'CANCELLED', 'REFUNDED'], true)) {
                        $subscription->update(['status' => 'CANCELLED', 'admin_override' => null]);
                    }

                    if ($event->status === 'PAID') {
                        if ($subscription) {
                            $this->issueLicenseIfMissing($subscription);
                        }
                        app(ReferralCommissionService::class)->accrueFor($payment);
                    } elseif (in_array($event->status, ['FAILED', 'CANCELLED', 'REFUNDED'], true)
                        && $subscription?->status === 'CANCELLED') {
                        License::where('subscription_id', $subscription->id)->where('status', 'ACTIVE')
                            ->update(['status' => 'REVOKED', 'updated_at' => now()]);
                    }
                } elseif ($payment->status === 'PAID' && $event->status === 'REFUNDED') {
                    $payment->update(['status' => 'REFUNDED']);
                    if ($payment->subscription_order_id) {
                        SubscriptionOrder::whereKey($payment->subscription_order_id)
                            ->lockForUpdate()->update(['status' => 'REFUNDED', 'updated_at' => now()]);
                    }
                    Commission::where('payment_id', $payment->id)
                        ->whereIn('status', ['PENDING', 'APPROVED'])
                        ->update(['status' => 'REVERSED', 'updated_at' => now()]);

                    $subscription = $payment->subscription;
                    if ($subscription && $payment->is_renewal) {
                        $durationDays = (int) ($subscription->plan_duration_days_snapshot ?: $subscription->plan->duration_days);
                        $endsAt = $subscription->ends_at?->copy()->subDays($durationDays);
                        $stillActive = $endsAt && $endsAt->isFuture();
                        $subscription->update([
                            'ends_at' => $endsAt,
                            'status' => $stillActive ? 'ACTIVE' : 'EXPIRED',
                        ]);
                        License::where('subscription_id', $subscription->id)
                            ->whereIn('status', ['ACTIVE', 'EXPIRED'])
                            ->update([
                                'status' => $stillActive ? 'ACTIVE' : 'EXPIRED',
                                'expires_at' => $endsAt,
                                'updated_at' => now(),
                            ]);
                    } elseif ($subscription) {
                        $subscription->update(['status' => 'CANCELLED', 'admin_override' => null]);
                        License::where('subscription_id', $subscription->id)->where('status', 'ACTIVE')
                            ->update(['status' => 'REVOKED', 'updated_at' => now()]);
                    }
                }

                $record->save();

                return ['duplicate' => false, 'processed' => true];
            });
        } catch (QueryException $exception) {
            $existing = PaymentWebhookEvent::where('provider', $provider)->where('event_id', $event->eventId)->first();
            if (!$existing) {
                throw $exception;
            }

            return ['duplicate' => true, 'conflict' => !hash_equals($existing->payload_hash, $payloadHash)];
        }
    }

    private function amountMatches(string $expected, string $received): bool
    {
        $normalize = static function (string $amount): ?string {
            if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $amount, $parts)) {
                return null;
            }

            $whole = ltrim($parts[1], '0') ?: '0';
            $fraction = str_pad($parts[2] ?? '', 2, '0');

            return $whole.'.'.$fraction;
        };

        $expected = $normalize($expected);
        $received = $normalize($received);

        return $expected !== null && $received !== null && hash_equals($expected, $received);
    }

    private function issueLicenseIfMissing(Subscription $subscription): void
    {
        $subscription = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
        if ($subscription->status !== 'ACTIVE' || !$subscription->ends_at) {
            throw new \RuntimeException('A paid subscription must be active and have an expiry before a license can be issued.');
        }
        if ($subscription->admin_override === 'DISABLED') {
            return;
        }
        $license = License::where('subscription_id', $subscription->id)->lockForUpdate()->first();
        if ($license) {
            if (in_array($license->status, ['ACTIVE', 'EXPIRED'], true)) {
                $license->update([
                    'status' => 'ACTIVE',
                    'expires_at' => $subscription->ends_at,
                    'max_accounts' => $subscription->plan_max_mt5_accounts_snapshot
                        ?: $subscription->plan->max_mt5_accounts,
                ]);
            }

            return;
        }

        License::issue([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'status' => 'ACTIVE',
            'activated_at' => $subscription->starts_at ?? now(),
            'expires_at' => $subscription->ends_at,
            'max_accounts' => $subscription->plan_max_mt5_accounts_snapshot
                ?: $subscription->plan->max_mt5_accounts,
        ]);
    }
}
