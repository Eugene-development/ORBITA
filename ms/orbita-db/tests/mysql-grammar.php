<?php
require __DIR__.'/../vendor/autoload.php';
$container = new Illuminate\Container\Container;
Illuminate\Support\Facades\Facade::setFacadeApplication($container);
$conn = new Illuminate\Database\MySqlConnection(function () { throw new RuntimeException('Real database connections are forbidden in grammar test'); }, 'orbita_recovery_test', '', ['version' => '8.0.36', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB']);
Illuminate\Support\Facades\Schema::swap($conn->getSchemaBuilder());
$migration = require __DIR__.'/../database/recovery/2026_10_14_000000_reconstruct_legacy_schema.php';
$queries = $conn->pretend(fn () => $migration->up());
$creates = array_filter($queries, fn ($q) => str_starts_with($q['query'], 'create table'));
if (count($creates) !== 50) { throw new RuntimeException('Expected 50 MySQL CREATE statements'); }
echo 'PASS: MySQL grammar compiles 50 CREATE TABLE statements without a database connection.'.PHP_EOL;
