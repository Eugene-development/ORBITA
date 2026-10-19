<?php
namespace App\Modules\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CatalogWriteGate
{
    public function __construct(private SchemaInspector $inspector) {}
    public function assertOpen(): array
    {
        $expected = config('orbita.approved_catalog_fingerprint');
        if (! config('orbita.catalog_writes_enabled') || ! config('orbita.writer_coordination_confirmed') || ! $expected || ! Schema::hasTable('orbita_schema_approvals')) {
            throw new HttpException(503, 'Редактирование каталога ожидает проверки схемы и переключения всех writers.');
        }
        $approval = DB::table('orbita_schema_approvals')->where('fingerprint', $expected)->where('writers_coordinated', true)->first();
        $report = $this->inspector->inspect();
        if (! $approval || ! hash_equals($expected, $report['fingerprint']) || $report['issues'] !== []) {
            throw new HttpException(503, 'Схема каталога не соответствует утвержденному снимку.');
        }
        return $report;
    }
    public function isOpen(): bool
    {
        try { $this->assertOpen(); return true; } catch (\Throwable) { return false; }
    }
}
