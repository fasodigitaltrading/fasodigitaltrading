<?php

namespace Tests\Feature;

use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class SelectedPackageRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_keeps_selected_package_and_opens_its_order_after_email_verification(): void
    {
        Mail::fake();
        $this->seedBceaoRate();
        $plan = SubscriptionPlan::create([
            'name' => 'STANDARD',
            'slug' => 'standard',
            'price' => '45.00',
            'list_price' => '45.00',
            'currency' => 'USD',
            'duration_days' => 30,
            'max_mt5_accounts' => 1,
            'capital_required' => '300.00',
            'estimated_daily_gain_usd' => '7.50',
            'features' => [],
            'status' => true,
            'sort_order' => 2,
        ]);

        $this->get(route('register', ['pack' => $plan->slug]))
            ->assertOk()
            ->assertSee('name="pack" value="standard"', false);

        $this->post(route('register.store'), [
            'first_name' => 'Acheteur',
            'last_name' => 'Test',
            'email' => 'package-buyer@example.test',
            'country' => 'Burkina Faso',
            'password' => 'a sufficiently long password',
            'password_confirmation' => 'a sufficiently long password',
            'terms' => '1',
            'pack' => $plan->slug,
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'package-buyer@example.test')->firstOrFail();
        $order = SubscriptionOrder::sole();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame($plan->id, $order->subscription_plan_id);
        $this->assertSame('45.00', $order->amount_usd);
        $this->assertSame(26708, $order->amount_xof);
        $this->assertSame('bceao-reference', $order->fx_provider);
        $this->assertSame('593.5200', $order->fx_rate);
        $this->assertSame('AWAITING_PAYMENT', $order->status);
        $this->assertDatabaseCount('payments', 0);

        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
        $this->actingAs($user)->get($verificationUrl)
            ->assertRedirect(route('subscriptions.orders.show', $order))
            ->assertSessionHas('status', 'Adresse e-mail confirmée. Votre commande est prête; aucun paiement n’a été effectué.');

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('AWAITING_PAYMENT', $order->fresh()->status);
    }

    public function test_an_existing_unverified_investor_can_prepare_a_selected_package_before_confirming_email(): void
    {
        $this->seedBceaoRate();
        $plan = SubscriptionPlan::create([
            'name' => 'PRO',
            'slug' => 'pro',
            'price' => '75.00',
            'list_price' => '75.00',
            'currency' => 'USD',
            'duration_days' => 30,
            'max_mt5_accounts' => 1,
            'capital_required' => '500.00',
            'estimated_daily_gain_usd' => '12.50',
            'features' => [],
            'status' => true,
            'sort_order' => 3,
        ]);
        $user = User::create([
            'first_name' => 'Acheteur',
            'last_name' => 'En attente',
            'email' => 'unverified-buyer@example.test',
            'password' => 'a sufficiently long password',
            'role' => 'INVESTOR',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($user)->post(route('subscriptions.orders.prepare', $plan))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('checkout.order_id');

        $order = SubscriptionOrder::sole();
        $this->assertSame(44514, $order->amount_xof);
        $this->assertDatabaseCount('payments', 0);

        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
        $this->actingAs($user)->get($verificationUrl)
            ->assertRedirect(route('subscriptions.orders.show', $order));
        $this->assertNotNull($user->fresh()->email_verified_at);
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
