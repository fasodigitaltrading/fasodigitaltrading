<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LicenseService
{
    public function validate(string $key, string $accountId, string $server): array
    {
        $accountHash = $this->accountHash($accountId, $server);

        return DB::transaction(function () use ($key, $accountHash) {
            $license = License::where('license_key_hash', hash('sha256', $key))->lockForUpdate()->first();
            if (!$license) {
                return ['valid' => false, 'status' => 'NOT_FOUND', 'account_allowed' => false];
            }

            return $this->evaluateAndBind($license, $accountHash);
        });
    }

    public function bindAccount(License $license, string $accountId, string $server): array
    {
        $accountHash = $this->accountHash($accountId, $server);

        return DB::transaction(function () use ($license, $accountHash) {
            $lockedLicense = License::whereKey($license->id)->lockForUpdate()->first();
            if (!$lockedLicense) {
                return ['valid' => false, 'status' => 'NOT_FOUND', 'account_allowed' => false];
            }

            return $this->evaluateAndBind($lockedLicense, $accountHash);
        });
    }

    private function accountHash(string $accountId, string $server): string
    {
        $key = (string) config('license.binding_key');
        $login = trim($accountId);
        $serverName = mb_strtolower(trim($server));

        if (strlen($key) < 32) {
            throw new RuntimeException('The license binding key is not configured.');
        }
        if (!preg_match('/^[0-9]{1,20}$/', $login) || $serverName === '' || strlen($serverName) > 150) {
            throw new RuntimeException('A valid MT5 login and server name are required.');
        }

        return hash_hmac('sha256', $serverName."\0".$login, $key);
    }

    private function evaluateAndBind(License $license, string $accountHash): array
    {
        if ($license->status === 'ACTIVE' && $license->expires_at?->isPast()) {
            $license->status = 'EXPIRED';
            $license->save();
        }

        $valid = $license->status === 'ACTIVE' && (!$license->expires_at || $license->expires_at->isFuture());
        if ($valid && $license->subscription_id && !$license->subscription?->isOperationallyActive()) {
            $valid = false;
        }
        $now = now();
        $license->last_validation_at = $now;
        $license->save();

        $accountAllowed = false;
        $status = $license->status;
        if ($valid) {
            $binding = $license->accountBindings()->where('account_hash', $accountHash)->lockForUpdate()->first();

            if ($binding) {
                $binding->update(['last_validated_at' => $now]);
                $accountAllowed = true;
            } elseif ($license->accountBindings()->count() < $license->max_accounts) {
                $license->accountBindings()->create([
                    'account_hash' => $accountHash,
                    'first_validated_at' => $now,
                    'last_validated_at' => $now,
                ]);
                $accountAllowed = true;
            } else {
                $status = 'ACCOUNT_LIMIT';
            }
        }

        return [
            'valid' => $valid,
            'status' => $status,
            'expires_at' => $license->expires_at?->toIso8601String(),
            'account_allowed' => $accountAllowed,
        ];
    }
}
