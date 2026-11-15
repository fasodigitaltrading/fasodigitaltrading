<?php

namespace App\Services;

use RuntimeException;

class Mt5ConnectorService implements Mt5ConnectorInterface
{
    private ?Mt5ConnectorInterface $connector = null;

    public function isConfigured(): bool
    {
        if (config('mt5.provider') !== 'metaapi') {
            return false;
        }

        $baseUrl = trim((string) config('mt5.metaapi.base_url'));
        $parts = parse_url($baseUrl);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && preg_match('/^mt-client-api-v1(?:\.[a-z0-9-]+)?\.agiliumtrade\.ai$/i', (string) ($parts['host'] ?? '')) === 1
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['query'])
            && !isset($parts['fragment']);
    }

    public function connect(array $credentials): bool
    {
        if (config('mt5.provider') !== 'metaapi') {
            throw new RuntimeException('No MT5 connector is configured.');
        }

        if (!$this->isConfigured()) {
            throw new RuntimeException('MetaApi must use an approved HTTPS endpoint before connecting.');
        }

        $this->connector = new MetaApiRestConnector();

        return $this->connector->connect($credentials);
    }

    public function getAccountInfo(): array
    {
        return $this->connected()->getAccountInfo();
    }

    public function getOpenPositions(): array
    {
        return $this->connected()->getOpenPositions();
    }

    public function getTradeHistory(): array
    {
        return $this->connected()->getTradeHistory();
    }

    public function disconnect(): void
    {
        $this->connector?->disconnect();
        $this->connector = null;
    }

    private function connected(): Mt5ConnectorInterface
    {
        return $this->connector ?? throw new RuntimeException('MT5 connector is not connected.');
    }
}
