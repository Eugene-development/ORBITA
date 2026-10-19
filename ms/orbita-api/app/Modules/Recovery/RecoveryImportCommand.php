<?php

declare(strict_types=1);

namespace App\Modules\Recovery;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RecoveryImportCommand extends Command
{
    protected $signature = 'orbita:recovery-import
        {directory : Directory containing the original headerless CSV snapshot}
        {--dry-run : Validate and report without writing; no DB connection unless target-database is provided}
        {--reassign-conflicting-image-ids : Keep the first image ID and assign new IDs to later occurrences, only without incoming references}
        {--target-database= : Exact configured database name (orbita_recovery_*)}';
    protected $description = 'Restore CSV into an empty offline recovery database; never enables catalog writes';

    public function handle(MySqlCsvReader $reader, RecoveryImporter $importer): int
    {
        try {
            $manifestPath = base_path('../orbita-db/database/recovery-manifest.json');
            if (! is_file($manifestPath)) {
                $manifestPath = '/recovery-manifest.json'; // Bundled in the orbita-db image.
            }
            if (! is_readable($manifestPath)) {
                throw new RecoveryException('Recovery manifest is unavailable. Run from the source checkout or the orbita-db image.');
            }
            $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
            $dataset = RecoveryDataset::load((string) $this->argument('directory'), $manifest, $reader, (bool) $this->option('reassign-conflicting-image-ids'));
            $target = (string) $this->option('target-database');
            if (! $this->option('dry-run') && $target === '') {
                throw new RecoveryException('An explicit --target-database=orbita_recovery_* is required for import.');
            }
            $report = $dataset->report;
            $report['mode'] = $this->option('dry-run') ? 'dry-run' : 'import';
            $report['database_checked'] = false;
            $report['catalog_writes_changed'] = false;
            if ($target !== '') {
                if (config('orbita.catalog_writes_enabled')) {
                    throw new RecoveryException('Disable catalog writes before offline recovery.');
                }
                $db = DB::connection();
                $test = app()->environment('testing');
                if ($this->option('dry-run')) {
                    $importer->assertReady($db, $dataset, $target, $test);
                } else {
                    $report['verified_tables'] = $importer->import($db, $dataset, $target, $test);
                }
                $report['database_checked'] = true;
                $report['target_database'] = $target;
            }
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            if (! $report['importable']) {
                $this->error('Unresolved duplicate IDs: review id_conflicts. Import refused; no rows written.');
                return self::FAILURE;
            }
            if ($report['relationship_issues']) {
                $this->components->warn('The snapshot contains relationship/uniqueness issues. Rows are preserved; review relationship_issues before enabling catalog writes.');
            }
            $this->info($this->option('dry-run') ? 'Validation complete. No rows written.' : 'Recovery committed. Catalog writes and schema approval remain unchanged.');
            return self::SUCCESS;
        } catch (RecoveryException $error) {
            $this->error($error->getMessage());
        } catch (Throwable) {
            // No exception text: DB errors can embed credentials, tokens and complete CSV values.
            $this->error('Recovery failed. Check schema, connection and file permissions. No CSV values are logged.');
        }
        return self::FAILURE;
    }
}
