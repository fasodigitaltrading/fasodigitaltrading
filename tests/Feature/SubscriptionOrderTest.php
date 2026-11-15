<?php

namespace Tests\Feature;

use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_a_usd_pack_creates_a_server_priced_order_and_falls_back_to_summary_when_payment_is_unavailable(): void
    {
        $this->seedBceaoRate();
        $user = $this->verifiedInvestor('buyer@example.test');
        $plan = $this->plan();
        $response = $this->actingAs($user)->post(route('subscriptions.orders.store', $plan), [
            'idempotency_key' => '6ebbbc93-6686-4a58-a914-407f1e198e4f',
        ]);

        $order = SubscriptionOrder::sole();
        $response->assertRedirect(route('subscriptions.orders.show', $order))
            ->assertSessionHas('payment_unavailable');
        $this->assertSame('30.00', $order->amount_usd);
        $this->assertSame(17806, $order->amount_xof);
        $this->assertSame('593.5200', $order->fx_rate);
        $this->assertSame(now()->toDateString(), $order->fx_rate_as_of->toDateString());
        $this->assertNotNull($order->fx_rate_updated_at);
        $this->assertSame('bceao-reference', $order->fx_provider);
        $this->assertSame('5.00', $order->estimated_daily_gain_usd_snapshot);
        $this->assertSame(30, $order->duration_days_snapshot);
        $this->assertSame('AWAITING_PAYMENT', $order->status);
        $this->assertDatabaseCount('payments', 0);

        $this->get(route('subscriptions.orders.show', $order))->assertOk()
            ->assertSee('30,00 USD')
            ->assertSee('17 806 XOF')
            ->assertSee('Cours officiel appliqué')
            ->assertSee('Devise du transfert mobile');
    }

    public function test_users_cannot_view_or_refresh_another_users_order(): void
    {
        $owner = $this->verifiedInvestor('owner@example.test');
        $other = $this->verifiedInvestor('other@example.test');
        $plan = $this->plan();
        $order = SubscriptionOrder::create([
            'user_id' => $owner->id,
            'subscription_plan_id' => $plan->id,
            'idempotency_key' => '4ecaa003-8a0e-46c5-9e29-69c1185e749d',
            'status' => 'AWAITING_PAYMENT',
            'plan_name_snapshot' => $plan->name,
            'amount_usd' => '10.00',
            'duration_days_snapshot' => 30,
            'max_mt5_accounts_snapshot' => 1,
        ]);

        $this->actingAs($other)->get(route('subscriptions.orders.show', $order))->assertNotFound();
        $this->post(route('subscriptions.orders.refresh', $order))->assertNotFound();
    }

    public function test_refreshing_a_pending_order_applies_the_latest_bceao_reference_rate(): void
    {
        $this->seedBceaoRate();
        $user = $this->verifiedInvestor('buyer@example.test');
        $plan = $this->plan();
        $order = SubscriptionOrder::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'idempotency_key' => 'bb9ddc20-3e2d-46ac-b839-ad0a41e78d63',
            'status' => 'AWAITING_PAYMENT',
            'plan_name_snapshot' => $plan->name,
            'amount_usd' => '10.00',
            'duration_days_snapshot' => 30,
            'max_mt5_accounts_snapshot' => 1,
            'amount_xof' => 5935,
            'fx_rate' => '593.5200',
            'fx_rate_as_of' => now()->toDateString(),
            'fx_rate_updated_at' => now(),
            'fx_provider' => 'currencyapi',
            'fx_source_url' => 'https://currencyapi.com/docs/rate/',
            'fx_fetched_at' => now(),
            'quote_expires_at' => now()->addMinutes(5),
        ]);

        $this->actingAs($user)->post(route('subscriptions.orders.refresh', $order))
            ->assertRedirect(route('subscriptions.orders.show', $order))
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertSame(5935, $order->amount_xof);
        $this->assertSame('593.5200', $order->fx_rate);
        $this->assertSame('bceao-reference', $order->fx_provider);
        $this->assertTrue($order->quote_expires_at->isFuture());
    }

    public function test_unapproved_non_usd_plan_cannot_create_a_customer_order(): void
    {
        $user = $this->verifiedInvestor('buyer@example.test');
        $plan = $this->plan();
        $plan->update(['currency' => 'XOF', 'price' => '5000.00']);

        $this->actingAs($user)->from(route('subscriptions'))->post(route('subscriptions.orders.store', $plan), [
            'idempotency_key' => '6ebbbc93-6686-4a58-a914-407f1e198e4f',
        ])->assertRedirect(route('subscriptions'))->assertSessionHasErrors('order');

        $this->assertDatabaseCount('subscription_orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    private function verifiedInvestor(string $email): User
    {
        $user = User::create([
            'first_name' => 'Test', 'last_name' => 'Buyer', 'email' => $email,
            'country' => 'Burkina Faso', 'password' => 'a-long-test-password',
            'role' => 'INVESTOR', 'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function plan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'STARTER', 'slug' => 'starter', 'price' => '30.00', 'list_price' => '30.00', 'currency' => 'USD',
            'duration_days' => 30, 'max_mt5_accounts' => 1, 'capital_required' => '200.00',
            'estimated_daily_gain_usd' => '5.00', 'features' => ['Lecture MT5'], 'status' => true,
        ]);
    }

    private function seedBceaoRate(): void
    {
        cache()->put('fx.bceao.usd-xof.latest', [
            'rate' => '593.5200',
            'as_of' => now()->toDateString(),
            'fetched_at' => now()->toIso8601String(),
            'source_url' => 'https://www.bceao.int/fr/cours/cours-de-reference-des-principales-devises-contre-Franc-CFA',
        ], now()->addDay());
    }
}
