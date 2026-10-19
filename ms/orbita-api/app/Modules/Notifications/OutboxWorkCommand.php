<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use Illuminate\Console\Command;

final class OutboxWorkCommand extends Command
{
    protected $signature = 'orbita:outbox-work {--once : Process one bounded batch and exit} {--sleep=3 : Idle poll seconds}';
    protected $description = 'Deliver durable database outbox records with crash recovery and retry.';
    private bool $shouldStop = false;

    public function handle(OutboxProcessor $processor): int
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            foreach ([SIGTERM, SIGINT] as $signal) {
                pcntl_signal($signal, function (): void { $this->shouldStop = true; });
            }
        }
        $batch = 0;
        do {
            $processed = $processor->processOne();
            if ($this->option('once') && (! $processed || ++$batch >= 100)) {
                break;
            }
            if (! $processed && ! $this->shouldStop) {
                sleep(max(1, min(30, (int) $this->option('sleep'))));
            }
        } while (! $this->shouldStop);
        return self::SUCCESS;
    }
}
