<?php
namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

abstract class LegacyModel extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
    protected function casts(): array { return ['is_active' => 'boolean', 'active' => 'boolean']; }
    protected static function booted(): void
    {
        static::addGlobalScope('orbita-project', fn (Builder $query) => $query->where($query->getModel()->qualifyColumn('key'), config('orbita.project_key')));
    }
    public function parentable(): MorphTo { return $this->morphTo(); }
}
