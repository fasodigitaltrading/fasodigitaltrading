<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\SubscriptionPlan;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seed_creates_catalog_data_without_shared_demo_accounts(): void
    {
        $this->app['env'] = 'production';
        foreach ([
            ['elite', 'Elite', '32.81', '500.00'],
            ['gold', 'Gold', '44.37', '750.00'],
            ['platinum', 'Platinum', '53.24', '1000.00'],
            ['diamond', 'Diamond', '71.76', '1500.00'],
            ['vip', 'VIP', '86.09', '2000.00'],
            ['black', 'Black', '116.22', '3000.00'],
        ] as [$slug, $name, $price, $capital]) {
            SubscriptionPlan::create([
                'name' => $name, 'slug' => $slug, 'price' => $price, 'list_price' => $price,
                'currency' => 'USD', 'duration_days' => 30, 'capital_required' => $capital,
                'status' => true,
            ]);
        }
        app(\Database\Seeders\DatabaseSeeder::class)->run();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('subscription_plans', 10);
        $this->assertSame(4, SubscriptionPlan::where('status', true)->count());
        $this->assertDatabaseCount('brokers', 0);
        $this->assertDatabaseCount('partners', 0);
        $this->assertDatabaseHas('subscription_plans', ['slug' => 'starter', 'price' => '30.00', 'capital_required' => '200.00', 'estimated_daily_gain_usd' => '5.00']);
        $this->assertDatabaseHas('subscription_plans', ['slug' => 'standard', 'price' => '45.00', 'capital_required' => '300.00', 'estimated_daily_gain_usd' => '7.50']);
        $this->assertDatabaseHas('subscription_plans', ['slug' => 'pro', 'price' => '75.00', 'capital_required' => '500.00', 'estimated_daily_gain_usd' => '12.50']);
        $this->assertDatabaseHas('subscription_plans', ['slug' => 'premium', 'price' => '120.00', 'capital_required' => '800.00', 'estimated_daily_gain_usd' => '20.00']);
        $this->assertDatabaseHas('subscription_plans', ['slug' => 'elite', 'price' => '32.81', 'status' => false]);
    }
}
