<?php

namespace Tests\Feature;

use App\Models\Broker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrokersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_only_displays_published_brokers_and_escapes_external_links(): void
    {
        Broker::create([
            'name' => 'Published Broker', 'slug' => 'published', 'affiliate_url' => 'https://broker.example/ref',
            'mt5_supported' => true, 'status' => true, 'sort_order' => 1,
        ]);
        Broker::create([
            'name' => 'Hidden Broker', 'slug' => 'hidden', 'status' => false, 'sort_order' => 2,
        ]);

        $this->get(route('brokers'))->assertOk()
            ->assertSee('Published Broker')->assertSee('rel="noopener noreferrer"', false)
            ->assertDontSee('Hidden Broker');
    }

    public function test_broker_management_requires_admin_mfa_and_only_accepts_https_links(): void
    {
        $admin = $this->makeUser('ADMIN', config('admin.email'));
        $this->actingAs($admin)->get(route('admin.brokers.index'))->assertForbidden();

        $admin->forceFill(['mfa_verified_at' => now()])->save();
        $this->actingAs($admin)->post(route('admin.brokers.store'), [
            'name' => 'Secure Broker', 'slug' => 'secure-broker', 'website_url' => 'http://unsafe.example',
            'sort_order' => 0, 'status' => 1,
        ])->assertSessionHasErrors('website_url');
        $this->assertDatabaseCount('brokers', 0);

        $this->actingAs($admin)->post(route('admin.brokers.store'), [
            'name' => 'Secure Broker', 'slug' => 'secure-broker', 'website_url' => 'https://secure.example',
            'affiliate_url' => 'https://secure.example/?ref=abc', 'sort_order' => 0,
            'mt5_supported' => 1, 'status' => 1,
        ])->assertRedirect(route('admin.brokers.index'));

        $this->assertDatabaseHas('brokers', ['slug' => 'secure-broker', 'status' => true]);
    }

    public function test_non_admin_cannot_manage_brokers(): void
    {
        $this->actingAs($this->makeUser('INVESTOR', 'investor-broker@example.test'))
            ->get(route('admin.brokers.index'))->assertForbidden();
    }

    private function makeUser(string $role, string $email): User
    {
        $user = User::create([
            'first_name' => 'Test', 'last_name' => 'User', 'email' => $email,
            'password' => 'correct horse battery staple', 'role' => $role, 'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
