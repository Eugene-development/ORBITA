<?php
namespace App\Modules\Catalog;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Directives\TrimDirective;

final class PreserveMediaHashTrim extends TrimDirective {
    protected function transformArgumentSet(ArgumentSet $argumentSet): ArgumentSet {
        foreach ($argumentSet->arguments as $name => $argument) {
            if ($name !== 'imageHash') { $argument->value = $this->sanitize($argument->value); }
        }
        return $argumentSet;
    }
}
