<?php
namespace App\Modules\Schema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Brick\Math\BigInteger;
final class CatalogApproveCommand extends Command {
    protected $signature = 'orbita:catalog-approve {fingerprint} {--writers-coordinated : All legacy table writers use the new allocator or are stopped}';
    protected $description = 'Persist an independently reviewed schema fingerprint; writes service tables only';
    public function handle(SchemaInspector $inspector): int {
        if (! $this->option('writers-coordinated')) { $this->error('Writer coordination must be confirmed explicitly.'); return self::FAILURE; }
        $report = $inspector->inspect();
        if ($report['issues'] || ! hash_equals($report['fingerprint'], (string) $this->argument('fingerprint'))) {
            $this->error('Schema differs or contains unresolved issues. No approval saved.'); return self::FAILURE;
        }
        DB::transaction(function () use ($report): void {
            $tables = config('orbita.write_tables'); sort($tables);
            foreach ($tables as $table) {
                DB::table('orbita_legacy_sequences')->insertOrIgnore(['table_name' => $table, 'next_id' => '1', 'created_at' => now(), 'updated_at' => now()]);
                $current = DB::table('orbita_legacy_sequences')->where('table_name', $table)->lockForUpdate()->first();
                $next = BigInteger::of($report['max_ids'][$table])->plus(1);
                if ($next->isGreaterThan(BigInteger::of($current->next_id))) {
                    DB::table('orbita_legacy_sequences')->where('table_name', $table)->update(['next_id' => (string) $next, 'updated_at' => now()]);
                }
            }
            DB::table('orbita_schema_approvals')->updateOrInsert(['fingerprint' => $report['fingerprint']], ['report' => json_encode($report, JSON_THROW_ON_ERROR), 'writers_coordinated' => true, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        });
        $this->info('Service-table approval saved. Catalog write flags remain separately controlled by deployment.');
        return self::SUCCESS;
    }
}
