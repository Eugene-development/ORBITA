<?php
namespace App\Modules\Catalog\Models;
use Illuminate\Database\Eloquent\Relations\MorphMany;
class Rubric extends LegacyModel {
    protected $table = 'rubric';
    public function category(): MorphMany { return $this->morphMany(Category::class, 'parentable'); }
    public function text(): MorphMany { return $this->morphMany(Text::class, 'parentable'); }
}
