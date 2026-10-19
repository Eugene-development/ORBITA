<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use Illuminate\Support\Facades\DB;

final class Outbox
{
    /** Call inside the business transaction; Redis availability is not required. */
    public function enqueue(string $kind, string $aggregateId, array $payload): void
    {
        DB::table('orbita_outbox')->insertOrIgnore([
            'kind' => $kind, 'aggregate_id' => $aggregateId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'status' => 'pending',
            'available_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
