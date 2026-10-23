<?php
namespace Tests;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyFixtures;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['lighthouse.schema_cache.enable' => false, 'lighthouse.query_cache.enable' => false, 'lighthouse.security.disable_introspection' => 0, 'session.secure' => false, 'logging.channels.stderr.level' => 'critical']);
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $name = DB::connection()->getDatabaseName();
            if (getenv('ORBITA_MYSQL_TESTS') !== '1' || ! str_starts_with($name, 'orbita_test_')) {
                throw new \RuntimeException('MySQL fixture tests require ORBITA_MYSQL_TESTS=1 and a database named orbita_test_*.');
            }
        }
        // Synthetic fixtures explicitly load canonical migrations; runtime API never does.
        $this->app->make('migrator')->path(base_path('../orbita-db/database/migrations'));
        $this->artisan('migrate:fresh', ['--force' => true, '--drop-views' => true])->assertExitCode(0);
        LegacyFixtures::create();
    }
    protected function fixtureProduct(int $id = 1, string $price = '100.00', string $unit = 'шт', string $key = '1', bool $active = true, ?int $priceId = null): void
    {
        LegacyFixtures::product($id, $price, $unit, $key, $active, $priceId);
    }
    protected function approveSyntheticCatalog(): void { LegacyFixtures::approve(); }
}
