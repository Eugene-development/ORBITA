<?php
return ['paths' => ['graphql', 'auth/*', 'api/*'], 'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_origins' => array_filter(explode(',', env('FRONTEND_ORIGINS', ''))), 'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept', 'X-XSRF-TOKEN', 'X-Requested-With'], 'exposed_headers' => [], 'max_age' => 3600, 'supports_credentials' => true];
