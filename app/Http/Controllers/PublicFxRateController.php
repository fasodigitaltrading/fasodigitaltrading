<?php

namespace App\Http\Controllers;

use App\Services\Fx\FixedUsdXofRateService;
use Illuminate\Http\JsonResponse;

final class PublicFxRateController
{
    public function __invoke(FixedUsdXofRateService $rates): JsonResponse
    {
        $quote = $rates->latest();

        return response()->json([
            'available' => $quote !== null,
            'usable' => $quote?->canBeUsedForCheckout() ?? false,
            'rate' => $quote?->rate,
            'as_of' => $quote?->asOf->toDateString(),
            'updated_at' => $quote?->updatedAt->toIso8601String(),
            'fetched_at' => $quote?->fetchedAt->toIso8601String(),
            'source_url' => $quote?->sourceUrl,
            'provider' => $quote?->provider,
            'stale' => false,
        ])->header('Cache-Control', 'private, no-store');
    }
}
