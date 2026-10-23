<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CatalogInspectCommandTest extends TestCase
{
    public function test_release_check_accepts_compatible_metadata_without_approving_writes(): void
    {
        $this->artisan('orbita:catalog-inspect', ['--check' => true])->assertSuccessful();
        $this->assertDatabaseCount('orbita_schema_approvals', 0);
        $this->assertFalse(config('orbita.catalog_writes_enabled'));
    }

    public function test_release_check_rejects_missing_legacy_tables_while_inspection_still_reports_them(): void
    {
        Schema::drop('product');
        $this->artisan('orbita:catalog-inspect', ['--check' => true])->assertFailed();
        $this->artisan('orbita:catalog-inspect')->assertSuccessful();
        $this->assertDatabaseCount('orbita_schema_approvals', 0);
    }
}
