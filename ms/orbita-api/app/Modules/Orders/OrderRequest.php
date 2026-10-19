<?php

declare(strict_types=1);

namespace App\Modules\Orders;

final class OrderRequest extends QuoteRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'quoteHash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/D'],
            'customer' => ['required', 'array:name,phone,address,comments'],
            'customer.name' => ['required', 'string', 'max:200'],
            'customer.phone' => ['required', 'string', 'regex:/^\+?[0-9() \-]{10,24}$/D',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    $digits = preg_replace('/\D/', '', (string) $value) ?? '';
                    if (strlen($digits) < 10 || strlen($digits) > 15) {
                        $fail('Укажите корректный телефон.');
                    }
                },
            ],
            'customer.address' => ['nullable', 'string', 'max:2000'],
            'customer.comments' => ['nullable', 'string', 'max:4000'],
            'onlinePayment' => ['required', 'boolean'],
            'idempotencyKey' => ['required', 'string', 'min:16', 'max:128', 'regex:/^[a-zA-Z0-9_\-]{16,128}$/D'],
        ];
    }
}
