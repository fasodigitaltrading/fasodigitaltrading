<?php

namespace Tests\Feature;

use App\Services\Fx\BceaoReferenceRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BceaoReferenceRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_table_parser_extracts_usd_rate_and_publication_date(): void
    {
        $html = <<<'HTML'
            <html><body>
            <h2>Cours des devises du vendredi 13 Novembre 2026</h2>
            <table><tr><th>Devise</th><th>FCFA</th></tr>
            <tr><td>Euro</td><td>655,9570</td></tr>
            <tr><td>Dollar us</td><td>593,5200</td></tr></table>
            </body></html>
            HTML;

        $rate = app(BceaoReferenceRateService::class)->parseOfficialPage(
            $html,
            'https://www.bceao.int/fr/cours/cours-de-reference-des-principales-devises-contre-Franc-CFA',
        );

        $this->assertSame('593.5200', $rate->rate);
        $this->assertSame('2026-11-13', $rate->asOf->toDateString());
        $this->assertSame(5935, $rate->amountXof('10.00'));
        $this->assertSame(8013, $rate->amountXof('13.50'));
    }

    public function test_latest_rate_does_not_make_network_requests_implicitly_in_automated_tests(): void
    {
        Http::preventStrayRequests();

        $this->assertNull(app(BceaoReferenceRateService::class)->latest());
    }

    public function test_checkout_rate_uses_the_explicit_test_fixture_without_network_access(): void
    {
        Http::preventStrayRequests();
        cache()->put('fx.bceao.usd-xof.latest', [
            'rate' => '593.5200',
            'as_of' => now()->toDateString(),
            'fetched_at' => now()->toIso8601String(),
            'source_url' => 'https://www.bceao.int/fr/cours/cours-de-reference-des-principales-devises-contre-Franc-CFA',
        ], now()->addDay());

        $rate = app(BceaoReferenceRateService::class)->currentForCheckout();

        $this->assertNotNull($rate);
        $this->assertSame('593.5200', $rate->rate);
        $this->assertTrue($rate->canBeUsedForCheckout());
    }

    public function test_refresh_command_reports_the_fixed_payment_rate_without_network_access(): void
    {
        Http::preventStrayRequests();
        cache()->put('fx.bceao.usd-xof.latest', [
            'rate' => '593.5200',
            'as_of' => now()->toDateString(),
            'fetched_at' => now()->toIso8601String(),
            'source_url' => 'https://www.bceao.int/fr/cours/cours-de-reference-des-principales-devises-contre-Franc-CFA',
        ], now()->addDay());

        $exit = Artisan::call('blackwolf:refresh-fx');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('1 USD = 600 XOF', $output);
        $this->assertStringNotContainsString('www.bceao.int', $output);
        $this->assertStringNotContainsString('593.5200', $output);
    }

    public function test_parser_rejects_pages_without_a_valid_official_rate_and_date(): void
    {
        $this->expectException(\RuntimeException::class);

        app(BceaoReferenceRateService::class)->parseOfficialPage(
            '<html><body>Page indisponible</body></html>',
            'https://www.bceao.int/fr/cours/cours-de-reference-des-principales-devises-contre-Franc-CFA',
        );
    }
}
