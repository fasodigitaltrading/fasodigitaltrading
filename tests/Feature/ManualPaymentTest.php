<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\ManualPaymentOtp;
use App\Models\ManualPaymentSubmission;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ManualPaymentReadiness;
use App\Services\ManualPaymentProofStorage;
use App\Mail\ManualPaymentReceivedAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_investor_can_submit_a_private_manual_transfer_proof_without_faking_a_payment(): void
    {
        Http::preventStrayRequests();
        config([
            'admin.email' => 'payments-admin@example.test',
            'license.binding_key' => str_repeat('k', 48),
            'payments.manual_proof_root' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'bwt-manual-'.Str::uuid(),
        ]);
        Mail::fake();
        [$user, $order] = $this->orderForInvestor();
        [$otp, $code] = $this->issueOtp($user, $order);

        $this->assertTrue(Hash::check($code, $otp->code_hash));
        $this->assertNotSame($code, $otp->code_hash);

        $this->actingAs($user)->post(route('subscriptions.orders.manual-payment', $order), [
            'method' => 'orange_money',
            'manual_payment_otp' => $code,
            'transaction_reference' => 'OM-2026-000123',
            'declared_amount' => '6000',
            'transaction_occurred_at' => now()->format('Y-m-d\\TH:i'),
            'payment_acknowledgement' => 'yes',
            'proof' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
        ])->assertRedirect(route('subscriptions.orders.show', $order))
            ->assertSessionHas('status');

        $submission = ManualPaymentSubmission::sole();
        $this->assertSame('6000.00', $submission->expected_amount);
        $this->assertSame('XOF', $submission->currency);
        $this->assertSame('0022667902537', $submission->recipient_snapshot);
        $this->assertSame('6000.00', $submission->declared_amount);
        $this->assertSame($otp->id, $submission->manual_payment_otp_id);
        $this->assertSame('PENDING', $submission->status);
        $this->assertSame('SENT', $submission->admin_email_status);
        $this->assertNotNull($submission->customer_acknowledged_at);
        $this->assertSame('manual-payment-v1', $submission->customer_terms_version);
        $this->assertTrue(StoragePath::exists((string) config('payments.manual_proof_root'), $submission->proof_path));
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('subscription_orders', ['id' => $order->id, 'status' => 'AWAITING_PAYMENT']);
        $this->assertNotNull($otp->fresh()->used_at);
        $this->assertNotNull($otp->fresh()->code_ciphertext);
        Mail::assertSent(ManualPaymentReceivedAlert::class, function (ManualPaymentReceivedAlert $mail) use ($code, $submission): bool {
            return $mail->hasTo('payments-admin@example.test')
                && $mail->otpCode === $code
                && $mail->submission->is($submission);
        });
        $this->actingAs($this->admin('payments-admin@example.test'))
            ->post(route('admin.manual-payments.notify', $submission))
            ->assertRedirect(route('admin.manual-payments.index'));
        Mail::assertSent(ManualPaymentReceivedAlert::class, 2);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'manual_payment.admin_email_retry',
            'entity_id' => $submission->id,
        ]);

        $this->actingAs($user)->get(route('subscriptions.orders.show', $order))
            ->assertOk()
            ->assertSee('Demande reçue')
            ->assertSee('N’effectuez pas un second transfert');
    }

    public function test_manual_otp_and_review_accept_the_current_bceao_order_quote(): void
    {
        Http::preventStrayRequests();
        config([
            'admin.email' => 'payments-admin@example.test',
            'license.binding_key' => str_repeat('m', 48),
            'payments.manual_proof_root' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'bwt-manual-'.Str::uuid(),
        ]);
        cache()->put('fx.bceao.usd-xof.latest', [
            'rate' => '593.5200',
            'as_of' => now()->toDateString(),
            'fetched_at' => now()->toIso8601String(),
            'source_url' => 'https://www.bceao.int/fr/cours/cours-de-reference-des-principales-devises-contre-Franc-CFA',
        ], now()->addDay());
        Mail::fake();
        [$user, $order] = $this->orderForInvestor();
        $order->forceFill([
            'amount_xof' => 5935,
            'fx_rate' => '593.5200',
            'fx_rate_as_of' => now()->toDateString(),
            'fx_rate_updated_at' => now(),
            'fx_provider' => 'bceao-reference',
            'fx_source_url' => 'https://www.bceao.int/fr/cours/cours-de-reference-des-principales-devises-contre-Franc-CFA',
            'fx_fetched_at' => now(),
            'quote_expires_at' => now()->addMinutes(30),
        ])->save();

        [$otp, $code] = $this->issueOtp($user, $order);
        $this->assertSame('5935.00', $otp->expected_amount);

        $this->actingAs($user)->post(route('subscriptions.orders.manual-payment', $order), [
            'method' => 'orange_money',
            'manual_payment_otp' => $code,
            'transaction_reference' => 'OM-BCEAO-2026-001',
            'declared_amount' => '5935',
            'transaction_occurred_at' => now()->format('Y-m-d\\TH:i'),
            'payment_acknowledgement' => 'yes',
            'proof' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
        ])->assertRedirect(route('subscriptions.orders.show', $order));

        $submission = ManualPaymentSubmission::sole();
        $this->assertSame('5935.00', $submission->expected_amount);
        $this->assertSame('PENDING', $submission->status);
        $this->assertDatabaseCount('payments', 0);

        $this->actingAs($this->admin('payments-admin@example.test'))->post(route('admin.manual-payments.approve', $submission), [
            'verified_amount' => '5935.00',
            'confirm_funds' => 'yes',
            'review_note' => 'Réception réelle vérifiée sur le portefeuille',
        ])->assertRedirect(route('admin.manual-payments.index'))->assertSessionHas('status');

        $this->assertSame('PAID', Payment::sole()->status);
        $this->assertSame('5935.00', Payment::sole()->amount);
        $this->assertSame('bceao-reference', Payment::sole()->metadata['fx_provider']);
        $this->assertSame('ACTIVE', Subscription::sole()->status);
        $this->assertSame('PAID', $order->fresh()->status);
    }

    public function test_manual_payment_code_rejects_wrong_and_replayed_values(): void
    {
        config([
            'admin.email' => 'payments-admin@example.test',
            'license.binding_key' => str_repeat('q', 48),
            'payments.manual_proof_root' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'bwt-manual-'.Str::uuid(),
        ]);
        Mail::fake();
        [$user, $order] = $this->orderForInvestor();
        [$otp, $code] = $this->issueOtp($user, $order);
        $wrongCode = str_pad((string) (((int) $code + 1) % 100_000_000), 8, '0', STR_PAD_LEFT);
        $payload = [
            'method' => 'orange_money',
            'manual_payment_otp' => $wrongCode,
            'transaction_reference' => 'OM',
            'declared_amount' => '6000',
            'transaction_occurred_at' => now()->format('Y-m-d\\TH:i'),
            'payment_acknowledgement' => 'yes',
            'proof' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
        ];

        $invalidFormResponse = $this->actingAs($user)->post(route('subscriptions.orders.manual-payment', $order), $payload);
        $invalidFormResponse->assertSessionHasErrors('transaction_reference');
        $invalidFormResponse->assertSessionHas('_old_input', fn (array $oldInput): bool => !array_key_exists('manual_payment_otp', $oldInput));

        $payload['transaction_reference'] = 'OM-2026-WRONG-1';
        $wrongCodeResponse = $this->actingAs($user)->post(route('subscriptions.orders.manual-payment', $order), $payload);
        $wrongCodeResponse->assertSessionHasErrors('manual_payment_otp');
        $wrongCodeResponse->assertSessionHas('_old_input', fn (array $oldInput): bool => !array_key_exists('manual_payment_otp', $oldInput));
        $this->assertSame(1, $otp->fresh()->failed_attempts);
        $this->assertDatabaseCount('manual_payment_submissions', 0);

        $payload['manual_payment_otp'] = $code;
        $payload['transaction_reference'] = 'OM-2026-REPLAY-1';
        $this->actingAs($user)->post(route('subscriptions.orders.manual-payment', $order), $payload)
            ->assertRedirect(route('subscriptions.orders.show', $order));
        $this->assertDatabaseCount('manual_payment_submissions', 1);

        $submission = ManualPaymentSubmission::sole();
        $this->actingAs($this->admin('payments-admin@example.test'))
            ->post(route('admin.manual-payments.reject', $submission), [
                'review_note' => 'Demande refusée pour vérifier la protection contre le rejeu.',
            ])->assertRedirect(route('admin.manual-payments.index'));

        $payload['transaction_reference'] = 'OM-2026-REPLAY-2';
        $this->actingAs($user)->post(route('subscriptions.orders.manual-payment', $order), $payload)
            ->assertSessionHasErrors('manual_payment_otp');
        $this->assertDatabaseCount('manual_payment_submissions', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('AWAITING_PAYMENT', $order->fresh()->status);
    }

    public function test_only_the_primary_mfa_admin_can_confirm_funds_and_activate_a_manual_payment(): void
    {
        config([
            'admin.email' => 'admin@example.test',
            'license.binding_key' => str_repeat('l', 48),
            'payments.manual_proof_root' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'bwt-manual-'.Str::uuid(),
        ]);
        [$user, $order] = $this->orderForInvestor();
        $proof = app(ManualPaymentProofStorage::class)->store(
            UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
        );
        $submission = ManualPaymentSubmission::create([
            'subscription_order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'orange_money',
            'recipient_snapshot' => '0022667902537',
            'expected_amount' => '6000.00',
            'currency' => 'XOF',
            'transaction_reference' => 'OM-REVIEW-0001',
            'proof_path' => $proof['path'],
            'proof_sha256' => $proof['sha256'],
            'status' => 'PENDING',
        ]);
        $admin = $this->admin('admin@example.test');

        $this->actingAs($admin)->post(route('admin.manual-payments.approve', $submission), [
            'verified_amount' => '6000.00',
            'confirm_funds' => 'yes',
            'review_note' => 'Montant vu sur le portefeuille marchand',
        ])->assertRedirect(route('admin.manual-payments.index'))
            ->assertSessionHas('status');

        $this->assertSame('PAID', $order->fresh()->status);
        $this->assertSame('APPROVED', $submission->fresh()->status);
        $this->assertSame('PAID', Payment::sole()->status);
        $this->assertSame('manual-transfer', Payment::sole()->provider);
        $this->assertSame('ACTIVE', Subscription::sole()->status);
        $this->assertSame(1, License::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'manual_payment.approved', 'entity_id' => $submission->id]);
        $this->assertDatabaseHas('payment_webhook_events', ['provider' => 'manual-transfer', 'processing_status' => 'PROCESSED']);
    }

    public function test_admin_cannot_approve_a_receipt_with_an_amount_different_from_the_order(): void
    {
        config([
            'admin.email' => 'admin@example.test',
            'license.binding_key' => str_repeat('m', 48),
            'payments.manual_proof_root' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'bwt-manual-'.Str::uuid(),
        ]);
        [$user, $order] = $this->orderForInvestor();
        $proof = app(ManualPaymentProofStorage::class)->store(
            UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
        );
        $submission = ManualPaymentSubmission::create([
            'subscription_order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'orange_money',
            'recipient_snapshot' => '0022667902537',
            'expected_amount' => '6000.00',
            'currency' => 'XOF',
            'transaction_reference' => 'OM-REVIEW-0002',
            'proof_path' => $proof['path'],
            'proof_sha256' => $proof['sha256'],
            'status' => 'PENDING',
        ]);

        $this->actingAs($this->admin('admin@example.test'))
            ->from(route('admin.manual-payments.index'))
            ->post(route('admin.manual-payments.approve', $submission), [
                'verified_amount' => '5999.00',
                'confirm_funds' => 'yes',
                'review_note' => 'Tentative avec montant différent',
            ])->assertRedirect(route('admin.manual-payments.index'))
            ->assertSessionHasErrors('verified_amount');

        $this->assertSame('PENDING', $submission->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('AWAITING_PAYMENT', $order->fresh()->status);
    }

    public function test_admin_cannot_approve_a_tampered_private_proof(): void
    {
        config([
            'admin.email' => 'admin@example.test',
            'license.binding_key' => str_repeat('t', 48),
            'payments.manual_proof_root' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'bwt-manual-'.Str::uuid(),
        ]);
        [$user, $order] = $this->orderForInvestor();
        $proof = app(ManualPaymentProofStorage::class)->store(
            UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
        );
        $submission = ManualPaymentSubmission::create([
            'subscription_order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'orange_money',
            'recipient_snapshot' => '0022667902537',
            'expected_amount' => '6000.00',
            'currency' => 'XOF',
            'transaction_reference' => 'OM-TAMPER-0001',
            'proof_path' => $proof['path'],
            'proof_sha256' => $proof['sha256'],
            'status' => 'PENDING',
        ]);
        file_put_contents((string) config('payments.manual_proof_root').DIRECTORY_SEPARATOR.$proof['path'], 'changed');

        $this->actingAs($this->admin('admin@example.test'))
            ->from(route('admin.manual-payments.index'))
            ->post(route('admin.manual-payments.approve', $submission), [
                'verified_amount' => '6000.00',
                'confirm_funds' => 'yes',
                'review_note' => 'Reçu altéré pendant le test',
            ])->assertRedirect(route('admin.manual-payments.index'))
            ->assertSessionHasErrors('manual_payment');

        $this->assertSame('PENDING', $submission->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('AWAITING_PAYMENT', $order->fresh()->status);
    }

    public function test_production_manual_payments_require_https_secure_sessions_and_distinct_keys(): void
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
            'legal.operator_name' => 'FASO DIGITAL TRADING',
            'legal.operator_address' => 'Ouagadougou',
            'legal.operator_email' => 'legal@example.test',
            'legal.disclosures_approved' => true,
        ]);
        $readiness = app(ManualPaymentReadiness::class);

        $this->assertFalse($readiness->isReady());
        config(['app.url' => 'https://payments.example.test']);
        $this->assertTrue($readiness->isReady());
        config(['session.secure' => false]);
        $this->assertFalse($readiness->isReady());
        config(['session.secure' => true, 'session.encrypt' => false]);
        $this->assertFalse($readiness->isReady());
        config(['session.encrypt' => true, 'app.debug' => true]);
        $this->assertFalse($readiness->isReady());
        config(['app.debug' => false, 'license.binding_key' => str_repeat('a', 40)]);
        $this->assertFalse($readiness->isReady());
        config(['license.binding_key' => str_repeat('b', 48), 'payments.manual_live_verified' => false]);
        $this->assertFalse($readiness->isReady());
        config(['payments.manual_live_verified' => true, 'legal.disclosures_approved' => false]);
        $this->assertFalse($readiness->isReady());
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

    private function issueOtp(User $user, SubscriptionOrder $order): array
    {
        $this->actingAs($user)->post(route('subscriptions.orders.manual-payment.otp', $order), [
            'method' => 'orange_money',
        ])->assertRedirect(route('subscriptions.orders.show', $order));

        $response = $this->actingAs($user)->get(route('subscriptions.orders.show', $order))->assertOk();
        preg_match('/class="otp-code" type="text" value="([0-9]{8})"/', (string) $response->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches, 'The one-time code should be shown after it is generated.');

        return [ManualPaymentOtp::latest('id')->firstOrFail(), $matches[1]];
    }

    private function admin(string $email): User
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => $email,
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
