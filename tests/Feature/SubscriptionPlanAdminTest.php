<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubscriptionPlanAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_mfa_admin_can_update_catalogue_and_old_subscription_terms_are_preserved(): void
    {
        $admin = $this->makeUser(config('admin.email'), 'ADMIN');
        $admin->forceFill(['mfa_verified_at' => now()])->save();
        $plan = $this->makePlan();
        $subscription = Subscription::create([
            'user_id' => $admin->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'ACTIVE',
            'plan_name_snapshot' => 'Starter',
            'plan_price_snapshot' => '10.00',
            'plan_currency_snapshot' => 'USD',
            'plan_duration_days_snapshot' => 30,
            'plan_max_mt5_accounts_snapshot' => 1,
            'plan_capital_required_snapshot' => '100.00',
        ]);

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => 'STARTER', 'description' => 'Offre mise à jour',
            'currency' => 'USD',
            'price' => '30.00', 'list_price' => '30.00', 'capital_required' => '200.00',
            'duration_days' => 30, 'max_mt5_accounts' => 1, 'sort_order' => 1, 'status' => '1',
        ])->assertRedirect(route('admin.plans.index'))->assertSessionHasNoErrors();

        $this->assertSame('30.00', $plan->fresh()->price);
        $this->assertSame(30, $plan->fresh()->duration_days);
        $this->assertSame('Offre mise à jour', $plan->fresh()->description);
        $this->assertSame('10.00', $subscription->fresh()->plan_price_snapshot);
        $this->assertSame(30, $subscription->fresh()->plan_duration_days_snapshot);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription_plan.updated', 'entity_id' => $plan->id]);
    }

    public function test_non_admin_cannot_open_or_update_the_catalogue(): void
    {
        $investor = $this->makeUser('investor@example.test', 'INVESTOR');
        $plan = $this->makePlan();

        $this->actingAs($investor)->get(route('admin.plans.index'))->assertForbidden();
        $this->actingAs($investor)->put(route('admin.plans.update', $plan), [])->assertForbidden();
        $this->assertSame('30.00', $plan->fresh()->price);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_invalid_price_order_is_rejected_without_changing_offer(): void
    {
        $admin = $this->makeUser(config('admin.email'), 'ADMIN');
        $admin->forceFill(['mfa_verified_at' => now()])->save();
        $plan = $this->makePlan();

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => 'Starter', 'currency' => 'USD', 'price' => '20.00', 'list_price' => '15.00',
            'capital_required' => '200.00', 'duration_days' => 30,
            'max_mt5_accounts' => 1, 'sort_order' => 1, 'status' => '1',
        ])->assertSessionHasErrors('list_price');

        $this->assertSame('30.00', $plan->fresh()->price);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_xof_offer_amounts_must_be_integers_for_paydunya_checkout(): void
    {
        $admin = $this->makeUser(config('admin.email'), 'ADMIN');
        $admin->forceFill(['mfa_verified_at' => now()])->save();
        $plan = $this->makePlan();

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => 'Starter', 'currency' => 'XOF', 'price' => '5000.50', 'list_price' => '6000.00',
            'capital_required' => '100000.00', 'duration_days' => 30, 'max_mt5_accounts' => 1,
            'sort_order' => 1, 'status' => '1',
        ])->assertSessionHasErrors('price');

        $this->assertSame('USD', $plan->fresh()->currency);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_xof_offer_price_must_be_positive(): void
    {
        $admin = $this->makeUser(config('admin.email'), 'ADMIN');
        $admin->forceFill(['mfa_verified_at' => now()])->save();
        $plan = $this->makePlan();

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => 'Starter', 'currency' => 'XOF', 'price' => '0', 'list_price' => '0',
            'capital_required' => '100000', 'duration_days' => 30, 'max_mt5_accounts' => 1,
            'sort_order' => 1, 'status' => '1',
        ])->assertSessionHasErrors('price');

        $this->assertSame('USD', $plan->fresh()->currency);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::create([
            'first_name' => 'Test', 'last_name' => 'Account', 'email' => $email,
            'password' => Hash::make('a-long-test-password'), 'role' => $role, 'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function makePlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'STARTER', 'slug' => 'starter', 'price' => '30.00', 'list_price' => '30.00',
            'currency' => 'USD', 'duration_days' => 30, 'max_mt5_accounts' => 1,
            'capital_required' => '200.00', 'estimated_daily_gain_usd' => '5.00', 'status' => true, 'sort_order' => 1,
        ]);
    }
}
