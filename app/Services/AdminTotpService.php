<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AdminTotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function provisioningUri(string $email, string $secret): string
    {
        $issuer = 'FASO DIGITAL TRADING';
        $label = rawurlencode($issuer.':'.$email);

        return 'otpauth://totp/'.$label.'?secret='.$secret
            .'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        return $this->matchingCounter($secret, $code, $timestamp) !== null;
    }

    public function verifyAndConsume(User $user, string $code, ?int $timestamp = null): bool
    {
        return DB::transaction(function () use ($user, $code, $timestamp): bool {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            if (!$locked || !$locked->admin_totp_secret) {
                return false;
            }

            $counter = $this->matchingCounter($locked->admin_totp_secret, $code, $timestamp);
            if ($counter === null || ($locked->admin_totp_last_step !== null
                && $counter <= (int) $locked->admin_totp_last_step)) {
                return false;
            }

            $locked->forceFill(['admin_totp_last_step' => $counter])->save();

            return true;
        });
    }

    private function matchingCounter(string $secret, string $code, ?int $timestamp): ?int
    {
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $counter = intdiv($timestamp ?? time(), 30);
        for ($offset = -1; $offset <= 1; $offset++) {
            $candidate = $counter + $offset;
            if ($candidate >= 0 && hash_equals($this->codeAt($secret, $candidate * 30), $code)) {
                return $candidate;
            }
        }

        return null;
    }

    public function codeAt(string $secret, int $timestamp): string
    {
        $counter = intdiv($timestamp, 30);
        $message = pack('N2', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff);
        $digest = hash_hmac('sha1', $message, $this->base32Decode($secret), true);
        $offset = ord($digest[19]) & 0x0f;
        $value = unpack('N', substr($digest, $offset, 4))[1] & 0x7fffffff;

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $encoded .= self::ALPHABET[bindec($chunk)];
        }

        return $encoded;
    }

    private function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(rtrim($encoded, '='));
        $bits = '';
        foreach (str_split($encoded) as $character) {
            $value = strpos(self::ALPHABET, $character);
            if ($value === false) {
                throw new \InvalidArgumentException('Invalid authenticator secret.');
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split(substr($bits, 0, intdiv(strlen($bits), 8) * 8), 8) as $byte) {
            $binary .= chr(bindec($byte));
        }

        return $binary;
    }
}
