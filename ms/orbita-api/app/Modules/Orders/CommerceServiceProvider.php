<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use App\Modules\Notifications\OutboxWorkCommand;
use App\Modules\Payments\DummyGateway;
use App\Modules\Payments\PaymentGateway;
use App\Modules\Payments\ReconcilePaymentsCommand;
use App\Modules\Payments\YooKassaGateway;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

final class CommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function ($app): PaymentGateway {
            if (config('commerce.payments.driver') === 'dummy' && $app->environment('local', 'testing')) {
                return $app->make(DummyGateway::class);
            }
            return $app->make(YooKassaGateway::class);
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        if ($this->app->runningInConsole()) {
            $this->commands([OutboxWorkCommand::class, ReconcilePaymentsCommand::class]);
            $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
                $schedule->command('orbita:payments-reconcile')->everyMinute()->withoutOverlapping();
                $schedule->command('orbita:outbox-work --once')->everyMinute()->withoutOverlapping();
            });
        }
    }
}
