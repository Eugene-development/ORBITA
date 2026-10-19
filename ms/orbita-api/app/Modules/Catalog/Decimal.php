<?php
namespace App\Modules\Catalog;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;
final class Decimal {
    public static function normalize(mixed $value): string {
        if (! is_string($value) || strlen($value) > 32 || ! preg_match('/^(0|[1-9][0-9]{0,9})(\.[0-9]{1,2})?$/D', $value)) {
            throw ValidationException::withMessages(['price' => 'Укажите неотрицательную цену с точностью до копеек.']);
        }
        return (string) BigDecimal::of($value)->toScale(2, RoundingMode::UNNECESSARY);
    }
}
