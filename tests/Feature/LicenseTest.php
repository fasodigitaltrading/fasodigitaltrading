<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\LicenseAccountBinding;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.binding_key' => 'a-long-independent-license-binding-secret']);
    }

    public function test_account_binding_stores_only_a_keyed_hash_and_enforces_account_limit(): void
    {
        [$license, $licenseKey] = $this->makeLicense();
        $service = app(LicenseService::class);

        $first = $service->validate($licenseKey, '12345678', 'Broker-Live-01');
        $again = $service->validate($licenseKey, '12345678', 'broker-live-01');
        $other = $service->validate($licenseKey, '12345679', 'Broker-Live-01');

        $this->assertTrue($first['valid']);
        $this->assertTrue($first['account_allowed']);
        $this->assertTrue($again['account_allowed']);
        $this->assertTrue($other['valid']);
        $this->assertFalse($other['account_allowed']);
        $this->assertSame('ACCOUNT_LIMIT', $other['status']);
        $this->assertDatabaseCount('license_account_bindings', 1);
        $this->assertNotSame('MT5-ACCOUNT-001', LicenseAccountBinding::sole()->account_hash);
        $this->assertSame(hash_hmac('sha256', "broker-live-01\0".'12345678', config('license.binding_key')), LicenseAccountBinding::sole()->account_hash);
        $this->assertTrue(Schema::hasTable('license_account_bindings'));
    }

    public function test_same_mt5_login_on_another_server_is_a_distinct_bound_account(): void
    {
        [$license, $licenseKey] = $this->makeLicense();
        $service = app(LicenseService::class);

        $this->assertTrue($service->validate($licenseKey, '12345678', 'Broker-Live-01')['account_allowed']);
        $otherServer = $service->validate($licenseKey, '12345678', 'Broker-Live-02');

        $this->assertTrue($otherServer['valid']);
        $this->assertFalse($otherServer['account_allowed']);
        $this->assertSame('ACCOUNT_LIMIT', $otherServer['status']);
    }

    public function test_expired_license_is_marked_expired_and_cannot_bind_an_account(): void
    {
        [$license, $licenseKey] = $this->makeLicense(['expires_at' => now()->subMinute()]);

        $result = app(LicenseService::class)->validate($licenseKey, '12345680', 'Broker-Live-01');

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['account_allowed']);
        $this->assertSame('EXPIRED', $license->fresh()->status);
        $this->assertDatabaseCount('license_account_bindings', 0);
    }

    public function test_license_key_migration_hashes_existing_keys_and_can_restore_them_on_rollback(): void
    {
        $user = User::create([
            'first_name' => 'Legacy', 'last_name' => 'Owner', 'email' => 'legacy-license@example.test',
            'password' => 'correct horse battery staple', 'role' => 'INVESTOR', 'status' => 'ACTIVE', 'email_verified_at' => now(),
        ]);
        $key = 'NXT-LEGACY-KEY-THAT-MUST-NOT-REMAIN-CLEAR';
        DB::table('licenses')->insert([
            'user_id' => $user->id, 'license_key' => $key, 'status' => 'ACTIVE', 'max_accounts' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/014_hash_license_keys.php');

        $migration->down();
        $migration->up();

        $row = DB::table('licenses')->where('user_id', $user->id)->first();
        $this->assertSame(hash('sha256', $key), $row->license_key_hash);
        $this->assertNotSame($key, $row->license_key);
        $this->assertSame($key, Crypt::decryptString($row->legacy_license_key_encrypted));

        $migration->down();
        $this->assertSame($key, DB::table('licenses')->where('user_id', $user->id)->value('license_key'));
    }

    public function test_new_license_key_is_hashed_and_recoverable_only_from_encrypted_storage(): void
    {
        [$license, $key] = $this->makeLicense();

        $this->assertStringStartsWith('BWT-', $key);
        $this->assertSame(hash('sha256', $key), $license->license_key_hash);
        $this->assertSame($key, $license->revealKey());
        $this->assertArrayNotHasKey('legacy_license_key_encrypted', $license->toArray());
        $this->assertArrayNotHasKey('license_key_hash', $license->toArray());
    }

    public function test_license_api_requires_tls_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';
        [, $licenseKey] = $this->makeLicense();

        $this->postJson('/api/robot/license/validate', [
            'license_key' => $licenseKey, 'account_id' => '12345681', 'server' => 'Broker-Live-01',
        ])->assertStatus(426);
    }

    public function test_license_api_binds_to_real_mt5_login_and_server_identity(): void
    {
        [, $licenseKey] = $this->makeLicense();

        $this->postJson('/api/robot/license/validate', [
            'license_key' => $licenseKey, 'account_id' => '12345682', 'server' => 'Broker-Live-01',
        ])->assertOk()->assertJsonPath('valid', true)->assertJsonPath('account_allowed', true);

        $this->assertDatabaseCount('license_account_bindings', 1);
    }

    public function test_license_api_fails_closed_without_independent_binding_secret(): void
    {
        [, $licenseKey] = $this->makeLicense();
        config(['license.binding_key' => '']);

        $this->postJson('/api/robot/license/validate', [
            'license_key' => $licenseKey, 'account_id' => '12345683', 'server' => 'Broker-Live-01',
        ])->assertStatus(503)->assertJsonPath('status', 'SERVICE_UNAVAILABLE');

        $this->assertDatabaseCount('license_account_bindings', 0);
    }

    private function makeLicense(array $overrides = []): array
    {
        $user = User::create([
            'first_name' => 'License', 'last_name' => 'Owner', 'email' => 'license@example.test',
            'password' => 'correct horse battery staple', 'role' => 'INVESTOR', 'status' => 'ACTIVE', 'email_verified_at' => now(),
        ]);

        return License::issue(array_merge([
            'user_id' => $user->id,
            'status' => 'ACTIVE', 'max_accounts' => 1, 'expires_at' => now()->addMonth(),
        ], $overrides));
    }
}
