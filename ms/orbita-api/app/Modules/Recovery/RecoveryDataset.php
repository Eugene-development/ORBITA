<?php

declare(strict_types=1);

namespace App\Modules\Recovery;

use Brick\Math\BigInteger;
use DateTimeImmutable;

/** A validated, immutable in-memory snapshot; files are not re-read during import. */
final readonly class RecoveryDataset
{
    public const SKIPPED_TABLES = ['password_resets', 'personal_access_tokens', 'jobs', 'failed_jobs', 'cart'];
    private const MAX_BYTES = 32 * 1024 * 1024;
    private const MAX_ROWS = 100000;

    private function __construct(public array $manifest, public array $records, public array $report) {}

    public static function load(string $directory, array $manifest, MySqlCsvReader $reader, bool $reassignImageIds = false): self
    {
        $directory = realpath($directory);
        if ($directory === false || ! is_dir($directory) || ! is_readable($directory)) {
            throw new RecoveryException('CSV directory is missing or unreadable.');
        }
        foreach (scandir($directory) as $file) {
            if (preg_match('/\.csv$/i', $file) && ! isset($manifest[substr($file, 0, -4)])) {
                throw new RecoveryException('Unknown CSV file; only manifest tables are accepted.');
            }
        }
        $records = $tables = $ids = $idConflicts = [];
        $totalBytes = $totalRows = 0;
        foreach ($manifest as $table => $spec) {
            $path = $directory.'/'.$table.'.csv';
            $skip = in_array($table, self::SKIPPED_TABLES, true);
            $tables[$table] = ['status' => 'missing', 'rows' => 0, 'import_rows' => 0, 'sha256' => null, 'max_id' => null, 'next_id' => null];
            $records[$table] = [];
            $ids[$table] = [];
            if ($spec['csv'] === 'missing') {
                if (file_exists($path) || is_link($path)) {
                    throw new RecoveryException("{$table}: unexpected recovered file; review the manifest first.");
                }
                continue;
            }
            if (is_link($path) || ! is_file($path) || ! is_readable($path)) {
                throw new RecoveryException("{$table}: required CSV is missing, unreadable or a symbolic link.");
            }
            $bytes = file_get_contents($path, length: self::MAX_BYTES - $totalBytes + 1);
            if ($bytes === false || ($totalBytes += strlen($bytes)) > self::MAX_BYTES) {
                throw new RecoveryException('CSV snapshot exceeds 32 MiB or cannot be read.');
            }
            $max = BigInteger::zero();
            $firstRecords = [];
            foreach ($reader->rows($bytes, $table) as $number => $fields) {
                if (++$totalRows > self::MAX_ROWS) {
                    throw new RecoveryException('CSV snapshot exceeds 100000 records.');
                }
                if (count($fields) !== count($spec['columns'])) {
                    throw new RecoveryException("{$table}: wrong column count in record {$number}.");
                }
                $row = array_combine($spec['columns'], $fields);
                if (! $skip) {
                    self::validateRow($table, $row, $number);
                    if (isset($row['id'])) {
                        if (array_key_exists($row['id'], $ids[$table])) {
                            $idConflicts[$table][] = ['id' => $row['id'], 'first_record' => $firstRecords[$row['id']], 'duplicate_record' => $number];
                        } else {
                            $ids[$table][$row['id']] = $row['key'] ?? null;
                            $firstRecords[$row['id']] = $number;
                        }
                        $max = BigInteger::max($max, BigInteger::of($row['id']));
                    }
                    $records[$table][] = $row;
                }
                $tables[$table]['rows']++;
            }
            if ($tables[$table]['rows'] !== $spec['rows']) {
                throw new RecoveryException("{$table}: row count differs from the recovery manifest (expected {$spec['rows']}, found {$tables[$table]['rows']}).");
            }
            $tables[$table]['status'] = $skip ? 'skipped' : 'ready';
            $tables[$table]['import_rows'] = count($records[$table]);
            $tables[$table]['sha256'] = hash('sha256', $bytes);
            if (! $skip && in_array('id', $spec['columns'], true)) {
                $tables[$table]['max_id'] = (string) $max;
                $tables[$table]['next_id'] = (string) $max->plus(1);
            }
        }
        $reassignments = [];
        $unresolved = $idConflicts;
        if ($reassignImageIds && isset($idConflicts['image'])) {
            $conflicting = array_fill_keys(array_column($idConflicts['image'], 'id'), true);
            $referencedImageIds = [];
            foreach ($records as $table => $rows) {
                foreach ($rows as $row) {
                    $type = $row['parentable_type'] ?? null;
                    if (($type === 'image' && isset($conflicting[$row['parentable_id'] ?? ''])) || ($type !== null && ! array_key_exists($type, $ids))) {
                        throw new RecoveryException('Image ID reassignment is ambiguous: incoming references or unknown morph aliases exist. Review the CSV manually.');
                    }
                    if ($type === 'image' && $row['parentable_id'] !== null) {
                        $referencedImageIds[$row['parentable_id']] = true;
                    }
                }
            }
            $max = BigInteger::of($tables['image']['max_id']);
            foreach ($idConflicts['image'] as $conflict) {
                do {
                    $max = $max->plus(1);
                } while (isset($referencedImageIds[(string) $max]));
                if ($max->isGreaterThan('4294967294')) {
                    throw new RecoveryException('Image ID reassignment would exhaust the auto-increment range.');
                }
                $index = $conflict['duplicate_record'] - 1;
                $records['image'][$index]['id'] = (string) $max;
                $ids['image'][(string) $max] = $records['image'][$index]['key'];
                $reassignments[] = ['table' => 'image', 'record' => $conflict['duplicate_record'], 'first_record' => $conflict['first_record'], 'original_id' => $conflict['id'], 'new_id' => (string) $max];
            }
            $tables['image']['max_id'] = (string) $max;
            $tables['image']['next_id'] = (string) $max->plus(1);
            unset($unresolved['image']);
        }
        $issues = self::relationships($records, $ids);
        return new self($manifest, $records, [
            'source_rows' => $totalRows,
            'import_rows' => array_sum(array_column($tables, 'import_rows')),
            'skipped_rows' => $totalRows - array_sum(array_column($tables, 'import_rows')),
            'tables' => $tables,
            'id_conflicts' => $idConflicts,
            'id_reassignments' => $reassignments,
            'unresolved_id_conflicts' => $unresolved,
            'importable' => $unresolved === [],
            'relationship_issues' => $issues,
        ]);
    }

    private static function validateRow(string $table, array $row, int $number): void
    {
        foreach (['id', 'parentable_id'] as $column) {
            if (! array_key_exists($column, $row)) {
                continue;
            }
            $value = $row[$column];
            if ($value === null && $column === 'parentable_id') {
                continue;
            }
            $maximum = $column === 'id' && $table !== 'users' ? '4294967294' : '18446744073709551614';
            if (! is_string($value) || ! preg_match('/^[1-9][0-9]*$/D', $value) || BigInteger::of($value)->isGreaterThan($maximum)) {
                throw new RecoveryException("{$table}: invalid {$column} in record {$number}.");
            }
        }
        if (isset($row['is_active']) && ! in_array($row['is_active'], ['0', '1'], true)) {
            throw new RecoveryException("{$table}: invalid is_active in record {$number}.");
        }
        if (array_key_exists('key', $row) && ($row['key'] === null || $row['key'] === '' || mb_strlen($row['key']) > 36)) {
            throw new RecoveryException("{$table}: invalid key in record {$number}.");
        }
        if ($table === 'price' && (! is_string($row['value']) || ! preg_match('/^-?[0-9]{1,18}(?:\.[0-9]{1,2})?$/D', $row['value']))) {
            throw new RecoveryException("{$table}: invalid exact decimal in record {$number}.");
        }
        foreach (['created_at', 'updated_at', 'deleted_at', 'email_verified_at'] as $column) {
            if (isset($row[$column])) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $row[$column]);
                if (! $date || $date->format('Y-m-d H:i:s') !== $row[$column]) {
                    throw new RecoveryException("{$table}: invalid {$column} in record {$number}.");
                }
            }
        }
    }

    /** Counts only: never include entity values, tokens or untrusted aliases in reports. */
    private static function relationships(array $records, array $ids): array
    {
        $issues = [];
        foreach ($records as $table => $rows) {
            $counts = ['missing_parent' => 0, 'unknown_parent_type' => 0, 'incomplete_parent' => 0, 'cross_key_parent' => 0, 'duplicate_slug_groups' => 0, 'duplicate_morph_groups' => 0];
            $slugs = $morphs = [];
            foreach ($rows as $row) {
                if (isset($row['slug']) && $row['slug'] !== '') {
                    $slug = json_encode([$row['key'] ?? null, $row['slug']], JSON_THROW_ON_ERROR);
                    $slugs[$slug] = ($slugs[$slug] ?? 0) + 1;
                }
                if (! array_key_exists('parentable_type', $row)) {
                    continue;
                }
                $type = $row['parentable_type'];
                $id = $row['parentable_id'];
                if ($type === null && $id === null) {
                    continue;
                }
                if ($type === null || $id === null) {
                    $counts['incomplete_parent']++;
                } elseif (! array_key_exists($type, $ids)) {
                    $counts['unknown_parent_type']++;
                } elseif (! array_key_exists($id, $ids[$type])) {
                    $counts['missing_parent']++;
                } elseif (isset($row['key']) && $ids[$type][$id] !== $row['key']) {
                    $counts['cross_key_parent']++;
                }
                if (in_array($table, ['price', 'unit', 'image', 'seotitle', 'seodescription'], true) && $type !== null && $id !== null) {
                    $pair = json_encode([$row['key'] ?? null, $type, $id], JSON_THROW_ON_ERROR);
                    $morphs[$pair] = ($morphs[$pair] ?? 0) + 1;
                }
            }
            $counts['duplicate_slug_groups'] = count(array_filter($slugs, static fn (int $count): bool => $count > 1));
            $counts['duplicate_morph_groups'] = count(array_filter($morphs, static fn (int $count): bool => $count > 1));
            if (array_sum($counts) > 0) {
                $issues[$table] = $counts;
            }
        }
        return $issues;
    }
}
