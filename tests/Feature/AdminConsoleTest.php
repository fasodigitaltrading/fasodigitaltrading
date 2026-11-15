<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\License;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_admin_console_sections_render_for_the_primary_mfa_admin(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        foreach ([
            'admin.dashboard', 'admin.users.index', 'admin.payments.index', 'admin.subscriptions.index',
            'admin.licenses.index', 'admin.trading-accounts.index', 'admin.partners.index', 'admin.commissions.index',
            'admin.plans.index', 'admin.support.index', 'admin.brvm-orders.index', 'admin.contact-requests.index',
            'admin.reports.index', 'admin.logs.index', 'admin.notifications.index', 'admin.settings.index',
            'admin.brokers.index', 'admin.manual-payments.index',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_admin_console_routes_remain_forbidden_to_investors(): void
    {
        $investor = $this->makeUser('client@example.test');
        $this->actingAs($investor);

        foreach ([
            'admin.users.index', 'admin.payments.index', 'admin.subscriptions.index', 'admin.licenses.index',
            'admin.trading-accounts.index', 'admin.partners.index', 'admin.commissions.index', 'admin.reports.index',
            'admin.logs.index', 'admin.notifications.index', 'admin.support.index',
        ] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_soft_deleting_a_user_preserves_their_subscription_and_can_restore_suspended(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser('retained@example.test');
        $plan = $this->makePlan();
        $subscription = Subscription::create([
            'user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'status' => 'PENDING',
            'plan_name_snapshot' => 'STARTER', 'plan_price_snapshot' => '30.00', 'plan_currency_snapshot' => 'USD',
            'plan_duration_days_snapshot' => 30,
        ]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user), [
            'confirmation' => 'SUPPRIMER', 'email_confirmation' => $user->email, 'reason' => 'Demande vérifiée du titulaire',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.user.soft_deleted', 'entity_id' => $user->id]);

        $this->post(route('admin.users.restore', $user->id), ['reason' => 'Restauration demandée après examen'])
            ->assertRedirect(route('admin.users.show', $user->id));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'SUSPENDED', 'deleted_at' => null]);
    }

    public function test_user_detail_does_not_disclose_mt5_tokens_or_license_keys(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser('private-record@example.test');
        TradingAccount::create([
            'user_id' => $user->id, 'account_number_masked' => '****4821', 'server' => 'Broker-Server',
            'connection_status' => 'DISCONNECTED', 'readonly_status' => true, 'currency' => 'USD',
            'account_access_token' => 'DO-NOT-RENDER-THIS-MT5-TOKEN',
        ]);
        License::create([
            'user_id' => $user->id, 'license_key' => 'DO-NOT-RENDER-THIS-LICENSE-KEY',
            'status' => 'SUSPENDED', 'max_accounts' => 1,
        ]);

        $this->actingAs($admin)->get(route('admin.users.show', $user->id))
            ->assertOk()->assertSee('****4821')
            ->assertDontSee('DO-NOT-RENDER-THIS-MT5-TOKEN')
            ->assertDontSee('DO-NOT-RENDER-THIS-LICENSE-KEY');
    }

    public function test_internal_support_notes_are_hidden_from_the_customer(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeUser('support-owner@example.test');
        $ticket = SupportTicket::create(['user_id' => $owner->id, 'subject' => 'Demande client', 'status' => 'OPEN']);
        SupportMessage::create([
            'support_ticket_id' => $ticket->id, 'user_id' => $admin->id, 'body' => 'Note privée équipe', 'is_internal' => true,
        ]);

        $this->actingAs($owner)->get(route('support.show', $ticket))->assertOk()->assertDontSee('Note privée équipe');
        $this->actingAs($admin)->get(route('support.show', $ticket))->assertOk()->assertSee('Note privée équipe')->assertSee('NOTE INTERNE');
    }

    public function test_admin_can_add_a_private_support_note_and_audited_ticket_status(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeUser('support-workflow@example.test');
        $ticket = SupportTicket::create(['user_id' => $owner->id, 'subject' => 'Paiement à contrôler', 'status' => 'OPEN']);

        $this->actingAs($admin)->post(route('admin.support.internal-note', $ticket), ['body' => 'Rapprocher le reçu avec le portefeuille marchand.'])
            ->assertRedirect(route('support.show', $ticket));
        $this->assertDatabaseHas('support_messages', [
            'support_ticket_id' => $ticket->id, 'is_internal' => true,
            'body' => 'Rapprocher le reçu avec le portefeuille marchand.',
        ]);

        $this->put(route('admin.support.update', $ticket), [
            'status' => 'IN_PROGRESS', 'priority' => 'HIGH', 'category' => 'PAYMENT', 'reason' => 'Pièce justificative à vérifier',
        ])->assertRedirect(route('support.show', $ticket));
        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'status' => 'IN_PROGRESS', 'priority' => 'HIGH', 'category' => 'PAYMENT', 'assigned_admin_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.support.updated', 'entity_id' => $ticket->id]);

        $this->actingAs($owner)->get(route('support.show', $ticket))->assertDontSee('Rapprocher le reçu avec le portefeuille marchand.');
    }

    public function test_commission_approval_and_manual_settlement_require_real_payment_and_reserve_period(): void
    {
        $admin = $this->makeAdmin();
        $partnerOwner = $this->makeUser('partner@example.test', 'PARTNER');
        $referred = $this->makeUser('referred@example.test');
        $partner = Partner::create(['user_id' => $partnerOwner->id, 'partner_code' => 'PARTNER10', 'commission_rate' => '10.00', 'status' => 'ACTIVE']);
        $payment = Payment::create([
            'user_id' => $referred->id, 'amount' => '30.00', 'currency' => 'USD', 'status' => 'PAID', 'paid_at' => now(),
        ]);
        $commission = Commission::create([
            'partner_id' => $partner->id, 'user_id' => $referred->id, 'payment_id' => $payment->id,
            'amount' => '3.00', 'currency' => 'USD', 'percentage' => '10.00', 'status' => 'PENDING',
            'available_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)->post(route('admin.commissions.control', $commission), [
            'action' => 'approve', 'reason' => 'Paiement reçu et réserve terminée',
        ])->assertRedirect();
        $this->assertSame('APPROVED', $commission->fresh()->status);

        $this->post(route('admin.commissions.control', $commission), [
            'action' => 'record-payout', 'payout_reference' => 'BANK-TRANSFER-001', 'confirm_payout' => 'yes',
            'reason' => 'Virement externe vérifié par référence',
        ])->assertRedirect();
        $commission->refresh();
        $this->assertSame('PAID', $commission->status);
        $this->assertSame('BANK-TRANSFER-001', $commission->payout_reference);
        $this->assertSame($admin->id, $commission->paid_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.commission.record-payout', 'entity_id' => $commission->id]);
    }

    public function test_license_cannot_be_reactivated_without_a_paid_subscription(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeUser('license-owner@example.test');
        $plan = $this->makePlan();
        $subscription = Subscription::create([
            'user_id' => $owner->id, 'subscription_plan_id' => $plan->id, 'status' => 'ACTIVE',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(29), 'plan_name_snapshot' => 'STARTER',
        ]);
        $license = License::create([
            'user_id' => $owner->id, 'subscription_id' => $subscription->id, 'license_key' => 'TEST-KEY-ADMIN-LICENSE',
            'status' => 'SUSPENDED', 'expires_at' => now()->addDays(29), 'max_accounts' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.licenses.control', $license), [
            'action' => 'reactivate', 'reason' => 'Vérification de renouvellement',
        ])->assertStatus(409);
        $this->assertSame('SUSPENDED', $license->fresh()->status);

        Payment::create(['user_id' => $owner->id, 'subscription_id' => $subscription->id, 'amount' => '30.00', 'currency' => 'USD', 'status' => 'PAID', 'paid_at' => now()]);
        $this->post(route('admin.licenses.control', $license), [
            'action' => 'reactivate', 'reason' => 'Paiement confirmé et compte actif',
        ])->assertRedirect();
        $this->assertSame('ACTIVE', $license->fresh()->status);
    }

    public function test_admin_can_suspend_a_partner_without_changing_the_commission_rate(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeUser('partner-admin@example.test', 'PARTNER');
        $partner = Partner::create(['user_id' => $owner->id, 'partner_code' => 'PARTNERADMIN01', 'commission_rate' => '10.00', 'status' => 'ACTIVE']);

        $this->actingAs($admin)->put(route('admin.partners.update', $partner), [
            'status' => 'SUSPENDED', 'reason' => 'Vérification de conformité demandée',
        ])->assertRedirect();

        $this->assertSame('SUSPENDED', $partner->fresh()->status);
        $this->assertSame('10.00', $partner->fresh()->commission_rate);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.partner.status_changed', 'entity_id' => $partner->id]);
    }

    private function makeAdmin(): User
    {
        $admin = $this->makeUser(config('admin.email'), 'ADMIN');
        $admin->forceFill(['mfa_verified_at' => now()])->save();
        return $admin;
    }

    private function makeUser(string $email, string $role = 'INVESTOR'): User
    {
        $user = User::create([
            'first_name' => 'Test', 'last_name' => 'User', 'email' => $email,
            'password' => 'a-strong-password-for-tests', 'role' => $role, 'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        return $user;
    }

    private function makePlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'STARTER', 'slug' => 'starter', 'price' => '30.00', 'currency' => 'USD', 'duration_days' => 30,
            'max_mt5_accounts' => 1, 'capital_required' => '200.00', 'estimated_daily_gain_usd' => '5.00', 'status' => true,
        ]);
    }
}
