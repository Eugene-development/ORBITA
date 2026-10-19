<?php
namespace App\Modules\Schema;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SchemaInspector
{
    public function inspect(): array
    {
        $driver = DB::connection()->getDriverName();
        $known = config('orbita.legacy_tables');
        $serverVersion = match ($driver) {
            'mysql' => DB::selectOne('SELECT VERSION() AS version')->version,
            'sqlite' => DB::selectOne('SELECT sqlite_version() AS version')->version,
            default => 'unsupported',
        };
        $schema = []; $issues = []; $maxima = []; $observed = [];
        if (! in_array($driver, ['mysql', 'sqlite'], true)) { $issues[] = 'Unsupported metadata driver'; }
        foreach ($known as $table) {
            if (! Schema::hasTable($table)) { $issues[] = "Missing legacy table: {$table}"; continue; }
            $columns = Schema::getColumns($table);
            $indexes = Schema::getIndexes($table);
            $foreignKeys = Schema::getForeignKeys($table);
            usort($columns, fn ($a, $b) => strcmp($a['name'], $b['name']));
            usort($indexes, fn ($a, $b) => strcmp($a['name'], $b['name']));
            usort($foreignKeys, fn ($a, $b) => strcmp($a['name'], $b['name']));
            $engine = $driver === 'mysql' ? DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table])?->engine : $driver;
            $schema[$table] = compact('columns', 'indexes', 'foreignKeys', 'engine');
            $names = array_column($columns, 'name');
            if (! in_array('id', $names) || ! in_array('key', $names)) { $issues[] = "Missing id/key in {$table}"; continue; }
            if (! collect($indexes)->contains(fn ($index) => $index['unique'] && $index['columns'] === ['id'])) { $issues[] = "id is not independently unique in {$table}"; }
            if ($driver === 'mysql' && strtolower((string) $engine) !== 'innodb') { $issues[] = "Nontransactional engine in {$table}"; }
            $maxima[$table] = (string) (DB::table($table)->max('id') ?? 0);
            $observed[$table] = ['keys' => DB::table($table)->select('key')->distinct()->orderBy('key')->limit(101)->pluck('key')->all(), 'morph_aliases' => []];
            if (count($observed[$table]['keys']) > 100) { $issues[] = "More than 100 distinct keys in {$table}; classification requires review"; }
            if (in_array('parentable_type', $names)) {
                $aliases = DB::table($table)->whereNotNull('parentable_type')->distinct()->orderBy('parentable_type')->pluck('parentable_type')->all();
                $observed[$table]['morph_aliases'] = $aliases;
                foreach ($aliases as $alias) {
                    if (! array_key_exists($alias, \Illuminate\Database\Eloquent\Relations\Relation::morphMap())) { $issues[] = "Unsupported morph alias in {$table}: {$alias}"; }
                }
            }
        }
        foreach (['price', 'unit', 'image', 'seotitle', 'seodescription'] as $table) {
            if (! isset($schema[$table])) { continue; }
            $names = array_column($schema[$table]['columns'], 'name');
            if (! in_array('parentable_type', $names) || ! in_array('parentable_id', $names)) { $issues[] = "Missing morph columns in {$table}"; continue; }
            $duplicates = DB::table($table)->select('parentable_type', 'parentable_id')->where('key', config('orbita.project_key'))
                ->whereNotNull('parentable_id')->groupBy('parentable_type', 'parentable_id')->havingRaw('COUNT(*) > 1')->limit(1)->exists();
            if ($duplicates) { $issues[] = "Ambiguous morphOne records in {$table}"; }
        }
        if (isset($schema['product'])) {
            $duplicateSlug = DB::table('product')->select('slug')->where('key', config('orbita.project_key'))->whereNotNull('slug')->where('slug', '!=', '')
                ->groupBy('slug')->havingRaw('COUNT(*) > 1')->limit(1)->exists();
            if ($duplicateSlug) { $issues[] = 'Duplicate product slugs'; }
        }
        if (isset($schema['price'])) {
            $price = collect($schema['price']['columns'])->firstWhere('name', 'value');
            if (! $price || preg_match('/float|double|real/i', $price['type_name'])) { $issues[] = 'Price storage does not preserve exact decimals'; }
        }
        $unmapped = [];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if (in_array($table, config('orbita.service_tables'), true) || in_array($table, $known, true)) { continue; }
            $columns = Schema::getColumns($table); $foreignKeys = Schema::getForeignKeys($table); $names = array_column($columns, 'name');
            $references = (in_array('parentable_type', $names) && in_array('parentable_id', $names))
                || collect($foreignKeys)->contains(fn ($fk) => in_array($fk['foreign_table'], $known, true))
                || collect($known)->contains(fn ($entity) => in_array($entity.'_id', $names, true));
            if ($references) {
                $unmapped[$table] = ['columns' => $columns, 'foreign_keys' => $foreignKeys];
                if (! in_array($table, config('orbita.reviewed_non_catalog_tables'), true)) { $issues[] = "Unmapped legacy relationship table: {$table}"; }
            }
        }
        $views = [];
        $viewRows = $driver === 'mysql'
            ? array_map(fn ($row) => (array) $row, DB::select('SELECT TABLE_NAME AS name, VIEW_DEFINITION AS definition, CHECK_OPTION AS check_option, SECURITY_TYPE AS security_type, DEFINER AS definer FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE()'))
            : Schema::getViews();
        foreach ($viewRows as $view) {
            if (empty($view['definition'])) { $issues[] = "View metadata is not visible: {$view['name']}"; continue; }
            if ($this->referencesCatalog((string) $view['definition'], $known)) {
                $views[$view['name']] = ['definition_hash' => hash('sha256', (string) $view['definition']),
                    'metadata_hash' => hash('sha256', json_encode($view, JSON_THROW_ON_ERROR))];
                if (! in_array($view['name'], config('orbita.reviewed_catalog_views'), true)) { $issues[] = "Unreviewed catalog view: {$view['name']}"; }
            }
        }
        $triggerRows = $driver === 'mysql'
            ? DB::select('SELECT TRIGGER_NAME AS name, EVENT_OBJECT_TABLE AS table_name, ACTION_TIMING AS timing, EVENT_MANIPULATION AS event, ACTION_STATEMENT AS definition, ACTION_ORDER AS action_order, SQL_MODE AS sql_mode, DEFINER AS definer, CHARACTER_SET_CLIENT AS character_set_client, COLLATION_CONNECTION AS collation_connection, DATABASE_COLLATION AS database_collation FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()')
            : DB::select("SELECT name, tbl_name AS table_name, sql AS definition FROM sqlite_master WHERE type = 'trigger'");
        $triggers = [];
        foreach ($triggerRows as $trigger) {
            if (in_array($trigger->table_name, $known, true) || $this->referencesCatalog((string) $trigger->definition, $known)) {
                $triggers[$trigger->name] = ['table' => $trigger->table_name, 'timing' => $trigger->timing ?? null, 'event' => $trigger->event ?? null, 'definition_hash' => hash('sha256', (string) $trigger->definition),
                    'metadata_hash' => hash('sha256', json_encode($trigger, JSON_THROW_ON_ERROR))];
                if (! in_array($trigger->name, config('orbita.reviewed_catalog_triggers'), true)) { $issues[] = "Unreviewed catalog trigger: {$trigger->name}"; }
            }
        }
        foreach (['schema', 'maxima', 'observed', 'unmapped', 'views', 'triggers'] as $name) { ksort($$name); }
        $fingerprint = hash('sha256', json_encode(['driver' => $driver, 'server_version' => $serverVersion, 'tables' => $schema, 'unmapped_relationships' => $unmapped, 'views' => $views, 'triggers' => $triggers], JSON_THROW_ON_ERROR));
        return ['fingerprint' => $fingerprint, 'driver' => $driver, 'server_version' => $serverVersion, 'schema' => $schema,
            'max_ids' => $maxima, 'observed' => $observed, 'unmapped_relationship_tables' => $unmapped, 'catalog_views' => $views, 'catalog_triggers' => $triggers,
            'issues' => array_values(array_unique($issues))];
    }
    private function referencesCatalog(string $definition, array $tables): bool
    {
        return (bool) preg_match('/(?<![a-z0-9_])(?:'.implode('|', array_map(fn ($name) => preg_quote($name, '/'), $tables)).')(?![a-z0-9_])/i', $definition);
    }
}
