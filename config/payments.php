<?php

return [
    'default_provider' => env('PAYMENT_PROVIDER', ''),
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
    // Enable only after confirming the destination accounts and live receipt process.
    'manual_live_verified' => filter_var(env('PAYMENTS_MANUAL_LIVE_VERIFIED', false), FILTER_VALIDATE_BOOL),
    'manual_methods' => [
        'orange_money' => [
            'label' => 'Orange Money',
            'recipient' => '0022667902537',
            'currency' => 'XOF',
        ],
        'moov_money' => [
            'label' => 'Moov Money',
            'recipient' => '22670497029',
            'currency' => 'XOF',
        ],
        'wave_money' => [
            'label' => 'Wave Money',
            'recipient' => '22670497029',
            'currency' => 'XOF',
        ],
        'sank_money' => [
            'label' => 'Sank Money',
            'recipient' => '22670497029',
            'currency' => 'XOF',
        ],
        'usdt_bep20' => [
            'label' => 'USDT · réseau BEP20',
            'recipient' => '0xF9C6Bb6b72091Ddc8F5DcCc2D90EBc73870D042D',
            'currency' => 'USDT',
        ],
    ],
];
