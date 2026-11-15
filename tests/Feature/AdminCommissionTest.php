<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_admin_can_review_commissions_without_automatically_settling_them(): void
    {
        $admin = $this->makeUser(config('admin.email'), 'ADMIN');
        $admin->forceFill(['mfa_verified_at' => now()])->save();
        [$partner, $follower, $commission] = $this->makeCommission();

        $this->actingAs($admin)->get(route('admin.commissions.index'))
            ->assertOk()
            ->assertSee($partner->partner_code)
            ->assertSee($follower->email)
            ->assertSee('la plateforme ne transfère pas d’argent')
            ->assertSee('500,00');

        $this->actingAs($admin)->get(route('admin.commissions.index', ['status' => 'PENDING']))
            ->assertOk()->assertSee('500,00');

        $this->assertSame('PENDING', $commission->fresh()->status);
    }

    public function test_investor_cannot_view_admin_commission_records(): void
    {
        $investor = $this->makeUser('investor@example.test', 'INVESTOR');

        $this->actingAs($investor)->get(route('admin.commissions.index'))->assertForbidden();
    }

    private function makeCommission(): array
    {
        $referrer = $this->makeUser('referrer@example.test', 'INVESTOR');
        $follower = $this->makeUser('follower@example.test', 'INVESTOR');
        $partner = Partner::create([
            'user_id' => $referrer->id,
            'partner_code' => 'ADMINREF1234',
            'commission_rate' => '10.00',
            'status' => 'ACTIVE',
        ]);
        $payment = Payment::create([
            'user_id' => $follower->id,
            'provider' => 'paydunya',
            'transaction_id' => 'admin-commission-payment',
            'amount' => '5000.00',
            'currency' => 'XOF',
            'status' => 'PAID',
            'paid_at' => now(),
        ]);
        $commission = Commission::create([
            'partner_id' => $partner->id,
            'user_id' => $follower->id,
            'payment_id' => $payment->id,
            'amount' => '500.00',
            'currency' => 'XOF',
            'percentage' => '10.00',
            'status' => 'PENDING',
            'available_at' => now()->addDays(14),
        ]);

        return [$partner, $follower, $commission];
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'Member',
            'email' => $email,
            'password' => 'a-long-test-password',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
