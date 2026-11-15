<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\License;
use App\Models\Partner;
use App\Policies\LicensePolicy;
use App\Policies\PartnerPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewRegistrationAdminAlert;
use App\Mail\WelcomeUser;
use App\Notifications\VerifyEmailNotification;
use App\Services\AdminTotpService;
use Tests\TestCase;

class AuthRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_framework_database_tables_and_application_tables_migrate(): void
    {
        foreach ([
            'users', 'subscription_plans', 'licenses', 'license_account_bindings', 'sessions', 'jobs', 'cache',
            'password_reset_tokens', 'audit_logs', 'payments', 'payment_webhook_events', 'trading_accounts',
            'trades', 'open_positions', 'performance_snapshots', 'support_tickets', 'support_messages', 'notifications',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing migrated table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('users', ['mfa_code_hash', 'mfa_code_expires_at', 'mfa_verified_at', 'admin_totp_secret', 'admin_totp_enabled_at', 'admin_totp_last_step', 'password_revision']));
        $this->assertTrue(Schema::hasColumns('licenses', ['license_key_hash', 'legacy_license_key_encrypted']));
    }

    public function test_regular_login_uses_email_and_redirects_to_the_role_dashboard(): void
    {
        $user = $this->makeUser();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'correct horse battery staple'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertSame((int) $user->password_revision, (int) session('auth.password_revision'));
    }

    public function test_registration_creates_an_investor_session_with_a_fresh_password_revision(): void
    {
        Notification::fake();
        Mail::fake();

        $this->post(route('register.store'), [
            'first_name' => 'Awa',
            'last_name' => 'Example',
            'email' => 'new-investor@example.test',
            'country' => 'France',
            'password' => 'a-new-long-password',
            'password_confirmation' => 'a-new-long-password',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'new-investor@example.test')->sole();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
        Mail::assertSent(WelcomeUser::class, fn (WelcomeUser $mail) => $mail->hasTo($user->email));
        Mail::assertSent(NewRegistrationAdminAlert::class, fn (NewRegistrationAdminAlert $mail) =>
            $mail->hasTo(config('admin.email')) && $mail->registeredUser->is($user));
        $this->assertAuthenticatedAs($user);
        $this->assertSame((int) $user->password_revision, (int) session('auth.password_revision'));
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(config('legal.terms_version'), $user->terms_version);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_admin_registration_alert_uses_the_requested_faso_digital_address(): void
    {
        $this->assertSame('fasodigitaltrading@gmail.com', config('admin.email'));
    }

    public function test_registration_rejects_existing_email_even_when_letter_case_differs(): void
    {
        $this->makeUser(['email' => 'Already.Used@example.test']);

        $this->from(route('register'))->post(route('register.store'), [
            'first_name' => 'Awa', 'last_name' => 'Example', 'email' => ' already.used@EXAMPLE.test ',
            'country' => 'Burkina Faso', 'password' => 'a-new-long-password',
            'password_confirmation' => 'a-new-long-password', 'terms' => '1',
        ])->assertRedirect(route('register'))->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_database_rejects_a_second_account_when_email_case_or_whitespace_differs(): void
    {
        $this->makeUser(['email' => 'single-account@example.test']);

        try {
            User::create([
                'first_name' => 'Duplicate', 'last_name' => 'Account',
                'email' => ' SINGLE-ACCOUNT@EXAMPLE.TEST ', 'password' => 'a-long-test-password',
                'role' => 'INVESTOR', 'status' => 'ACTIVE',
            ]);
            $this->fail('The normalized unique database index must reject the duplicate address.');
        } catch (QueryException) {
            $this->assertDatabaseCount('users', 1);
        }
    }

    public function test_only_the_configured_primary_admin_email_can_start_admin_login(): void
    {
        $otherAdmin = $this->makeUser(['email' => 'another-admin@example.test', 'role' => 'ADMIN']);

        $this->from(route('portal.admin.login'))->post(route('portal.admin.authenticate'), [
            'email' => $otherAdmin->email, 'password' => 'correct horse battery staple',
        ])->assertRedirect(route('portal.admin.login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_login_enrolls_an_authenticator_before_authentication(): void
    {
        $admin = $this->makeUser(['role' => 'ADMIN']);

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'correct horse battery staple'])
            ->assertRedirect(route('mfa.setup'));

        $this->assertGuest();
        $admin->refresh();
        $this->assertNotEmpty($admin->admin_totp_secret);
        $this->assertNull($admin->admin_totp_enabled_at);
        $this->get(route('mfa.setup'))->assertOk()->assertSee($admin->admin_totp_secret);

        $totp = app(AdminTotpService::class);
        $code = $totp->codeAt($admin->admin_totp_secret, now()->timestamp);
        $this->post(route('mfa.setup.verify'), ['code' => $code])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->admin_totp_enabled_at);
        $this->assertNotNull($admin->fresh()->mfa_verified_at);
    }

    public function test_totp_matches_the_rfc_6238_sha1_vector(): void
    {
        $totp = app(AdminTotpService::class);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', $totp->codeAt($secret, 59));
        $this->assertTrue($totp->verify($secret, '287082', 59));
        $this->assertFalse($totp->verify($secret, '287083', 59));
    }

    public function test_admin_totp_code_cannot_be_replayed_within_its_valid_time_step(): void
    {
        $admin = $this->makeUser(['role' => 'ADMIN']);
        $totp = app(AdminTotpService::class);
        $secret = $totp->generateSecret();
        $timestamp = now()->timestamp;
        $admin->forceFill(['admin_totp_secret' => $secret])->save();
        $code = $totp->codeAt($secret, $timestamp);

        $this->assertTrue($totp->verifyAndConsume($admin, $code, $timestamp));
        $this->assertFalse($totp->verifyAndConsume($admin->fresh(), $code, $timestamp));
    }

    public function test_valid_authenticator_code_completes_admin_login(): void
    {
        $admin = $this->makeUser(['role' => 'ADMIN']);
        $totp = app(AdminTotpService::class);
        $admin->forceFill([
            'admin_totp_secret' => $totp->generateSecret(),
            'admin_totp_enabled_at' => now(),
        ])->save();
        $this->withSession(['auth.mfa_user_id' => $admin->id]);

        $code = $totp->codeAt($admin->admin_totp_secret, now()->timestamp);
        $this->post(route('mfa.verify'), ['code' => $code])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->mfa_verified_at);
    }

    public function test_non_admin_cannot_access_another_role_dashboard_and_suspended_accounts_are_denied(): void
    {
        $investor = $this->makeUser();
        $this->actingAs($investor)
            ->get(route('partner.dashboard'))
            ->assertNotFound();

        $investor->forceFill(['status' => 'SUSPENDED'])->save();
        $this->actingAs($investor)
            ->get(route('investor.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_dashboard_requires_a_completed_mfa_challenge(): void
    {
        $admin = $this->makeUser(['role' => 'ADMIN']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $admin->forceFill(['mfa_verified_at' => now()])->save();
        $this->actingAs($admin->fresh())
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_non_primary_admin_is_denied_even_when_mfa_is_marked_verified(): void
    {
        $admin = $this->makeUser(['email' => 'former-admin@example.test', 'role' => 'ADMIN']);
        $admin->forceFill(['mfa_verified_at' => now()])->save();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_user_policy_blocks_idor_and_requires_mfa_for_administrator_access(): void
    {
        $policy = new UserPolicy();
        $owner = $this->makeUser();
        $other = $this->makeUser(['email' => 'other@example.test']);
        $admin = $this->makeUser(['email' => config('admin.email'), 'role' => 'ADMIN']);

        $this->assertTrue($policy->view($owner, $owner));
        $this->assertFalse($policy->view($owner, $other));
        $this->assertFalse($policy->view($admin, $other));

        $admin->forceFill(['mfa_verified_at' => now()])->save();
        $this->assertTrue($policy->view($admin->fresh(), $other));
    }

    public function test_license_and_partner_policies_limit_records_to_the_owner_or_a_verified_admin(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser(['email' => 'other@example.test']);
        $admin = $this->makeUser(['email' => config('admin.email'), 'role' => 'ADMIN']);
        $license = new License(['user_id' => $owner->id]);
        $partner = new Partner(['user_id' => $owner->id]);

        $this->assertTrue((new LicensePolicy())->view($owner, $license));
        $this->assertFalse((new LicensePolicy())->view($other, $license));
        $this->assertTrue((new PartnerPolicy())->view($owner, $partner));
        $this->assertFalse((new PartnerPolicy())->view($other, $partner));

        $admin->forceFill(['mfa_verified_at' => now()])->save();
        $this->assertTrue((new LicensePolicy())->view($admin->fresh(), $license));
        $this->assertTrue((new PartnerPolicy())->view($admin->fresh(), $partner));
    }

    private function makeUser(array $attributes = []): User
    {
        $defaultEmail = ($attributes['role'] ?? 'INVESTOR') === 'ADMIN'
            ? config('admin.email')
            : 'user@example.test';
        $user = User::create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $defaultEmail,
            'country' => 'France',
            'password' => 'correct horse battery staple',
            'role' => 'INVESTOR',
            'status' => 'ACTIVE',
        ], $attributes));
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
