<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_ticket_notifies_admin_and_isolated_from_other_users(): void
    {
        $admin = $this->makeUser('ADMIN', config('admin.email'));
        $owner = $this->makeUser('INVESTOR', 'support-owner@example.test');
        $other = $this->makeUser('INVESTOR', 'support-other@example.test');

        $this->actingAs($owner)->post(route('support.store'), [
            'subject' => 'Connexion MT5', 'body' => 'Je rencontre un problème de connexion.',
        ])->assertRedirect();

        $ticket = SupportTicket::sole();
        $this->assertDatabaseHas('support_messages', ['support_ticket_id' => $ticket->id, 'user_id' => $owner->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id]);

        $this->actingAs($other)->get(route('support.show', $ticket))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.support.index'))->assertForbidden();

        $admin->forceFill(['mfa_verified_at' => now()])->save();
        $this->actingAs($admin)->get(route('admin.support.index'))->assertOk()->assertSee('Connexion MT5');
        $this->actingAs($admin)->post(route('support.reply', $ticket), ['body' => 'Nous vérifions votre demande.'])
            ->assertRedirect(route('support.show', $ticket));
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id]);
    }

    public function test_site_content_is_admin_mfa_only_and_rendered_as_text(): void
    {
        $admin = $this->makeUser('ADMIN', config('admin.email'));
        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'home_headline' => '<script>alert(1)</script>', 'home_description' => 'Description sûre',
        ])->assertForbidden();

        $admin->forceFill(['mfa_verified_at' => now()])->save();
        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'home_headline' => '<script>alert(1)</script>', 'home_description' => 'Description sûre',
        ])->assertRedirect(route('admin.settings.index'));

        $this->get(route('home'))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_users_cannot_mark_another_users_notification_as_read(): void
    {
        $user = $this->makeUser('INVESTOR', 'notice-a@example.test');
        $other = $this->makeUser('INVESTOR', 'notice-b@example.test');
        $notification = $user->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'test', 'data' => ['title' => 'Owned'],
        ]);

        $this->actingAs($other)->post(route('notifications.read', $notification->id))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
        $this->actingAs($user)->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('notifications.index'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    private function makeUser(string $role, string $email): User
    {
        $user = User::create([
            'first_name' => 'Support', 'last_name' => 'Test', 'email' => $email,
            'password' => 'correct horse battery staple', 'role' => $role, 'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
