<?php

return [
    'provider' => env('MT5_CONNECTOR'),
    'metaapi' => [
        'base_url' => env('METAAPI_REST_BASE_URL'),
        'timeout_seconds' => (int) env('METAAPI_TIMEOUT_SECONDS', 15),
        'history_days' => (int) env('METAAPI_HISTORY_DAYS', 30),
    ],
];
