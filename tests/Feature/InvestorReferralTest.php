<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestorReferralTest extends TestCase
{
    use RefreshDatabase;

    public function test_investor_can_generate_a_ten_percent_referral_code(): void
    {
        $investor = $this->makeUser('referrer@example.test');

        $this->actingAs($investor)->post(route('investor.referral-code.store'))
            ->assertRedirect(route('investor.dashboard'));

        $partner = Partner::where('user_id', $investor->id)->sole();
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{12}$/', $partner->partner_code);
        $this->assertSame('10.00', $partner->commission_rate);

        $this->actingAs($investor)->get(route('investor.dashboard'))
            ->assertOk()->assertSee($partner->partner_code)->assertSee('10.00 %');

        $this->actingAs($investor)->get(route('partner.dashboard'))
            ->assertOk()->assertSee('Mon espace partenaire')->assertSee($partner->partner_code);
    }

    public function test_investor_without_a_partner_record_cannot_open_the_partner_dashboard(): void
    {
        $investor = $this->makeUser('no-partner@example.test');

        $this->actingAs($investor)->get(route('partner.dashboard'))->assertNotFound();
    }

    public function test_every_confirmed_referral_payment_creates_one_ten_percent_commission(): void
    {
        $referrer = $this->makeUser('referrer@example.test');
        $referred = $this->makeUser('referred@example.test');
        $partner = Partner::create([
            'user_id' => $referrer->id, 'partner_code' => 'REFERRER10', 'commission_rate' => '10.00', 'status' => 'ACTIVE',
        ]);
        Referral::create(['partner_id' => $partner->id, 'user_id' => $referred->id]);

        $first = $this->makePaidPayment($referred, '100.00');
        $renewal = $this->makePaidPayment($referred, '250.00');
        $service = app(ReferralCommissionService::class);
        $firstCommission = $service->accrueFor($first);
        $this->assertSame($firstCommission->id, $service->accrueFor($first)?->id);
        $renewalCommission = $service->accrueFor($renewal);

        $pendingPayment = $this->makePaidPayment($referred, '200.00');
        $pendingPayment->update(['status' => 'PENDING', 'paid_at' => null]);
        $this->assertNull($service->accrueFor($pendingPayment));

        $this->assertSame('10.00', $firstCommission->amount);
        $this->assertSame('10.00', $firstCommission->percentage);
        $this->assertSame('25.00', $renewalCommission->amount);
        $this->assertSame('10.00', $renewalCommission->percentage);
        $this->assertDatabaseCount('commissions', 2);
    }

    public function test_new_partner_records_default_to_the_requested_ten_percent_rate(): void
    {
        $investor = $this->makeUser('new-partner@example.test');
        $partner = Partner::create(['user_id' => $investor->id, 'partner_code' => 'TENPERCENT01']);

        $this->assertSame('10.00', $partner->fresh()->commission_rate);
    }

    public function test_referred_investor_can_open_the_partner_portal_but_other_users_cannot_read_it(): void
    {
        $referrer = $this->makeUser('portal-referrer@example.test');
        $referred = $this->makeUser('portal-follower@example.test');
        $referred->forceFill(['first_name' => 'FilleulVisible'])->save();
        $partner = Partner::create(['user_id' => $referrer->id, 'partner_code' => 'PORTAL10']);
        Referral::create(['partner_id' => $partner->id, 'user_id' => $referred->id]);

        $this->actingAs($referrer)->get(route('partner.dashboard'))
            ->assertOk()->assertSee('FilleulVisible');
        $this->actingAs($referred)->get(route('partner.dashboard'))->assertNotFound();
    }

    private function makeUser(string $email): User
    {
        $user = User::create([
            'first_name' => 'Test', 'last_name' => 'Investor', 'email' => $email,
            'password' => 'a-long-test-password', 'role' => 'INVESTOR', 'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function makePaidPayment(User $user, string $amount): Payment
    {
        return Payment::create([
            'user_id' => $user->id, 'provider' => 'paydunya', 'amount' => $amount,
            'currency' => 'XOF', 'status' => 'PAID', 'paid_at' => now(),
        ]);
    }
}
