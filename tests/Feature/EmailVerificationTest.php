<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_account_pages_require_a_signed_email_verification_link(): void
    {
        $user = $this->makeUnverifiedUser();
        $this->actingAs($user)->withSession(['auth.password_revision' => 0]);

        $this->get(route('investor.dashboard'))->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee($user->email);

        $notification = new VerifyEmailNotification();
        $mail = $notification->toMail($user);
        $this->assertTrue(URL::hasValidSignature(HttpRequest::create($mail->actionUrl)));

        $this->get($mail->actionUrl)->assertRedirect(route('investor.dashboard'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get(route('investor.dashboard'))->assertOk();
    }

    public function test_signed_link_with_a_different_email_hash_is_rejected(): void
    {
        $user = $this->makeUnverifiedUser();
        $this->actingAs($user)->withSession(['auth.password_revision' => 0]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $user->id,
            'hash' => sha1('different@example.test'),
        ]);

        $this->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_user_can_request_another_verification_email(): void
    {
        Notification::fake();
        $user = $this->makeUnverifiedUser();

        $this->actingAs($user)->withSession(['auth.password_revision' => 0])
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'));

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_verification_resend_is_limited_to_three_requests_per_hour(): void
    {
        Notification::fake();
        $user = $this->makeUnverifiedUser();
        $this->actingAs($user)->withSession(['auth.password_revision' => 0]);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->from(route('verification.notice'))->post(route('verification.send'))
                ->assertRedirect(route('verification.notice'));
        }
        $this->from(route('verification.notice'))->post(route('verification.send'))
            ->assertRedirect()
            ->assertSessionHasErrors('verification');

        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 3);
    }

    private function makeUnverifiedUser(): User
    {
        return User::create([
            'first_name' => 'Awa',
            'last_name' => 'Unverified',
            'email' => 'unverified@example.test',
            'country' => 'France',
            'password' => 'a-current-long-password',
            'role' => 'INVESTOR',
            'status' => 'ACTIVE',
        ]);
    }
}
