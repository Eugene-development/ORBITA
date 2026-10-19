<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use Illuminate\Validation\ValidationException;

/** Integer arithmetic for rubles (2 places) and measured quantities (3 places). */
final class Decimal
{
    public const MAX_MINOR = 2_147_483_647;
    public const MAX_QUANTITY_MILLI = 1_000_000_000;

    public static function parse(string $value, int $scale, int $max, string $field): int
    {
        if (! preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,'.$scale.'}))?$/D', $value, $matches)) {
            throw ValidationException::withMessages([$field => 'Укажите положительное число с допустимой точностью.']);
        }
        $factor = 10 ** $scale;
        $result = ((int) $matches[1]) * $factor + (int) str_pad($matches[2] ?? '', $scale, '0');
        if ($result > $max) {
            throw ValidationException::withMessages([$field => 'Значение слишком велико.']);
        }
        return $result;
    }

    public static function format(int $value, int $scale = 2): string
    {
        $factor = 10 ** $scale;
        return intdiv($value, $factor).'.'.str_pad((string) ($value % $factor), $scale, '0', STR_PAD_LEFT);
    }

    public static function lineTotal(int $priceMinor, int $quantityMilli): int
    {
        // Inputs are bounded so the multiplication stays below signed 64-bit limits.
        $minor = intdiv($priceMinor * $quantityMilli + 500, 1000);
        if ($minor < 1 || $minor > self::MAX_MINOR) {
            throw ValidationException::withMessages(['items' => 'Сумма позиции вне допустимого диапазона.']);
        }
        return $minor;
    }
}
