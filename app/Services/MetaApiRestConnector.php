<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use InvalidArgumentException;
use RuntimeException;

/** Read-only MetaApi REST connector. Supply an account-scoped reader token at runtime. */
class MetaApiRestConnector implements Mt5ConnectorInterface
{
    private ?string $accountId = null;
    private ?string $accessToken = null;
    private ?string $baseUrl = null;

    public function connect(array $credentials): bool
    {
        foreach (['password', 'master_password', 'otp', 'pin', 'secret'] as $forbidden) {
            if (array_key_exists($forbidden, $credentials)) {
                throw new InvalidArgumentException('Only an account-scoped MetaApi reader token and MetaApi account ID are accepted.');
            }
        }

        $accountId = $credentials['account_id'] ?? null;
        $accessToken = $credentials['access_token'] ?? null;
        $baseUrl = (string) config('mt5.metaapi.base_url');
        $parts = parse_url($baseUrl);

        if (!is_string($accountId) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $accountId)
            || !is_string($accessToken) || trim($accessToken) === ''
            || !is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https'
            || !preg_match('/^mt-client-api-v1(?:\.[a-z0-9-]+)?\.agiliumtrade\.ai$/i', $parts['host'] ?? '')
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('MetaApi REST connector is not configured with a valid HTTPS endpoint and account-scoped credentials.');
        }

        $this->accountId = $accountId;
        $this->accessToken = $accessToken;
        $this->baseUrl = rtrim($baseUrl, '/');

        return true;
    }

    public function getAccountInfo(): array
    {
        return $this->get("account-information");
    }

    public function getOpenPositions(): array
    {
        return $this->get('positions');
    }

    public function getTradeHistory(): array
    {
        $end = now()->utc();
        $start = $end->copy()->subDays(max(1, min(90, (int) config('mt5.metaapi.history_days', 30))));
        $path = 'history-deals/time/'.rawurlencode($start->format('Y-m-d\TH:i:s.v\Z')).'/'.rawurlencode($end->format('Y-m-d\TH:i:s.v\Z'));

        return $this->get($path, ['limit' => 1000, 'offset' => 0]);
    }

    public function disconnect(): void
    {
        $this->accountId = null;
        $this->accessToken = null;
        $this->baseUrl = null;
    }

    private function get(string $path, array $query = []): array
    {
        if (!$this->accountId || !$this->accessToken || !$this->baseUrl) {
            throw new RuntimeException('MetaApi connector is not connected.');
        }

        $url = $this->baseUrl.'/users/current/accounts/'.rawurlencode($this->accountId).'/'.$path;

        try {
            $response = $this->request()->get($url, $query)->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('MetaApi read request failed with HTTP '.$exception->response->status().'.', previous: $exception);
        }

        $data = $response->json();
        if (!is_array($data)) {
            throw new RuntimeException('MetaApi returned an invalid JSON response.');
        }

        return $data;
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders(['auth-token' => $this->accessToken])
            ->timeout(max(1, min(60, (int) config('mt5.metaapi.timeout_seconds', 15))));
    }
}
