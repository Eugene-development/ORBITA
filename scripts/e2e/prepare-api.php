<?php
declare(strict_types=1);
// Only the dedicated disposable SQLite database is prepared by this browser fixture.
$root = dirname(__DIR__, 2);
$database = $root.'/runtime/e2e.sqlite';
if (getenv('ORBITA_E2E') !== '1' || getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== $database) {
    throw new RuntimeException('Browser fixture refuses any other database/environment.');
}
if (! is_dir(dirname($database))) { mkdir(dirname($database), 0700, true); }
file_put_contents($database, '');
require $root.'/ms/orbita-api/vendor/autoload.php';
$app = require $root.'/ms/orbita-api/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
Tests\Support\LegacyFixtures::create();
Tests\Support\LegacyFixtures::product(1, '449.64', 'шт');
Tests\Support\LegacyFixtures::product(2, '19000.00', 'м3');
Tests\Support\LegacyFixtures::product(3, '200.00', 'шт', '2');
Tests\Support\LegacyFixtures::product(4, '50.00', 'шт', '1', false);
Tests\Support\LegacyFixtures::approve();
App\Modules\Auth\Employee::create(['name' => 'Тестовый редактор', 'email' => 'editor@example.test', 'password' => 'Orbita-test-2026!', 'role' => 'editor', 'is_active' => true]);
$report = $app->make(App\Modules\Schema\SchemaInspector::class)->inspect();
echo json_encode(['fingerprint' => $report['fingerprint']], JSON_THROW_ON_ERROR);
