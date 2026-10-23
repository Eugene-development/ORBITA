<?php

declare(strict_types=1);

namespace App\Modules\Migrations;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ConnectionCheckCommand extends Command
{
    protected $signature = 'orbita:connection-check';
    protected $description = 'Read the selected MySQL database and TLS cipher without changing data';

    public function handle(): int
    {
        $db = DB::connection();
        if ($db->getDriverName() !== 'mysql') {
            $this->error('Connection check requires MySQL.');
            return self::FAILURE;
        }
        $name = $db->selectOne('SELECT DATABASE() AS name')->name;
        $cipher = $db->selectOne("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->Value ?? '';
        if ($name !== $db->getDatabaseName() || $cipher === '') {
            $this->error('Database name differs or MySQL TLS is not active.');
            return self::FAILURE;
        }
        $this->line(json_encode(['database' => $name, 'tls_cipher' => $cipher], JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
