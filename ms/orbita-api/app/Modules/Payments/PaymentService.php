<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Modules\Notifications\Outbox;
use App\Modules\Orders\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PaymentService
{
    public function __construct(private readonly PaymentGateway $gateway, private readonly Outbox $outbox) {}

    public function isEnabled(): bool
    {
        if (! in_array(config('commerce.payments.driver'), ['yookassa', 'dummy'], true)) {
            return false;
        }
        if (config('commerce.payments.driver') === 'dummy' && ! app()->environment('local', 'testing')) {
            return false;
        }
        return (bool) config('commerce.payments.enabled') && $this->gateway->isConfigured();
    }

    public function ensureCreated(string $orderId): void
    {
        $payment = DB::table('orbita_payments')->where('order_id', $orderId)->first();
        if (! $payment || $payment->provider_id !== null || $payment->status === 'needs_review' || ! $this->isEnabled()) {
            return;
        }
        // YooKassa retains idempotency for 24h. Stop automatic creation before that expires.
        if ($payment->first_requested_at !== null && CarbonImmutable::parse($payment->first_requested_at)->lte(now()->subHours(23))) {
            DB::transaction(function () use ($payment): void {
                $current = DB::table('orbita_payments')->where('id', $payment->id)->lockForUpdate()->first();
                if ($current->provider_id === null) {
                    DB::table('orbita_payments')->where('id', $payment->id)->update([
                        'status' => 'needs_review', 'last_error_code' => 'creation_window_expired', 'updated_at' => now(),
                    ]);
                    DB::table('orbita_orders')->where('id', $payment->order_id)->update(['status' => 'payment_review', 'updated_at' => now()]);
                    $this->outbox->enqueue('payment.needs_review', $payment->order_id, ['orderId' => $payment->order_id]);
                }
            }, 3);
            return;
        }
        try {
            // Save the exact request before contacting the provider so retries stay identical.
            $payment = DB::transaction(function () use ($payment): object {
                $current = DB::table('orbita_payments')->where('id', $payment->id)->lockForUpdate()->first();
                if ($current->provider_request === null) {
                    $order = DB::table('orbita_orders')->find($current->order_id);
                    $items = DB::table('orbita_order_items')->where('order_id', $current->order_id)->orderBy('id')->get()->all();
                    $body = $this->gateway->requestBody($order, $current, $items);
                    DB::table('orbita_payments')->where('id', $current->id)->update([
                        'provider_request' => json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'first_requested_at' => now(),
                        'updated_at' => now(),
                    ]);
                    // MySQL JSON storage canonicalizes object keys. Read its persisted representation
                    // before the very first send so every retry has an identical serialized body.
                    $current = DB::table('orbita_payments')->where('id', $current->id)->lockForUpdate()->first();
                }
                return $current;
            }, 3);
            DB::table('orbita_payments')->where('id', $payment->id)->increment('attempts', 1, ['last_checked_at' => now()]);
            $payload = $this->gateway->create(json_decode($payment->provider_request, true, 512, JSON_THROW_ON_ERROR), $payment->idempotency_key);
            $this->applyVerified($payment->id, $payload);
        } catch (Throwable $exception) {
            $this->recordError($payment->id, $exception);
        }
    }

    public function reconcile(string $paymentId): void
    {
        $payment = DB::table('orbita_payments')->find($paymentId);
        if (! $payment || in_array($payment->status, ['succeeded', 'canceled', 'needs_review'], true)) {
            return;
        }
        if ($payment->provider === 'dummy' && ! app()->environment('local', 'testing')) {
            return;
        }
        if ($payment->provider_id === null) {
            $this->ensureCreated($payment->order_id);
            return;
        }
        try {
            $this->applyVerified($payment->id, $this->gateway->fetch($payment->provider_id));
        } catch (Throwable $exception) {
            $this->recordError($payment->id, $exception);
        }
    }

    public function webhook(array $untrusted): void
    {
        $providerId = (string) ($untrusted['object']['id'] ?? '');
        $hint = $untrusted['object']['metadata']['payment_id'] ?? null;
        $payment = DB::table('orbita_payments')->where('provider_id', $providerId)->first();
        if (! $payment && is_string($hint) && preg_match('/^[a-f0-9-]{36}$/Di', $hint)) {
            $payment = DB::table('orbita_payments')->where('id', $hint)->whereNull('provider_id')->first();
        }
        if (! $payment || $payment->provider !== 'yookassa') {
            return;
        }
        // Webhook event/status/amount are never treated as proof of payment.
        $payload = $this->gateway->fetch($providerId);
        if (($payload['id'] ?? null) !== $providerId) {
            throw new PaymentFailure('provider_id_mismatch');
        }
        $this->applyVerified($payment->id, $payload);
    }

    private function applyVerified(string $id, array $payload): void
    {
        DB::transaction(function () use ($id, $payload): void {
            $payment = DB::table('orbita_payments')->where('id', $id)->lockForUpdate()->first();
            $providerId = $payload['id'] ?? null;
            $status = $payload['status'] ?? null;
            if (! is_string($providerId) || ! preg_match('/^[a-f0-9-]{36}$/Di', $providerId)
                || ($payment->provider_id !== null && $payment->provider_id !== $providerId)
                || ! in_array($status, ['pending', 'waiting_for_capture', 'succeeded', 'canceled'], true)
                || ($payload['metadata']['payment_id'] ?? null) !== $id
                || ($payload['metadata']['order_id'] ?? null) !== $payment->order_id
                || ($payload['amount']['currency'] ?? null) !== $payment->currency
                || ! is_string($payload['amount']['value'] ?? null)
                || Decimal::parse($payload['amount']['value'], 2, Decimal::MAX_MINOR, 'provider.amount') !== (int) $payment->amount_minor
                || ($status === 'succeeded' && ($payload['paid'] ?? null) !== true)
                || (app()->environment('production') && ($payload['test'] ?? false))) {
                throw new PaymentFailure('provider_state_mismatch');
            }
            if ($payment->provider === 'yookassa' && (string) ($payload['recipient']['account_id'] ?? '') !== (string) config('commerce.payments.shop_id')) {
                throw new PaymentFailure('provider_shop_mismatch');
            }
            // Final provider states are immutable; a late pending notification cannot downgrade them.
            if (in_array($payment->status, ['succeeded', 'canceled'], true)) {
                return;
            }
            $url = $payload['confirmation']['confirmation_url'] ?? $payment->confirmation_url;
            if ($url !== null && (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
                || ! in_array(parse_url($url, PHP_URL_SCHEME), app()->environment('local', 'testing') ? ['http', 'https'] : ['https'], true))) {
                throw new PaymentFailure('confirmation_url_invalid');
            }
            DB::table('orbita_payments')->where('id', $id)->update([
                'provider_id' => $providerId, 'status' => $status, 'confirmation_url' => $url,
                'last_error_code' => $status === 'waiting_for_capture' ? 'unexpected_capture_stage' : null,
                'last_checked_at' => now(), 'updated_at' => now(),
            ]);
            $orderStatus = match ($status) {
                'succeeded' => 'paid', 'canceled' => 'payment_canceled', default => 'awaiting_payment',
            };
            DB::table('orbita_orders')->where('id', $payment->order_id)->update(['status' => $orderStatus, 'updated_at' => now()]);
            if (in_array($status, ['succeeded', 'canceled'], true)) {
                $this->outbox->enqueue('payment.'.$status, $payment->order_id, ['orderId' => $payment->order_id]);
            }
        }, 3);
    }

    private function recordError(string $id, Throwable $exception): void
    {
        $code = $exception instanceof PaymentFailure ? $exception->getMessage() : 'provider_unavailable';
        DB::table('orbita_payments')->where('id', $id)->update([
            'last_error_code' => mb_substr($code, 0, 64), 'last_checked_at' => now(), 'updated_at' => now(),
        ]);
        Log::warning('Payment operation needs retry.', ['payment_id' => $id, 'error_code' => $code]);
    }
}
