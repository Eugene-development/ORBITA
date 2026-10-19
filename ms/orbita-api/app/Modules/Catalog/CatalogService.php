<?php
namespace App\Modules\Catalog;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CatalogService
{
    public function products(array $args, bool $admin = false): array
    {
        $query = Product::query()->with(['price', 'unit', 'image', 'text', 'property']);
        if (! $admin) { $query->where('is_active', true); }
        elseif (array_key_exists('status', $args) && $args['status'] !== null) { $query->where('is_active', $args['status']); }
        if (! empty($args['categoryId'])) { $query->where('parentable_type', 'category')->where('parentable_id', $args['categoryId']); }
        if (! empty($args['search'])) {
            $search = mb_substr($args['search'], 0, 100);
            $query->where('value', 'like', '%'.addcslashes($search, '%_\\').'%');
        }
        $first = max(1, min(100, (int) ($args['first'] ?? 24)));
        $page = max(1, (int) ($args['page'] ?? 1));
        $paginator = $query->orderBy('id')->paginate($first, ['*'], 'page', $page);
        return ['data' => $paginator->items(), 'paginatorInfo' => ['currentPage' => $paginator->currentPage(), 'lastPage' => $paginator->lastPage(), 'total' => $paginator->total(), 'hasMorePages' => $paginator->hasMorePages()]];
    }
    public function productIds(array $ids): array
    {
        if (count($ids) > 100) { throw ValidationException::withMessages(['ids' => 'Не более 100 товаров.']); }
        return Product::query()->where('is_active', true)->whereIn('id', $ids)->with(['price', 'unit', 'image'])->orderBy('id')->get()->all();
    }
    /** Authoritative catalog snapshots; product IDs and price IDs are independent. */
    public function quoteProducts(array $ids, bool $lock = false): array
    {
        if (count($ids) > 100) { throw ValidationException::withMessages(['products' => 'Не более 100 товаров.']); }
        if ($lock && DB::transactionLevel() === 0) { throw new \LogicException('Checkout locks require a transaction.'); }
        $query = Product::query()->where('is_active', true)->whereIn('id', $ids)->orderBy('id');
        if ($lock) { $query->lockForUpdate(); }
        $products = $query->get();
        $lockedRelations = [];
        if ($lock) {
            $productIds = $products->modelKeys();
            foreach (['price', 'unit'] as $table) {
                $rows = DB::table($table)->where('key', config('orbita.project_key'))->where('parentable_type', 'product')->whereIn('parentable_id', $productIds)->orderBy('id')->lockForUpdate()->get();
                $groups = $rows->groupBy('parentable_id');
                if ($groups->contains(fn ($records) => $records->count() !== 1)) {
                    throw ValidationException::withMessages(['products' => 'Неоднозначные данные цены или единицы товара.']);
                }
                $lockedRelations[$table] = $rows->keyBy('parentable_id');
            }
        }
        if (! $lock) { $products->load(['price', 'unit']); }
        return $products->map(function ($product) use ($lock, $lockedRelations): array {
            // Use locking-read results rather than a potentially older repeatable-read snapshot.
            $price = $lock ? $lockedRelations['price']->get($product->id) : $product->price;
            $unit = $lock ? $lockedRelations['unit']->get($product->id) : $product->unit;
            return [
            'id' => (string) $product->id, 'slug' => $product->slug, 'value' => $product->value,
            'price' => $price ? Decimal::normalize((string) $price->value) : null,
            'unit' => (string) ($unit?->value ?? ''), 'is_active' => (bool) $product->is_active,
            'key' => config('orbita.project_key'),
            ];
        })->all();
    }
}
