<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql', 'url' => env('DB_URL'), 'host' => env('DB_HOST', 'db'),
            'port' => env('DB_PORT', '3306'), 'database' => env('DB_DATABASE', 'orbita'),
            'username' => env('DB_USERNAME', 'orbita'), 'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''), 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '', 'prefix_indexes' => true, 'strict' => true, 'engine' => 'InnoDB',
            'options' => extension_loaded('pdo_mysql') && env('MYSQL_ATTR_SSL_CA') ? [
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
            ] : [],
        ],
        'sqlite' => ['driver' => 'sqlite', 'database' => env('DB_DATABASE', ':memory:'), 'prefix' => '', 'foreign_key_constraints' => true],
    ],
    'migrations' => ['table' => 'orbita_migrations', 'update_date_on_publish' => true],
    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),
        'options' => ['prefix' => env('REDIS_PREFIX', 'orbita:')],
        'default' => ['host' => env('REDIS_HOST', 'redis'), 'password' => env('REDIS_PASSWORD'), 'port' => env('REDIS_PORT', 6379), 'database' => env('REDIS_DB', 0)],
        'cache' => ['host' => env('REDIS_HOST', 'redis'), 'password' => env('REDIS_PASSWORD'), 'port' => env('REDIS_PORT', 6379), 'database' => env('REDIS_CACHE_DB', 1)],
    ],
];
