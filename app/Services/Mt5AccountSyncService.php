<?php

namespace App\Services;

use App\Models\License;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class Mt5AccountSyncService
{
    public function attach(User $user, License $license, string $providerAccountId, string $readerToken): TradingAccount
    {
        $this->assertLicenseOwnerAndActive($license, $user);
        $snapshot = $this->fetch($providerAccountId, $readerToken);

        return DB::transaction(function () use ($user, $license, $providerAccountId, $readerToken, $snapshot) {
            $lockedLicense = License::whereKey($license->id)->lockForUpdate()->firstOrFail();
            $this->assertLicenseOwnerAndActive($lockedLicense, $user);

            if (TradingAccount::where('provider_account_id', $providerAccountId)->exists()) {
                throw ValidationException::withMessages(['provider_account_id' => 'Ce compte MetaApi est déjà rattaché.']);
            }

            $login = (string) ($snapshot['info']['login'] ?? '');
            $server = (string) ($snapshot['info']['server'] ?? '');
            $binding = app(LicenseService::class)->bindAccount($lockedLicense, $login, $server);
            if (!$binding['valid'] || !$binding['account_allowed']) {
                throw ValidationException::withMessages(['license_key' => 'Cette licence ne permet pas de rattacher ce compte.']);
            }

            $info = $snapshot['info'];
            $account = TradingAccount::create([
                'user_id' => $user->id,
                'account_number_masked' => $this->maskLogin($info['login'] ?? null),
                'server' => is_string($info['server'] ?? null) ? mb_substr($info['server'], 0, 150) : null,
                'connection_status' => 'CONNECTED',
                'readonly_status' => true,
                'balance' => $this->numeric($info['balance'] ?? null),
                'equity' => $this->numeric($info['equity'] ?? null),
                'currency' => is_string($info['currency'] ?? null) ? mb_substr($info['currency'], 0, 10) : 'USD',
                'last_sync_at' => now(),
                'provider_account_id' => $providerAccountId,
                'account_access_token' => $readerToken,
            ]);

            $this->persistSnapshot($account, $snapshot);

            return $account;
        });
    }

    public function sync(TradingAccount $account): TradingAccount
    {
        $snapshot = $this->fetch((string) $account->provider_account_id, (string) $account->account_access_token);

        return DB::transaction(function () use ($account, $snapshot) {
            $account = TradingAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $info = $snapshot['info'];
            $account->update([
                'account_number_masked' => $this->maskLogin($info['login'] ?? null),
                'server' => is_string($info['server'] ?? null) ? mb_substr($info['server'], 0, 150) : null,
                'connection_status' => 'CONNECTED',
                'balance' => $this->numeric($info['balance'] ?? null),
                'equity' => $this->numeric($info['equity'] ?? null),
                'currency' => is_string($info['currency'] ?? null) ? mb_substr($info['currency'], 0, 10) : 'USD',
                'last_sync_at' => now(),
            ]);
            $this->persistSnapshot($account, $snapshot);

            return $account->fresh();
        });
    }

    private function fetch(string $accountId, string $readerToken): array
    {
        $connector = app(Mt5ConnectorService::class);
        $connector->connect(['account_id' => $accountId, 'access_token' => $readerToken]);
        try {
            $info = $connector->getAccountInfo();
            $positions = $connector->getOpenPositions();
            $trades = $connector->getTradeHistory();
        } catch (Throwable $exception) {
            $connector->disconnect();
            throw new RuntimeException('Unable to read the MetaApi account. Check its reader token and connection state.', previous: $exception);
        }
        $connector->disconnect();

        if (!isset($info['balance'], $info['equity']) || !is_array($positions)) {
            throw new RuntimeException('MetaApi returned incomplete account data.');
        }

        if (!is_array($trades)) {
            throw new RuntimeException('MetaApi returned invalid historical deal data.');
        }

        return ['info' => $info, 'positions' => $positions, 'trades' => $trades];
    }

    private function persistSnapshot(TradingAccount $account, array $snapshot): void
    {
        $info = $snapshot['info'];
        $equity = (float) $this->numeric($info['equity'] ?? 0);
        $peakEquity = (float) ($account->performanceSnapshots()->max('equity') ?? $equity);
        $drawdown = $peakEquity > 0 ? max(0, (($peakEquity - $equity) / $peakEquity) * 100) : 0;

        $account->performanceSnapshots()->create([
            'captured_at' => now(), 'balance' => $this->numeric($info['balance'] ?? 0),
            'equity' => $this->numeric($info['equity'] ?? 0), 'profit_loss' => $this->numeric($info['profit'] ?? 0),
            'drawdown' => number_format(min($drawdown, 999999.99), 2, '.', ''),
        ]);

        $this->persistPositions($account, $snapshot['positions']);

        $this->persistTrades($account, $snapshot['trades'] ?? []);
    }

    private function persistPositions(TradingAccount $account, array $remotePositions): void
    {
        $positions = [];
        foreach ($remotePositions as $position) {
            if (!is_array($position)) {
                throw new RuntimeException('MetaApi returned an invalid open-position record.');
            }

            $externalId = trim((string) ($position['id'] ?? ''));
            $typeValue = strtoupper((string) ($position['type'] ?? ''));
            $symbol = trim((string) ($position['symbol'] ?? ''));
            $volume = $position['volume'] ?? null;

            if ($externalId === '' || mb_strlen($externalId) > 100 || $symbol === '' || mb_strlen($symbol) > 30
                || (!str_ends_with($typeValue, 'BUY') && !str_ends_with($typeValue, 'SELL'))
                || !is_numeric($volume) || !is_finite((float) $volume) || (float) $volume < 0) {
                throw new RuntimeException('MetaApi returned incomplete open-position identity or values.');
            }
            if (array_key_exists($externalId, $positions)) {
                throw new RuntimeException('MetaApi returned duplicate open-position identifiers.');
            }

            $profit = $position['unrealizedProfit'] ?? $position['profit'] ?? 0;
            if (!is_numeric($profit) || !is_finite((float) $profit)) {
                throw new RuntimeException('MetaApi returned an invalid open-position profit value.');
            }

            $positions[$externalId] = [
                'external_id' => $externalId,
                'symbol' => $symbol,
                'type' => str_ends_with($typeValue, 'BUY') ? 'BUY' : 'SELL',
                'volume' => $this->numeric($volume),
                'entry_price' => $this->nullableNumeric($position['openPrice'] ?? null),
                'current_price' => $this->nullableNumeric($position['currentPrice'] ?? null),
                'floating_profit_loss' => $this->numeric($profit),
                'opened_at' => $this->parseDate($position['time'] ?? null),
            ];
        }

        $capturedAt = now();
        $stale = $account->positions()->where('is_open', true);
        if ($positions !== []) {
            $stale->where(function ($query) use ($positions) {
                $query->whereNotIn('external_id', array_keys($positions))->orWhereNull('external_id');
            });
        }

        foreach ($stale->get() as $closedPosition) {
            $account->positionSnapshots()->create([
                'external_id' => $closedPosition->external_id,
                'symbol' => $closedPosition->symbol,
                'type' => $closedPosition->type,
                'volume' => $closedPosition->volume,
                'entry_price' => $closedPosition->entry_price,
                'current_price' => $closedPosition->current_price,
                'floating_profit_loss' => $closedPosition->floating_profit_loss,
                'opened_at' => $closedPosition->opened_at,
                'is_open' => false,
                'captured_at' => $capturedAt,
            ]);
            $closedPosition->update(['is_open' => false, 'closed_at' => $capturedAt, 'last_seen_at' => $capturedAt]);
        }

        foreach ($positions as $position) {
            $account->positions()->updateOrCreate(
                ['external_id' => $position['external_id']],
                [...$position, 'is_open' => true, 'closed_at' => null, 'last_seen_at' => $capturedAt],
            );
            $account->positionSnapshots()->create([
                ...$position,
                'is_open' => true,
                'captured_at' => $capturedAt,
            ]);
        }
    }

    private function persistTrades(TradingAccount $account, array $deals): void
    {
        $groups = [];
        foreach ($deals as $deal) {
            if (!is_array($deal) || empty($deal['positionId'])) {
                continue;
            }
            $positionId = mb_substr((string) $deal['positionId'], 0, 100);
            $entry = strtoupper((string) ($deal['entryType'] ?? ''));
            $type = strtoupper((string) ($deal['type'] ?? ''));
            if (!isset($groups[$positionId])) {
                $groups[$positionId] = ['open' => [], 'close' => []];
            }
            if ($entry === 'DEAL_ENTRY_IN' && (str_ends_with($type, 'BUY') || str_ends_with($type, 'SELL'))) {
                $groups[$positionId]['open'][] = $deal;
            } elseif (in_array($entry, ['DEAL_ENTRY_OUT', 'DEAL_ENTRY_OUT_BY', 'DEAL_ENTRY_INOUT'], true)) {
                $groups[$positionId]['close'][] = $deal;
            }
        }

        foreach ($groups as $positionId => $group) {
            if ($group['open'] === []) {
                continue;
            }
            $first = $group['open'][0];
            $openVolume = array_sum(array_map(fn ($deal) => (float) ($deal['volume'] ?? 0), $group['open']));
            $closedVolume = array_sum(array_map(fn ($deal) => (float) ($deal['volume'] ?? 0), $group['close']));
            $exitValue = array_sum(array_map(fn ($deal) => (float) ($deal['volume'] ?? 0) * (float) ($deal['price'] ?? 0), $group['close']));
            $realizedProfit = array_sum(array_map(fn ($deal) => (float) ($deal['profit'] ?? 0), $group['close']));
            $openedAt = $this->parseDate($first['time'] ?? null);
            $closedAt = $group['close'] === [] ? null : $this->parseDate($group['close'][array_key_last($group['close'])]['time'] ?? null);

            $account->trades()->updateOrCreate(
                ['external_id' => $positionId],
                [
                    'symbol' => mb_substr((string) ($first['symbol'] ?? 'UNKNOWN'), 0, 30),
                    'type' => str_ends_with(strtoupper((string) $first['type']), 'BUY') ? 'BUY' : 'SELL',
                    'volume' => $this->numeric($openVolume),
                    'entry_price' => $this->nullableNumeric($first['price'] ?? null),
                    'exit_price' => $closedVolume > 0 ? $this->numeric($exitValue / $closedVolume) : null,
                    'profit_loss' => $this->numeric($realizedProfit),
                    'opened_at' => $openedAt,
                    'closed_at' => $closedAt,
                ],
            );
        }
    }

    private function parseDate(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            return now()->parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function assertLicenseOwnerAndActive(License $license, User $user): void
    {
        if ($license->user_id !== $user->id || $license->status !== 'ACTIVE'
            || ($license->expires_at && $license->expires_at->isPast())) {
            throw ValidationException::withMessages(['license_key' => 'Licence invalide ou expirée.']);
        }
    }

    private function maskLogin(mixed $login): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($login ?? ''));
        if ($digits === '') {
            return 'Compte MT5';
        }

        return str_repeat('*', max(4, strlen($digits) - 4)).substr($digits, -4);
    }

    private function numeric(mixed $value): string
    {
        return is_numeric($value) && is_finite((float) $value) ? (string) $value : '0';
    }

    private function nullableNumeric(mixed $value): ?string
    {
        return is_numeric($value) && is_finite((float) $value) ? (string) $value : null;
    }
}
