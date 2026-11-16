<?php

namespace App\Services;

use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Fx\FixedUsdXofRateService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SubscriptionOrderCreator
{
    public function __construct(private readonly FixedUsdXofRateService $rates)
    {
    }

    public function create(User $user, SubscriptionPlan $plan, ?string $idempotencyKey = null): SubscriptionOrder
    {
        $plan = SubscriptionPlan::whereKey($plan->id)
            ->where('status', true)
            ->whereIn('slug', SubscriptionPlan::PUBLIC_CATALOGUE_SLUGS)
            ->lockForUpdate()
            ->firstOrFail();
        if (!$plan->matchesApprovedPublicTerms()) {
            throw ValidationException::withMessages([
                'order' => 'Cette offre ne peut pas être commandée : ses conditions doivent correspondre au catalogue publié.',
            ]);
        }

        $quote = $this->rates->currentForCheckout();
        $amountXof = $quote?->amountXof((string) $plan->price);
        $quoteUsable = $quote?->canBeUsedForCheckout() && $amountXof !== null;

        return SubscriptionOrder::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'idempotency_key' => $idempotencyKey ?: (string) Str::uuid(),
            'status' => 'AWAITING_PAYMENT',
            'plan_name_snapshot' => $plan->name,
            'plan_description_snapshot' => $plan->description,
            'plan_features_snapshot' => $plan->features ?: [],
            'amount_usd' => $plan->price,
            'duration_days_snapshot' => $plan->duration_days,
            'max_mt5_accounts_snapshot' => $plan->max_mt5_accounts,
            'capital_required_snapshot' => $plan->capital_required,
            'estimated_daily_gain_usd_snapshot' => $plan->estimated_daily_gain_usd,
            'amount_xof' => $amountXof,
            'fx_rate' => $quote?->rate,
            'fx_rate_as_of' => $quote?->asOf->toDateString(),
            'fx_rate_updated_at' => $quote?->updatedAt,
            'fx_provider' => $quote?->provider,
            'fx_source_url' => $quote?->sourceUrl !== '' ? $quote?->sourceUrl : null,
            'fx_fetched_at' => $quote?->fetchedAt,
            'quote_expires_at' => $quoteUsable
                ? now()->addMinutes(max(1, (int) config('fx.quote_valid_minutes', 30)))
                : null,
        ]);
    }
}
