<?php
namespace Tests\Support;
use App\Modules\Schema\SchemaInspector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Synthetic schema for isolated tests. This is NOT a recovered production schema. */
final class LegacyFixtures
{
    public static function create(): void
    {
        if (! app()->environment('testing')) { throw new \LogicException('Synthetic fixtures may only be created in tests.'); }
        foreach (config('orbita.legacy_tables') as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->unsignedBigInteger('id')->primary(); $table->string('key', 64); $table->boolean('is_active')->default(true); $table->boolean('active')->default(true);
                $table->string('slug')->nullable();
                if ($name === 'price' && DB::connection()->getDriverName() === 'mysql') { $table->decimal('value', 14, 2)->nullable(); }
                else { $table->text('value')->nullable(); }
                $table->string('parentable_type', 64)->nullable(); $table->unsignedBigInteger('parentable_id')->nullable();
                $table->string('hash', 128)->nullable(); $table->string('alt')->nullable(); $table->timestamps();
                $table->index(['key', 'parentable_type', 'parentable_id']);
            });
        }
        DB::table('catalog')->insert(['id' => 1, 'key' => '1', 'value' => 'Каталог', 'slug' => 'catalog']);
        DB::table('rubric')->insert(['id' => 1, 'key' => '1', 'value' => 'Материалы', 'slug' => 'materials', 'parentable_type' => 'catalog', 'parentable_id' => 1]);
        DB::table('category')->insert(['id' => 12, 'key' => '1', 'value' => 'Товары', 'slug' => 'goods', 'parentable_type' => 'rubric', 'parentable_id' => 1]);
        DB::table('category')->insert(['id' => 13, 'key' => '2', 'value' => 'Другой проект', 'slug' => 'other', 'parentable_type' => 'rubric', 'parentable_id' => 1]);
    }
    public static function product(int $id = 1, string $price = '100.00', string $unit = 'шт', string $key = '1', bool $active = true, ?int $priceId = null): void
    {
        DB::table('product')->insert(['id' => $id, 'key' => $key, 'value' => 'Товар '.$id, 'slug' => 'product-'.$id, 'is_active' => $active, 'parentable_type' => 'category', 'parentable_id' => $key === '1' ? 12 : 13]);
        DB::table('price')->insert(['id' => $priceId ?? 1000 + $id, 'key' => $key, 'value' => $price, 'parentable_type' => 'product', 'parentable_id' => $id]);
        DB::table('unit')->insert(['id' => 2000 + $id, 'key' => $key, 'value' => $unit, 'parentable_type' => 'product', 'parentable_id' => $id]);
    }
    public static function approve(): void
    {
        $report = app(SchemaInspector::class)->inspect();
        if ($report['issues']) { throw new \RuntimeException(implode(', ', $report['issues'])); }
        foreach (config('orbita.write_tables') as $table) {
            DB::table('orbita_legacy_sequences')->insert(['table_name' => $table, 'next_id' => (int) $report['max_ids'][$table] + 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('orbita_schema_approvals')->insert(['fingerprint' => $report['fingerprint'], 'report' => json_encode($report), 'writers_coordinated' => true, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        config(['orbita.catalog_writes_enabled' => true, 'orbita.writer_coordination_confirmed' => true, 'orbita.approved_catalog_fingerprint' => $report['fingerprint']]);
    }
}
