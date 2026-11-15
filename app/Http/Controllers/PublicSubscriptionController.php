<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Services\SubscriptionCatalogAvailability;
use Illuminate\View\View;

class PublicSubscriptionController extends Controller
{
    public function index(SubscriptionCatalogAvailability $availability): View
    {
        $plans = SubscriptionPlan::where('status', true)
            ->whereIn('slug', SubscriptionPlan::PUBLIC_CATALOGUE_SLUGS)
            ->orderBy('sort_order')->get()
            ->filter(fn (SubscriptionPlan $plan) => $plan->matchesApprovedPublicTerms())
            ->values();

        return view('public.subscriptions', [
            'plans' => $plans,
            ...$availability->forPlans($plans),
        ]);
    }
}
