<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Auth\Employee;
use App\Modules\Notifications\OrderNotification;
use App\Modules\Notifications\OutboxProcessor;
use App\Modules\Orders\Decimal;
use App\Modules\Payments\PaymentService;
use Illuminate\Http\Client\Request as ProviderRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class CommerceTest extends TestCase
{
    private bool $fixtureApproved = false;
    protected function setUp(): void
    {
        parent::setUp();
        config(['commerce.payments.enabled' => false, 'commerce.manager_email' => 'manager@example.test']);
        Http::preventStrayRequests();
        $this->withSession(['commerce_owner' => 'test-guest-owner']);
    }

    private function cart(string $id = '1', string $quantity = '1'): array
    {
        return ['items' => [['productId' => $id, 'quantity' => $quantity]]];
    }

    private function orderInput(array $cart, bool $online = false, ?string $key = null): array
    {
        if (! $this->fixtureApproved) {
            $this->approveSyntheticCatalog();
            $this->fixtureApproved = true;
        }
        $quote = $this->postJson('/api/checkout/quote', $cart)->assertOk()->json();
        return $cart + [
            'quoteHash' => $quote['quoteHash'], 'onlinePayment' => $online,
            'idempotencyKey' => $key ?? (string) Str::uuid(),
            'customer' => ['name' => 'Покупатель', 'phone' => '+7 (999) 123-45-67', 'address' => 'Адрес', 'comments' => 'Комментарий'],
        ];
    }

    private function enableProvider(): void
    {
        config([
            'commerce.payments.enabled' => true, 'commerce.payments.driver' => 'yookassa',
            'commerce.payments.shop_id' => 'test-shop', 'commerce.payments.secret_key' => 'fixture-secret',
            'commerce.payments.return_url' => 'https://orbita.example.test/spasibo',
            'commerce.payments.receipt_mode' => 'external',
        ]);
    }

    private function providerState(array $body, string $id, string $status = 'pending'): array
    {
        return [
            'id' => $id, 'status' => $status, 'paid' => $status === 'succeeded', 'test' => true,
            'amount' => $body['amount'], 'metadata' => $body['metadata'],
            'recipient' => ['account_id' => 'test-shop'],
            'confirmation' => ['confirmation_url' => 'https://yoomoney.ru/verified-confirmation/'.$id],
        ];
    }

    public function test_quote_uses_independent_price_id_and_exact_decimal_rounding(): void
    {
        $this->fixtureProduct(1, '199.99', 'м²', '1', true, 57);
        $this->postJson('/api/checkout/quote', $this->cart('1', '0.500'))->assertOk()
            ->assertJsonPath('total', '100.00')->assertJsonPath('items.0.unitPrice', '199.99')
            ->assertJsonPath('items.0.quantity', '0.500')->assertJsonPath('items.0.lineTotal', '100.00');
        $this->assertSame(100, Decimal::lineTotal(19999, 5));
    }

    public function test_quote_rejects_fractional_pieces_and_excess_quantity_precision(): void
    {
        $this->fixtureProduct();
        $this->postJson('/api/checkout/quote', $this->cart('1', '1.5'))->assertUnprocessable();
        $this->postJson('/api/checkout/quote', $this->cart('1', '1.0001'))->assertUnprocessable();
        $this->postJson('/api/checkout/quote', $this->cart('1', '-1'))->assertUnprocessable();
    }
    public function test_original_linear_metre_aliases_accept_fractional_quantity(): void
    {
        foreach (['м.п.', 'м.п', 'мп'] as $index => $unit) {
            $id = $index + 1;
            $this->fixtureProduct($id, '199.99', $unit);
            $this->postJson('/api/checkout/quote', $this->cart((string) $id, '0.500'))->assertOk()
                ->assertJsonPath('total', '100.00')->assertJsonPath('items.0.unit', $unit)
                ->assertJsonPath('items.0.quantity', '0.500')->assertJsonPath('items.0.lineTotal', '100.00');
        }
    }

    public function test_quote_cannot_order_another_project_or_archived_product(): void
    {
        $this->fixtureProduct(2, '10.00', 'шт', '2');
        $this->fixtureProduct(3, '10.00', 'шт', '1', false);
        $this->postJson('/api/checkout/quote', $this->cart('2'))->assertUnprocessable();
        $this->postJson('/api/checkout/quote', $this->cart('3'))->assertUnprocessable();
        $this->assertDatabaseCount('orbita_orders', 0);
    }

    public function test_price_change_requires_new_explicit_quote_confirmation(): void
    {
        $this->fixtureProduct();
        $input = $this->orderInput($this->cart());
        DB::table('price')->where('id', 1001)->update(['value' => '120.00']);
        $this->postJson('/api/orders', $input)->assertConflict()
            ->assertJsonPath('code', 'QUOTE_CHANGED')->assertJsonPath('quote.total', '120.00');
        $this->assertDatabaseCount('orbita_orders', 0);
        $this->assertDatabaseCount('orbita_outbox', 0);
    }

    public function test_unreviewed_catalog_allows_readonly_quote_but_blocks_new_orders(): void
    {
        $this->fixtureProduct();
        $cart = $this->cart();
        $quote = $this->postJson('/api/checkout/quote', $cart)->assertOk()->json();
        $input = $cart + [
            'quoteHash' => $quote['quoteHash'], 'onlinePayment' => false, 'idempotencyKey' => (string) Str::uuid(),
            'customer' => ['name' => 'Покупатель', 'phone' => '+79991234567'],
        ];
        $this->postJson('/api/orders', $input)->assertStatus(503)->assertJsonPath('code', 'CHECKOUT_UNAVAILABLE');
        $this->assertDatabaseCount('orbita_orders', 0);
    }

    public function test_new_ambiguous_relation_closes_checkout_without_breaking_saved_order_status(): void
    {
        $this->fixtureProduct();
        $input = $this->orderInput($this->cart());
        $first = $this->postJson('/api/orders', $input)->assertCreated()->json();
        DB::table('price')->insert(['id' => 9001, 'key' => '1', 'value' => '999.00', 'parentable_type' => 'product', 'parentable_id' => 1]);
        $input['idempotencyKey'] = (string) Str::uuid();
        $this->postJson('/api/orders', $input)->assertStatus(503)->assertJsonPath('code', 'CHECKOUT_UNAVAILABLE');
        $this->getJson('/api/orders/'.$first['id'])->assertOk()->assertJsonPath('total', '100.00');
        $this->assertDatabaseCount('orbita_orders', 1);
    }

    public function test_offline_order_snapshot_and_outbox_are_atomic_and_idempotent(): void
    {
        $this->fixtureProduct();
        $input = $this->orderInput($this->cart('1', '2'));
        $first = $this->postJson('/api/orders', $input)->assertCreated()->assertJsonPath('status', 'received')->json();
        DB::table('price')->where('id', 1001)->update(['value' => '200.00']);
        $this->postJson('/api/orders', $input)->assertCreated()->assertJsonPath('id', $first['id'])->assertJsonPath('total', '200.00');
        $this->assertDatabaseCount('orbita_orders', 1);
        $this->assertDatabaseCount('orbita_order_items', 1);
        $this->assertDatabaseCount('orbita_outbox', 1);
        $this->assertDatabaseHas('orbita_order_items', ['unit_price_minor' => 10000, 'quantity_milli' => 2000]);
        $input['customer']['comments'] = 'Другой запрос';
        $this->postJson('/api/orders', $input)->assertConflict()->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
        Http::assertNothingSent();
    }

    public function test_missing_outbox_storage_rolls_back_order_instead_of_false_success(): void
    {
        $this->fixtureProduct();
        $input = $this->orderInput($this->cart());
        Schema::drop('orbita_outbox');
        $this->postJson('/api/orders', $input)->assertStatus(500);
        $this->assertDatabaseCount('orbita_orders', 0);
        $this->assertDatabaseCount('orbita_order_items', 0);
    }

    public function test_guest_status_is_bound_to_session_and_is_not_public_by_uuid(): void
    {
        $this->fixtureProduct();
        $input = $this->orderInput($this->cart());
        $id = $this->postJson('/api/orders', $input)->assertCreated()->json('id');
        $this->getJson('/api/orders/'.$id)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->withSession(['commerce_owner' => 'another-guest'])->getJson('/api/orders/'.$id)->assertNotFound();
        $this->getJson('/api/admin/orders')->assertUnauthorized();
    }

    public function test_disabled_employee_cannot_read_private_order_list(): void
    {
        $employee = Employee::create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'fixture-password', 'role' => 'editor', 'is_active' => false]);
        $this->actingAs($employee)->getJson('/api/admin/orders')->assertForbidden();
    }

    public function test_payments_fail_closed_until_receipt_and_merchant_configuration_exist(): void
    {
        $this->fixtureProduct();
        $input = $this->orderInput($this->cart(), true);
        $this->postJson('/api/orders', $input)->assertUnprocessable()->assertJsonPath('code', 'PAYMENT_UNAVAILABLE');
        $this->enableProvider();
        config(['commerce.payments.receipt_mode' => null]);
        $this->postJson('/api/orders', $input)->assertUnprocessable()->assertJsonPath('code', 'PAYMENT_UNAVAILABLE');
        $this->assertDatabaseCount('orbita_orders', 0);
        Http::assertNothingSent();
    }

    public function test_online_payment_uses_capture_server_amount_and_provider_confirmation_url(): void
    {
        $this->enableProvider();
        $this->fixtureProduct(1, '199.99', 'м²');
        $providerId = (string) Str::uuid();
        Http::fake(function (ProviderRequest $request) use ($providerId) {
            $this->assertDatabaseCount('orbita_orders', 1);
            $this->assertDatabaseCount('orbita_outbox', 1);
            return Http::response($this->providerState($request->data(), $providerId));
        });
        $input = $this->orderInput($this->cart('1', '0.5'), true);
        $order = $this->postJson('/api/orders', $input)->assertCreated()->assertJsonPath('paymentStatus', 'pending')
            ->assertJsonPath('confirmationUrl', 'https://yoomoney.ru/verified-confirmation/'.$providerId)->json();
        $payment = DB::table('orbita_payments')->where('order_id', $order['id'])->first();
        Http::assertSent(fn (ProviderRequest $request): bool => $request->method() === 'POST'
            && $request['capture'] === true && $request['amount']['value'] === '100.00'
            && $request['amount']['currency'] === 'RUB'
            && $request->hasHeader('Idempotence-Key', $payment->idempotency_key)
            && $request['metadata']['order_id'] === $order['id']);
        $this->postJson('/api/orders', $input)->assertCreated()->assertJsonPath('id', $order['id']);
        Http::assertSentCount(1);
    }

    public function test_provider_timeout_keeps_order_and_retries_identical_payment_request(): void
    {
        $this->enableProvider();
        $this->fixtureProduct();
        $providerId = (string) Str::uuid();
        $bodies = [];
        $keys = [];
        Http::fake(function (ProviderRequest $request) use (&$bodies, &$keys, $providerId) {
            $bodies[] = $request->data();
            $keys[] = $request->header('Idempotence-Key');
            if (count($bodies) === 1) {
                return Http::response(['description' => 'No raw provider error should be logged'], 503);
            }
            return Http::response($this->providerState($request->data(), $providerId));
        });
        $input = $this->orderInput($this->cart(), true);
        $first = $this->postJson('/api/orders', $input)->assertCreated()->assertJsonPath('paymentStatus', 'creating')->json();
        $this->postJson('/api/orders', $input)->assertCreated()->assertJsonPath('id', $first['id'])->assertJsonPath('paymentStatus', 'pending');
        $this->assertSame($bodies[0], $bodies[1]);
        $this->assertSame($keys[0], $keys[1]);
        $this->assertDatabaseCount('orbita_orders', 1);
        $this->assertDatabaseCount('orbita_payments', 1);
    }

    public function test_missing_webhook_is_recovered_by_authenticated_reconciliation(): void
    {
        $this->enableProvider();
        $this->fixtureProduct();
        $providerId = (string) Str::uuid();
        $body = [];
        Http::fake(function (ProviderRequest $request) use ($providerId, &$body) {
            if ($request->method() === 'POST') {
                $body = $request->data();
                return Http::response($this->providerState($body, $providerId));
            }
            return Http::response($this->providerState($body, $providerId, 'succeeded'));
        });
        $order = $this->postJson('/api/orders', $this->orderInput($this->cart(), true))->assertCreated()->json();
        $paymentId = DB::table('orbita_payments')->where('order_id', $order['id'])->value('id');
        app(PaymentService::class)->reconcile($paymentId);
        $this->getJson('/api/orders/'.$order['id'])->assertOk()->assertJsonPath('status', 'paid');
        $this->assertDatabaseHas('orbita_outbox', ['kind' => 'payment.succeeded', 'aggregate_id' => $order['id']]);
        app(PaymentService::class)->reconcile($paymentId);
        $this->assertSame(1, DB::table('orbita_outbox')->where('kind', 'payment.succeeded')->count());
    }

    public function test_webhook_does_not_trust_posted_success_or_wrong_verified_amount(): void
    {
        $this->enableProvider();
        $this->fixtureProduct();
        $providerId = (string) Str::uuid();
        $body = [];
        $verifiedOverride = null;
        Http::fake(function (ProviderRequest $request) use ($providerId, &$body, &$verifiedOverride) {
            if ($request->method() === 'POST') {
                $body = $request->data();
            }
            return Http::response($verifiedOverride ?? $this->providerState($body, $providerId));
        });
        $order = $this->postJson('/api/orders', $this->orderInput($this->cart(), true))->assertCreated()->json();
        $notification = ['type' => 'notification', 'event' => 'payment.succeeded', 'object' => ['id' => $providerId, 'status' => 'succeeded', 'paid' => true]];
        $this->postJson('/api/payments/yookassa/webhook', $notification)->assertOk();
        $this->assertDatabaseHas('orbita_orders', ['id' => $order['id'], 'status' => 'awaiting_payment']);
        $wrong = $this->providerState($body, $providerId, 'succeeded');
        $wrong['amount']['value'] = '1.00';
        $verifiedOverride = $wrong;
        $this->postJson('/api/payments/yookassa/webhook', $notification)->assertStatus(503);
        $this->assertDatabaseHas('orbita_orders', ['id' => $order['id'], 'status' => 'awaiting_payment']);
    }

    public function test_unknown_creation_is_not_reissued_after_provider_idempotency_window(): void
    {
        $this->enableProvider();
        $this->fixtureProduct();
        Http::fake(['*' => Http::response([], 503)]);
        $order = $this->postJson('/api/orders', $this->orderInput($this->cart(), true))->assertCreated()->json();
        $paymentId = DB::table('orbita_payments')->where('order_id', $order['id'])->value('id');
        DB::table('orbita_payments')->where('id', $paymentId)->update(['first_requested_at' => now()->subHours(24)]);
        app(PaymentService::class)->reconcile($paymentId);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('orbita_payments', ['id' => $paymentId, 'status' => 'needs_review', 'last_error_code' => 'creation_window_expired']);
        $this->assertDatabaseHas('orbita_orders', ['id' => $order['id'], 'status' => 'payment_review']);
    }

    public function test_notification_failure_retries_from_database_and_uses_saved_snapshot(): void
    {
        $this->fixtureProduct();
        $order = $this->postJson('/api/orders', $this->orderInput($this->cart()))->assertCreated()->json();
        DB::table('product')->where('id', 1)->update(['value' => 'Изменённое название']);
        $mailManager = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->once()->with('manager@example.test')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP error containing private information'));
        $processor = app(OutboxProcessor::class);
        $this->assertTrue($processor->processOne());
        $this->assertDatabaseHas('orbita_outbox', ['aggregate_id' => $order['id'], 'status' => 'pending', 'attempts' => 1, 'last_error_code' => 'mail_delivery_failed']);
        $this->travel(20)->seconds();
        Mail::swap($mailManager);
        Mail::fake();
        $this->assertTrue($processor->processOne());
        Mail::assertSent(OrderNotification::class, static fn (OrderNotification $mail): bool => $mail->items[0]->product_name === 'Товар 1');
        $this->assertDatabaseHas('orbita_outbox', ['aggregate_id' => $order['id'], 'status' => 'delivered', 'attempts' => 2]);
    }

    public function test_stale_outbox_worker_lease_is_recovered(): void
    {
        $this->fixtureProduct();
        $this->postJson('/api/orders', $this->orderInput($this->cart()))->assertCreated();
        DB::table('orbita_outbox')->update(['status' => 'processing', 'locked_at' => now()->subMinutes(10), 'locked_by' => (string) Str::uuid()]);
        Mail::fake();
        $this->assertTrue(app(OutboxProcessor::class)->processOne());
        $this->assertDatabaseHas('orbita_outbox', ['status' => 'delivered', 'locked_by' => null]);
        Mail::assertSent(OrderNotification::class);
    }

    public function test_newsletter_persists_once_and_returns_pending_not_a_sent_email_claim(): void
    {
        Mail::fake();
        $this->postJson('/api/subscriptions', ['email' => 'Subscriber@example.test'])->assertStatus(202)->assertJsonPath('status', 'pending');
        $this->postJson('/api/subscriptions', ['email' => 'subscriber@example.test'])->assertStatus(202);
        $this->assertDatabaseCount('orbita_subscriptions', 1);
        $this->assertDatabaseHas('orbita_outbox', ['kind' => 'subscription.created', 'status' => 'pending']);
        Mail::assertNothingOutgoing();
    }
}
