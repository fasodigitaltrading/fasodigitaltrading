<?php

return [
    'default' => env('MAIL_MAILER', 'smtp'),
    'mailers' => [
        'log' => ['transport' => 'log'],
        'array' => ['transport' => 'array'],
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME', (int) env('MAIL_PORT', 587) === 465 ? 'smtps' : 'smtp'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => (int) env('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 15,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
            'auto_tls' => true,
            'require_tls' => true,
        ],
    ],
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        'name' => env('MAIL_FROM_NAME', 'FASO DIGITAL TRADING'),
    ],
];
