<?php
namespace App\Modules\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

final class HealthController
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
            foreach (['orbita_users', 'orbita_orders', 'orbita_outbox', 'orbita_schema_approvals'] as $table) {
                if (! Schema::hasTable($table)) { return response()->json(['status' => 'unavailable'], 503); }
            }
            if (config('cache.default') === 'redis' || config('session.driver') === 'redis') { Redis::connection()->ping(); }
            return response()->json(['status' => 'ok']);
        } catch (\Throwable) { return response()->json(['status' => 'unavailable'], 503); }
    }
}
