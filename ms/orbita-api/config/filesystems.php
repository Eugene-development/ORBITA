<?php
return ['default' => 'local', 'disks' => [
    'local' => ['driver' => 'local', 'root' => storage_path('app/public'), 'throw' => true],
    's3' => ['driver' => 's3', 'key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'), 'bucket' => env('AWS_BUCKET'), 'endpoint' => env('AWS_ENDPOINT'),
        'url' => env('MEDIA_BASE_URL'), 'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'throw' => true],
]];
