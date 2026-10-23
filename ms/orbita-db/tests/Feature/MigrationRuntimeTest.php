<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Migrations\CatalogFingerprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class MigrationRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    public function test_default_service_migrations_preserve_existing_catalogue_and_can_run_twice(): void
    {
        Schema::create('product', fn (Blueprint $table) => $table->increments('id'));
        DB::table('product')->insert(['id' => 7]);
        $fingerprint = new CatalogFingerprint;
        $before = $fingerprint->capture(DB::connection());
        $this->artisan('orbita:service-migrate', ['--force' => true])->assertSuccessful();
        $this->artisan('orbita:service-migrate', ['--force' => true])->assertSuccessful();
        $this->assertSame($before, $fingerprint->capture(DB::connection()));
        $this->assertSame(7, DB::table('product')->value('id'));
        $this->assertTrue(Schema::hasTable('orbita_orders'));
        $this->assertFalse(Schema::hasTable('category'));
        $this->assertSame(3, DB::table('orbita_migrations')->count());
    }

    public function test_recovery_is_an_explicit_path_and_uses_the_separate_migration_journal(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('migrations'));
        $this->artisan('migrate', ['--path' => 'database/recovery', '--force' => true])->assertSuccessful();
        $manifest = json_decode(file_get_contents(database_path('recovery-manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach (array_keys($manifest) as $table) $this->assertTrue(Schema::hasTable($table));
        $this->assertSame(0, DB::table('migrations')->count());
        $this->assertSame(4, DB::table('orbita_migrations')->count());
        $this->assertSame(0, DB::table('product')->count());
        $this->assertSame(0, DB::table('image')->count());
    }

    public function test_fingerprint_detects_catalogue_ddl_but_ignores_service_tables(): void
    {
        $fingerprint = new CatalogFingerprint;
        $before = $fingerprint->capture(DB::connection());
        Schema::create('orbita_sentinel', fn (Blueprint $table) => $table->id());
        $this->assertSame($before, $fingerprint->capture(DB::connection()));
        Schema::create('product', fn (Blueprint $table) => $table->id());
        $this->assertNotSame($before, $fingerprint->capture(DB::connection()));
    }

    public function test_tunnel_configuration_resolves_relative_ca_and_verifies_server_identity(): void
    {
        if (! extension_loaded('pdo_mysql')) $this->markTestSkipped('PDO MySQL required for TLS constants.');
        $settings = config('database.connections.mysql');
        $this->assertSame('mysql', $settings['host']);
        $this->assertSame('13306', $settings['port']);
        $this->assertSame(base_path('cert/mysql-ca.pem'), $settings['options'][\PDO::MYSQL_ATTR_SSL_CA]);
        $this->assertTrue($settings['options'][\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]);
        $this->assertArrayNotHasKey('url', $settings);
    }

    public function test_destructive_commands_are_blocked_outside_tests(): void
    {
        Schema::create('product', fn (Blueprint $table) => $table->id());
        // Laravel disables Symfony command events in unit tests; enable the CLI path.
        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->rerouteSymfonyCommandEvents();
        $kernel->setArtisan(null);
        $this->app['env'] = 'production';
        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            $this->fail('Destructive command was accepted');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('clearing and seeding are disabled', $error->getMessage());
            $this->assertTrue(Schema::hasTable('product'));
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
