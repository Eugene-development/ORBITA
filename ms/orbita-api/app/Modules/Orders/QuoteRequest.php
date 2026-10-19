<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:productId,quantity'],
            'items.*.productId' => ['required', 'string', 'regex:/^[1-9][0-9]{0,18}$/D', 'distinct:strict'],
            'items.*.quantity' => ['required', 'string', 'max:20', 'regex:/^(0|[1-9][0-9]{0,6})(?:\.[0-9]{1,3})?$/D'],
        ];
    }
}
