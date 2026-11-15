<?php

namespace App\Services\Fx;

use Carbon\CarbonImmutable;

final readonly class ExchangeRateQuote
{
    public CarbonImmutable $asOf;

    public function __construct(
        public string $rate,
        public CarbonImmutable $updatedAt,
        public CarbonImmutable $fetchedAt,
        public string $sourceUrl,
        public string $provider = 'currencyapi',
        public bool $stale = false,
    ) {
        $this->asOf = $updatedAt->startOfDay();
    }

    public function canBeUsedForCheckout(): bool
    {
        if ($this->provider === 'fixed-600') {
            return !$this->stale && $this->rate === '600.0000';
        }

        $maxAgeSeconds = max(60, (int) config('fx.max_rate_age_seconds', 180));

        return !$this->stale
            && $this->updatedAt->lessThanOrEqualTo(now()->addSeconds(30))
            && $this->updatedAt->greaterThanOrEqualTo(now()->subSeconds($maxAgeSeconds));
    }

    public function amountXof(string $usdAmount): ?int
    {
        if (!preg_match('/^(\d{1,10})\.(\d{2})$/', $usdAmount, $matches)) {
            return null;
        }

        $rateScaled = (int) round((float) $this->rate * 10000, 0, PHP_ROUND_HALF_UP);
        $usdCents = ((int) $matches[1] * 100) + (int) $matches[2];
        if ($usdCents < 1 || $usdCents > 5_000_000_000 || $rateScaled < 1) {
            return null;
        }

        $amount = intdiv(($usdCents * $rateScaled) + 500_000, 1_000_000);

        return $amount > 0 && $amount <= 9_999_999_999 ? $amount : null;
    }

    public function markedStale(): self
    {
        return new self(
            $this->rate,
            $this->updatedAt,
            $this->fetchedAt,
            $this->sourceUrl,
            $this->provider,
            true,
        );
    }
}
