<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Modules\Orders\Decimal;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class YooKassaGateway implements PaymentGateway
{
    private const URL = 'https://api.yookassa.ru/v3';

    public function isConfigured(): bool
    {
        if (! filled(config('commerce.payments.shop_id')) || ! filled(config('commerce.payments.secret_key'))) {
            return false;
        }
        $returnUrl = (string) config('commerce.payments.return_url');
        if (! filter_var($returnUrl, FILTER_VALIDATE_URL) || parse_url($returnUrl, PHP_URL_SCHEME) !== 'https') {
            return false;
        }
        $mode = config('commerce.payments.receipt_mode');
        if ($mode === 'external') {
            return true;
        }
        return $mode === 'yookassa'
            && filled(config('commerce.payments.receipt_vat_code'))
            && filled(config('commerce.payments.receipt_payment_mode'))
            && filled(config('commerce.payments.receipt_payment_subject'));
    }

    public function requestBody(object $order, object $payment, array $items): array
    {
        if (! $this->isConfigured()) {
            throw new PaymentFailure('configuration_missing');
        }
        $returnUrl = (string) config('commerce.payments.return_url');
        $body = [
            'amount' => ['value' => Decimal::format((int) $payment->amount_minor), 'currency' => 'RUB'],
            'capture' => true,
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'order='.rawurlencode($order->id),
            ],
            'description' => 'Заказ '.$order->id.' — товары; доставка отдельно',
            'metadata' => ['order_id' => $order->id, 'payment_id' => $payment->id],
        ];
        if (config('commerce.payments.receipt_mode') === 'yookassa') {
            $phone = preg_replace('/\D/', '', $order->customer_phone) ?? '';
            if (strlen($phone) === 11 && str_starts_with($phone, '8')) {
                $phone = '7'.substr($phone, 1);
            }
            $receipt = [
                'customer' => ['phone' => $phone],
                'items' => array_map(static function ($item): array {
                    $line = [
                        'description' => mb_substr($item->product_name, 0, 128),
                        'quantity' => Decimal::format((int) $item->quantity_milli, 3),
                        'amount' => ['value' => Decimal::format((int) $item->unit_price_minor), 'currency' => 'RUB'],
                        'vat_code' => (int) config('commerce.payments.receipt_vat_code'),
                        'payment_mode' => config('commerce.payments.receipt_payment_mode'),
                        'payment_subject' => config('commerce.payments.receipt_payment_subject'),
                    ];
                    if (filled(config('commerce.payments.receipt_measure'))) {
                        $line['measure'] = config('commerce.payments.receipt_measure');
                    }
                    return $line;
                }, $items),
            ];
            if (filled(config('commerce.payments.receipt_tax_system_code'))) {
                $receipt['tax_system_code'] = (int) config('commerce.payments.receipt_tax_system_code');
            }
            $body['receipt'] = $receipt;
        }
        return $body;
    }

    public function create(array $body, string $idempotencyKey): array
    {
        $response = $this->client()->withHeaders(['Idempotence-Key' => $idempotencyKey])->post(self::URL.'/payments', $body);
        if (! $response->successful() || ! is_array($response->json())) {
            throw new PaymentFailure('provider_http_'.$response->status());
        }
        return $response->json();
    }

    public function fetch(string $providerId): array
    {
        if (! preg_match('/^[a-f0-9-]{36}$/Di', $providerId)) {
            throw new PaymentFailure('provider_id_invalid');
        }
        $response = $this->client()->get(self::URL.'/payments/'.$providerId);
        if (! $response->successful() || ! is_array($response->json())) {
            throw new PaymentFailure('provider_http_'.$response->status());
        }
        return $response->json();
    }

    private function client(): PendingRequest
    {
        if (! filled(config('commerce.payments.shop_id')) || ! filled(config('commerce.payments.secret_key'))) {
            throw new PaymentFailure('configuration_missing');
        }
        // Fixed endpoint, redirects disabled: merchant credentials never follow an arbitrary URL.
        return Http::withBasicAuth((string) config('commerce.payments.shop_id'), (string) config('commerce.payments.secret_key'))
            ->acceptJson()->asJson()->connectTimeout(5)->timeout(15)->withOptions(['allow_redirects' => false]);
    }
}
