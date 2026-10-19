<?php
declare(strict_types=1);

use App\Modules\Catalog\LegacyIdAllocator;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || getenv('ORBITA_MYSQL_TESTS') !== '1' || DB::connection()->getDriverName() !== 'mysql' || ! str_starts_with(DB::connection()->getDatabaseName(), 'orbita_test_')) {
    fwrite(STDERR, "Allocator worker is restricted to isolated MySQL fixtures.\n"); exit(2);
}
$ids = DB::transaction(function (): array {
    $ids = app(LegacyIdAllocator::class)->allocate(['unit' => 5, 'product' => 5, 'price' => 5]);
    // Keep the lock briefly to force contention with the other simultaneously started workers.
    usleep(150000);
    foreach ($ids['product'] as $index => $id) {
        DB::table('product')->insert(['id' => $id, 'key' => '1', 'value' => 'Concurrent fixture '.$id, 'slug' => 'concurrent-'.$id, 'parentable_type' => 'category', 'parentable_id' => 12]);
        DB::table('price')->insert(['id' => $ids['price'][$index], 'key' => '1', 'value' => '10.25', 'parentable_type' => 'product', 'parentable_id' => $id]);
        DB::table('unit')->insert(['id' => $ids['unit'][$index], 'key' => '1', 'value' => 'шт', 'parentable_type' => 'product', 'parentable_id' => $id]);
    }
    return $ids;
}, attempts: 3);
echo json_encode($ids, JSON_THROW_ON_ERROR);
