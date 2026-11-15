<?php

namespace Tests\Feature;

use App\Models\ContactRequest;
use App\Models\License;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InvestorPortalAndContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_position_history_schema_is_append_only_and_account_deletion_is_restricted(): void
    {
        $this->assertTrue(Schema::hasColumns('open_positions', ['external_id', 'is_open', 'closed_at', 'last_seen_at']));
        $this->assertTrue(Schema::hasColumns('position_snapshots', [
            'trading_account_id', 'external_id', 'symbol', 'type', 'volume', 'floating_profit_loss', 'captured_at',
        ]));

        $user = User::create([
            'first_name' => 'History', 'last_name' => 'Owner', 'email' => 'history-owner@example.test',
            'password' => Hash::make('a-long-test-password'), 'role' => 'INVESTOR', 'status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);
        $account = TradingAccount::create([
            'user_id' => $user->id, 'account_number_masked' => '****1234', 'readonly_status' => true,
        ]);
        $account->positionSnapshots()->create([
            'external_id' => 'position-record-1', 'symbol' => 'test-symbol', 'type' => 'BUY',
            'volume' => '0.0100', 'floating_profit_loss' => '0.00', 'captured_at' => now(),
        ]);

        try {
            $account->delete();
            $this->fail('An account with retained position history must not be deletable.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseHas('trading_accounts', ['id' => $account->id]);
            $this->assertDatabaseCount('position_snapshots', 1);
        }
    }

    public function test_the_public_pack_inquiry_is_saved_for_the_admin_portal(): void
    {
        SubscriptionPlan::create([
            'name' => 'STARTER', 'slug' => 'starter', 'description' => 'Essai', 'price' => 0,
            'currency' => 'USD', 'duration_days' => 1, 'max_mt5_accounts' => 1,
            'capital_required' => 20, 'features' => [], 'status' => true,
        ]);

        $this->from(route('home'))->post(route('contact.store'), [
            'name' => 'Awa Test',
            'email' => 'awa@example.test',
            'intent' => 'demo',
            'package' => 'starter',
            'message' => 'Je souhaite découvrir le pack Starter.',
            'privacy_consent' => '1',
        ])->assertRedirect(route('home'))->assertSessionHas('contact_status');

        $this->assertDatabaseHas('contact_requests', [
            'email' => 'awa@example.test', 'package' => 'starter', 'status' => 'NEW',
        ]);
        $contact = ContactRequest::sole();
        $this->assertNotNull($contact->privacy_accepted_at);
        $this->assertSame('contact-v2', $contact->privacy_notice_version);

        $admin = User::create([
            'first_name' => 'Site', 'last_name' => 'Admin', 'email' => config('admin.email'),
            'password' => Hash::make('a-long-test-password'), 'role' => 'ADMIN', 'status' => 'ACTIVE',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)->get(route('admin.contact-requests.index'))->assertForbidden();
        $this->actingAs($admin)->put(route('admin.contact-requests.update', $contact), ['status' => 'RESOLVED'])->assertForbidden();

        $admin->forceFill(['mfa_verified_at' => now()])->save();
        $this->actingAs($admin)->get(route('admin.contact-requests.index'))
            ->assertOk()->assertSee('awa@example.test')->assertSee('Je souhaite découvrir le pack Starter.');
        $this->put(route('admin.contact-requests.update', $contact), ['status' => 'IN_PROGRESS'])
            ->assertRedirect(route('admin.contact-requests.index'));
        $this->assertDatabaseHas('contact_requests', ['id' => $contact->id, 'status' => 'IN_PROGRESS']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'contact_request.status_updated', 'entity_type' => ContactRequest::class, 'entity_id' => $contact->id,
        ]);
    }

    public function test_investor_and_administrator_portals_enforce_the_account_role(): void
    {
        $investor = User::create([
            'first_name' => 'Awa', 'last_name' => 'Investor', 'email' => 'investor@example.test',
            'password' => Hash::make('a-long-test-password'), 'role' => 'INVESTOR', 'status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);

        $this->get(route('portal.investor.login'))->assertOk()->assertSee('Connexion investisseur');
        $this->get(route('portal.admin.login'))->assertOk()->assertSee('Connexion administrateur');

        $this->post(route('portal.admin.authenticate'), [
            'email' => $investor->email, 'password' => 'a-long-test-password',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_legal_information_page_discloses_missing_operator_details(): void
    {
        config(['legal.operator_name' => null, 'legal.operator_address' => null, 'legal.operator_email' => null]);

        $this->get(route('legal'))->assertOk()
            ->assertSee('Informations exploitant à compléter')
            ->assertSee('Confidentialité')
            ->assertSee('FASO DIGITAL TRADING');
    }

    public function test_investor_dashboard_reveals_only_the_current_users_license_key(): void
    {
        $owner = User::create([
            'first_name' => 'License', 'last_name' => 'Owner', 'email' => 'license-owner@example.test',
            'password' => Hash::make('a-long-test-password'), 'role' => 'INVESTOR', 'status' => 'ACTIVE',
        ]);
        $owner->forceFill(['email_verified_at' => now()])->save();
        $other = User::create([
            'first_name' => 'Other', 'last_name' => 'Investor', 'email' => 'other-license@example.test',
            'password' => Hash::make('a-long-test-password'), 'role' => 'INVESTOR', 'status' => 'ACTIVE',
        ]);
        $other->forceFill(['email_verified_at' => now()])->save();
        [, $licenseKey] = License::issue([
            'user_id' => $owner->id, 'status' => 'ACTIVE', 'max_accounts' => 1, 'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($owner)->get(route('investor.dashboard'))->assertOk()->assertSee($licenseKey);
        $this->actingAs($other)->get(route('investor.dashboard'))->assertOk()->assertDontSee($licenseKey);
    }

    public function test_investor_dashboard_lists_only_the_current_users_subscriptions_and_orders(): void
    {
        $owner = $this->makeInvestor('dashboard-owner@example.test');
        $other = $this->makeInvestor('dashboard-other@example.test');
        $ownerPlan = $this->makePlan('Private owner plan', 'private-owner-plan');
        $otherPlan = $this->makePlan('Private other plan', 'private-other-plan');

        Subscription::create([
            'user_id' => $owner->id,
            'subscription_plan_id' => $ownerPlan->id,
            'status' => 'ACTIVE',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'plan_name_snapshot' => 'Owner active subscription',
            'plan_price_snapshot' => '25.00',
            'plan_currency_snapshot' => 'USD',
        ]);
        Subscription::create([
            'user_id' => $other->id,
            'subscription_plan_id' => $otherPlan->id,
            'status' => 'ACTIVE',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'plan_name_snapshot' => 'Other private subscription',
            'plan_price_snapshot' => '80.00',
            'plan_currency_snapshot' => 'USD',
        ]);
        SubscriptionOrder::create([
            'user_id' => $owner->id,
            'subscription_plan_id' => $ownerPlan->id,
            'idempotency_key' => 'a83a17fb-1435-4723-9949-40e8d568f751',
            'status' => 'AWAITING_PAYMENT',
            'plan_name_snapshot' => 'Owner pending order',
            'amount_usd' => '25.00',
            'duration_days_snapshot' => 30,
            'max_mt5_accounts_snapshot' => 1,
        ]);
        SubscriptionOrder::create([
            'user_id' => $other->id,
            'subscription_plan_id' => $otherPlan->id,
            'idempotency_key' => '5c9d22f4-0665-4bf4-8b80-7e232b8b1571',
            'status' => 'AWAITING_PAYMENT',
            'plan_name_snapshot' => 'Other private order',
            'amount_usd' => '80.00',
            'duration_days_snapshot' => 90,
            'max_mt5_accounts_snapshot' => 2,
        ]);

        $this->actingAs($owner)->get(route('investor.dashboard'))
            ->assertOk()
            ->assertSee('Owner active subscription')
            ->assertSee('Owner pending order')
            ->assertSee('Abonnements actifs')
            ->assertDontSee('Other private subscription')
            ->assertDontSee('Other private order');
    }

    public function test_dashboard_does_not_request_metaapi_tokens_before_the_connector_is_ready(): void
    {
        config(['mt5.provider' => null, 'mt5.metaapi.base_url' => null]);
        $investor = $this->makeInvestor('mt5-unavailable@example.test');

        $this->actingAs($investor)->get(route('investor.dashboard'))
            ->assertOk()
            ->assertSee('Connexion MT5 en attente de configuration')
            ->assertDontSee('name="account_access_token"', false)
            ->assertDontSee('Token MetaApi reader limité à ce compte');
    }

    private function makeInvestor(string $email): User
    {
        $user = User::create([
            'first_name' => 'Investisseur', 'last_name' => 'Test', 'email' => $email,
            'password' => Hash::make('a-long-test-password'), 'role' => 'INVESTOR', 'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function makePlan(string $name, string $slug): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => $name, 'slug' => $slug, 'description' => 'Plan de test', 'price' => '25.00',
            'currency' => 'USD', 'duration_days' => 30, 'max_mt5_accounts' => 1,
            'features' => [], 'status' => true,
        ]);
    }
}
