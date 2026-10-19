<?php

declare(strict_types=1);

namespace App\Modules\Payments;

interface PaymentGateway
{
    public function isConfigured(): bool;
    public function requestBody(object $order, object $payment, array $items): array;
    public function create(array $body, string $idempotencyKey): array;
    public function fetch(string $providerId): array;
}
