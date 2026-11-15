<?php

namespace Tests\Feature;

use App\Models\BrvmPurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrvmPurchaseRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_investor_can_record_only_a_non_executable_purchase_request(): void
    {
        $investor = $this->makeUser('investor@example.test', 'INVESTOR');

        $this->actingAs($investor)->post(route('investor.brvm.orders.store'), [
            'symbol' => 'SONA',
            'quantity' => 25,
            'limit_price' => '12500',
        ])->assertSessionHasErrors('acknowledged_non_executable');
        $this->assertDatabaseCount('brvm_purchase_requests', 0);

        $this->actingAs($investor)->post(route('investor.brvm.orders.store'), [
            'symbol' => ' sona ',
            'security_name' => 'Société test',
            'quantity' => 25,
            'limit_price' => '12500',
            'acknowledged_non_executable' => '1',
        ])->assertRedirect(route('investor.brvm.index'))
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'n’a pas encore été transmise'));

        $purchaseRequest = BrvmPurchaseRequest::sole();
        $this->assertSame('SONA', $purchaseRequest->symbol);
        $this->assertSame(25, $purchaseRequest->quantity);
        $this->assertSame('RECEIVED', $purchaseRequest->status);
        $this->assertNotNull($purchaseRequest->customer_acknowledged_at);
        $this->assertNull($purchaseRequest->quote_price);
        $this->assertNull($purchaseRequest->quote_at);
        $this->assertNull($purchaseRequest->forwarded_at);
        $this->assertNull($purchaseRequest->completed_at);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_investor_sees_only_their_own_requests_and_no_fabricated_live_quote(): void
    {
        $owner = $this->makeUser('owner@example.test', 'INVESTOR');
        $other = $this->makeUser('other@example.test', 'INVESTOR');
        $owner->brvmPurchaseRequests()->create([
            'symbol' => 'OWNX', 'quantity' => 2, 'status' => 'RECEIVED', 'customer_acknowledged_at' => now(),
        ]);
        $other->brvmPurchaseRequests()->create([
            'symbol' => 'HIDE', 'quantity' => 9, 'status' => 'RECEIVED', 'customer_acknowledged_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('investor.brvm.index'))
            ->assertOk()
            ->assertSee('OWNX')
            ->assertDontSee('HIDE')
            ->assertSee('Cotations BRVM en temps réel indisponibles')
            ->assertSee('https://www.brvm.org/fr/flux-en-temps-reel')
            ->assertSee('ne transmet pas automatiquement un ordre');

        $this->actingAs($other)->get(route('investor.brvm.index'))->assertOk()->assertSee('HIDE')->assertDontSee('OWNX');
    }

    public function test_admin_requires_coris_reference_and_audits_valid_manual_follow_up(): void
    {
        config(['brvm.coris_manual_channel_enabled' => true]);
        $admin = $this->makeUser(config('admin.email'), 'ADMIN');
        $admin->forceFill(['mfa_verified_at' => now()])->save();
        $investor = $this->makeUser('client@example.test', 'INVESTOR');
        $purchaseRequest = $investor->brvmPurchaseRequests()->create([
            'symbol' => 'SONA', 'quantity' => 3, 'status' => 'RECEIVED', 'customer_acknowledged_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.brvm-orders.index'))
            ->assertOk()
            ->assertSee('Demandes d’achat BRVM')
            ->assertSee('Aucun envoi API à Coris Bourse n’est configuré')
            ->assertSee('SONA');

        $this->actingAs($admin)->put(route('admin.brvm-orders.update', $purchaseRequest), [
            'status' => 'FORWARDED', 'admin_note' => 'A transmettre à Coris',
        ])->assertSessionHasErrors('coris_reference');
        $this->assertSame('RECEIVED', $purchaseRequest->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);

        $this->actingAs($admin)->put(route('admin.brvm-orders.update', $purchaseRequest), [
            'status' => 'FORWARDED', 'coris_reference' => 'CORIS-REQ-123', 'admin_note' => 'Transmise par canal autorisé',
        ])->assertRedirect(route('admin.brvm-orders.index'));
        $this->assertSame('FORWARDED', $purchaseRequest->fresh()->status);
        $this->assertNotNull($purchaseRequest->fresh()->forwarded_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'brvm_purchase_request.status_updated',
            'entity_id' => $purchaseRequest->id,
        ]);

        $this->actingAs($admin)->put(route('admin.brvm-orders.update', $purchaseRequest), [
            'status' => 'EXECUTED', 'coris_reference' => 'CORIS-REQ-123', 'admin_note' => 'Confirmation à vérifier',
        ])->assertStatus(409);
        $this->assertSame('FORWARDED', $purchaseRequest->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_forwarding_is_disabled_until_an_authorized_coris_channel_is_configured(): void
    {
        config(['brvm.coris_manual_channel_enabled' => false]);
        $admin = $this->makeUser(config('admin.email'), 'ADMIN');
        $admin->forceFill(['mfa_verified_at' => now()])->save();
        $investor = $this->makeUser('client-channel@example.test', 'INVESTOR');
        $purchaseRequest = $investor->brvmPurchaseRequests()->create([
            'symbol' => 'SONA', 'quantity' => 1, 'status' => 'RECEIVED', 'customer_acknowledged_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.brvm-orders.index'))
            ->assertOk()
            ->assertSee('Aucun canal Coris autorisé n’est actuellement configuré')
            ->assertDontSee('value="FORWARDED"', false);

        $this->actingAs($admin)->put(route('admin.brvm-orders.update', $purchaseRequest), [
            'status' => 'FORWARDED', 'coris_reference' => 'CORIS-123', 'admin_note' => 'Transmission demandée',
        ])->assertSessionHasErrors('status');
        $this->assertSame('RECEIVED', $purchaseRequest->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_non_primary_admin_cannot_manage_brvm_requests(): void
    {
        $otherAdmin = $this->makeUser('other-admin@example.test', 'ADMIN');
        $otherAdmin->forceFill(['mfa_verified_at' => now()])->save();

        $this->actingAs($otherAdmin)->get(route('admin.brvm-orders.index'))->assertForbidden();
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => 'a-long-password-for-testing',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
