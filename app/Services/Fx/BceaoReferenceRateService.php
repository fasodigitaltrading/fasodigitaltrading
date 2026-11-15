<?php

namespace App\Services\Fx;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class BceaoReferenceRateService
{
    private const CACHE_KEY = 'fx.bceao.usd-xof.latest';
    private const FAILURE_KEY = 'fx.bceao.usd-xof.failure';

    public function latest(): ?BceaoReferenceRate
    {
        $cached = Cache::get(self::CACHE_KEY);
        $cachedRate = is_array($cached) ? $this->fromArray($cached, false) : null;
        $refreshMinutes = max(5, (int) config('fx.refresh_minutes', 360));

        // Feature tests never access the public network implicitly. Tests for the
        // parser can feed a page fixture directly to parseOfficialPage().
        if (app()->environment('testing') && !$cachedRate) {
            return null;
        }

        if ($cachedRate && $cachedRate->fetchedAt->greaterThan(now()->subMinutes($refreshMinutes))) {
            return $cachedRate;
        }

        if (Cache::has(self::FAILURE_KEY)) {
            return $cachedRate ? new BceaoReferenceRate(
                $cachedRate->rate,
                $cachedRate->asOf,
                $cachedRate->fetchedAt,
                $cachedRate->sourceUrl,
                true,
            ) : null;
        }

        return $this->refresh();
    }

    public function currentForCheckout(): ?BceaoReferenceRate
    {
        // Order creation must use the latest official publication available,
        // rather than a display cache that may be several hours old. Tests use
        // their explicit cache fixture and never reach the public network.
        $rate = app()->environment('testing') ? $this->latest() : $this->refresh();

        return $rate?->canBeUsedForCheckout() ? $rate : null;
    }

    public function refresh(): ?BceaoReferenceRate
    {
        $cached = Cache::get(self::CACHE_KEY);
        $cachedRate = is_array($cached) ? $this->fromArray($cached, false) : null;

        if (app()->environment('testing')) {
            return $cachedRate;
        }

        try {
            $url = (string) config('fx.bceao_reference_url');
            $parts = parse_url($url);
            if (!is_array($parts)
                || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                || strtolower((string) ($parts['host'] ?? '')) !== 'www.bceao.int') {
                throw new RuntimeException('The configured BCEAO source is not an approved HTTPS host.');
            }

            $response = Http::withOptions(['allow_redirects' => false])->accept('text/html')
                ->connectTimeout(4)
                ->timeout(8)
                ->get($url)
                ->throw();

            $parsed = $this->parseOfficialPage($response->body(), $url);
            $ageLimit = max(1, (int) config('fx.max_rate_age_days', 7));
            if ($parsed->asOf->isFuture() || $parsed->asOf->lessThan(now()->startOfDay()->subDays($ageLimit))) {
                throw new RuntimeException('The official BCEAO rate is too old for use.');
            }

            Cache::put(self::CACHE_KEY, [
                'rate' => $parsed->rate,
                'as_of' => $parsed->asOf->toDateString(),
                'fetched_at' => $parsed->fetchedAt->toIso8601String(),
                'source_url' => $parsed->sourceUrl,
            ], now()->addDays($ageLimit + 1));
            Cache::forget(self::FAILURE_KEY);

            return $parsed;
        } catch (Throwable $exception) {
            Cache::put(self::FAILURE_KEY, true, now()->addMinutes(10));
            Log::warning('BCEAO reference-rate refresh failed.', ['exception' => $exception::class]);

            if ($cachedRate && $cachedRate->asOf->greaterThanOrEqualTo(
                now()->startOfDay()->subDays(max(1, (int) config('fx.max_rate_age_days', 7))),
            )) {
                return new BceaoReferenceRate(
                    $cachedRate->rate,
                    $cachedRate->asOf,
                    $cachedRate->fetchedAt,
                    $cachedRate->sourceUrl,
                    true,
                );
            }

            return null;
        }
    }

    public function parseOfficialPage(string $html, string $sourceUrl): BceaoReferenceRate
    {
        if (strlen($html) > 5_000_000) {
            throw new RuntimeException('The BCEAO response exceeds the allowed size.');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            if (!$document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                throw new RuntimeException('The BCEAO reference-rate page could not be parsed.');
            }

            $xpath = new DOMXPath($document);
            $rows = $xpath->query('//tr');
            $rate = null;
            foreach ($rows ?: [] as $row) {
                $cells = $xpath->query('./th|./td', $row);
                if (!$cells || $cells->length < 2) {
                    continue;
                }

                $currency = $this->normalize((string) $cells->item(0)?->textContent);
                if (in_array($currency, ['dollar us', 'dollar américain', 'dollar americain'], true)) {
                    $rawRate = $this->normalize((string) $cells->item(1)?->textContent);
                    $rawRate = str_replace([' ', "\u{00A0}", "\u{202F}"], '', $rawRate);
                    $rawRate = str_replace(',', '.', $rawRate);
                    if (!is_numeric($rawRate)) {
                        break;
                    }

                    $numeric = (float) $rawRate;
                    if ($numeric < 200 || $numeric > 2000) {
                        break;
                    }
                    $rate = number_format($numeric, 4, '.', '');
                    break;
                }
            }

            $text = $this->normalize((string) $document->textContent);
            if (!preg_match('/Cours des devises du ([\p{L}]+)\s+(\d{1,2})\s+([\p{L}]+)\s+(\d{4})/iu', $text, $dateMatch)) {
                throw new RuntimeException('The BCEAO page does not contain a recognizable publication date.');
            }

            $asOf = $this->parseFrenchDate($dateMatch[2], $dateMatch[3], $dateMatch[4]);
            if (!$rate || !$asOf) {
                throw new RuntimeException('The BCEAO page does not contain a valid USD/XOF reference row.');
            }

            return new BceaoReferenceRate($rate, $asOf, CarbonImmutable::now(), $sourceUrl);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function fromArray(array $value, bool $stale): ?BceaoReferenceRate
    {
        try {
            if (!is_string($value['rate'] ?? null) || !is_string($value['as_of'] ?? null)
                || !is_string($value['fetched_at'] ?? null) || !is_string($value['source_url'] ?? null)) {
                return null;
            }

            if (!preg_match('/^\d{3,4}\.\d{4}$/', $value['rate'])
                || (float) $value['rate'] < 200 || (float) $value['rate'] > 2000) {
                return null;
            }
            $source = parse_url($value['source_url']);
            if (!is_array($source) || strtolower((string) ($source['scheme'] ?? '')) !== 'https'
                || strtolower((string) ($source['host'] ?? '')) !== 'www.bceao.int') {
                return null;
            }

            return new BceaoReferenceRate(
                $value['rate'],
                CarbonImmutable::parse($value['as_of']),
                CarbonImmutable::parse($value['fetched_at']),
                $value['source_url'],
                $stale,
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function parseFrenchDate(string $day, string $month, string $year): ?CarbonImmutable
    {
        $months = [
            'janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4,
            'mai' => 5, 'juin' => 6, 'juillet' => 7, 'août' => 8, 'aout' => 8,
            'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12, 'decembre' => 12,
        ];
        $monthNumber = $months[mb_strtolower($month)] ?? null;

        if (!$monthNumber || !checkdate($monthNumber, (int) $day, (int) $year)) {
            return null;
        }

        return CarbonImmutable::create((int) $year, $monthNumber, (int) $day)->startOfDay();
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtolower($value);
    }
}
