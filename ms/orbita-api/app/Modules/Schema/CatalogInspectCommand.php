<?php
namespace App\Modules\Schema;
use Illuminate\Console\Command;
final class CatalogInspectCommand extends Command {
    protected $signature = 'orbita:catalog-inspect';
    protected $description = 'Print read-only legacy schema metadata; does not approve or repair it';
    public function handle(SchemaInspector $inspector): int {
        $this->line(json_encode($inspector->inspect(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
