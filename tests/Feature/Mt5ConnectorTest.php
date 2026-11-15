<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\TradingAccount;
use App\Models\User;
use App\Services\MetaApiRestConnector;
use App\Services\Mt5ConnectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class Mt5ConnectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_unconfigured_connector_fails_closed(): void
    {
        config(['mt5.provider' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No MT5 connector is configured.');
        (new Mt5ConnectorService())->connect([]);
    }

    public function test_connector_readiness_requires_the_approved_https_host_without_url_secrets_or_query(): void
    {
        $service = new Mt5ConnectorService();
        config([
            'mt5.provider' => 'metaapi',
            'mt5.metaapi.base_url' => 'https://mt-client-api-v1.new-york.agiliumtrade.ai',
        ]);
        $this->assertTrue($service->isConfigured());

        config(['mt5.metaapi.base_url' => 'http://mt-client-api-v1.new-york.agiliumtrade.ai']);
        $this->assertFalse($service->isConfigured());

        config(['mt5.metaapi.base_url' => 'https://mt-client-api-v1.new-york.agiliumtrade.ai?token=secret']);
        $this->assertFalse($service->isConfigured());
    }

    public function test_connector_rejects_master_passwords_and_authentication_secrets(): void
    {
        config([
            'mt5.provider' => 'metaapi',
            'mt5.metaapi.base_url' => 'https://mt-client-api-v1.new-york.agiliumtrade.ai',
        ]);

        $this->expectException(InvalidArgumentException::class);
        (new MetaApiRestConnector())->connect([
            'account_id' => '865d3a4d-3803-486d-bdf3-a85679d9fad2',
            'access_token' => 'reader-token-value',
            'password' => 'must-never-be-accepted',
        ]);
    }

    public function test_connector_rejects_untrusted_hosts_before_requesting_data(): void
    {
        config(['mt5.provider' => 'metaapi', 'mt5.metaapi.base_url' => 'https://attacker.example']);

        $this->expectException(RuntimeException::class);
        (new MetaApiRestConnector())->connect([
            'account_id' => '865d3a4d-3803-486d-bdf3-a85679d9fad2',
            'access_token' => 'reader-token-value',
        ]);
    }

    public function test_reader_token_is_encrypted_at_rest_and_hidden_from_serialized_account(): void
    {
        $user = $this->makeUser('reader-owner@example.test');
        $token = 'actual-shaped-metaapi-reader-token';
        $account = TradingAccount::create([
            'user_id' => $user->id,
            'account_number_masked' => '****5678',
            'connection_status' => 'DISCONNECTED',
            'readonly_status' => true,
            'provider_account_id' => '865d3a4d-3803-486d-bdf3-a85679d9fad2',
            'account_access_token' => $token,
        ]);

        $this->assertSame($token, $account->fresh()->account_access_token);
        $this->assertStringNotContainsString($token, $account->fresh()->getRawOriginal('account_access_token'));
        $this->assertArrayNotHasKey('account_access_token', $account->fresh()->toArray());
    }

    public function test_linking_fails_without_a_configured_connector_and_creates_no_account_binding(): void
    {
        config(['mt5.provider' => null]);
        $owner = $this->makeUser('account-owner@example.test');
        [$license, $licenseKey] = License::issue([
            'user_id' => $owner->id,
            'status' => 'ACTIVE',
            'max_accounts' => 1,
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($owner)->from(route('investor.dashboard'))->post(route('investor.accounts.store'), [
            'license_key' => $licenseKey,
            'provider_account_id' => '865d3a4d-3803-486d-bdf3-a85679d9fad2',
            'account_access_token' => 'reader-token-value',
        ])->assertRedirect(route('investor.dashboard'))->assertSessionHasErrors('connection');

        $this->assertDatabaseCount('trading_accounts', 0);
        $this->assertDatabaseCount('license_account_bindings', 0);
        $this->assertSame(hash('sha256', $licenseKey), $license->fresh()->license_key_hash);
    }

    public function test_investor_cannot_sync_another_users_account(): void
    {
        $owner = $this->makeUser('account-owner@example.test');
        $other = $this->makeUser('other-investor@example.test');
        $account = TradingAccount::create([
            'user_id' => $owner->id,
            'account_number_masked' => '****5678',
            'connection_status' => 'DISCONNECTED',
            'provider_account_id' => '865d3a4d-3803-486d-bdf3-a85679d9fad2',
            'account_access_token' => 'encrypted-reader-token',
            'readonly_status' => true,
        ]);

        $this->actingAs($other)->post(route('investor.accounts.sync', $account))->assertNotFound();
    }

    public function test_failed_sync_marks_error_without_overwriting_last_good_account_data(): void
    {
        config(['mt5.provider' => null]);
        $owner = $this->makeUser('sync-owner@example.test');
        $owner->forceFill(['email_verified_at' => now()])->save();
        $account = TradingAccount::create([
            'user_id' => $owner->id,
            'account_number_masked' => '****5678',
            'connection_status' => 'CONNECTED',
            'readonly_status' => true,
            'balance' => '1245.67',
            'equity' => '1300.12',
            'provider_account_id' => '865d3a4d-3803-486d-bdf3-a85679d9fad2',
            'account_access_token' => 'encrypted-reader-token',
        ]);

        $this->actingAs($owner)->from(route('investor.dashboard'))
            ->post(route('investor.accounts.sync', $account))
            ->assertRedirect(route('investor.dashboard'))
            ->assertSessionHasErrors('connection');

        $this->assertSame('ERROR', $account->fresh()->connection_status);
        $this->assertSame('1245.67', $account->fresh()->balance);
        $this->assertSame('1300.12', $account->fresh()->equity);
    }

    private function makeUser(string $email): User
    {
        $user = User::create([
            'first_name' => 'Account',
            'last_name' => 'Owner',
            'email' => $email,
            'password' => 'correct horse battery staple',
            'role' => 'INVESTOR',
            'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
