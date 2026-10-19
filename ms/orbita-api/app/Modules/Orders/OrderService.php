<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use App\Modules\Notifications\Outbox;
use App\Modules\Payments\PaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OrderService
{
    public function __construct(
        private readonly QuoteService $quotes,
        private readonly PaymentService $payments,
        private readonly Outbox $outbox,
        private readonly CheckoutGate $gate,
    ) {}

    public function create(array $input, string $sessionHash): array
    {
        $input['onlinePayment'] = (bool) $input['onlinePayment'];
        $canonicalItems = $input['items'];
        usort($canonicalItems, static fn ($a, $b): int => strcmp($a['productId'], $b['productId']));
        foreach ($canonicalItems as &$item) {
            $item['quantity'] = Decimal::format(Decimal::parse($item['quantity'], 3, Decimal::MAX_QUANTITY_MILLI, 'items.quantity'), 3);
        }
        unset($item);
        $customer = [
            'name' => trim($input['customer']['name']), 'phone' => trim($input['customer']['phone']),
            'address' => trim($input['customer']['address'] ?? ''), 'comments' => trim($input['customer']['comments'] ?? ''),
        ];
        $requestHash = hash('sha256', json_encode([
            $canonicalItems, $input['quoteHash'], $customer, $input['onlinePayment'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        try {
            $id = DB::transaction(function () use ($input, $sessionHash, $requestHash, $customer): string {
                $existing = $this->findReplay($sessionHash, $input['idempotencyKey'], $requestHash, true);
                if ($existing !== null) {
                    return $existing;
                }
                $this->gate->assertReady();
                if ($input['onlinePayment'] && ! $this->payments->isEnabled()) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'Онлайн-оплата пока недоступна. Можно отправить заказ менеджеру.',
                        'code' => 'PAYMENT_UNAVAILABLE',
                    ], 422));
                }
                $quote = $this->quotes->quote($input['items'], true);
                if (! hash_equals($quote['quoteHash'], $input['quoteHash'])) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'Цена или состав корзины изменились. Подтвердите обновлённую сумму.',
                        'code' => 'QUOTE_CHANGED', 'quote' => $quote,
                    ], 409));
                }
                $id = (string) Str::uuid();
                $totalMinor = Decimal::parse($quote['total'], 2, Decimal::MAX_MINOR, 'total');
                $now = now();
                DB::table('orbita_orders')->insert([
                    'id' => $id, 'session_hash' => $sessionHash, 'idempotency_key' => $input['idempotencyKey'],
                    'request_hash' => $requestHash, 'quote_hash' => $quote['quoteHash'],
                    'customer_name' => $customer['name'], 'customer_phone' => $customer['phone'],
                    'address' => $customer['address'], 'comments' => $customer['comments'],
                    'total_minor' => $totalMinor, 'currency' => 'RUB',
                    'online_payment' => $input['onlinePayment'],
                    'status' => $input['onlinePayment'] ? 'awaiting_payment' : 'received',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                foreach ($quote['items'] as $item) {
                    DB::table('orbita_order_items')->insert([
                        'order_id' => $id, 'product_id' => $item['productId'],
                        'product_slug' => $item['slug'], 'product_name' => $item['name'], 'unit' => $item['unit'],
                        'quantity_milli' => Decimal::parse($item['quantity'], 3, Decimal::MAX_QUANTITY_MILLI, 'quantity'),
                        'unit_price_minor' => Decimal::parse($item['unitPrice'], 2, Decimal::MAX_MINOR, 'price'),
                        'line_total_minor' => Decimal::parse($item['lineTotal'], 2, Decimal::MAX_MINOR, 'total'),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                if ($input['onlinePayment']) {
                    $paymentId = (string) Str::uuid();
                    DB::table('orbita_payments')->insert([
                        'id' => $paymentId, 'order_id' => $id, 'provider' => config('commerce.payments.driver'),
                        'idempotency_key' => $paymentId, 'status' => 'creating', 'amount_minor' => $totalMinor,
                        'currency' => 'RUB', 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                $this->outbox->enqueue('order.created', $id, ['orderId' => $id]);
                return $id;
            }, 3);
        } catch (QueryException $exception) {
            // A concurrent first request can win the unique idempotency key insert.
            if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw $exception;
            }
            $id = $this->findReplay($sessionHash, $input['idempotencyKey'], $requestHash);
            if ($id === null) {
                throw $exception;
            }
        }
        // Provider calls happen after commit. A timeout never discards the order.
        $this->payments->ensureCreated($id);
        return $this->present($id);
    }

    private function findReplay(string $sessionHash, string $key, string $hash, bool $lock = false): ?string
    {
        $query = DB::table('orbita_orders')->where('session_hash', $sessionHash)->where('idempotency_key', $key);
        $order = ($lock ? $query->lockForUpdate() : $query)->first();
        if ($order === null) {
            return null;
        }
        if (! hash_equals($order->request_hash, $hash)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Этот ключ уже использован для другого заказа.', 'code' => 'IDEMPOTENCY_CONFLICT',
            ], 409));
        }
        return $order->id;
    }

    public function owned(string $id, string $sessionHash): array
    {
        abort_unless(DB::table('orbita_orders')->where('id', $id)->where('session_hash', $sessionHash)->exists(), 404);
        return $this->present($id);
    }

    public function present(string $id, bool $admin = false): array
    {
        $order = DB::table('orbita_orders')->find($id);
        abort_unless($order, 404);
        $payment = DB::table('orbita_payments')->where('order_id', $id)->first();
        $result = [
            'id' => $order->id, 'status' => $order->status,
            'paymentStatus' => $payment?->status ?? 'not_requested',
            'total' => Decimal::format((int) $order->total_minor), 'currency' => $order->currency,
            'confirmationUrl' => $payment?->confirmation_url,
            'deliveryNotice' => 'Доставка и разгрузка согласуются и оплачиваются отдельно.',
            'createdAt' => \Illuminate\Support\Carbon::parse($order->created_at, 'UTC')->toIso8601String(),
            'items' => DB::table('orbita_order_items')->where('order_id', $id)->orderBy('id')->get()->map(static fn ($item): array => [
                'productId' => (string) $item->product_id, 'name' => $item->product_name, 'slug' => $item->product_slug,
                'unit' => $item->unit, 'quantity' => Decimal::format((int) $item->quantity_milli, 3),
                'unitPrice' => Decimal::format((int) $item->unit_price_minor),
                'lineTotal' => Decimal::format((int) $item->line_total_minor),
            ])->all(),
        ];
        if ($admin) {
            $result['paymentErrorCode'] = $payment?->last_error_code;
            $result['customer'] = [
                'name' => $order->customer_name, 'phone' => $order->customer_phone,
                'address' => $order->address, 'comments' => $order->comments,
            ];
        }
        return $result;
    }
}
