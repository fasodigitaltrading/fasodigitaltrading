<?php

namespace Tests\Feature;

use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubscriptionCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED = [
        'starter' => ['30.00', '200.00', '5.00', 18000],
        'standard' => ['45.00', '300.00', '7.50', 27000],
        'pro' => ['75.00', '500.00', '12.50', 45000],
        'premium' => ['120.00', '800.00', '20.00', 72000],
    ];

    public function test_catalogue_shows_exactly_four_final_usd_packs_with_fixed_xof_equivalents(): void
    {
        app(\Database\Seeders\DatabaseSeeder::class)->run();

        $this->assertSame(4, SubscriptionPlan::where('status', true)->count());
        $this->get(route('subscriptions'))->assertOk()
            ->assertSee('STARTER')->assertSee('STANDARD')->assertSee('PRO')->assertSee('PREMIUM')
            ->assertSee('200,00 USD')->assertSee('300,00 USD')->assertSee('500,00 USD')->assertSee('800,00 USD')
            ->assertSee('30,00 USD')->assertSee('45,00 USD')->assertSee('75,00 USD')->assertSee('120,00 USD')
            ->assertSee('5,00 USD')->assertSee('7,50 USD')->assertSee('12,50 USD')->assertSee('20,00 USD')
            ->assertSee('18 000 XOF')->assertSee('27 000 XOF')->assertSee('45 000 XOF')->assertSee('72 000 XOF')
            ->assertSee('Taux appliqué : 1 USD = 600 XOF')->assertSee('30 jours')
            ->assertSee('Choisir ce pack')->assertDontSee('BCEAO')
            ->assertSee('Les gains prévisionnels sont des objectifs indicatifs et ne constituent pas une garantie de rendement. Le trading comporte des risques de perte partielle ou totale du capital investi.');
    }

    public function test_each_pack_leads_to_a_server_priced_order_summary_without_simulating_payment(): void
    {
        app(\Database\Seeders\DatabaseSeeder::class)->run();
        $user = User::create([
            'first_name' => 'Test', 'last_name' => 'Investor', 'email' => 'catalog-buyer@example.test',
            'password' => 'a sufficiently long password', 'role' => 'INVESTOR', 'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        foreach (self::EXPECTED as $slug => [$price, $capital, $daily, $xof]) {
            $plan = SubscriptionPlan::where('slug', $slug)->firstOrFail();
            $response = $this->actingAs($user)->post(route('subscriptions.orders.store', $plan), [
                'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            ])->assertSessionHas('payment_unavailable');

            $order = SubscriptionOrder::latest('id')->firstOrFail();
            $response->assertRedirect(route('subscriptions.orders.show', $order));
            $this->assertSame($price, $order->amount_usd);
            $this->assertSame($capital, $order->capital_required_snapshot);
            $this->assertSame($daily, $order->estimated_daily_gain_usd_snapshot);
            $this->assertSame($xof, $order->amount_xof);
            $this->assertSame(30, $order->duration_days_snapshot);
            $this->assertSame('AWAITING_PAYMENT', $order->status);
            $this->get(route('subscriptions.orders.show', $order))->assertOk()
                ->assertSee('Seul le paiement en USDT BEP20 est accepté')
                ->assertSee('0xF9C6Bb6b72091Ddc8F5DcCc2D90EBc73870D042D')
                ->assertSee('la vérification de la transaction par l’administrateur')
                ->assertSee('ÉTAPE 2 · JUSTIFICATIF DU TRANSFERT')
                ->assertSee('J’ai payé · envoyer la capture')
                ->assertSee(number_format((float) $price, 2, ',', ' ').' USDT')
                ->assertSee('0x55d398326f99059ff775485246999027b3197955')
                ->assertDontSee('manual_payment_otp')
                ->assertDontSee('transaction_reference')
                ->assertDontSee('Orange Money')
                ->assertDontSee('Payer en ligne · paydunya');
        }

        $this->assertDatabaseCount('subscription_orders', 4);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_legacy_plan_remains_stored_but_cannot_be_selected_by_new_orders(): void
    {
        app(\Database\Seeders\DatabaseSeeder::class)->run();
        $legacy = SubscriptionPlan::create([
            'name' => 'Elite', 'slug' => 'elite', 'price' => '32.81', 'list_price' => '50.00',
            'currency' => 'USD', 'duration_days' => 30, 'capital_required' => '500.00',
            'status' => false,
        ]);
        $user = User::create([
            'first_name' => 'Test', 'last_name' => 'Investor', 'email' => 'legacy-buyer@example.test',
            'password' => 'a sufficiently long password', 'role' => 'INVESTOR', 'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user)->post(route('subscriptions.orders.store', $legacy), [
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertNotFound();
        $this->get(route('subscriptions'))->assertDontSee('ELITE');
        $this->assertDatabaseHas('subscription_plans', ['id' => $legacy->id, 'slug' => 'elite', 'status' => false]);
        $this->assertDatabaseCount('subscription_orders', 0);
    }

}
