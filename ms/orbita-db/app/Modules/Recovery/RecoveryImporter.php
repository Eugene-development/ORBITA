<?php

declare(strict_types=1);

namespace App\Modules\Recovery;

use Brick\Math\BigInteger;
use Illuminate\Database\Connection;
use Throwable;

/** Offline disaster recovery only. Does not approve the schema or enable writers. */
final class RecoveryImporter
{
    private const SERVICE_TABLES = [
        'orbita_migrations', 'orbita_users', 'orbita_password_reset_tokens', 'orbita_legacy_sequences',
        'orbita_schema_approvals', 'orbita_media', 'orbita_audit', 'orbita_orders', 'orbita_order_items',
        'orbita_payments', 'orbita_outbox', 'orbita_subscriptions',
    ];

    public function assertReady(Connection $db, RecoveryDataset $dataset, string $target, bool $inMemoryTest = false): void
    {
        if (! $dataset->report['importable']) {
            throw new RecoveryException('Unresolved duplicate IDs in CSV; review id_conflicts from --dry-run. No rows were imported.');
        }
        $driver = $db->getDriverName();
        $test = $inMemoryTest && $driver === 'sqlite' && $db->getDatabaseName() === ':memory:' && $target === ':memory:';
        if (! $test && ($driver !== 'mysql' || $target === '')) {
            throw new RecoveryException('Import requires MySQL and an explicit target database name.');
        }
        if ($db->getDatabaseName() !== $target || $db->getTablePrefix() !== '') {
            throw new RecoveryException('Target database differs from the configured connection, or a table prefix is set.');
        }
        if ($driver === 'mysql') {
            if ($db->selectOne('SELECT DATABASE() AS name')->name !== $target) {
                throw new RecoveryException('The live connection uses a different database.');
            }
            $mode = $db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
            if (! preg_match('/(?:^|,)STRICT_(?:ALL|TRANS)_TABLES(?:,|$)/', $mode)) {
                throw new RecoveryException('MySQL strict SQL mode is required to prevent silent coercion.');
            }
            if ($db->selectOne('SELECT COUNT(*) AS total FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()')->total != 0) {
                throw new RecoveryException('Recovery database must not contain triggers.');
            }
        } elseif ($db->selectOne("SELECT COUNT(*) AS total FROM sqlite_master WHERE type = 'trigger'")->total != 0) {
            throw new RecoveryException('Recovery database must not contain triggers.');
        }
        $schema = $db->getSchemaBuilder();
        $expected = array_keys($dataset->manifest);
        $actual = $schema->getTableListing(schemaQualified: false);
        if (array_diff($expected, $actual) || array_diff($actual, $expected, self::SERVICE_TABLES)) {
            throw new RecoveryException('Expected the complete recovery schema and optional empty ORBITA service tables only.');
        }
        if ($schema->getViews() !== []) {
            throw new RecoveryException('Recovery database must not contain views.');
        }
        foreach ($actual as $table) {
            if ($table !== 'orbita_migrations' && $db->table($table)->exists()) {
                throw new RecoveryException("{$table}: target table is not empty; no rows were imported.");
            }
        }
        foreach ($dataset->manifest as $table => $spec) {
            $columns = $schema->getColumns($table);
            if (array_column($columns, 'name') !== $spec['columns'] || $schema->getForeignKeys($table) !== []) {
                throw new RecoveryException("{$table}: schema differs from the recovery manifest.");
            }
            if ($driver === 'mysql') {
                $engine = $db->selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table])?->engine;
                if (strtolower((string) $engine) !== 'innodb') {
                    throw new RecoveryException("{$table}: InnoDB is required for atomic recovery.");
                }
            }
            if (in_array('id', $spec['columns'], true)) {
                $id = array_find($columns, static fn (array $column): bool => $column['name'] === 'id');
                $uniqueId = array_any($schema->getIndexes($table), static fn (array $index): bool => $index['unique'] && $index['columns'] === ['id']);
                if (! $id['auto_increment'] || ! $uniqueId) {
                    throw new RecoveryException("{$table}: an independently unique auto-increment ID is required.");
                }
            }
            // Catch NULL and string truncation before any insert, even with a foreign schema.
            foreach ($dataset->records[$table] as $number => $row) {
                foreach ($columns as $column) {
                    $value = $row[$column['name']];
                    if ($value === null && ! $column['nullable']) {
                        throw new RecoveryException("{$table}: NULL in a required column, record ".($number + 1).'.');
                    }
                    if ($value !== null && preg_match('/^(?:var)?char\((\d+)\)/i', $column['type'], $match) && mb_strlen($value) > (int) $match[1]) {
                        throw new RecoveryException("{$table}: string exceeds column capacity, record ".($number + 1).'.');
                    }
                }
            }
        }
    }

    public function import(Connection $db, RecoveryDataset $dataset, string $target, bool $inMemoryTest = false): array
    {
        if ($db->transactionLevel() !== 0) {
            throw new RecoveryException('Recovery must own its transaction.');
        }
        $this->assertReady($db, $dataset, $target, $inMemoryTest);
        $lock = 'orbita-recovery:'.hash('sha1', $target);
        $locked = false;
        $statsExpiry = null;
        try {
            if ($db->getDriverName() === 'mysql') {
                $locked = (int) $db->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired === 1;
                if (! $locked) {
                    throw new RecoveryException('Another recovery importer owns this database.');
                }
                $statsExpiry = $db->selectOne('SELECT @@SESSION.information_schema_stats_expiry AS expiry')->expiry;
                // MySQL caches TABLES.AUTO_INCREMENT; verify against fresh engine metadata.
                $db->statement('SET SESSION information_schema_stats_expiry = 0');
            }
            // A second check under the importer lock prevents two recovery processes from racing.
            $this->assertReady($db, $dataset, $target, $inMemoryTest);
            $result = $db->transaction(function () use ($db, $dataset): array {
                $verified = [];
                foreach ($dataset->records as $table => $rows) {
                    foreach (array_chunk($rows, 100) as $batch) {
                        try {
                            $db->table($table)->insert($batch);
                            if ($db->getDriverName() === 'mysql' && $this->mysqlWarnings($db) !== []) {
                                throw new RecoveryException("{$table}: MySQL reported a conversion warning; recovery rolled back.");
                            }
                        } catch (Throwable) {
                            // QueryException includes full SQL and bindings. Never expose it, even with -vvv.
                            throw new RecoveryException("{$table}: insert failed or produced a warning; recovery rolled back.");
                        }
                    }
                }
                foreach ($dataset->records as $table => $rows) {
                    $count = $db->table($table)->count();
                    if ($count !== count($rows)) {
                        throw new RecoveryException("{$table}: row count verification failed; recovery rolled back.");
                    }
                    $verified[$table] = ['rows' => $count, 'max_id' => null, 'auto_increment' => null];
                    if (in_array('id', $dataset->manifest[$table]['columns'], true)) {
                        $max = (string) ($db->table($table)->max('id') ?? 0);
                        $expected = $dataset->report['tables'][$table]['max_id'] ?? '0';
                        if ($max !== $expected) {
                            throw new RecoveryException("{$table}: maximum ID verification failed; recovery rolled back.");
                        }
                        $verified[$table]['max_id'] = $max;
                        if ($db->getDriverName() === 'mysql') {
                            $next = (string) $db->selectOne('SELECT AUTO_INCREMENT AS next_id FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table])->next_id;
                            if (! ctype_digit($next) || BigInteger::of($next)->isLessThanOrEqualTo($max)) {
                                throw new RecoveryException("{$table}: auto-increment verification failed; recovery rolled back.");
                            }
                            $verified[$table]['auto_increment'] = $next;
                        }
                    }
                }
                return $verified;
            }, 1);
        } finally {
            if ($statsExpiry !== null) {
                try {
                    $db->statement('SET SESSION information_schema_stats_expiry = ?', [$statsExpiry]);
                } catch (Throwable) {
                    $db->disconnect();
                }
            }
            if ($locked) {
                try {
                    $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
                } catch (Throwable) {
                    // Disconnect releases connection-scoped locks; do not misreport a committed import.
                    $db->disconnect();
                }
            }
        }
        return $result;
    }

    private function mysqlWarnings(Connection $db): array
    {
        $pdo = $db->getPdo();
        $emulated = $pdo->getAttribute(\PDO::ATTR_EMULATE_PREPARES);
        // Native preparation of SHOW WARNINGS itself emits warning 1295.
        // Use the direct protocol for this fixed diagnostic SQL only.
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
        try {
            return $pdo->query('SHOW WARNINGS')->fetchAll(\PDO::FETCH_ASSOC);
        } finally {
            $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, $emulated);
        }
    }
}
