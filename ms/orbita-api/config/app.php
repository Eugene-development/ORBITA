<?php
return [
    'name' => env('APP_NAME', 'ORBITA'), 'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false), 'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'UTC', 'locale' => 'ru', 'fallback_locale' => 'en', 'faker_locale' => 'ru_RU',
    'cipher' => 'AES-256-CBC', 'key' => env('APP_KEY'), 'previous_keys' => [],
    'maintenance' => ['driver' => 'file'],
];
