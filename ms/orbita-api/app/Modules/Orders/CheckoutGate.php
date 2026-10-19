<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use App\Modules\Schema\SchemaInspector;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Reads may remain available while accepting new authoritative order snapshots is closed. */
final class CheckoutGate
{
    public function __construct(private readonly SchemaInspector $inspector) {}

    public function assertReady(): void
    {
        $expected = (string) config('orbita.approved_catalog_fingerprint');
        if ($expected === '' || ! Schema::hasTable('orbita_schema_approvals')) {
            $this->unavailable();
        }
        $approval = DB::table('orbita_schema_approvals')->where('fingerprint', $expected)->where('writers_coordinated', true)->first();
        if ($approval === null) {
            $this->unavailable();
        }
        $report = $this->inspector->inspect();
        if (! hash_equals($expected, $report['fingerprint']) || $report['issues'] !== []) {
            $this->unavailable();
        }
    }

    private function unavailable(): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Приём новых заказов ожидает проверки каталога. Свяжитесь с менеджером.',
            'code' => 'CHECKOUT_UNAVAILABLE',
        ], 503));
    }
}
