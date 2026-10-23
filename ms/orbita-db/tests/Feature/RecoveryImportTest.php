<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Recovery\MySqlCsvReader;
use App\Modules\Recovery\RecoveryDataset;
use App\Modules\Recovery\RecoveryException;
use App\Modules\Recovery\RecoveryImporter;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\MySqlConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/** Writes only to a fresh SQLite :memory: database; all CSV records are synthetic. */
final class RecoveryImportTest extends TestCase
{
    private string $directory;
    private array $manifest;
    private array $rows = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'orbita.catalog_writes_enabled' => false]);
        DB::purge('sqlite');
        (require base_path('database/recovery/2026_10_14_000000_reconstruct_legacy_schema.php'))->up();
        $this->directory = sys_get_temp_dir().'/orbita-recovery-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        $this->manifest = json_decode(file_get_contents(base_path('database/recovery-manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($this->manifest as $table => &$spec) {
            $spec['rows'] = 0;
            if ($spec['csv'] !== 'missing') {
                $spec['csv'] = 'empty';
                file_put_contents($this->directory.'/'.$table.'.csv', '');
            }
        }
        unset($spec);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') as $path) {
            unlink($path);
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    private function add(string $table, array $override = []): void
    {
        $defaults = ['id' => (string) (count($this->rows[$table] ?? []) + 1), 'key' => '1', 'is_active' => '1',
            'value' => $table === 'price' ? '12.50' : 'Synthetic value', 'slug' => 'synthetic', 'hash' => 'synthetic.webp', 'alt' => '',
            'migration' => 'synthetic_migration', 'batch' => '1', 'email' => 'synthetic@example.invalid', 'token' => 'synthetic-token-do-not-log'];
        $row = [];
        foreach ($this->manifest[$table]['columns'] as $column) {
            $row[$column] = array_key_exists($column, $override) ? $override[$column] : ($defaults[$column] ?? null);
        }
        $this->rows[$table][] = $row;
        $this->manifest[$table]['rows'] = count($this->rows[$table]);
        $this->manifest[$table]['csv'] = 'nonempty';
        file_put_contents($this->directory.'/'.$table.'.csv', implode('', array_map(fn (array $item): string => $this->encode($item), $this->rows[$table])));
    }

    private function encode(array $row): string
    {
        return implode(',', array_map(static fn (?string $value): string => $value === null ? '\\N' : '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t', "\0" => '\\0']).'"', array_values($row)))."\n";
    }

    private function dataset(): RecoveryDataset
    {
        return RecoveryDataset::load($this->directory, $this->manifest, new MySqlCsvReader);
    }

    public function test_reader_distinguishes_null_empty_and_literal_backslashes_without_double_decoding(): void
    {
        $bytes = "\xEF\xBB\xBF".<<<'CSV'
\N,"\N","\\N","","quote: \"yes\"","slash: \\n","line\nnext","tab\tend","zero\0end","double ""quote""",last,
CSV;
        $rows = iterator_to_array((new MySqlCsvReader)->rows($bytes."\r\n", 'product'));
        $this->assertSame([null, '\\N', '\\N', '', 'quote: "yes"', 'slash: \\n', "line\nnext", "tab\tend", "zero\0end", 'double "quote"', 'last', ''], $rows[1]);
        $this->assertSame([["actual\nmultiline", 'x']], array_values(iterator_to_array((new MySqlCsvReader)->rows("\"actual\nmultiline\",x", 'product'))));
        $this->assertSame([], iterator_to_array((new MySqlCsvReader)->rows('', 'product')));
    }

    public static function malformedCsv(): array
    {
        return [['"unclosed'], ['"closed"suffix'], ['unquoted"quote'], ['trailing\\'], ["\xFF"]];
    }

    #[DataProvider('malformedCsv')]
    public function test_malformed_csv_is_rejected_without_values_in_the_error(string $bytes): void
    {
        $this->expectException(RecoveryException::class);
        iterator_to_array((new MySqlCsvReader)->rows($bytes, 'product'));
    }

    public function test_snapshot_preserves_ids_keys_decimals_and_reports_relationship_problems(): void
    {
        $this->add('category', ['id' => '7', 'parentable_type' => 'menu', 'parentable_id' => '99']);
        $this->add('product', ['id' => '500', 'key' => '2', 'parentable_type' => 'category', 'parentable_id' => '7', 'value' => 'Text, "quoted" \\N']);
        $this->add('price', ['parentable_type' => 'product', 'parentable_id' => '500', 'key' => '2', 'value' => '400222432.25']);
        $this->add('price', ['parentable_type' => 'product', 'parentable_id' => '500', 'key' => '2']);
        $this->add('password_resets');
        $this->add('migrations');
        $dataset = $this->dataset();
        $this->assertSame('400222432.25', $dataset->records['price'][0]['value']);
        $this->assertSame('Text, "quoted" \\N', $dataset->records['product'][0]['value']);
        $this->assertSame('501', $dataset->report['tables']['product']['next_id']);
        $this->assertSame(1, $dataset->report['relationship_issues']['category']['missing_parent']);
        $this->assertSame(1, $dataset->report['relationship_issues']['product']['cross_key_parent']);
        $this->assertSame(1, $dataset->report['relationship_issues']['price']['duplicate_morph_groups']);
        $this->assertSame(1, $dataset->report['skipped_rows']);
        $this->assertSame([], $dataset->records['password_resets']);
        $this->assertStringNotContainsString('synthetic-token-do-not-log', json_encode($dataset->report));
    }

    public function test_duplicate_ids_fail_even_for_tables_without_a_project_key(): void
    {
        $this->add('migrations', ['id' => '10']);
        $this->add('migrations', ['id' => '10']);
        $dataset = $this->dataset();
        $this->assertSame([['id' => '10', 'first_record' => 1, 'duplicate_record' => 2]], $dataset->report['id_conflicts']['migrations']);
        $this->assertFalse($dataset->report['importable']);
        $this->expectExceptionMessage('Unresolved duplicate IDs');
        (new RecoveryImporter)->import(DB::connection(), $dataset, ':memory:', true);
    }

    public function test_explicit_image_reassignment_preserves_every_record_and_reports_deterministic_new_ids(): void
    {
        $this->add('image', ['id' => '10', 'hash' => 'first.webp']);
        $this->add('image', ['id' => '10', 'hash' => 'second.webp']);
        $this->add('image', ['id' => '99', 'hash' => 'maximum.webp']);
        $this->add('image', ['id' => '10', 'hash' => 'third.webp']);
        $path = $this->directory.'/image.csv';
        $hash = hash_file('sha256', $path);
        $dataset = RecoveryDataset::load($this->directory, $this->manifest, new MySqlCsvReader, true);
        $this->assertTrue($dataset->report['importable']);
        $this->assertSame(['10', '100', '99', '101'], array_column($dataset->records['image'], 'id'));
        $this->assertSame(['100', '101'], array_column($dataset->report['id_reassignments'], 'new_id'));
        $this->assertSame('102', $dataset->report['tables']['image']['next_id']);
        $this->assertSame($hash, hash_file('sha256', $path));
        (new RecoveryImporter)->import(DB::connection(), $dataset, ':memory:', true);
        $this->assertSame(4, DB::table('image')->count());
        $this->assertSame('second.webp', DB::table('image')->where('id', 100)->value('hash'));
        $this->assertSame(102, DB::table('image')->insertGetId(['hash' => 'next.webp', 'alt' => '']));
    }

    public function test_image_reassignment_refuses_ambiguous_incoming_links_and_other_id_collisions(): void
    {
        $this->add('image', ['id' => '10']);
        $this->add('image', ['id' => '10']);
        $this->add('product', ['parentable_type' => 'image', 'parentable_id' => '10']);
        $this->expectExceptionMessage('reassignment is ambiguous');
        RecoveryDataset::load($this->directory, $this->manifest, new MySqlCsvReader, true);
    }

    public function test_image_option_does_not_resolve_other_tables_duplicate_ids(): void
    {
        $this->add('product', ['id' => '10']);
        $this->add('product', ['id' => '10']);
        $dataset = RecoveryDataset::load($this->directory, $this->manifest, new MySqlCsvReader, true);
        $this->assertFalse($dataset->report['importable']);
        $this->expectExceptionMessage('Unresolved duplicate IDs');
        (new RecoveryImporter)->import(DB::connection(), $dataset, ':memory:', true);
    }

    public function test_new_image_ids_do_not_turn_existing_orphan_links_into_links_to_unrelated_images(): void
    {
        $this->add('image', ['id' => '10']);
        $this->add('image', ['id' => '10']);
        $this->add('product', ['parentable_type' => 'image', 'parentable_id' => '11']);
        $dataset = RecoveryDataset::load($this->directory, $this->manifest, new MySqlCsvReader, true);
        $this->assertSame('12', $dataset->records['image'][1]['id']);
        $this->assertSame(1, $dataset->report['relationship_issues']['product']['missing_parent']);
    }

    public function test_missing_known_file_and_unknown_file_are_rejected(): void
    {
        unlink($this->directory.'/product.csv');
        try {
            $this->dataset();
            $this->fail('Missing file accepted');
        } catch (RecoveryException $error) {
            $this->assertStringContainsString('required CSV', $error->getMessage());
        }
        file_put_contents($this->directory.'/product.csv', '');
        file_put_contents($this->directory.'/unreviewed.csv', 'secret');
        $this->expectExceptionMessage('Unknown CSV');
        $this->dataset();
    }

    public function test_truncated_snapshot_is_rejected(): void
    {
        $this->add('product');
        file_put_contents($this->directory.'/product.csv', '');
        $this->expectExceptionMessage('row count differs');
        $this->dataset();
    }

    public static function invalidRecords(): array
    {
        return [['id', '0'], ['id', '4294967295'], ['id', '1e2'], ['is_active', 'yes'], ['key', ''],
            ['created_at', '2026-02-30 10:00:00'], ['parentable_id', '-1']];
    }

    #[DataProvider('invalidRecords')]
    public function test_invalid_scalar_values_are_rejected(string $column, string $value): void
    {
        $this->add('product', [$column => $value]);
        $this->expectException(RecoveryException::class);
        $this->dataset();
    }

    public function test_decimal_values_are_never_rounded_or_parsed_as_floats(): void
    {
        $this->add('price', ['value' => '1.001']);
        $this->expectExceptionMessage('invalid exact decimal');
        $this->dataset();
    }

    public function test_import_is_atomic_preserves_legacy_journal_and_does_not_enable_service_writers(): void
    {
        $this->add('product', ['id' => '500', 'key' => '2', 'value' => 'Preserved']);
        $this->add('price', ['id' => '900', 'parentable_type' => 'product', 'parentable_id' => '500']);
        $this->add('migrations', ['id' => '49']);
        $this->add('password_resets');
        Schema::create('orbita_migrations', fn (Blueprint $table) => $table->increments('id'));
        DB::table('orbita_migrations')->insert(['id' => 1]);
        $dataset = $this->dataset();
        file_put_contents($this->directory.'/product.csv', 'changed after preflight');
        $result = (new RecoveryImporter)->import(DB::connection(), $dataset, ':memory:', true);
        $this->assertSame(['rows' => 1, 'max_id' => '500', 'auto_increment' => null], $result['product']);
        $this->assertSame('2', DB::table('product')->first()->key);
        $this->assertSame('Preserved', DB::table('product')->first()->value);
        $this->assertSame(49, DB::table('migrations')->first()->id);
        $this->assertSame(1, DB::table('orbita_migrations')->count());
        $this->assertSame(0, DB::table('password_resets')->count());
        $this->assertFalse(Schema::hasTable('orbita_schema_approvals'));
        $this->assertSame(501, DB::table('product')->insertGetId(['key' => '1', 'value' => 'Next']));
        $this->assertFalse(config('orbita.catalog_writes_enabled'));
    }

    public function test_existing_last_table_prevents_all_inserts(): void
    {
        $this->add('product');
        DB::table('migrations')->insert(['migration' => 'existing', 'batch' => 1]);
        $this->expectExceptionMessage('target table is not empty');
        try {
            (new RecoveryImporter)->import(DB::connection(), $this->dataset(), ':memory:', true);
        } finally {
            $this->assertSame(0, DB::table('product')->count());
            $this->assertSame(1, DB::table('migrations')->count());
        }
    }

    public function test_failure_after_an_earlier_insert_rolls_back_without_leaking_bindings(): void
    {
        $this->add('category');
        $this->add('product');
        $this->add('price');
        DB::connection()->beforeExecuting(static function (string $sql): void {
            if (str_starts_with($sql, 'insert into "price"')) {
                throw new RuntimeException('synthetic-secret-binding-do-not-log');
            }
        });
        try {
            (new RecoveryImporter)->import(DB::connection(), $this->dataset(), ':memory:', true);
            $this->fail('Late insert failure was ignored');
        } catch (RecoveryException $error) {
            $this->assertStringContainsString('rolled back', $error->getMessage());
            $this->assertStringNotContainsString('synthetic-secret-binding', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
        $this->assertSame(0, DB::table('category')->count());
        $this->assertSame(0, DB::table('product')->count());
    }

    public function test_non_mysql_targets_are_rejected_outside_in_memory_tests(): void
    {
        $this->expectExceptionMessage('Import requires MySQL');
        (new RecoveryImporter)->assertReady(DB::connection(), $this->dataset(), 'orbita');
    }

    public function test_an_explicit_target_name_is_still_required(): void
    {
        $db = new MySqlConnection(static fn () => throw new RuntimeException('Database must not be queried'), 'orbita', '', ['driver' => 'mysql']);
        $this->expectExceptionMessage('explicit target database name');
        (new RecoveryImporter)->assertReady($db, $this->dataset(), '');
    }

    public function test_target_name_must_match_the_configured_database_before_any_query(): void
    {
        $db = new MySqlConnection(static fn () => throw new RuntimeException('Database must not be queried'), 'orbita', '', ['driver' => 'mysql']);
        $this->expectExceptionMessage('Target database differs from the configured connection');
        (new RecoveryImporter)->assertReady($db, $this->dataset(), 'other_database');
    }

    public function test_wrong_schema_and_nonempty_service_tables_are_rejected(): void
    {
        Schema::create('orbita_schema_approvals', fn (Blueprint $table) => $table->increments('id'));
        DB::table('orbita_schema_approvals')->insert(['id' => 1]);
        try {
            (new RecoveryImporter)->assertReady(DB::connection(), $this->dataset(), ':memory:', true);
            $this->fail('Approved database accepted');
        } catch (RecoveryException $error) {
            $this->assertStringContainsString('target table is not empty', $error->getMessage());
        }
        Schema::drop('orbita_schema_approvals');
        Schema::drop('migrations');
        $this->expectExceptionMessage('complete recovery schema');
        (new RecoveryImporter)->assertReady(DB::connection(), $this->dataset(), ':memory:', true);
    }

    public function test_command_is_registered_and_missing_source_returns_failure(): void
    {
        $this->artisan('orbita:recovery-import', ['directory' => $this->directory.'/missing', '--dry-run' => true])
            ->expectsOutputToContain('CSV directory is missing')->assertFailed();
    }

    public function test_command_file_only_dry_run_never_uses_the_database(): void
    {
        $original = json_decode(file_get_contents(base_path('database/recovery-manifest.json')), true);
        foreach ($original as $table => $spec) {
            if ($spec['csv'] === 'missing') continue;
            $stream = fopen($this->directory.'/'.$table.'.csv', 'wb');
            for ($index = 1; $index <= $spec['rows']; $index++) {
                $row = [];
                foreach ($spec['columns'] as $column) {
                    $row[$column] = match ($column) {
                        'id', 'batch' => (string) $index, 'key', 'is_active' => '1',
                        'value' => $table === 'price' ? '12.50' : 'Synthetic', 'slug' => 'slug-'.$index,
                        'hash' => 'synthetic.webp', 'migration' => 'synthetic_'.$index,
                        'email' => 'fixture@example.invalid', 'token' => 'fixture-token', default => null,
                    };
                }
                fwrite($stream, $this->encode($row));
            }
            fclose($stream);
        }
        DB::connection()->beforeExecuting(static fn () => throw new RuntimeException('Dry-run must never query DB'));
        $this->artisan('orbita:recovery-import', ['directory' => $this->directory, '--dry-run' => true])
            ->expectsOutputToContain('"database_checked": false')->assertSuccessful();
    }
}
