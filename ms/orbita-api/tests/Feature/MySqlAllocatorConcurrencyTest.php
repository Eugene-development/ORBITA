<?php
namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('mysql')]
class MySqlAllocatorConcurrencyTest extends TestCase
{
    public function test_multiple_processes_allocate_unique_global_ids_and_atomic_graphs(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') { $this->markTestSkipped('Real MySQL locking test; use the isolated mysql profile.'); }
        $this->fixtureProduct(9, key: '2', priceId: 501);
        $this->approveSyntheticCatalog();
        $config = config('database.connections.mysql');
        $env = [
            'APP_ENV' => 'testing', 'ORBITA_MYSQL_TESTS' => '1', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
            'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_DATABASE' => $config['database'],
            'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'], 'DB_SOCKET' => $config['unix_socket'],
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ];
        $workers = [];
        for ($index = 0; $index < 4; $index++) {
            $worker = new Process([PHP_BINARY, base_path('tests/Support/allocator-worker.php')], base_path(), $env, timeout: 20);
            $worker->start(); $workers[] = $worker;
        }
        $ids = ['product' => [], 'price' => [], 'unit' => []];
        foreach ($workers as $worker) {
            $worker->wait(); $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $result = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            foreach ($ids as $table => $_) { array_push($ids[$table], ...$result[$table]); }
        }
        foreach ($ids as $table => $values) {
            $this->assertCount(20, array_unique($values));
            $this->assertDatabaseCount($table, 21);
        }
        $this->assertGreaterThan(9, min(array_map('intval', $ids['product'])));
        $this->assertGreaterThan(501, min(array_map('intval', $ids['price'])));
        foreach (DB::table('product')->where('key', '1')->get() as $product) {
            $this->assertSame(1, DB::table('price')->where('parentable_type', 'product')->where('parentable_id', $product->id)->count());
            $this->assertSame(1, DB::table('unit')->where('parentable_type', 'product')->where('parentable_id', $product->id)->count());
        }
        $this->assertDatabaseHas('product', ['id' => 9, 'key' => '2']);
        $this->assertDatabaseHas('price', ['id' => 501, 'parentable_id' => 9, 'key' => '2']);
    }
}
