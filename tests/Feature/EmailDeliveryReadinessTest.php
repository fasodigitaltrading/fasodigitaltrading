<?php

namespace Tests\Feature;

use App\Support\EmailDeliveryReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailDeliveryReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_smtp_send_is_blocked_until_a_live_delivery_is_attested(): void
    {
        app()['env'] = 'production';
        config([
            'app.env' => 'production',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.mail.example.net',
            'mail.from.address' => 'no-reply@blackwolf.example.net',
            'mail.mailers.smtp.username' => 'test-user',
            'mail.mailers.smtp.password' => 'test-password',
            'email.live_verified' => false,
        ]);

        $readiness = app(EmailDeliveryReadiness::class);
        $this->assertFalse($readiness->canSend());
        $this->assertTrue($readiness->canAttemptTest());
        $this->assertSame('paramètres présents — test d’envoi réel requis', $readiness->status());
    }

    public function test_registration_does_not_create_an_unverifiable_account_without_smtp(): void
    {
        app()['env'] = 'production';
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '',
            'mail.from.address' => '',
            'email.live_verified' => false,
        ]);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('L’inscription est momentanément indisponible')
            ->assertSee('href="/#contact"', false);

        $csrfToken = str_repeat('a', 40);
        $this->withSession(['_token' => $csrfToken])->from(route('register'))->post(route('register.store'), [
            '_token' => $csrfToken,
            'first_name' => 'Awa', 'last_name' => 'Example', 'email' => 'blocked@example.test',
            'country' => 'Burkina Faso', 'password' => 'a-new-long-password',
            'password_confirmation' => 'a-new-long-password', 'terms' => '1',
        ])->assertRedirect(route('register'))->assertSessionHasErrors('email_service');

        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.test']);
    }
}
