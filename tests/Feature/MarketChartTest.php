<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_embeds_tradingview_chart_for_gold_and_selectable_stocks(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('tv-advanced-chart', false)
            ->assertSee('https://widgets.tradingview-widget.com/w/en/tv-advanced-chart.js', false)
            ->assertSee('OANDA:XAUUSD', false)
            ->assertSee('NASDAQ:AAPL', false)
            ->assertSee('NASDAQ:MSFT', false)
            ->assertSee('NASDAQ:NVDA', false)
            ->assertSee('NYSE:TSLA', false)
            ->assertSee('AMEX:SPY', false)
            ->assertSee('DONNÉES TRADINGVIEW')
            ->assertSee('Quelles données de marché sont affichées ?')
            ->assertSee('Le graphique TradingView affiche l’or et une sélection d’actions américaines')
            ->assertSee('Les cotations BRVM ne sont pas reproduites ici');
    }
}
