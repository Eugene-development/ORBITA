<?php

declare(strict_types=1);

// Standalone, synthetic, in-memory test: never boots the application or reads .env.
require __DIR__.'/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Facade;

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$db = new Manager;
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$db->setAsGlobal();
$db->bootEloquent();
$db->getContainer()->bind('db.schema', fn () => $db->schema());
Facade::setFacadeApplication($db->getContainer());
$migration = require __DIR__.'/../database/recovery/2026_10_14_000000_reconstruct_legacy_schema.php';
$manifest = json_decode(file_get_contents(__DIR__.'/../database/recovery-manifest.json'), true, flags: JSON_THROW_ON_ERROR);

// A collision on the final table must fail before the first table is created.
Schema::create('migrations', fn (Blueprint $t) => $t->increments('id'));
try {
    $migration->up();
    throw new LogicException('Preflight failed to refuse an existing table');
} catch (RuntimeException $e) {
    check(str_contains($e->getMessage(), 'Recovery requires absent'), 'Unexpected preflight error');
}
check(! Schema::hasTable('users'), 'Preflight left a partial schema');
Schema::drop('migrations');

$migration->up();
check(count(Schema::getTableListing(schemaQualified: false)) === 50, 'Expected 50 legacy tables');
foreach ($manifest as $table => $evidence) {
    check(Schema::getColumnListing($table) === $evidence['columns'], "Column order mismatch: {$table}");
    if (in_array('parentable_id', $evidence['columns'], true)) {
        $indexes = Schema::getIndexes($table);
        check(count(array_filter($indexes, fn ($i) => $i['columns'] === ['parentable_type', 'parentable_id'] && ! $i['unique'])) === 1, "Missing non-unique morph index: {$table}");
        check(Schema::getForeignKeys($table) === [], "Invalid polymorphic FK: {$table}");
    }
}

class RecoveryProduct extends Model { protected $table = 'product'; protected $guarded = []; public function properties() { return $this->morphMany(RecoveryProperty::class, 'parentable'); } }
class RecoveryCategory extends Model { protected $table = 'category'; protected $guarded = []; }
class RecoveryProperty extends Model { protected $table = 'property'; protected $guarded = []; public function parentable() { return $this->morphTo(); } }
Relation::enforceMorphMap(['product' => RecoveryProduct::class, 'category' => RecoveryCategory::class]);
$product = RecoveryProduct::create(['id' => 10, 'key' => '1', 'value' => 'Synthetic product']);
$category = RecoveryCategory::create(['id' => 10, 'key' => '2', 'value' => 'Synthetic category', 'slug' => 'category']);
$product->properties()->createMany([['value' => 'First'], ['value' => 'Second']]);
$other = RecoveryProperty::create(['key' => '2', 'value' => 'Other owner', 'parentable_type' => 'category', 'parentable_id' => 10]);
check($product->properties()->count() === 2, 'Polymorphic one-to-many lost children or mixed owner types');
check($other->parentable instanceof RecoveryCategory, 'Morph alias failed to resolve');
check($product->properties()->first()->parentable instanceof RecoveryProduct, 'Inverse morph failed');
$db->table('price')->insert(['key' => '1', 'value' => '400222432.25', 'parentable_type' => 'product', 'parentable_id' => 10]);
$db->table('price')->insert(['key' => '1', 'value' => '12.50', 'parentable_type' => 'product', 'parentable_id' => 10]);
check($db->table('price')->count() === 2, 'Duplicate morph children must remain permitted');
$db->table('seoquery')->insert(['value' => 'Detached query', 'parentable_type' => null, 'parentable_id' => null]);

// Service tables are outside the recovery rollback scope.
Schema::create('orbita_test_sentinel', fn (Blueprint $t) => $t->id());
$migration->down();
check(Schema::hasTable('orbita_test_sentinel'), 'Rollback removed a service table');
foreach (array_keys($manifest) as $table) { check(! Schema::hasTable($table), "Rollback left {$table}"); }
$migration->up();
$migration->down();
echo "PASS: 50 tables, column order, indexes, collision preflight, morphMany/morphTo, nullable owners, rollback and reapply.\n";
