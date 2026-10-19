<?php
namespace App\Modules\Catalog;
use App\Modules\Catalog\Models\Catalog;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Rubric;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CatalogQuery
{
    public function catalog(mixed $root, array $args): mixed { return Catalog::query()->orderBy('id')->get(); }
    public function catalogOne(mixed $root, array $args): mixed { return Catalog::query()->where('slug', $args['slug'])->first(); }
    public function rubricOne(mixed $root, array $args): mixed { return Rubric::query()->where('slug', $args['slug'])->first(); }
    public function categoryOne(mixed $root, array $args): mixed { return Category::query()->where('slug', $args['slug'])->first(); }
    public function productOne(mixed $root, array $args): mixed { return Product::query()->where('is_active', true)->where('slug', $args['slug'])->first(); }
    public function products(mixed $root, array $args): array { return app(CatalogService::class)->products($args); }
    public function productIds(mixed $root, array $args): array { return app(CatalogService::class)->productIds($args['ids']); }
    public function adminProducts(mixed $root, array $args): array { Gate::authorize('catalog-crud'); return app(CatalogService::class)->products($args, true); }
    public function adminProduct(mixed $root, array $args): mixed { Gate::authorize('catalog-crud'); return Product::query()->find($args['id']); }
    public function categoryId(Product $product): ?string { return $product->parentable_type === 'category' ? (string) $product->parentable_id : null; }
    public function referenceData(mixed $root, array $args): array {
        Gate::authorize('catalog-crud');
        return ['categories' => Category::query()->orderBy('value')->get(), 'units' => DB::table('unit')->where('key', config('orbita.project_key'))->select('value')->distinct()->orderBy('value')->get(),
            'catalogWritesEnabled' => app(\App\Modules\Schema\CatalogWriteGate::class)->isOpen()];
    }
}
