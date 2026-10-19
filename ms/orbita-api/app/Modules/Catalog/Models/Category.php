<?php
namespace App\Modules\Catalog\Models;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
class Category extends LegacyModel {
    protected $table = 'category';
    public function product(): MorphMany { return $this->morphMany(Product::class, 'parentable')->where('is_active', true); }
    public function text(): MorphMany { return $this->morphMany(Text::class, 'parentable'); }
    public function seoTitle(): MorphOne { return $this->morphOne(SeoTitle::class, 'parentable'); }
    public function seoDescription(): MorphOne { return $this->morphOne(SeoDescription::class, 'parentable'); }
}
