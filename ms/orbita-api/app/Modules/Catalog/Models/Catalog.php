<?php
namespace App\Modules\Catalog\Models;
use Illuminate\Database\Eloquent\Relations\MorphMany;
class Catalog extends LegacyModel {
    protected $table = 'catalog';
    public function rubric(): MorphMany { return $this->morphMany(Rubric::class, 'parentable'); }
    public function text(): MorphMany { return $this->morphMany(Text::class, 'parentable'); }
}
