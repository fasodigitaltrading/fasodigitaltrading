<?php

namespace App\Services\Fx;

use Carbon\CarbonImmutable;

final class FixedUsdXofRateService
{
    public function latest(): ExchangeRateQuote
    {
        return $this->quote();
    }

    public function currentForCheckout(): ExchangeRateQuote
    {
        return $this->quote();
    }

    public function refresh(): ExchangeRateQuote
    {
        return $this->quote();
    }

    private function quote(): ExchangeRateQuote
    {
        $appliedAt = CarbonImmutable::now('UTC');

        return new ExchangeRateQuote(
            (string) config('fx.fixed_usd_xof', '600.0000'),
            $appliedAt,
            $appliedAt,
            '',
            'fixed-600',
        );
    }
}
