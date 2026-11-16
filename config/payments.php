<?php

return [
    // New checkouts are crypto-only and manually verified. Keep PayDunya registered
    // solely to reconcile historical invoices and callbacks; never start new ones.
    'default_provider' => '',
    'providers' => [
        'paydunya' => App\Services\Payments\PayDunyaProvider::class,
    ],
    'paydunya' => [
        'master_key' => env('PAYDUNYA_MASTER_KEY'),
        'private_key' => env('PAYDUNYA_PRIVATE_KEY'),
        'token' => env('PAYDUNYA_TOKEN'),
        'currency' => strtoupper(env('PAYDUNYA_CURRENCY', 'XOF')),
        'timeout_seconds' => (int) env('PAYDUNYA_TIMEOUT_SECONDS', 15),
        // Operational attestation only. It must not prevent the first live invoice;
        // the subscription still requires the signed callback plus server confirmation.
        'live_verified' => filter_var(env('PAYDUNYA_LIVE_VERIFIED', false), FILTER_VALIDATE_BOOL),
    ],
    'currency' => env('PAYMENTS_CURRENCY', 'XOF'),
    'success_url' => env('PAYMENTS_SUCCESS_URL', env('APP_URL', 'http://localhost').'/subscriptions?checkout=success'),
    'cancel_url' => env('PAYMENTS_CANCEL_URL', env('APP_URL', 'http://localhost').'/subscriptions?checkout=cancelled'),
    'manual_proof_root' => storage_path('app/private/manual-payment-proofs'),
    'manual_proof_disk' => env('PAYMENTS_MANUAL_PROOF_DISK', 'local'),
    'manual_proof_storage_persistent' => filter_var(env('PAYMENTS_MANUAL_PROOF_STORAGE_PERSISTENT', false), FILTER_VALIDATE_BOOL),
    'crypto' => [
        'network' => 'BNB Smart Chain (BEP20)',
        'chain_id' => 56,
        'symbol' => 'USDT · Binance-Peg BSC-USD',
        'token_contract' => '0x55d398326f99059ff775485246999027b3197955',
        'minimum_confirmations' => (int) env('BSC_MINIMUM_CONFIRMATIONS', 15),
        'explorer_url' => 'https://bscscan.com',
    ],
    // Enable only after confirming the destination accounts and live receipt process.
    'manual_live_verified' => filter_var(env('PAYMENTS_MANUAL_LIVE_VERIFIED', false), FILTER_VALIDATE_BOOL),
    'manual_methods' => [
        'usdt_bep20' => [
            'label' => 'Binance-Peg BSC-USD · BEP20',
            'recipient' => '0xF9C6Bb6b72091Ddc8F5DcCc2D90EBc73870D042D',
            'currency' => 'USDT',
        ],
    ],
];
