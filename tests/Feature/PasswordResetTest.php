<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_request_does_not_disclose_unknown_addresses(): void
    {
        Notification::fake();
        $this->get(route('password.request'))->assertOk()->assertSee('Mot de passe oublié ?');

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'absent@example.test'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'Si un compte correspond à cette adresse, vérifiez votre boîte de réception. Si aucun message n’arrive, contactez le support.');

        Notification::assertNothingSent();
    }

    public function test_password_reset_explains_global_smtp_unavailability_without_sending(): void
    {
        app()['env'] = 'production';
        config([
            'app.env' => 'production',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.from.address' => '',
            'email.live_verified' => false,
        ]);
        Notification::fake();

        try {
            $this->get(route('password.request'))
                ->assertOk()
                ->assertSee('Aucun lien n’a été envoyé')
                ->assertSee('disabled', false);

            $csrfToken = str_repeat('a', 40);
            $this->withSession(['_token' => $csrfToken])
                ->from(route('password.request'))
                ->post(route('password.email'), [
                    '_token' => $csrfToken,
                    'email' => 'awa@example.test',
                ])
                ->assertRedirect(route('password.request'))
                ->assertSessionHas('email_service_unavailable');

            Notification::assertNothingSent();
        } finally {
            app()['env'] = 'testing';
            config(['app.env' => 'testing']);
        }
    }

    public function test_reset_link_uses_the_branded_notification(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'Si un compte correspond à cette adresse, vérifiez votre boîte de réception. Si aucun message n’arrive, contactez le support.');

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) {
            return is_string($notification->token) && $notification->token !== '';
        });
    }

    public function test_valid_reset_changes_password_and_advances_the_session_revision(): void
    {
        $user = $this->makeUser();
        $token = Password::createToken($user);
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()->assertSee('Choisissez un nouveau mot de passe.');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-new-long-password',
            'password_confirmation' => 'a-new-long-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');

        $user->refresh();
        $this->assertTrue(Hash::check('a-new-long-password', $user->password));
        $this->assertSame(1, (int) $user->password_revision);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_password_revision_invalidates_an_older_authenticated_session(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->withSession(['auth.password_revision' => 0]);
        $user->forceFill(['password_revision' => 1])->save();

        $this->get(route('support.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    private function makeUser(): User
    {
        $user = User::create([
            'first_name' => 'Awa',
            'last_name' => 'Example',
            'email' => 'awa@example.test',
            'country' => 'France',
            'password' => 'a-current-long-password',
            'role' => 'INVESTOR',
            'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
