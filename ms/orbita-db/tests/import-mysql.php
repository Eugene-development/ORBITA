<?php

declare(strict_types=1);

// Run with native local PHP against a disposable MySQL 8.4 on an explicit loopback test port.
// Never boots Laravel or reads .env, real CSV, existing databases or production credentials.
require __DIR__.'/../vendor/autoload.php';

use App\Modules\Recovery\MySqlCsvReader;
use App\Modules\Recovery\RecoveryDataset;
use App\Modules\Recovery\RecoveryException;
use App\Modules\Recovery\RecoveryImporter;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Facades\Facade;

if (getenv('ORBITA_RECOVERY_MYSQL_TESTS') !== '1') {
    throw new RuntimeException('Run only against disposable MySQL with ORBITA_RECOVERY_MYSQL_TESTS=1.');
}
function verify(bool $condition, string $message): void {
    if (! $condition) throw new RuntimeException($message);
}
$testPort = filter_var(getenv('ORBITA_RECOVERY_MYSQL_TEST_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]);
if ($testPort === false) throw new RuntimeException('An explicit disposable loopback MySQL test port is required.');
$admin = new PDO('mysql:host=127.0.0.1;port='.$testPort.';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
// A normal target name proves direct import needs no recovery-specific prefix.
$database = 'orbita_test_csv_'.bin2hex(random_bytes(8));
$directory = sys_get_temp_dir().'/'.$database;
mkdir($directory, 0700);
$admin->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $capsule = new Manager;
    $capsule->addConnection(['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => $testPort, 'database' => $database,
        'username' => 'root', 'password' => '', 'prefix' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'strict' => true, 'engine' => 'InnoDB']);
    $capsule->setAsGlobal();
    $capsule->getContainer()->bind('db.schema', fn () => $capsule->schema());
    Facade::setFacadeApplication($capsule->getContainer());
    (require __DIR__.'/../database/recovery/2026_10_14_000000_reconstruct_legacy_schema.php')->up();
    $manifest = json_decode(file_get_contents(__DIR__.'/../database/recovery-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    $values = [
        'category' => [['id' => '10', 'key' => '1', 'is_active' => '1', 'value' => 'Synthetic category', 'slug' => 'category']],
        'product' => [['id' => '500', 'key' => '2', 'is_active' => '1', 'value' => "Text, \"quoted\" \\N\nnext", 'slug' => null]],
        'price' => [['id' => '900', 'key' => '2', 'is_active' => '1', 'value' => '400222432.25', 'parentable_type' => 'product', 'parentable_id' => '500']],
        'image' => [
            ['id' => '20', 'key' => '2', 'is_active' => '1', 'hash' => 'first.webp', 'alt' => '', 'parentable_type' => 'product', 'parentable_id' => '500'],
            ['id' => '20', 'key' => '2', 'is_active' => '1', 'hash' => 'second.webp', 'alt' => '', 'parentable_type' => 'product', 'parentable_id' => '500'],
        ],
        'migrations' => [['id' => '49', 'migration' => 'synthetic_legacy_journal', 'batch' => '1']],
        'password_resets' => [['email' => 'synthetic@example.invalid', 'token' => 'synthetic-secret-token', 'created_at' => null]],
    ];
    foreach ($manifest as $table => &$spec) {
        $spec['rows'] = count($values[$table] ?? []);
        if ($spec['csv'] === 'missing') continue;
        $spec['csv'] = $spec['rows'] ? 'nonempty' : 'empty';
        $bytes = '';
        foreach ($values[$table] ?? [] as $record) {
            $fields = [];
            foreach ($spec['columns'] as $column) {
                $value = $record[$column] ?? null;
                $fields[] = $value === null ? '\\N' : '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n']).'"';
            }
            $bytes .= implode(',', $fields)."\n";
        }
        file_put_contents($directory.'/'.$table.'.csv', $bytes);
    }
    unset($spec);
    $reader = new MySqlCsvReader;
    $dataset = RecoveryDataset::load($directory, $manifest, $reader, true);
    $db = $capsule->getConnection();
    $importer = new RecoveryImporter;
    try {
        $importer->assertReady($db, $dataset, $database.'_wrong');
        throw new RuntimeException('Mismatched target name was accepted');
    } catch (RecoveryException $error) {
        verify(str_contains($error->getMessage(), 'differs from the configured connection'), 'Unexpected target-name error');
    }
    $importer->assertReady($db, $dataset, $database);
    // Fractional truncation can be a warning even in strict mode; never accept it.
    $db->statement('ALTER TABLE price MODIFY value DECIMAL(20,1) NOT NULL');
    try {
        $importer->import($db, $dataset, $database);
        throw new RuntimeException('Decimal conversion warning was ignored');
    } catch (RecoveryException $error) {
        verify(str_contains($error->getMessage(), 'rolled back'), 'Unexpected conversion-warning error');
    }
    verify($db->table('product')->count() === 0, 'Conversion warning did not roll back');
    $db->statement('ALTER TABLE price MODIFY value DECIMAL(20,2) NOT NULL');
    // Force a late SQL error after category and product inserts; do not print its SQL/bindings.
    $db->statement('ALTER TABLE price ADD CONSTRAINT synthetic_recovery_failure CHECK (value < 0)');
    try {
        $importer->import($db, $dataset, $database);
        throw new RuntimeException('Expected a late insert failure');
    } catch (RecoveryException $error) {
        verify(str_contains($error->getMessage(), 'rolled back'), 'Unexpected import error');
        verify(! str_contains($error->getMessage(), 'synthetic-secret-token'), 'Error exposed a secret');
    }
    verify($db->table('category')->count() === 0 && $db->table('product')->count() === 0, 'MySQL failed to roll back prior inserts');
    $db->statement('ALTER TABLE price DROP CHECK synthetic_recovery_failure');
    $result = $importer->import($db, $dataset, $database);
    verify($db->table('price')->value('value') === '400222432.25', 'MySQL changed exact decimal');
    verify($db->table('product')->value('value') === $values['product'][0]['value'], 'MySQL changed CSV text');
    verify($db->table('product')->value('slug') === null, 'MySQL changed NULL');
    verify($db->table('product')->value('key') === '2', 'MySQL changed project key');
    verify($db->table('image')->count() === 2 && $db->table('image')->where('id', 21)->value('hash') === 'second.webp', 'Image reassignment lost data');
    verify($db->table('password_resets')->count() === 0, 'Restored a reset token');
    verify($db->table('migrations')->value('id') === 49, 'Lost the archival journal');
    verify($result['product']['auto_increment'] === '501', 'Wrong MySQL AUTO_INCREMENT');
    verify($db->table('product')->insertGetId(['value' => 'Next synthetic product']) === 501, 'New IDs collide');
    try {
        $importer->import($db, $dataset, $database);
        throw new RuntimeException('Repeat import was accepted');
    } catch (RecoveryException $error) {
        verify(str_contains($error->getMessage(), 'not empty'), 'Unexpected repeat-import error');
    }
    $db->table('image')->delete();
    $db->table('price')->delete();
    $db->table('product')->delete();
    $db->table('category')->delete();
    $db->table('migrations')->delete();
    $db->statement('ALTER TABLE migrations ENGINE=MyISAM');
    try {
        $importer->assertReady($db, $dataset, $database);
        throw new RuntimeException('Nontransactional engine accepted');
    } catch (RecoveryException $error) {
        verify(str_contains($error->getMessage(), 'InnoDB'), 'Unexpected engine error');
    }
    $db->statement('ALTER TABLE migrations ENGINE=InnoDB');
    $fingerprint = new App\Modules\Migrations\CatalogFingerprint;
    $before = $fingerprint->capture($db);
    $native = new Symfony\Component\Process\Process([PHP_BINARY, dirname(__DIR__).'/artisan', 'orbita:service-migrate', '--force'], dirname(__DIR__), [
        'APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $directory.'/unused-config.php',
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => (string) $testPort,
        'DB_DATABASE' => $database, 'DB_USERNAME' => 'root', 'DB_PASSWORD' => '',
        'MYSQL_ATTR_SSL_CA' => '', 'ORBITA_CATALOG_WRITES_ENABLED' => 'false',
    ]);
    $native->run();
    verify($native->isSuccessful(), 'Native standalone Artisan migration failed: '.$native->getOutput().$native->getErrorOutput());
    verify($db->table('orbita_migrations')->count() === 3, 'Native runtime did not apply its canonical migrations');
    verify($db->getSchemaBuilder()->hasTable('orbita_orders'), 'Native service schema is incomplete');
    verify($before === $fingerprint->capture($db), 'Native service migrations changed catalogue structure');
    echo "PASS: native standalone Laravel service migrations, MySQL 8.4 direct import, target-name guard, atomic/conversion-warning rollback, exact decimals/text/NULL/key, image reassignment, AUTO_INCREMENT, repeat refusal and InnoDB guard.\n";
} finally {
    $admin->exec('DROP DATABASE `'.$database.'`');
    foreach (glob($directory.'/*') as $path) unlink($path);
    rmdir($directory);
}
