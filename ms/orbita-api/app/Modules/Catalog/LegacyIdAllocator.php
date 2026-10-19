<?php
namespace App\Modules\Catalog;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use LogicException;
final class LegacyIdAllocator {
    /** @return array<string,list<string>> */
    public function allocate(array $counts): array {
        if (DB::transactionLevel() < 1) { throw new LogicException('Allocate legacy IDs inside the catalog transaction.'); }
        ksort($counts); $ids = [];
        foreach ($counts as $table => $count) {
            if (! in_array($table, config('orbita.write_tables'), true) || ! is_int($count) || $count < 0) { throw new LogicException('Invalid allocation request.'); }
            if ($count === 0) { $ids[$table] = []; continue; }
            $row = DB::table('orbita_legacy_sequences')->where('table_name', $table)->lockForUpdate()->first();
            if (! $row) { throw new LogicException('Legacy sequence has not been initialized by schema approval.'); }
            $next = BigInteger::of($row->next_id); $ids[$table] = [];
            for ($i = 0; $i < $count; $i++) { $ids[$table][] = (string) $next->plus($i); }
            DB::table('orbita_legacy_sequences')->where('table_name', $table)->update(['next_id' => (string) $next->plus($count), 'updated_at' => now()]);
        }
        return $ids;
    }
}
