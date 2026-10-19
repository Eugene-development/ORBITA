<?php

return [
    'manager_email' => env('ORDER_MANAGER_EMAIL'),
    'measured_units' => ['кг', 'г', 'т', 'тонна', 'тонн', 'kg', 'g', 'м', 'п.м.', 'п.м', 'м.п.', 'м.п', 'мп', 'пог.м', 'пог.м.', 'погонныйметр', 'm', 'м2', 'м²', 'кв.м', 'кв.м.', 'm2', 'м3', 'м³', 'куб.м', 'куб.м.', 'm3', 'л', 'литр', 'l'],
    'outbox_lease_seconds' => 300,
    'payments' => [
        'enabled' => (bool) env('PAYMENTS_ENABLED', false),
        'driver' => env('PAYMENT_DRIVER', 'yookassa'),
        'shop_id' => env('YOOKASSA_SHOP_ID'),
        'secret_key' => env('YOOKASSA_SECRET_KEY'),
        'return_url' => env('YOOKASSA_RETURN_URL'),
        // Select explicitly: external (existing receipt service) or yookassa (API receipt).
        'receipt_mode' => env('YOOKASSA_RECEIPT_MODE'),
        'receipt_vat_code' => env('YOOKASSA_RECEIPT_VAT_CODE'),
        'receipt_tax_system_code' => env('YOOKASSA_RECEIPT_TAX_SYSTEM_CODE'),
        'receipt_payment_mode' => env('YOOKASSA_RECEIPT_PAYMENT_MODE'),
        'receipt_payment_subject' => env('YOOKASSA_RECEIPT_PAYMENT_SUBJECT'),
        'receipt_measure' => env('YOOKASSA_RECEIPT_MEASURE'),
        'dummy_status' => 'pending',
    ],
];
