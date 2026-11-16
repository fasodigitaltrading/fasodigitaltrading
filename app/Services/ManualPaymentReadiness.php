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

        if (config('payments.manual_proof_disk') === 's3'
            && !class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class)) {
            return false;
        }
        if (!in_array(config('payments.manual_proof_disk'), ['local', 's3'], true)) {
            return false;
        }
        if (config('payments.manual_proof_disk') === 's3') {
            $s3 = (array) config('filesystems.disks.s3', []);
            $endpoint = parse_url((string) ($s3['endpoint'] ?? ''));
            if (trim((string) ($s3['bucket'] ?? '')) === ''
                || trim((string) ($s3['region'] ?? '')) === ''
                || trim((string) ($s3['key'] ?? '')) === ''
                || trim((string) ($s3['secret'] ?? '')) === ''
                || !is_array($endpoint)
                || strtolower((string) ($endpoint['scheme'] ?? '')) !== 'https'
                || trim((string) ($endpoint['host'] ?? '')) === '') {
                return false;
            }
        }

        $scheme = strtolower((string) parse_url((string) config('app.url'), PHP_URL_SCHEME));

        return $scheme === 'https'
            && config('session.secure') === true
            && config('session.encrypt') === true
            && config('app.debug') === false
            && config('payments.manual_live_verified') === true
            && config('payments.manual_proof_storage_persistent') === true
            && config('payments.manual_proof_disk') !== 'public'
            && trim((string) config('legal.operator_name')) !== ''
            && trim((string) config('legal.operator_address')) !== ''
            && filter_var((string) config('legal.operator_email'), FILTER_VALIDATE_EMAIL) !== false
            && config('legal.disclosures_approved') === true;
    }
}
