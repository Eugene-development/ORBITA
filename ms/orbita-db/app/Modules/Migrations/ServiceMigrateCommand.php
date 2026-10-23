<?php

declare(strict_types=1);

namespace App\Modules\Migrations;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ServiceMigrateCommand extends Command
{
    protected $signature = 'orbita:service-migrate {--pretend : Show SQL without applying it} {--force : Run without the production prompt}';
    protected $description = 'Apply canonical service migrations locally, checking catalogue structure before and after';

    public function handle(CatalogFingerprint $fingerprint): int
    {
        $db = DB::connection();
        $before = $fingerprint->capture($db);
        $status = $this->call('migrate', ['--force' => (bool) $this->option('force'), '--pretend' => (bool) $this->option('pretend')]);
        if ($status !== self::SUCCESS) return $status;
        if (! hash_equals($before, $fingerprint->capture($db))) {
            $this->error('Catalogue schema changed. Stop the release; no schema approval or writer enablement was performed.');
            return self::FAILURE;
        }
        $this->info('Service migrations complete; catalogue structure unchanged.');
        return self::SUCCESS;
    }
}
