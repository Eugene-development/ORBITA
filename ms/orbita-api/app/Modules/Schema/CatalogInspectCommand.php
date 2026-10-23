<?php
namespace App\Modules\Schema;
use Illuminate\Console\Command;
final class CatalogInspectCommand extends Command {
    protected $signature = 'orbita:catalog-inspect {--check : Fail when catalog metadata is incompatible}';
    protected $description = 'Print read-only legacy schema metadata; does not approve or repair it';
    public function handle(SchemaInspector $inspector): int {
        $report = $inspector->inspect();
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if ($this->option('check') && ($report['issues'] !== [] || $report['schema'] === [])) {
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
