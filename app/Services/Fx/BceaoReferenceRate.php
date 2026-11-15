<?php

namespace App\Services\Fx;

use Carbon\CarbonImmutable;

final readonly class BceaoReferenceRate
{
    public function __construct(
        public string $rate,
        public CarbonImmutable $asOf,
        public CarbonImmutable $fetchedAt,
        public string $sourceUrl,
        public bool $stale = false,
    ) {}

    public function canBeUsedForCheckout(): bool
    {
        return !$this->stale
            && $this->asOf->greaterThanOrEqualTo(now()->startOfDay()->subDays(3));
    }

    public function amountXof(string $usdAmount): ?int
    {
        if (!preg_match('/^(\d{1,10})\.(\d{2})$/', $usdAmount, $matches)) {
            return null;
        }

        $rateScaled = (int) round((float) $this->rate * 10000, 0, PHP_ROUND_HALF_UP);
        $usdCents = ((int) $matches[1] * 100) + (int) $matches[2];
        // Bound the multiplication, and also respect PayDunya's integer XOF amount limit.
        if ($usdCents < 1 || $usdCents > 5_000_000_000 || $rateScaled < 1) {
            return null;
        }

        $amount = intdiv(($usdCents * $rateScaled) + 500_000, 1_000_000);

        return $amount > 0 && $amount <= 9_999_999_999 ? $amount : null;
    }
}
