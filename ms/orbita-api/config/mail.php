<?php
return ['default' => env('MAIL_MAILER', 'smtp'), 'mailers' => [
    'smtp' => ['transport' => 'smtp', 'scheme' => env('MAIL_SCHEME'), 'host' => env('MAIL_HOST'), 'port' => env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME'), 'password' => env('MAIL_PASSWORD'), 'timeout' => 15],
    'array' => ['transport' => 'array'], 'log' => ['transport' => 'log'],
], 'from' => ['address' => env('MAIL_FROM_ADDRESS', 'noreply@example.test'), 'name' => 'ORBITA']];
