<?php

namespace Tests\Feature;

use App\Mail\AdminSmtpTest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminSmtpTestTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_admin_with_mfa_can_send_smtp_test_only_to_configured_admin_address(): void
    {
        Mail::fake();
        $admin = User::create([
            'first_name' => 'Primary', 'last_name' => 'Administrator',
            'email' => config('admin.email'), 'password' => 'a-long-test-password',
            'role' => 'ADMIN', 'status' => 'ACTIVE',
        ]);
        $admin->forceFill([
            'email_verified_at' => now(),
            'admin_totp_secret' => 'encrypted-test-secret',
            'admin_totp_enabled_at' => now(),
            'mfa_verified_at' => now(),
        ])->save();

        $this->actingAs($admin)->post(route('admin.services.smtp-test'))
            ->assertRedirect()->assertSessionHas('smtp_test_accepted');

        Mail::assertSent(AdminSmtpTest::class, fn (AdminSmtpTest $mail) => $mail->hasTo(config('admin.email')));
    }

    public function test_smtp_test_does_not_claim_delivery_or_send_when_production_configuration_is_missing(): void
    {
        Mail::fake();
        app()['env'] = 'production';
        config([
            'app.env' => 'production',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '',
            'mail.from.address' => '',
        ]);
        $admin = User::create([
            'first_name' => 'Primary', 'last_name' => 'Administrator',
            'email' => config('admin.email'), 'password' => 'a-long-test-password',
            'role' => 'ADMIN', 'status' => 'ACTIVE',
        ]);
        $admin->forceFill([
            'email_verified_at' => now(), 'admin_totp_enabled_at' => now(), 'mfa_verified_at' => now(),
        ])->save();

        $csrfToken = str_repeat('a', 40);
        $this->actingAs($admin)->withSession(['_token' => $csrfToken])->from(route('admin.dashboard'))
            ->post(route('admin.services.smtp-test'), ['_token' => $csrfToken])
            ->assertRedirect(route('admin.dashboard'))->assertSessionHasErrors('smtp');

        Mail::assertNothingSent();
    }

    public function test_investor_cannot_trigger_admin_smtp_test(): void
    {
        $investor = User::create([
            'first_name' => 'Investor', 'last_name' => 'Test', 'email' => 'investor@example.test',
            'password' => 'a-long-test-password', 'role' => 'INVESTOR', 'status' => 'ACTIVE',
        ]);
        $investor->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($investor)->post(route('admin.services.smtp-test'))->assertForbidden();
    }
}
