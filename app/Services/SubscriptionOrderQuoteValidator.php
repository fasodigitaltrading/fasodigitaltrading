<?php

namespace App\Services;

use App\Models\SubscriptionOrder;
use App\Services\Fx\BceaoReferenceRate;
use App\Services\Fx\ExchangeRateQuote;
use Carbon\CarbonImmutable;

final class SubscriptionOrderQuoteValidator
{
    /** Validate the immutable rate provenance and XOF calculation, even after a quote expires. */
    public function hasValidSnapshot(SubscriptionOrder $order): bool
    {
        if (!$order->amount_xof || !$order->fx_rate || !$this->validUsdAmount((string) $order->amount_usd)) {
            return false;
        }

        if ($order->fx_provider === 'fixed-600') {
            if ((string) $order->fx_rate !== '600.0000') {
                return false;
            }
            $rate = new ExchangeRateQuote('600.0000', CarbonImmutable::now(), CarbonImmutable::now(), '', 'fixed-600');
        } elseif ($order->fx_provider === 'bceao-reference') {
            $rateValue = (string) $order->fx_rate;
            $source = parse_url((string) $order->fx_source_url);
            if (!preg_match('/^\d{3,4}\.\d{4}$/', $rateValue)
                || (float) $rateValue < 200 || (float) $rateValue > 2000
                || !$order->fx_rate_as_of || !$order->fx_fetched_at
                || !$this->isOfficialSource($source)
                || $order->fx_rate_as_of->isFuture()
                || $order->fx_fetched_at->isFuture()) {
                return false;
            }
            $rate = new BceaoReferenceRate(
                $rateValue,
                CarbonImmutable::instance($order->fx_rate_as_of),
                CarbonImmutable::instance($order->fx_fetched_at),
                (string) $order->fx_source_url,
            );
        } else {
            return false;
        }

        return $rate->amountXof((string) $order->amount_usd) === (int) $order->amount_xof;
    }

    /** A new transfer or checkout requires a valid, unexpired quote. */
    public function canPayNow(SubscriptionOrder $order): bool
    {
        if (!$this->hasValidSnapshot($order) || !$order->quote_expires_at?->isFuture()) {
            return false;
        }

        // Historical BCEAO orders remain auditable, but only new fixed-rate
        // quotes may start a payment. Pending legacy orders must be refreshed.
        return $order->fx_provider === 'fixed-600';
    }

    private function validUsdAmount(string $amount): bool
    {
        return preg_match('/^\d{1,10}\.\d{2}$/', $amount) === 1 && (float) $amount > 0;
    }

    private function isOfficialSource(array|false $source): bool
    {
        return is_array($source)
            && strtolower((string) ($source['scheme'] ?? '')) === 'https'
            && strtolower((string) ($source['host'] ?? '')) === 'www.bceao.int'
            && !isset($source['user']) && !isset($source['pass'])
            && !isset($source['query']) && !isset($source['fragment']);
    }
}
