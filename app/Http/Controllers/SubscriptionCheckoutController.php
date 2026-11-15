<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\SubscriptionPlan;

/**
 * Compatibility endpoint for old bookmarks. New checkouts must originate from
 * a server-priced SubscriptionOrder and a verified exchange-rate quote.
 */
class SubscriptionCheckoutController extends Controller
{
    public function __invoke(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        unset($request, $plan);
        return redirect()->route('subscriptions')->withErrors([
            'payment' => 'Le paiement direct est désactivé. Choisissez un pack dans le catalogue pour créer un récapitulatif de commande sécurisé.',
        ]);
    }
}
