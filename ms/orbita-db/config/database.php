<?php

declare(strict_types=1);

$ca = (string) env('MYSQL_ATTR_SSL_CA', 'cert/mysql-ca.pem');
$caPath = $ca === '' || str_starts_with($ca, '/') ? $ca : base_path($ca);

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql', 'host' => env('DB_HOST', 'mysql'), 'port' => env('DB_PORT', '13306'),
            'database' => env('DB_DATABASE', ''), 'username' => env('DB_USERNAME', ''),
            'password' => env('DB_PASSWORD', ''), 'unix_socket' => '',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
            'prefix_indexes' => true, 'strict' => true, 'engine' => 'InnoDB',
            'options' => extension_loaded('pdo_mysql') && $caPath !== '' ? [
                PDO::MYSQL_ATTR_SSL_CA => $caPath,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
            ] : [],
        ],
        'sqlite' => ['driver' => 'sqlite', 'database' => env('DB_DATABASE', ':memory:'), 'prefix' => '', 'foreign_key_constraints' => true],
    ],
    'migrations' => ['table' => 'orbita_migrations', 'update_date_on_publish' => true],
];
