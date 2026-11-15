<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Keep the approved four-pack public catalogue consistent on fresh installs
     * and reseeds. Historical plan rows remain in place but are not selectable.
     */
    public function run(): void
    {
        $features = [
            'Espace investisseur et suivi du dossier',
            'Suivi de l’abonnement et de la licence après validation effective du paiement',
            'Rattachement d’un compte MT5 en lecture seule si le connecteur et les autorisations sont disponibles',
            'Demandes d’assistance depuis la plateforme',
        ];
        $plans = [
            ['starter', 'STARTER', '30.00', '200.00', '5.00'],
            ['standard', 'STANDARD', '45.00', '300.00', '7.50'],
            ['pro', 'PRO', '75.00', '500.00', '12.50'],
            ['premium', 'PREMIUM', '120.00', '800.00', '20.00'],
        ];

        foreach ($plans as $sort => [$slug, $name, $price, $capital, $daily]) {
            SubscriptionPlan::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => null,
                    'price' => $price,
                    'list_price' => $price,
                    'currency' => 'USD',
                    'duration_days' => 30,
                    'max_mt5_accounts' => 1,
                    'capital_required' => $capital,
                    'projected_daily_gain' => $daily,
                    'estimated_daily_gain_usd' => $daily,
                    'features' => $features,
                    'status' => true,
                    'sort_order' => $sort + 1,
                ],
            );
        }

        DB::table('subscription_plans')
            ->whereNotIn('slug', ['starter', 'standard', 'pro', 'premium'])
            ->update(['status' => false, 'updated_at' => now()]);
    }
}
