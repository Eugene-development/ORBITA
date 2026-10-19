<?php
namespace App\Modules\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Schema\CatalogWriteGate;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ProductAction
{
    public function __construct(private CatalogWriteGate $writeGate, private LegacyIdAllocator $allocator) {}
    public function save(array $input, ?string $id = null): Product
    {
        Gate::authorize('catalog-crud');
        $report = $this->writeGate->assertOpen();
        $input = Validator::make($input, [
            'value' => ['required', 'string', 'max:255'], 'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'],
            'categoryId' => ['required', 'integer', 'min:1'], 'price' => ['required', 'string', 'max:32'], 'unit' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'], 'imageHash' => ['nullable', 'string', 'max:128'],
            'imageAlt' => ['nullable', 'string', 'max:255'], 'texts' => ['sometimes', 'array', 'max:50'], 'properties' => ['sometimes', 'array', 'max:50'],
            'texts.*.id' => ['nullable', 'integer', 'min:1'], 'texts.*.key' => ['sometimes', 'string', 'in:1'], 'texts.*.value' => ['required', 'string', 'max:20000'],
            'texts.*.slug' => ['nullable', 'string', 'max:255'], 'properties.*.id' => ['nullable', 'integer', 'min:1'], 'properties.*.key' => ['sometimes', 'string', 'in:1'],
            'properties.*.value' => ['required', 'string', 'max:2000'], 'properties.*.slug' => ['nullable', 'string', 'max:255'],
        ])->validate();
        $input['price'] = Decimal::normalize($input['price']);
        $this->assertPriceFits($input['price'], $report['schema']['price']['columns']);
        $hasImage = isset($input['imageHash']) && $input['imageHash'] !== '';
        if ($hasImage) {
            \App\Modules\Media\MediaHash::assertSafe($input['imageHash']);
            $known = DB::table('orbita_media')->where('hash', $input['imageHash'])->pluck('hash')->contains(fn ($stored) => $stored === $input['imageHash'])
                || DB::table('image')->where('key', config('orbita.project_key'))->where('hash', $input['imageHash'])->pluck('hash')->contains(fn ($stored) => $stored === $input['imageHash']);
            if (! $known) { throw ValidationException::withMessages(['imageHash' => 'Сначала загрузите изображение.']); }
        }
        return DB::transaction(function () use ($input, $id, $hasImage): Product {
            // Every create/update takes the same counter locks before graph locks, including updates.
            $counts = ['product' => $id ? 0 : 1, 'price' => 1, 'unit' => 1, 'image' => $hasImage ? 1 : 0,
                'text' => count($input['texts'] ?? []), 'property' => count($input['properties'] ?? [])];
            $allocated = $this->allocator->allocate($counts);
            $category = Category::query()->whereKey($input['categoryId'])->lockForUpdate()->firstOrFail();
            $product = $id ? Product::query()->whereKey($id)->lockForUpdate()->firstOrFail() : null;
            $productId = $id ?? $allocated['product'][0];
            $duplicate = Product::query()->where('slug', $input['slug'])->where('id', '!=', $productId)->exists();
            if ($duplicate) { throw ValidationException::withMessages(['slug' => 'Такой URL уже используется.']); }
            $attributes = ['key' => config('orbita.project_key'), 'value' => $input['value'], 'slug' => $input['slug'],
                'parentable_type' => 'category', 'parentable_id' => $category->id, 'is_active' => $input['is_active'] ?? $product?->is_active ?? true];
            if ($id) { $this->updateRow('product', $productId, $attributes); }
            else { $this->insertRow('product', $productId, $attributes); }
            $this->saveOne('price', $productId, ['value' => $input['price']], $allocated['price'][0]);
            $this->saveOne('unit', $productId, ['value' => $input['unit']], $allocated['unit'][0]);
            if (array_key_exists('imageHash', $input)) {
                if ($hasImage) {
                    $imageAttributes = ['hash' => $input['imageHash']];
                    if (array_key_exists('imageAlt', $input)) { $imageAttributes['alt'] = $input['imageAlt'] ?? ''; }
                    $this->saveOne('image', $productId, $imageAttributes, $allocated['image'][0]);
                }
                else { $this->deleteChildren('image', $productId, $this->children('image', $productId)->pluck('id')->map(fn ($id) => (string) $id)->all()); }
            } elseif (array_key_exists('imageAlt', $input)) { $this->children('image', $productId)->update(['alt' => $input['imageAlt'] ?? '']); }
            foreach (['text' => 'texts', 'property' => 'properties'] as $table => $field) {
                if (array_key_exists($field, $input)) { $this->saveMany($table, $productId, $input[$field], $allocated[$table]); }
            }
            $this->audit($id ? 'product.update' : 'product.create', $productId, ['value', 'slug', 'categoryId', 'price', 'unit', 'imageHash', 'texts', 'properties']);
            return Product::query()->with(['price', 'unit', 'image', 'text', 'property'])->findOrFail($productId);
        }, attempts: 3);
    }
    public function setActive(string $id, bool $active): Product
    {
        Gate::authorize('catalog-crud'); $this->writeGate->assertOpen();
        return DB::transaction(function () use ($id, $active): Product {
            Product::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->updateRow('product', $id, ['is_active' => $active]);
            $this->audit($active ? 'product.restore' : 'product.archive', $id, ['is_active']);
            return Product::query()->with(['price', 'unit', 'image', 'text', 'property'])->findOrFail($id);
        });
    }
    private function children(string $table, string $id): \Illuminate\Database\Query\Builder
    {
        return DB::table($table)->where('key', config('orbita.project_key'))->where('parentable_type', 'product')->where('parentable_id', $id);
    }
    private function saveOne(string $table, string $productId, array $attributes, string $newId): void
    {
        $existing = $this->children($table, $productId)->lockForUpdate()->first();
        if ($existing) { $this->updateRow($table, (string) $existing->id, $attributes); }
        else {
            if ($table === 'image' && ! array_key_exists('alt', $attributes)) { $attributes['alt'] = ''; }
            $this->insertRow($table, $newId, array_merge($attributes, ['key' => config('orbita.project_key'), 'parentable_type' => 'product', 'parentable_id' => $productId]));
        }
    }
    private function saveMany(string $table, string $productId, array $items, array $ids): void
    {
        $existing = $this->children($table, $productId)->orderBy('id')->lockForUpdate()->get()->keyBy('id'); $kept = [];
        foreach ($items as $index => $item) {
            $childId = isset($item['id']) ? (string) $item['id'] : $ids[$index];
            // Legacy key is always assigned by the server; optional labels use the existing slug column.
            $attrs = ['key' => config('orbita.project_key'), 'value' => $item['value']];
            if (isset($item['slug']) && Schema::hasColumn($table, 'slug')) { $attrs['slug'] = $item['slug']; }
            if (isset($item['id'])) {
                if (! $existing->has($childId) || in_array($childId, $kept, true)) { throw ValidationException::withMessages([$table => 'Связанный ID не принадлежит этому товару или указан дважды.']); }
                $this->updateRow($table, $childId, $attrs);
            } else { $this->insertRow($table, $childId, array_merge($attrs, ['parentable_type' => 'product', 'parentable_id' => $productId])); }
            $kept[] = $childId;
        }
        $deleteIds = $existing->keys()->map(fn ($id) => (string) $id)->diff($kept)->all();
        if ($deleteIds) { $this->deleteChildren($table, $productId, $deleteIds); }
    }
    private function deleteChildren(string $table, string $productId, array $ids): void
    {
        if (! $ids) { return; }
        foreach (config('orbita.legacy_tables') as $dependent) {
            if (! Schema::hasColumn($dependent, 'parentable_type') || ! Schema::hasColumn($dependent, 'parentable_id')) { continue; }
            // Check globally: a legacy edge from another key must also never become orphaned.
            if (DB::table($dependent)->where('parentable_type', $table)->whereIn('parentable_id', $ids)->select('id')->lockForUpdate()->first()) {
                throw ValidationException::withMessages([$table => 'Эта запись используется другими legacy связями. Удаление требует отдельной проверки.']);
            }
        }
        $this->children($table, $productId)->whereIn('id', $ids)->delete();
    }
    private function insertRow(string $table, string $id, array $attributes): void
    {
        $attributes['id'] = $id;
        foreach (['created_at', 'updated_at'] as $column) { if (Schema::hasColumn($table, $column)) { $attributes[$column] = now(); } }
        if (Schema::hasColumn($table, 'active')) { $attributes['active'] = true; }
        DB::table($table)->insert($attributes);
    }
    private function updateRow(string $table, string $id, array $attributes): void
    {
        if (Schema::hasColumn($table, 'updated_at')) { $attributes['updated_at'] = now(); }
        DB::table($table)->where('key', config('orbita.project_key'))->where('id', $id)->update($attributes);
    }
    private function assertPriceFits(string $price, array $columns): void
    {
        $column = collect($columns)->firstWhere('name', 'value'); $type = strtolower($column['type']);
        $scale = null;
        if (preg_match('/int/', $type)) { $scale = 0; }
        elseif (preg_match('/(?:decimal|numeric)\(\d+,\s*(\d+)\)/', $type, $match)) { $scale = (int) $match[1]; }
        if ($scale !== null) {
            try { BigDecimal::of($price)->toScale($scale, RoundingMode::UNNECESSARY); }
            catch (\Throwable) { throw ValidationException::withMessages(['price' => 'Текущая колонка legacy БД не поддерживает эту точность цены.']); }
        }
    }
    private function audit(string $action, string $id, array $fields): void
    {
        DB::table('orbita_audit')->insert(['actor_id' => auth()->id(), 'action' => $action, 'entity_type' => 'product', 'entity_id' => $id,
            'changes' => json_encode(['fields' => $fields], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    }
}
