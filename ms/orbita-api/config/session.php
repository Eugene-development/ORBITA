<?php
return [
    'driver' => env('SESSION_DRIVER', 'redis'), 'lifetime' => 120, 'expire_on_close' => false, 'encrypt' => true,
    'files' => storage_path('framework/sessions'), 'connection' => env('SESSION_CONNECTION', 'default'),
    'table' => 'orbita_sessions', 'store' => null, 'lottery' => [2, 100], 'cookie' => 'orbita_session',
    'path' => '/', 'domain' => env('SESSION_DOMAIN'), 'secure' => env('SESSION_SECURE_COOKIE', true),
    'http_only' => true, 'same_site' => 'lax', 'partitioned' => false,
];
