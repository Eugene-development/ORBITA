<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Migrations\ConnectionCheckCommand;
use App\Modules\Migrations\ServiceMigrateCommand;
use App\Modules\Recovery\RecoveryImportCommand;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['events']->listen(CommandStarting::class, function (CommandStarting $event): void {
            if (! $this->app->environment('testing') && in_array($event->command, ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe', 'db:seed'], true)) {
                throw new RuntimeException('Database clearing and seeding are disabled. Use explicit migrations on the selected database.');
            }
        });
        $this->commands([RecoveryImportCommand::class, ServiceMigrateCommand::class, ConnectionCheckCommand::class]);
    }
}
