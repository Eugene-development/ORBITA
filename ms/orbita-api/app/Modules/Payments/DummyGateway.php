<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Modules\Orders\Decimal;
use Illuminate\Support\Facades\DB;

/** Local-only integration fixture; it never contacts a bank or a payment service. */
final class DummyGateway implements PaymentGateway
{
    public function isConfigured(): bool { return app()->environment('local', 'testing'); }

    public function requestBody(object $order, object $payment, array $items): array
    {
        if (! $this->isConfigured()) {
            throw new PaymentFailure('dummy_not_allowed');
        }
        return [
            'amount' => ['value' => Decimal::format((int) $payment->amount_minor), 'currency' => 'RUB'],
            'capture' => true, 'metadata' => ['order_id' => $order->id, 'payment_id' => $payment->id],
            'confirmation' => ['type' => 'redirect', 'return_url' => rtrim((string) config('app.url'), '/').'/spasibo?order='.$order->id],
        ];
    }

    public function create(array $body, string $idempotencyKey): array
    {
        if (! $this->isConfigured()) {
            throw new PaymentFailure('dummy_not_allowed');
        }
        return [
            'id' => $body['metadata']['payment_id'], 'status' => 'pending', 'paid' => false,
            'amount' => $body['amount'], 'metadata' => $body['metadata'], 'test' => true,
            'confirmation' => ['confirmation_url' => $body['confirmation']['return_url']],
        ];
    }

    public function fetch(string $providerId): array
    {
        if (! $this->isConfigured()) {
            throw new PaymentFailure('dummy_not_allowed');
        }
        $payment = DB::table('orbita_payments')->where('provider_id', $providerId)->first();
        if (! $payment) {
            throw new PaymentFailure('payment_not_found');
        }
        $status = config('commerce.payments.dummy_status', 'pending');
        return [
            'id' => $providerId, 'status' => $status, 'paid' => $status === 'succeeded', 'test' => true,
            'amount' => ['value' => Decimal::format((int) $payment->amount_minor), 'currency' => 'RUB'],
            'metadata' => ['order_id' => $payment->order_id, 'payment_id' => $payment->id],
        ];
    }
}
