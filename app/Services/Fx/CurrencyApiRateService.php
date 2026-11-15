<?php

namespace App\Services\Fx;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class CurrencyApiRateService
{
    private const CACHE_KEY = 'fx.currencyapi.usd-xof.latest';
    private const FAILURE_KEY = 'fx.currencyapi.usd-xof.failure';
    private const LOCK_KEY = 'fx.currencyapi.usd-xof.refresh-lock';
    private const ENDPOINT = 'https://api.currencyapi.com/v3/rate';
    private const SOURCE_URL = 'https://currencyapi.com/docs/rate/';

    public function isConfigured(): bool
    {
        return trim((string) config('fx.currencyapi.api_key')) !== '';
    }

    public function latest(): ?ExchangeRateQuote
    {
        $cached = $this->cachedQuote();

        if (app()->environment('testing')) {
            return $cached;
        }

        if (!$this->isConfigured()) {
            return $cached?->markedStale();
        }

        $refreshSeconds = max(15, (int) config('fx.refresh_seconds', 60));
        if ($cached && $cached->canBeUsedForCheckout()
            && $cached->fetchedAt->greaterThan(now()->subSeconds($refreshSeconds))) {
            return $cached;
        }

        if (Cache::has(self::FAILURE_KEY)) {
            return $cached?->markedStale();
        }

        return $this->refresh() ?? $cached?->markedStale();
    }

    public function currentForCheckout(): ?ExchangeRateQuote
    {
        if (app()->environment('testing')) {
            $quote = $this->latest();
        } else {
            $quote = $this->refresh();
        }

        return $quote?->canBeUsedForCheckout() ? $quote : null;
    }

    public function refresh(): ?ExchangeRateQuote
    {
        $cached = $this->cachedQuote();

        if (app()->environment('testing')) {
            return $cached;
        }

        if (!$this->isConfigured() || Cache::has(self::FAILURE_KEY)) {
            return $cached?->markedStale();
        }

        $lock = Cache::lock(self::LOCK_KEY, 15);
        if (!$lock->get()) {
            return $cached?->markedStale();
        }

        try {
            $endpoint = (string) config('fx.currencyapi.endpoint', self::ENDPOINT);
            $parts = parse_url($endpoint);
            if (!is_array($parts)
                || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                || strtolower((string) ($parts['host'] ?? '')) !== 'api.currencyapi.com'
                || (string) ($parts['path'] ?? '') !== '/v3/rate') {
                throw new RuntimeException('The configured currency-rate API endpoint is not approved.');
            }

            $response = Http::withOptions(['allow_redirects' => false])
                ->withHeaders(['apikey' => (string) config('fx.currencyapi.api_key')])
                ->acceptJson()
                ->connectTimeout(4)
                ->timeout(8)
                ->get($endpoint, ['from' => 'USD', 'to' => 'XOF'])
                ->throw();

            $quote = $this->parseResponse($response->json());
            if (!$quote->canBeUsedForCheckout()) {
                throw new RuntimeException('The exchange-rate response is too old or has a future timestamp.');
            }

            Cache::put(self::CACHE_KEY, [
                'rate' => $quote->rate,
                'updated_at' => $quote->updatedAt->toIso8601String(),
                'fetched_at' => $quote->fetchedAt->toIso8601String(),
                'source_url' => $quote->sourceUrl,
                'provider' => $quote->provider,
            ], now()->addDay());
            Cache::forget(self::FAILURE_KEY);

            return $quote;
        } catch (Throwable $exception) {
            Cache::put(self::FAILURE_KEY, true, now()->addSeconds(45));
            Log::warning('Live currency-rate refresh failed.', ['exception' => $exception::class]);

            return $cached?->markedStale();
        } finally {
            $lock->release();
        }
    }

    /** @param array<string, mixed>|null $payload */
    public function parseResponse(?array $payload): ExchangeRateQuote
    {
        $from = strtoupper((string) data_get($payload, 'data.from'));
        $to = strtoupper((string) data_get($payload, 'data.to'));
        $rawRate = data_get($payload, 'data.rate');
        $rawUpdatedAt = data_get($payload, 'meta.last_updated_at');

        if ($from !== 'USD' || $to !== 'XOF' || !is_numeric($rawRate) || !is_string($rawUpdatedAt)) {
            throw new RuntimeException('The exchange-rate response is missing the requested USD/XOF quote.');
        }

        $numericRate = (float) $rawRate;
        if (!is_finite($numericRate) || $numericRate < 200 || $numericRate > 2000) {
            throw new RuntimeException('The USD/XOF quote is outside its allowed range.');
        }

        try {
            $updatedAt = CarbonImmutable::parse($rawUpdatedAt)->utc();
        } catch (Throwable) {
            throw new RuntimeException('The exchange-rate response has an invalid update timestamp.');
        }

        $quote = new ExchangeRateQuote(
            number_format($numericRate, 4, '.', ''),
            $updatedAt,
            CarbonImmutable::now('UTC'),
            self::SOURCE_URL,
        );

        if (!$quote->canBeUsedForCheckout()) {
            throw new RuntimeException('The exchange-rate response is too old or has a future timestamp.');
        }

        return $quote;
    }

    private function cachedQuote(): ?ExchangeRateQuote
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (!is_array($cached)
            || !is_string($cached['rate'] ?? null)
            || !is_string($cached['updated_at'] ?? null)
            || !is_string($cached['fetched_at'] ?? null)
            || ($cached['provider'] ?? null) !== 'currencyapi') {
            return null;
        }

        try {
            $rate = $cached['rate'];
            $numericRate = (float) $rate;
            $source = parse_url((string) ($cached['source_url'] ?? ''));
            if (!preg_match('/^\d{3,4}\.\d{4}$/', $rate)
                || !is_finite($numericRate) || $numericRate < 200 || $numericRate > 2000
                || !is_array($source) || strtolower((string) ($source['scheme'] ?? '')) !== 'https'
                || strtolower((string) ($source['host'] ?? '')) !== 'currencyapi.com') {
                return null;
            }

            return new ExchangeRateQuote(
                $rate,
                CarbonImmutable::parse($cached['updated_at'])->utc(),
                CarbonImmutable::parse($cached['fetched_at'])->utc(),
                self::SOURCE_URL,
            );
        } catch (Throwable) {
            return null;
        }
    }
}
