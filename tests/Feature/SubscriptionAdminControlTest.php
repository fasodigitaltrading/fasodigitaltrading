<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionAdminControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_admin_can_activate_and_suspend_a_subscription_with_audited_license_changes(): void
    {
        config(['license.binding_key' => str_repeat('k', 32)]);
        $admin = $this->makeAdmin();
        $owner = $this->makeUser();
        $plan = SubscriptionPlan::create([
            'name' => 'Starter', 'slug' => 'starter', 'price' => '5000.00', 'currency' => 'XOF',
            'duration_days' => 30, 'max_mt5_accounts' => 2, 'status' => true,
        ]);
        $subscription = Subscription::create([
            'user_id' => $owner->id, 'subscription_plan_id' => $plan->id, 'status' => 'PENDING',
            'plan_name_snapshot' => $plan->name, 'plan_price_snapshot' => $plan->price,
            'plan_currency_snapshot' => $plan->currency, 'plan_duration_days_snapshot' => 30,
            'plan_max_mt5_accounts_snapshot' => 2,
        ]);

        $this->actingAs($admin)->post(route('admin.subscriptions.control', $subscription), [
            'action' => 'activate', 'reason' => 'Paiement vérifié hors plateforme',
            'payment_reference' => 'MOBILE-RECEIPT-2026-001', 'confirm_payment_received' => 'yes',
        ])->assertRedirect(route('admin.subscriptions.index'));

        $subscription->refresh();
        $license = License::where('subscription_id', $subscription->id)->sole();
        $this->assertSame('ACTIVE', $subscription->status);
        $this->assertSame('ACTIVE', $subscription->admin_override);
        $this->assertSame('ACTIVE', $license->status);
        $this->assertSame(2, $license->max_accounts);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.admin_activated', 'entity_id' => $subscription->id]);

        $this->actingAs($admin)->post(route('admin.subscriptions.control', $subscription), [
            'action' => 'deactivate', 'reason' => 'Suspension demandée par le client',
        ])->assertRedirect(route('admin.subscriptions.index'));

        $subscription->refresh();
        $license->refresh();
        $this->assertSame('DISABLED', $subscription->admin_override);
        $this->assertSame('SUSPENDED', $license->status);
        $this->assertFalse($subscription->isOperationallyActive());
        $validation = app(LicenseService::class)->validate($license->revealKey(), '123456', 'Broker-Demo');
        $this->assertFalse($validation['valid']);
        $this->assertSame('SUSPENDED', $validation['status']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.admin_deactivated', 'entity_id' => $subscription->id]);
    }

    private function makeAdmin(): User
    {
        $admin = User::create([
            'first_name' => 'FASO', 'last_name' => 'Admin', 'email' => config('admin.email'),
            'password' => 'a-strong-admin-password-123', 'role' => 'ADMIN', 'status' => 'ACTIVE',
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'mfa_verified_at' => now()])->save();

        return $admin;
    }

    private function makeUser(): User
    {
        $user = User::create([
            'first_name' => 'Awa', 'last_name' => 'Investor', 'email' => 'subscriber@example.test',
            'password' => 'a-long-investor-password', 'role' => 'INVESTOR', 'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
