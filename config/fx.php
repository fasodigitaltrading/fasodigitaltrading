<?php

return [
    'provider' => 'bceao-reference',
    // Retained for legacy manual-transfer orders; the public catalogue uses
    // the official BCEAO reference-rate service and never this fixed value.
    'fixed_usd_xof' => '600.0000',
    'refresh_seconds' => (int) env('FX_RATE_REFRESH_SECONDS', 60),
    'max_rate_age_seconds' => (int) env('FX_RATE_MAX_AGE_SECONDS', 180),
    'currencyapi' => [
        'endpoint' => env('CURRENCYAPI_ENDPOINT', 'https://api.currencyapi.com/v3/rate'),
        'api_key' => env('CURRENCYAPI_API_KEY'),
    ],
    'quote_valid_minutes' => (int) env('FX_QUOTE_VALID_MINUTES', 5),

    // BCEAO publishes the daily official reference table; stale rates cannot
    // be used to launch a new payment.
    'bceao_reference_url' => env('FX_BCEAO_REFERENCE_URL', 'https://www.bceao.int/fr/cours/cours-de-reference-des-principales-devises-contre-Franc-CFA'),
    'refresh_minutes' => (int) env('FX_RATE_REFRESH_MINUTES', 360),
    'max_rate_age_days' => (int) env('FX_RATE_MAX_AGE_DAYS', 7),
];
