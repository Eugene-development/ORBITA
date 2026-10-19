<?php
namespace App\Modules\Catalog\Models;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
class Product extends LegacyModel {
    protected $table = 'product';
    public function price(): MorphOne { return $this->morphOne(Price::class, 'parentable'); }
    public function unit(): MorphOne { return $this->morphOne(Unit::class, 'parentable'); }
    public function image(): MorphOne { return $this->morphOne(Image::class, 'parentable'); }
    public function text(): MorphMany { return $this->morphMany(Text::class, 'parentable'); }
    public function property(): MorphMany { return $this->morphMany(Property::class, 'parentable'); }
}
