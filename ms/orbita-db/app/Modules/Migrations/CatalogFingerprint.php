<?php

declare(strict_types=1);

namespace App\Modules\Migrations;

use Illuminate\Database\Connection;

/** Read-only structural snapshot of every non-service table, view and trigger. */
final class CatalogFingerprint
{
    public function capture(Connection $db): string
    {
        $schema = $db->getSchemaBuilder();
        $tables = [];
        foreach ($schema->getTables() as $table) {
            $name = $table['name'];
            if (str_starts_with($name, 'orbita_')) continue;
            $columns = $schema->getColumns($name);
            $indexes = $schema->getIndexes($name);
            $foreignKeys = $schema->getForeignKeys($name);
            usort($columns, static fn ($a, $b) => strcmp($a['name'], $b['name']));
            usort($indexes, static fn ($a, $b) => strcmp($a['name'], $b['name']));
            usort($foreignKeys, static fn ($a, $b) => strcmp($a['name'], $b['name']));
            $tables[$name] = ['columns' => $columns, 'indexes' => $indexes, 'foreign_keys' => $foreignKeys, 'engine' => $table['engine'] ?? null];
        }
        ksort($tables);
        $views = $schema->getViews();
        usort($views, static fn ($a, $b) => strcmp($a['name'], $b['name']));
        $triggers = $db->getDriverName() === 'mysql'
            ? $db->select('SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE, ACTION_TIMING, EVENT_MANIPULATION, ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() ORDER BY TRIGGER_NAME')
            : $db->select("SELECT name, tbl_name, sql FROM sqlite_master WHERE type = 'trigger' ORDER BY name");
        return hash('sha256', json_encode([$tables, $views, $triggers], JSON_THROW_ON_ERROR));
    }
}
