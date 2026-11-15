<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Exceptions\PaymentProviderUnavailable;
use App\Exceptions\InvalidPaymentWebhookSignature;
use App\Services\PaymentProviderFactory;
use App\Services\Payments\PayDunyaProvider;
use App\Services\PaymentWebhookProcessor;
use App\Services\Payments\VerifiedPaymentEvent;
use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_migrations_create_idempotency_and_webhook_storage(): void
    {
        $this->assertTrue(Schema::hasColumns('payments', ['checkout_url', 'idempotency_key', 'is_renewal']));
        $this->assertTrue(Schema::hasColumns('payment_webhook_events', ['provider', 'event_id', 'payload_hash', 'processing_status']));
    }

    public function test_checkout_button_stays_hidden_when_a_current_bceao_quote_is_missing(): void
    {
        config([
            'payments.default_provider' => 'paydunya',
            'payments.paydunya.master_key' => 'merchant-master-key',
            'payments.paydunya.private_key' => 'merchant-private-key',
            'payments.paydunya.token' => 'merchant-token',
            'payments.paydunya.currency' => 'XOF',
            'payments.prices_xof_approved' => true,
            'payments.paydunya.live_verified' => false,
            'legal.operator_name' => 'FASO DIGITAL TRADING',
            'legal.operator_address' => 'Ouagadougou',
            'legal.operator_email' => config('admin.email'),
            'legal.disclosures_approved' => true,
            'license.binding_key' => str_repeat('b', 32),
            'app.url' => 'https://blackwolf.example.test',
        ]);
        $user = $this->makeUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $plan = $this->makePlan();
        $plan->update(['currency' => 'XOF', 'price' => '5000.00']);

        $this->actingAs($user)->get(route('subscriptions'))->assertOk()
            ->assertDontSee('Payer en ligne · paydunya')
            ->assertSee('cours de référence publié par la BCEAO')
            ->assertSee('Le cours BCEAO n’est pas disponible pour le moment');

        $plan->update(['currency' => 'USD']);
        $this->actingAs($user)->get(route('subscriptions'))->assertOk()
            ->assertDontSee('Payer en ligne · paydunya');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_checkout_is_rejected_until_fixed_xof_prices_are_approved(): void
    {
        config([
            'payments.default_provider' => 'paydunya',
            'payments.paydunya.master_key' => 'merchant-master-key',
            'payments.paydunya.private_key' => 'merchant-private-key',
            'payments.paydunya.token' => 'merchant-token',
            'payments.paydunya.currency' => 'XOF',
            'payments.prices_xof_approved' => false,
            'legal.operator_name' => 'FASO DIGITAL TRADING',
            'legal.operator_address' => 'Ouagadougou',
            'legal.operator_email' => config('admin.email'),
            'legal.disclosures_approved' => true,
            'license.binding_key' => str_repeat('b', 32),
            'app.url' => 'https://blackwolf.example.test',
        ]);
        $user = $this->makeUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $plan = $this->makePlan();
        $plan->update(['currency' => 'XOF', 'price' => '5000.00']);

        $this->actingAs($user)->get(route('subscriptions'))->assertOk()
            ->assertSee('Aucune formule n’est publiée pour le moment')
            ->assertDontSee('5 000,00 USD')
            ->assertDontSee('Payer en ligne · paydunya');

        $this->actingAs($user)->from(route('subscriptions'))->post(route('subscriptions.checkout', $plan), [
            'idempotency_key' => 'f5b2744d-2ee0-408b-a706-9103c3db96e9',
        ])->assertRedirect(route('subscriptions'))->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_checkout_stays_disabled_without_a_real_provider_and_writes_no_payment_data(): void
    {
        config(['payments.default_provider' => '', 'payments.providers' => []]);
        $user = $this->makeUser();
        $plan = $this->makePlan();

        $this->actingAs($user)->from(route('subscriptions'))->post(route('subscriptions.checkout', $plan), [
            'idempotency_key' => '48fd55f2-c781-4ca7-8c0c-cdfdece535aa',
        ])->assertRedirect(route('subscriptions'))->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_webhook_is_unavailable_until_a_real_provider_is_configured(): void
    {
        config(['payments.providers' => []]);

        $this->postJson('/api/payments/webhooks/paydunya', ['event' => 'payment.paid'])->assertNotFound();

        $this->assertDatabaseCount('payment_webhook_events', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_invalid_paydunya_webhook_hash_is_rejected_before_any_network_request(): void
    {
        config([
            'payments.paydunya.master_key' => 'merchant-master-key',
            'payments.paydunya.private_key' => 'merchant-private-key',
            'payments.paydunya.token' => 'merchant-token',
            'payments.paydunya.currency' => 'XOF',
            'payments.paydunya.live_verified' => true,
            'legal.operator_name' => 'FASO DIGITAL TRADING',
            'legal.operator_address' => 'Ouagadougou',
            'legal.operator_email' => config('admin.email'),
            'legal.disclosures_approved' => true,
            'license.binding_key' => str_repeat('b', 32),
            'app.url' => 'https://blackwolf.example.test',
        ]);
        Http::preventStrayRequests();
        $payload = http_build_query(['data' => json_encode([
            'hash' => str_repeat('0', 128),
            'invoice' => ['token' => 'invoice-token-123'],
        ], JSON_THROW_ON_ERROR)]);

        $this->expectException(InvalidPaymentWebhookSignature::class);
        (new PayDunyaProvider())->verifyWebhook($payload, []);
    }

    public function test_paydunya_adapter_stays_disabled_without_real_merchant_credentials(): void
    {
        config([
            'payments.default_provider' => '',
            'payments.paydunya.master_key' => '',
            'payments.paydunya.private_key' => '',
            'payments.paydunya.token' => '',
            'payments.paydunya.live_verified' => false,
            'legal.operator_name' => '',
        ]);

        $provider = new PayDunyaProvider();
        $this->assertFalse($provider->isConfigured());
        $this->assertFalse($provider->supportsCheckout('XOF', '5000.00'));
        $this->expectException(PaymentProviderUnavailable::class);
        app(PaymentProviderFactory::class)->make('paydunya');
    }

    public function test_first_live_checkout_is_not_blocked_by_the_unverified_status_flag(): void
    {
        config([
            'app.url' => 'https://fasodigitaltrading.com',
            'payments.paydunya.master_key' => 'merchant-master-key',
            'payments.paydunya.private_key' => 'merchant-private-key',
            'payments.paydunya.token' => 'merchant-token',
            'payments.paydunya.currency' => 'XOF',
            'payments.paydunya.live_verified' => false,
            'legal.operator_name' => 'FASO DIGITAL TRADING',
            'legal.operator_address' => 'Ouagadougou',
            'legal.operator_email' => 'legal@example.test',
            'legal.disclosures_approved' => true,
            'license.binding_key' => str_repeat('b', 48),
        ]);

        $provider = new PayDunyaProvider();

        $this->assertTrue($provider->isConfigured());
        $this->assertTrue($provider->supportsCheckout('XOF', '5000.00'));
    }

    public function test_readiness_reports_public_https_and_legal_blockers_without_exposing_secrets(): void
    {
        config([
            'app.url' => 'http://127.0.0.1:8000',
            'payments.paydunya.master_key' => 'configured-master-secret',
            'payments.paydunya.private_key' => 'configured-private-secret',
            'payments.paydunya.token' => 'configured-token-secret',
            'payments.paydunya.currency' => 'XOF',
            'legal.operator_name' => '',
            'legal.operator_address' => '',
            'legal.operator_email' => '',
            'legal.disclosures_approved' => false,
            'license.binding_key' => str_repeat('b', 48),
        ]);

        $provider = new PayDunyaProvider();
        $issues = $provider->readinessIssues();

        $this->assertFalse($provider->isConfigured());
        $this->assertContains('APP_URL doit être une adresse HTTPS publique joignable par PayDunya', $issues);
        $this->assertContains('nom légal de l’exploitant absent', $issues);
        $this->assertContains('adresse légale de l’exploitant absente', $issues);
        $this->assertContains('adresse e-mail légale de l’exploitant invalide ou absente', $issues);
        $this->assertContains('mentions légales et informations de risque non approuvées', $issues);
        $this->assertStringNotContainsString('configured-master-secret', implode(' ', $issues));
        $this->assertStringNotContainsString('configured-private-secret', implode(' ', $issues));
        $this->assertStringNotContainsString('configured-token-secret', implode(' ', $issues));
    }

    public function test_confirmed_renewal_extends_entitlement_and_refund_reverts_only_that_period(): void
    {
        config(['license.binding_key' => str_repeat('b', 32)]);
        $user = $this->makeUser();
        $referrer = User::create([
            'first_name' => 'Referral', 'last_name' => 'Owner', 'email' => 'ref-owner@example.test',
            'password' => 'correct horse battery staple', 'role' => 'INVESTOR', 'status' => 'ACTIVE',
        ]);
        $partner = Partner::create(['user_id' => $referrer->id, 'partner_code' => 'RENEWAL10']);
        Referral::create(['partner_id' => $partner->id, 'user_id' => $user->id]);
        $plan = $this->makePlan();
        $originalExpiry = now()->addDays(5)->startOfSecond();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'ACTIVE',
            'starts_at' => now()->subDays(25),
            'ends_at' => $originalExpiry,
            'plan_name_snapshot' => $plan->name,
            'plan_price_snapshot' => '29.00',
            'plan_currency_snapshot' => 'XOF',
            'plan_duration_days_snapshot' => 30,
            'plan_max_mt5_accounts_snapshot' => 1,
        ]);
        [$license] = License::issue([
            'user_id' => $user->id, 'subscription_id' => $subscription->id, 'status' => 'ACTIVE',
            'activated_at' => now()->subDays(25), 'expires_at' => $originalExpiry, 'max_accounts' => 1,
        ]);
        $payment = Payment::create([
            'user_id' => $user->id, 'subscription_id' => $subscription->id, 'provider' => 'paydunya',
            'transaction_id' => 'provider-renewal-1', 'amount' => '5000.00', 'currency' => 'XOF',
            'status' => 'PENDING', 'idempotency_key' => 'cb5d2d6f-e66f-49ce-9258-c48a4055bc01',
            'is_renewal' => true,
        ]);

        $processor = app(PaymentWebhookProcessor::class);
        $processor->process('paydunya', new VerifiedPaymentEvent(
            'renewal-paid-1', 'invoice.paid', 'provider-renewal-1', 'PAID', '5000.00', 'XOF',
        ), '{"verified":"paid"}');

        $this->assertSame('ACTIVE', $subscription->fresh()->status);
        $this->assertTrue($subscription->fresh()->ends_at->greaterThan($originalExpiry));
        $this->assertTrue($license->fresh()->expires_at->equalTo($subscription->fresh()->ends_at));
        $this->assertDatabaseHas('commissions', ['payment_id' => $payment->id, 'amount' => '500.00', 'status' => 'PENDING']);

        $processor->process('paydunya', new VerifiedPaymentEvent(
            'renewal-refunded-1', 'invoice.refunded', 'provider-renewal-1', 'REFUNDED', '5000.00', 'XOF',
        ), '{"verified":"refunded"}');

        $this->assertSame('REFUNDED', $payment->fresh()->status);
        $this->assertSame('ACTIVE', $subscription->fresh()->status);
        $this->assertTrue($subscription->fresh()->ends_at->equalTo($originalExpiry));
        $this->assertSame('ACTIVE', $license->fresh()->status);
        $this->assertTrue($license->fresh()->expires_at->equalTo($originalExpiry));
        $this->assertDatabaseHas('commissions', ['payment_id' => $payment->id, 'status' => 'REVERSED']);
    }

    public function test_registration_attributes_an_active_partner_referral_without_creating_a_payment(): void
    {
        Notification::fake();
        $partnerUser = User::create([
            'first_name' => 'Partner', 'last_name' => 'Test', 'email' => 'partner@example.test',
            'password' => 'correct horse battery staple', 'role' => 'PARTNER', 'status' => 'ACTIVE',
        ]);
        $partnerUser->forceFill(['email_verified_at' => now()])->save();
        $partner = Partner::create([
            'user_id' => $partnerUser->id, 'partner_code' => 'PARTNER42', 'commission_rate' => '10.00', 'status' => 'ACTIVE',
        ]);

        $this->post(route('register.store'), [
            'first_name' => 'Referred', 'last_name' => 'Investor', 'email' => 'new-investor@example.test',
            'country' => 'France', 'password' => 'a sufficiently long password',
            'password_confirmation' => 'a sufficiently long password', 'terms' => 'on', 'ref' => 'PARTNER42',
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('referrals', ['partner_id' => $partner->id, 'user_id' => 2]);
        $this->assertNotNull(User::find(2)->terms_accepted_at);
        $this->assertSame('terms-v2', User::find(2)->terms_version);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_registration_rejects_a_suspended_referral_code_instead_of_silently_dropping_attribution(): void
    {
        $partnerUser = User::create([
            'first_name' => 'Partner', 'last_name' => 'Test', 'email' => 'suspended-partner@example.test',
            'password' => 'correct horse battery staple', 'role' => 'PARTNER', 'status' => 'ACTIVE',
        ]);
        $partner = Partner::create([
            'user_id' => $partnerUser->id, 'partner_code' => 'SUSPENDED10', 'commission_rate' => '10.00', 'status' => 'SUSPENDED',
        ]);

        $this->from(route('register'))->post(route('register.store'), [
            'first_name' => 'New', 'last_name' => 'Investor', 'email' => 'new-referral@example.test',
            'country' => 'Burkina Faso', 'password' => 'a sufficiently long password',
            'password_confirmation' => 'a sufficiently long password', 'terms' => 'on', 'ref' => $partner->partner_code,
        ])->assertRedirect(route('register'))->assertSessionHasErrors('ref');

        $this->assertDatabaseMissing('users', ['email' => 'new-referral@example.test']);
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_public_catalogue_contains_no_synthetic_forecast_or_demo_payment_claim(): void
    {
        app(\Database\Seeders\DatabaseSeeder::class)->run();
        cache()->put('fx.bceao.usd-xof.latest', [
            'rate' => '593.5200',
            'as_of' => now()->toDateString(),
            'fetched_at' => now()->toIso8601String(),
            'source_url' => 'https://www.bceao.int/fr/cours/cours-de-reference-des-principales-devises-contre-Franc-CFA',
        ], now()->addDay());

        $this->get(route('home'))->assertOk()
            ->assertSee('FASO DIGITAL TRADING')
            ->assertSee('USD reste la devise de référence')
            ->assertSee('cours de référence BCEAO')
            ->assertSee('Quelles données de marché sont affichées ?')
            ->assertSee('Les cotations BRVM ne sont pas reproduites ici')
            ->assertSee('Gain journalier prévisionnel')
            ->assertSee('≈ 17 806 XOF')
            ->assertSee('Choisir ce pack')
            ->assertSee('Les gains prévisionnels sont des objectifs indicatifs')
            ->assertDontSee('Taux fixe de 600 XOF');

        $this->get(route('subscriptions'))->assertOk()
            ->assertSee('FASO DIGITAL TRADING')
            ->assertSee('STARTER')
            ->assertSee('30,00 USD')
            ->assertSee('200,00 USD')
            ->assertSee('5,00 USD')
            ->assertSee('Aucun prestataire de paiement compatible n’est configuré')
            ->assertSee('STANDARD')
            ->assertSee('PRO')
            ->assertSee('PREMIUM')
            ->assertDontSee('ELITE')
            ->assertDontSee('VIP')
            ->assertSee('30 jours');
    }

    public function test_paydunya_rejects_zero_xof_even_after_live_configuration_is_verified(): void
    {
        config([
            'payments.paydunya.master_key' => 'merchant-master-key',
            'payments.paydunya.private_key' => 'merchant-private-key',
            'payments.paydunya.token' => 'merchant-token',
            'payments.paydunya.currency' => 'XOF',
            'payments.paydunya.live_verified' => true,
            'legal.operator_name' => 'FASO DIGITAL TRADING',
            'legal.operator_address' => 'Ouagadougou',
            'legal.operator_email' => config('admin.email'),
            'legal.disclosures_approved' => true,
            'license.binding_key' => str_repeat('b', 32),
            'app.url' => 'https://blackwolf.example.test',
        ]);

        $provider = new PayDunyaProvider();
        $this->assertTrue($provider->supportsCheckout('XOF', '5000.00'));
        $this->assertFalse($provider->supportsCheckout('XOF', '0.00'));
        $this->assertFalse($provider->supportsCheckout('XOF', '0000'));
    }

    public function test_live_paydunya_requires_hardened_production_sessions_and_distinct_keys(): void
    {
        config([
            'app.env' => 'production',
            'app.url' => 'https://fasodigitaltrading.com',
            'app.key' => str_repeat('a', 40),
            'app.debug' => false,
            'session.secure' => true,
            'session.encrypt' => true,
            'payments.paydunya.master_key' => 'merchant-master-key',
            'payments.paydunya.private_key' => 'merchant-private-key',
            'payments.paydunya.token' => 'merchant-token',
            'payments.paydunya.currency' => 'XOF',
            'payments.paydunya.live_verified' => true,
            'legal.operator_name' => 'FASO DIGITAL TRADING',
            'legal.operator_address' => 'Ouagadougou',
            'legal.operator_email' => 'legal@example.test',
            'legal.disclosures_approved' => true,
            'license.binding_key' => str_repeat('b', 48),
        ]);

        $provider = new PayDunyaProvider();
        $this->assertTrue($provider->isConfigured());
        config(['session.secure' => false]);
        $this->assertFalse($provider->isConfigured());
        config(['session.secure' => true, 'session.encrypt' => false]);
        $this->assertFalse($provider->isConfigured());
        config(['session.encrypt' => true, 'app.debug' => true]);
        $this->assertFalse($provider->isConfigured());
        config(['app.debug' => false, 'license.binding_key' => str_repeat('a', 40)]);
        $this->assertFalse($provider->isConfigured());
    }

    private function makeUser(): User
    {
        $user = User::create([
            'first_name' => 'Test', 'last_name' => 'Investor', 'email' => 'pay@example.test',
            'password' => 'correct horse battery staple', 'role' => 'INVESTOR', 'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function makePlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Starter', 'slug' => 'starter', 'price' => '29.00', 'currency' => 'USD',
            'duration_days' => 30, 'max_mt5_accounts' => 1, 'status' => true,
        ]);
    }
}
