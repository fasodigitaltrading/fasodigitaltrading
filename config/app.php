<?php
return [
    'name' => env('APP_NAME', 'FASO DIGITAL TRADING'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => env('APP_LOCALE', 'fr'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'fr'),
    'key' => env('APP_KEY'),
    'previous_keys' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
    ))),
    'cipher' => 'AES-256-CBC',
];
