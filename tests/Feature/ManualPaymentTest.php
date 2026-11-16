<?php

namespace Tests\Feature;

use App\Mail\ManualPaymentConfirmed;
use App\Mail\ManualPaymentReceivedAlert;
use App\Models\License;
use App\Models\ManualPaymentSubmission;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ManualPaymentProofStorage;
use App\Services\ManualPaymentProofObjectStorage;
use App\Services\ManualPaymentReadiness;
use Aws\Result;
use Aws\S3\S3Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_investor_submits_only_a_screenshot_and_no_payment_is_activated(): void
    {
        Http::preventStrayRequests();
        $this->configurePayment();
        Mail::fake();
        [$user, $order] = $this->orderForInvestor();

        $this->actingAs($user)->get(route('subscriptions.orders.show', $order))
            ->assertOk()
            ->assertSee('data-qr-address="0xF9C6Bb6b72091Ddc8F5DcCc2D90EBc73870D042D"', false)
            ->assertSee('J’ai payé · envoyer la capture');

        $this->actingAs($user)->post(route('subscriptions.orders.manual-payment', $order), [
            'method' => 'usdt_bep20',
            'payment_acknowledgement' => 'yes',
            'proof' => $this->screenshot(),
        ])->assertRedirect(route('subscriptions.orders.show', $order))
            ->assertSessionHas('status');

        $submission = ManualPaymentSubmission::sole();
        $this->assertSame('10.00', $submission->expected_amount);
        $this->assertSame('USDT', $submission->currency);
        $this->assertSame('0xF9C6Bb6b72091Ddc8F5DcCc2D90EBc73870D042D', $submission->recipient_snapshot);
        $this->assertStringStartsWith('REQ-', $submission->transaction_reference);
        $this->assertNull($submission->declared_amount);
        $this->assertNull($submission->transaction_occurred_at);
        $this->assertNull($submission->manual_payment_otp_id);
        $this->assertSame('PENDING', $submission->status);
        $this->assertSame('SENT', $submission->admin_email_status);
        $this->assertNotNull($submission->customer_acknowledged_at);
        $this->assertSame('manual-payment-v2-crypto', $submission->customer_terms_version);
        $this->assertTrue(StoragePath::exists((string) config('payments.manual_proof_root'), $submission->proof_path));
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('subscription_orders', ['id' => $order->id, 'status' => 'AWAITING_PAYMENT']);

        Mail::assertSent(ManualPaymentReceivedAlert::class, function (ManualPaymentReceivedAlert $mail) use ($submission): bool {
            $attachments = $mail->attachments();

            return $mail->hasTo('payments-admin@example.test')
                && $mail->otpCode === null
                && $mail->submission->is($submission)
                && count($attachments) === 1
                && $attachments[0]->as === 'capture-transaction-'.$submission->id.'.png'
                && $attachments[0]->mime === 'image/png';
        });

        $this->actingAs($this->admin())->get(route('admin.manual-payments.index'))
            ->assertOk()->assertSee('data-review-countdown', false)
            ->assertSee('Priorité de traitement');

        $this->actingAs($user)->get(route('subscriptions.orders.show', $order))
            ->assertOk()
            ->assertSee('Capture reçue sous la référence')
            ->assertSee('data-review-countdown', false)
            ->assertSee('N’effectuez pas un second transfert');
    }

    public function test_pdf_is_rejected_because_payment_proof_must_be_a_screenshot(): void
    {
        $this->configurePayment();
        Mail::fake();
        [$user, $order] = $this->orderForInvestor();

        $this->actingAs($user)->post(route('subscriptions.orders.manual-payment', $order), [
            'method' => 'usdt_bep20',
            'payment_acknowledgement' => 'yes',
            'proof' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
        ])->assertSessionHasErrors('proof');

        $this->assertDatabaseCount('manual_payment_submissions', 0);
        $this->assertDatabaseCount('payments', 0);
        Mail::assertNothingSent();
    }

    public function test_only_admin_verification_of_hash_contract_recipient_amount_and_confirmations_activates_access(): void
    {
        Http::preventStrayRequests();
        $this->configurePayment();
        Mail::fake();
        [$user, $order] = $this->orderForInvestor();
        $this->submitScreenshot($user, $order);
        $submission = ManualPaymentSubmission::sole();

        $this->actingAs($this->admin())->post(route('admin.manual-payments.approve', $submission), [
            'verified_amount' => '10.00',
            'verified_transaction_hash' => '0x'.str_repeat('a', 64),
            'verified_token_contract' => config('payments.crypto.token_contract'),
            'verified_recipient' => $submission->recipient_snapshot,
            'verified_confirmations' => 15,
            'confirm_token_contract' => 'yes',
            'confirm_recipient' => 'yes',
            'confirm_funds' => 'yes',
            'review_note' => 'Transaction contrôlée sur BscScan et montant reçu exact.',
        ])->assertRedirect(route('admin.manual-payments.index'))->assertSessionHas('status');

        $this->assertSame('PAID', Payment::sole()->status);
        $this->assertSame('0x'.str_repeat('a', 64), Payment::sole()->transaction_id);
        $this->assertSame('10.00', Payment::sole()->amount);
        $this->assertSame('USDT', Payment::sole()->currency);
        $this->assertSame('ACTIVE', Subscription::sole()->status);
        $this->assertSame('PAID', $order->fresh()->status);
        $this->assertSame('APPROVED', $submission->fresh()->status);
        $this->assertSame('0x'.str_repeat('a', 64), $submission->fresh()->verified_transaction_hash);
        $this->assertSame(15, $submission->fresh()->verified_confirmations);
        $this->assertSame(1, License::count());
        Mail::assertSent(ManualPaymentConfirmed::class, fn (ManualPaymentConfirmed $mail): bool => $mail->hasTo($user->email));
    }

    public function test_wrong_token_contract_cannot_activate_payment(): void
    {
        $this->configurePayment();
        Mail::fake();
        [$user, $order] = $this->orderForInvestor();
        $this->submitScreenshot($user, $order);
        $submission = ManualPaymentSubmission::sole();

        $this->actingAs($this->admin())
            ->from(route('admin.manual-payments.index'))
            ->post(route('admin.manual-payments.approve', $submission), [
                ...$this->validApprovalData($submission),
                'verified_token_contract' => '0x'.str_repeat('0', 40),
            ])->assertRedirect(route('admin.manual-payments.index'))
            ->assertSessionHasErrors('verified_token_contract');

        $this->assertSame('PENDING', $submission->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('AWAITING_PAYMENT', $order->fresh()->status);
    }

    public function test_duplicate_bsc_transaction_hash_is_rejected(): void
    {
        $this->configurePayment();
        Mail::fake();
        [$firstUser, $firstOrder] = $this->orderForInvestor();
        $this->submitScreenshot($firstUser, $firstOrder);
        $first = ManualPaymentSubmission::sole();
        $hash = '0x'.str_repeat('b', 64);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.manual-payments.approve', $first), [
            ...$this->validApprovalData($first),
            'verified_transaction_hash' => $hash,
        ])->assertRedirect(route('admin.manual-payments.index'));

        [$secondUser, $secondOrder] = $this->orderForInvestor();
        $this->submitScreenshot($secondUser, $secondOrder);
        $second = ManualPaymentSubmission::whereKeyNot($first->id)->sole();
        $this->actingAs($admin)->from(route('admin.manual-payments.index'))
            ->post(route('admin.manual-payments.approve', $second), [
                ...$this->validApprovalData($second),
                'verified_transaction_hash' => strtoupper($hash),
            ])->assertRedirect(route('admin.manual-payments.index'))
            ->assertSessionHasErrors('verified_transaction_hash');

        $this->assertSame('PENDING', $second->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_admin_can_request_more_information_without_deleting_history_and_customer_can_resubmit(): void
    {
        $this->configurePayment();
        Mail::fake();
        [$user, $order] = $this->orderForInvestor();
        $this->submitScreenshot($user, $order);
        $first = ManualPaymentSubmission::sole();

        $this->actingAs($this->admin())->post(route('admin.manual-payments.request-info', $first), [
            'review_note' => 'Merci de joindre une capture où le montant total apparaît.',
        ])->assertRedirect(route('admin.manual-payments.index'));

        $this->assertSame('INFO_REQUIRED', $first->fresh()->status);
        $this->actingAs($user)->get(route('subscriptions.orders.show', $order))
            ->assertOk()->assertSee('L’administrateur demande une information');

        $this->submitScreenshot($user, $order);
        $this->assertDatabaseCount('manual_payment_submissions', 2);
        $this->assertSame('INFO_REQUIRED', $first->fresh()->status);
        $this->assertSame('PENDING', ManualPaymentSubmission::whereKeyNot($first->id)->sole()->status);
        $this->assertSame('AWAITING_PAYMENT', $order->fresh()->status);
    }

    public function test_wrong_amount_cannot_be_approved(): void
    {
        $this->configurePayment();
        Mail::fake();
        [$user, $order] = $this->orderForInvestor();
        $this->submitScreenshot($user, $order);
        $submission = ManualPaymentSubmission::sole();

        $this->actingAs($this->admin())
            ->from(route('admin.manual-payments.index'))
            ->post(route('admin.manual-payments.approve', $submission), [
                ...$this->validApprovalData($submission),
                'verified_amount' => '9.99',
            ])->assertRedirect(route('admin.manual-payments.index'))
            ->assertSessionHasErrors('verified_amount');

        $this->assertSame('PENDING', $submission->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('AWAITING_PAYMENT', $order->fresh()->status);
    }

    public function test_admin_cannot_approve_a_tampered_private_proof(): void
    {
        $this->configurePayment();
        Mail::fake();
        [$user, $order] = $this->orderForInvestor();
        $this->submitScreenshot($user, $order);
        $submission = ManualPaymentSubmission::sole();
        file_put_contents((string) config('payments.manual_proof_root').DIRECTORY_SEPARATOR.$submission->proof_path, 'changed');

        $this->actingAs($this->admin())
            ->from(route('admin.manual-payments.index'))
            ->post(route('admin.manual-payments.approve', $submission), $this->validApprovalData($submission))
            ->assertRedirect(route('admin.manual-payments.index'))
            ->assertSessionHasErrors('manual_payment');

        $this->assertSame('PENDING', $submission->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('AWAITING_PAYMENT', $order->fresh()->status);
    }

    public function test_s3_payment_proof_upload_does_not_send_unsupported_object_acl(): void
    {
        config([
            'payments.manual_proof_disk' => 's3',
            'filesystems.disks.s3.bucket' => 'private-proof-bucket',
            'filesystems.disks.s3.endpoint' => 'https://proofs.r2.example.test',
            'filesystems.disks.s3.key' => 'access-key',
            'filesystems.disks.s3.secret' => 'secret-key',
            'filesystems.disks.s3.region' => 'auto',
        ]);
        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('putObject')
            ->once()
            ->with(Mockery::on(function (array $parameters): bool {
                $this->assertArrayNotHasKey('ACL', $parameters);
                $this->assertSame('private-proof-bucket', $parameters['Bucket']);
                return isset($parameters['Body'], $parameters['ContentType'], $parameters['Key']);
            }))
            ->andReturn(new Result());
        $client->shouldReceive('getObject')->twice()
            ->andReturn(new Result(['Body' => Utils::streamFor('screenshot bytes')]));
        $client->shouldReceive('deleteObject')->once()->andReturn(new Result());

        $objectStorage = Mockery::mock(ManualPaymentProofObjectStorage::class)->makePartial();
        $objectStorage->shouldAllowMockingProtectedMethods()
            ->shouldReceive('client')->times(4)->andReturn($client);
        $this->app->instance(ManualPaymentProofObjectStorage::class, $objectStorage);

        $storage = app(ManualPaymentProofStorage::class);
        $result = $storage->store($this->screenshot());

        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}\.png$/', $result['path']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['sha256']);
        $this->assertSame('screenshot bytes', $storage->contents($result['path']));
        $this->assertTrue($storage->matchesHash($result['path'], hash('sha256', 'screenshot bytes')));
        $storage->delete($result['path']);
    }

    public function test_production_manual_payments_require_https_secure_sessions_durable_private_storage_and_distinct_keys(): void
    {
        config([
            'app.env' => 'production',
            'app.url' => 'http://payments.example.test',
            'app.debug' => false,
            'app.key' => str_repeat('a', 40),
            'license.binding_key' => str_repeat('b', 48),
            'session.secure' => true,
            'session.encrypt' => true,
            'payments.manual_live_verified' => true,
            'payments.manual_proof_storage_persistent' => false,
            'payments.manual_proof_disk' => 'local',
            'legal.operator_name' => 'FASO DIGITAL TRADING',
            'legal.operator_address' => 'Ouagadougou',
            'legal.operator_email' => 'legal@example.test',
            'legal.disclosures_approved' => true,
        ]);
        $readiness = app(ManualPaymentReadiness::class);

        $this->assertFalse($readiness->isReady());
        config(['app.url' => 'https://payments.example.test', 'payments.manual_proof_storage_persistent' => true]);
        $this->assertTrue($readiness->isReady());
        config([
            'payments.manual_proof_disk' => 's3',
            'filesystems.disks.s3.bucket' => '',
            'filesystems.disks.s3.region' => 'eu-central-1',
        ]);
        $this->assertFalse($readiness->isReady());
        config([
            'filesystems.disks.s3.bucket' => 'private-proof-bucket',
            'filesystems.disks.s3.endpoint' => 'https://proofs.r2.example.test',
            'filesystems.disks.s3.key' => 'access-key',
            'filesystems.disks.s3.secret' => 'secret-key',
        ]);
        $this->assertTrue($readiness->isReady());
        config(['filesystems.disks.s3.endpoint' => 'http://proofs.r2.example.test']);
        $this->assertFalse($readiness->isReady());
        config(['payments.manual_proof_disk' => 'public']);
        $this->assertFalse($readiness->isReady());
    }

    private function configurePayment(): void
    {
        config([
            'admin.email' => 'payments-admin@example.test',
            'license.binding_key' => str_repeat('k', 48),
            'payments.manual_proof_disk' => 'local',
            'payments.manual_proof_root' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'bwt-manual-'.Str::uuid(),
            'payments.crypto.minimum_confirmations' => 15,
        ]);
    }

    private function submitScreenshot(User $user, SubscriptionOrder $order): void
    {
        $this->actingAs($user)->post(route('subscriptions.orders.manual-payment', $order), [
            'method' => 'usdt_bep20',
            'payment_acknowledgement' => 'yes',
            'proof' => $this->screenshot(),
        ])->assertRedirect(route('subscriptions.orders.show', $order));
    }

    private function validApprovalData(ManualPaymentSubmission $submission): array
    {
        return [
            'verified_amount' => (string) $submission->expected_amount,
            'verified_transaction_hash' => '0x'.str_repeat('c', 64),
            'verified_token_contract' => config('payments.crypto.token_contract'),
            'verified_recipient' => $submission->recipient_snapshot,
            'verified_confirmations' => 15,
            'confirm_token_contract' => 'yes',
            'confirm_recipient' => 'yes',
            'confirm_funds' => 'yes',
            'review_note' => 'Transaction contrôlée sur BscScan et montant reçu exact.',
        ];
    }

    private function orderForInvestor(): array
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'Buyer',
            'email' => 'buyer-'.Str::uuid().'@example.test',
            'password' => 'a-long-test-password',
            'role' => 'INVESTOR',
            'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter-'.Str::lower(Str::random(6)),
            'price' => '10.00',
            'currency' => 'USD',
            'duration_days' => 30,
            'max_mt5_accounts' => 1,
            'capital_required' => '100.00',
            'features' => ['Lecture MT5'],
            'status' => true,
        ]);
        $order = SubscriptionOrder::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'AWAITING_PAYMENT',
            'plan_name_snapshot' => $plan->name,
            'amount_usd' => '10.00',
            'duration_days_snapshot' => 30,
            'max_mt5_accounts_snapshot' => 1,
            'capital_required_snapshot' => '100.00',
            'amount_xof' => 6000,
            'fx_rate' => '600.0000',
            'fx_rate_as_of' => now()->toDateString(),
            'fx_rate_updated_at' => now(),
            'fx_provider' => 'fixed-600',
            'fx_fetched_at' => now(),
            'quote_expires_at' => now()->addMinutes(5),
        ]);

        return [$user, $order];
    }

    private function screenshot(): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WlK8L8AAAAASUVORK5CYII=', true);
        $this->assertIsString($png);

        return UploadedFile::fake()->createWithContent('capture.png', $png);
    }

    private function admin(): User
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'payments-admin@example.test',
            'password' => 'a-long-test-password',
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'mfa_verified_at' => now()])->save();

        return $admin;
    }
}

final class StoragePath
{
    public static function exists(string $root, string $path): bool
    {
        return is_file($root.DIRECTORY_SEPARATOR.$path);
    }
}
