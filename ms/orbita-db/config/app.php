<?php
return [
    'name' => 'ORBITA DB', 'env' => env('APP_ENV', 'production'), 'debug' => false,
    'url' => 'http://localhost', 'timezone' => 'UTC', 'locale' => 'ru', 'fallback_locale' => 'en',
    'key' => env('APP_KEY'), 'cipher' => 'AES-256-CBC', 'maintenance' => ['driver' => 'file'],
];
