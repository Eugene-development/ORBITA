<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'orbita:payments-reconcile';
    protected $description = 'Reconcile nonterminal payments using authenticated provider state.';

    public function handle(PaymentService $payments): int
    {
        DB::table('orbita_payments')->whereNotIn('status', ['succeeded', 'canceled', 'needs_review'])
            ->where(fn ($query) => $query->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinute()))
            ->orderBy('id')->chunkById(100, function ($rows) use ($payments): void {
                foreach ($rows as $row) {
                    $payments->reconcile($row->id);
                }
            }, 'id');
        return self::SUCCESS;
    }
}
