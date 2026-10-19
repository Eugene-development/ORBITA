<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use App\Modules\Catalog\CatalogService;
use Illuminate\Validation\ValidationException;

final class QuoteService
{
    public function __construct(private readonly CatalogService $catalog) {}

    public function quote(array $requested, bool $lock = false): array
    {
        $input = [];
        foreach ($requested as $index => $item) {
            $id = (string) $item['productId'];
            if (isset($input[$id])) {
                throw ValidationException::withMessages(['items.'.$index.'.productId' => 'Товар указан дважды.']);
            }
            $input[$id] = (string) $item['quantity'];
        }
        uksort($input, static fn ($a, $b): int => strcmp((string) $a, (string) $b));
        $products = [];
        foreach ($this->catalog->quoteProducts(array_map('strval', array_keys($input)), $lock) as $product) {
            $products[(string) $product['id']] = $product;
        }
        $items = [];
        $totalMinor = 0;
        foreach ($input as $id => $quantity) {
            $product = $products[$id] ?? null;
            if (! $product || ! $product['is_active'] || (string) $product['key'] !== '1') {
                throw ValidationException::withMessages(['items' => 'В корзине есть недоступный товар. Обновите корзину.']);
            }
            $unit = (string) $product['unit'];
            $quantityMilli = Decimal::parse($quantity, 3, Decimal::MAX_QUANTITY_MILLI, 'items.quantity');
            if ($quantityMilli < 1 || (! $this->isMeasuredUnit($unit) && $quantityMilli % 1000 !== 0)) {
                throw ValidationException::withMessages(['items.quantity' => 'Для этой единицы измерения нужно целое положительное количество.']);
            }
            $priceMinor = Decimal::parse((string) $product['price'], 2, Decimal::MAX_MINOR, 'items.price');
            $lineMinor = Decimal::lineTotal($priceMinor, $quantityMilli);
            $totalMinor += $lineMinor;
            if ($totalMinor > Decimal::MAX_MINOR) {
                throw ValidationException::withMessages(['items' => 'Сумма заказа слишком велика. Разделите заказ.']);
            }
            $items[] = [
                'productId' => (string) $id, 'name' => (string) $product['value'],
                'slug' => (string) ($product['slug'] ?? ''), 'unit' => $unit,
                'quantity' => Decimal::format($quantityMilli, 3),
                'unitPrice' => Decimal::format($priceMinor), 'lineTotal' => Decimal::format($lineMinor),
            ];
        }
        $hash = hash_hmac('sha256', json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), (string) config('app.key'));
        return ['items' => $items, 'total' => Decimal::format($totalMinor), 'currency' => 'RUB', 'quoteHash' => $hash];
    }

    public function isMeasuredUnit(string $unit): bool
    {
        $normalized = mb_strtolower(preg_replace('/\s+/u', '', trim($unit)) ?? '');
        return in_array($normalized, (array) config('commerce.measured_units'), true);
    }
}
