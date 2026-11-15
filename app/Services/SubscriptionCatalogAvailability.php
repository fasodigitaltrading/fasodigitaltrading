<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Exceptions\PaymentProviderUnavailable;
use App\Services\Fx\BceaoReferenceRateService;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionCatalogAvailability
{
    public function forPlans(Collection $plans): array
    {
        $providerName = trim((string) config('payments.default_provider'));
        $fxQuote = app(BceaoReferenceRateService::class)->latest();
        $provider = null;

        if ($fxQuote?->canBeUsedForCheckout() && $providerName !== '') {
            try {
                $provider = app(PaymentProviderFactory::class)->make($providerName);
            } catch (PaymentProviderUnavailable) {
                // Missing live credentials or prerequisites leave checkout disabled.
            }
        }

        $checkoutPlanIds = [];
        if ($provider && $fxQuote) {
            $checkoutPlanIds = $plans->filter(function (SubscriptionPlan $plan) use ($provider, $fxQuote): bool {
                if (!$plan->matchesApprovedPublicTerms()) {
                    return false;
                }
                $amountXof = $fxQuote->amountXof((string) $plan->price);

                return $amountXof !== null && $provider->supportsCheckout('XOF', (string) $amountXof);
            })->modelKeys();
        }

        $orderPlanIds = $plans->filter(fn (SubscriptionPlan $plan): bool =>
            in_array($plan->slug, SubscriptionPlan::PUBLIC_CATALOGUE_SLUGS, true)
            && strtoupper($plan->currency) === 'USD'
            && $plan->matchesApprovedPublicTerms()
        )->modelKeys();

        return [
            'checkoutPlanIds' => $checkoutPlanIds,
            'orderPlanIds' => $orderPlanIds,
            'providerName' => $providerName,
            'fxQuote' => $fxQuote,
        ];
    }
}
