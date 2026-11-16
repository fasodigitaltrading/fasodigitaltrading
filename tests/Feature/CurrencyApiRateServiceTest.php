<?php

namespace Tests\Feature;

use App\Services\Fx\CurrencyApiRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurrencyApiRateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_parser_accepts_only_a_recent_usd_xof_quote(): void
    {
        $quote = app(CurrencyApiRateService::class)->parseResponse([
            'meta' => ['last_updated_at' => now()->toIso8601String()],
            'data' => [
                'from' => 'USD',
                'to' => 'XOF',
                'rate' => 592.99752,
                'inverse' => 0.001686,
            ],
        ]);

        $this->assertSame('592.9975', $quote->rate);
        $this->assertTrue($quote->canBeUsedForCheckout());
        $this->assertSame(5930, $quote->amountXof('10.00'));
        $this->assertSame('currencyapi', $quote->provider);
    }

    public function test_missing_api_key_never_makes_a_live_request(): void
    {
        config(['fx.currencyapi.api_key' => '']);
        Http::preventStrayRequests();

        $this->assertFalse(app(CurrencyApiRateService::class)->isConfigured());
        $this->assertNull(app(CurrencyApiRateService::class)->refresh());
    }

    public function test_public_rate_endpoint_uses_the_fixed_payment_rate_without_network_access(): void
    {
        config(['fx.currencyapi.api_key' => 'must-not-be-used']);
        Http::preventStrayRequests();
        $this->getJson(route('fx.usd-xof'))
            ->assertOk()
            ->assertJson([
                'available' => true,
                'usable' => true,
                'rate' => '600.0000',
                'as_of' => now()->toDateString(),
                'provider' => 'fixed-600',
                'source_url' => '',
                'stale' => false,
            ]);
    }

    public function test_rate_parser_rejects_stale_or_wrong_currency_responses(): void
    {
        $service = app(CurrencyApiRateService::class);
        $this->expectException(\RuntimeException::class);

        $service->parseResponse([
            'meta' => ['last_updated_at' => now()->subMinutes(10)->toIso8601String()],
            'data' => ['from' => 'USD', 'to' => 'XOF', 'rate' => 593.0],
        ]);
    }
}
