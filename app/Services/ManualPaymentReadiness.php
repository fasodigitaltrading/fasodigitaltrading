<?php

namespace App\Services;

final class ManualPaymentReadiness
{
    public function isReady(): bool
    {
        $bindingKey = (string) config('license.binding_key');
        $appKey = (string) config('app.key');
        if (strlen($bindingKey) < 32 || strlen($appKey) < 20 || hash_equals($bindingKey, $appKey)) {
            return false;
        }

        if (config('app.env') !== 'production') {
            return true;
        }

        $scheme = strtolower((string) parse_url((string) config('app.url'), PHP_URL_SCHEME));

        return $scheme === 'https'
            && config('session.secure') === true
            && config('session.encrypt') === true
            && config('app.debug') === false
            && config('payments.manual_live_verified') === true
            && trim((string) config('legal.operator_name')) !== ''
            && trim((string) config('legal.operator_address')) !== ''
            && filter_var((string) config('legal.operator_email'), FILTER_VALIDATE_EMAIL) !== false
            && config('legal.disclosures_approved') === true;
    }
}
