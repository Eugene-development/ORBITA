<?php

declare(strict_types=1);

namespace App\Modules\Schema;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Release readiness only: the API never loads or applies migration source files. */
final class ServiceMigrationsCheckCommand extends Command
{
    protected $signature = 'orbita:migrations-check';
    protected $description = 'Read-only check that this API release has all required service migrations';

    public function handle(): int
    {
        $db = DB::connection();
        if (! $db->getSchemaBuilder()->hasTable('orbita_migrations')) {
            $this->error('Service migrations are missing. Run the local orbita-db service through the SSH tunnel.');
            return self::FAILURE;
        }
        $required = json_decode(file_get_contents(resource_path('required-migrations.json')), true, flags: JSON_THROW_ON_ERROR);
        $applied = $db->table('orbita_migrations')->pluck('migration')->all();
        if (array_diff($required, $applied)) {
            $this->error('Pending service migrations. Apply this release using local orbita-db before starting API.');
            return self::FAILURE;
        }
        $this->info('Required service migrations are applied.');
        return self::SUCCESS;
    }
}
