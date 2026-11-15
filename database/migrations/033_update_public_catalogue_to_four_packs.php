<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const PACKS = [
        'starter' => ['name' => 'STARTER', 'price' => '30.00', 'capital' => '200.00', 'daily' => '5.00', 'sort' => 1],
        'standard' => ['name' => 'STANDARD', 'price' => '45.00', 'capital' => '300.00', 'daily' => '7.50', 'sort' => 2],
        'pro' => ['name' => 'PRO', 'price' => '75.00', 'capital' => '500.00', 'daily' => '12.50', 'sort' => 3],
        'premium' => ['name' => 'PREMIUM', 'price' => '120.00', 'capital' => '800.00', 'daily' => '20.00', 'sort' => 4],
    ];

    private const FEATURES = [
        'Espace investisseur et suivi du dossier',
        'Suivi de l’abonnement et de la licence après validation du paiement',
        'Rattachement d’un compte MT5 en lecture seule (connecteur et autorisation requis)',
        'Demandes d’assistance depuis la plateforme',
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('subscription_plans', 'estimated_daily_gain_usd')) {
            Schema::table('subscription_plans', function (Blueprint $table): void {
                $table->decimal('estimated_daily_gain_usd', 12, 2)->nullable();
            });
        }
        if (!Schema::hasColumn('subscription_orders', 'estimated_daily_gain_usd_snapshot')) {
            Schema::table('subscription_orders', function (Blueprint $table): void {
                $table->decimal('estimated_daily_gain_usd_snapshot', 12, 2)->nullable();
            });
        }

        foreach (self::PACKS as $slug => $pack) {
            $values = [
                'name' => $pack['name'],
                'price' => $pack['price'],
                'list_price' => $pack['price'],
                'currency' => 'USD',
                'duration_days' => 30,
                'capital_required' => $pack['capital'],
                'projected_daily_gain' => $pack['daily'],
                'estimated_daily_gain_usd' => $pack['daily'],
                'features' => json_encode(self::FEATURES, JSON_THROW_ON_ERROR),
                'status' => true,
                'sort_order' => $pack['sort'],
                'updated_at' => now(),
            ];

            // Update existing catalogue rows in place. A fresh install creates
            // its public plans through DatabaseSeeder after migrations.
            DB::table('subscription_plans')->where('slug', $slug)->update($values);
        }

        DB::table('subscription_plans')->whereNotIn('slug', array_keys(self::PACKS))->update([
            'status' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        throw new LogicException('Keep the approved catalogue and historical order snapshots. Use a reviewed forward migration for future changes.');
    }
};
