<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class MigrationOwnershipTest extends TestCase
{
    public function test_api_checks_required_migrations_without_writing_the_journal(): void
    {
        $before = DB::table('orbita_migrations')->get()->toJson();
        $this->artisan('orbita:migrations-check')->assertSuccessful();
        $this->assertSame($before, DB::table('orbita_migrations')->get()->toJson());
        DB::table('orbita_migrations')->where('migration', '2026_10_04_000200_create_commerce_tables')->delete();
        $this->artisan('orbita:migrations-check')->assertFailed();
    }

    public function test_api_cannot_migrate_or_seed_outside_isolated_tests(): void
    {
        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->rerouteSymfonyCommandEvents();
        $kernel->setArtisan(null);
        $this->app['env'] = 'production';
        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->fail('API applied migrations');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Run migrations locally from ms/orbita-db', $error->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
