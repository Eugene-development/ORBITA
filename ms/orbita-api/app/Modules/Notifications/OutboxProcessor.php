<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class OutboxProcessor
{
    public function processOne(): bool
    {
        $lease = max(60, (int) config('commerce.outbox_lease_seconds', 300));
        $event = DB::transaction(function () use ($lease): ?object {
            $event = DB::table('orbita_outbox')->where(function ($query) use ($lease): void {
                $query->where(fn ($pending) => $pending->where('status', 'pending')->where('available_at', '<=', now()))
                    ->orWhere(fn ($expired) => $expired->where('status', 'processing')->where('locked_at', '<', now()->subSeconds($lease)));
            })->orderBy('id')->lockForUpdate()->first();
            if ($event === null) {
                return null;
            }
            $event->locked_by = (string) Str::uuid();
            $event->attempts = (int) $event->attempts + 1;
            DB::table('orbita_outbox')->where('id', $event->id)->update([
                'status' => 'processing', 'locked_at' => now(), 'locked_by' => $event->locked_by,
                'attempts' => $event->attempts, 'updated_at' => now(),
            ]);
            return $event;
        }, 3);
        if ($event === null) {
            return false;
        }
        try {
            $this->deliver($event);
            DB::table('orbita_outbox')->where('id', $event->id)->where('locked_by', $event->locked_by)->update([
                'status' => 'delivered', 'delivered_at' => now(), 'locked_at' => null, 'locked_by' => null,
                'last_error_code' => null, 'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $backoff = min(3600, 15 * (2 ** min(8, $event->attempts - 1)));
            $code = $exception instanceof RuntimeException && in_array($exception->getMessage(), ['manager_email_missing', 'production_mail_transport_invalid', 'outbox_kind_unknown', 'order_missing'], true)
                ? $exception->getMessage() : 'mail_delivery_failed';
            DB::table('orbita_outbox')->where('id', $event->id)->where('locked_by', $event->locked_by)->update([
                'status' => 'pending', 'available_at' => now()->addSeconds($backoff), 'locked_at' => null, 'locked_by' => null,
                'last_error_code' => $code, 'updated_at' => now(),
            ]);
            Log::warning('Outbox delivery needs retry.', ['event_id' => $event->id, 'error_code' => $code]);
        }
        return true;
    }

    private function deliver(object $event): void
    {
        $manager = config('commerce.manager_email');
        if (! is_string($manager) || ! filter_var($manager, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('manager_email_missing');
        }
        $mailer = config('mail.default');
        if (app()->environment('production') && in_array(config('mail.mailers.'.$mailer.'.transport'), ['log', 'array'], true)) {
            throw new RuntimeException('production_mail_transport_invalid');
        }
        $payload = json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR);
        if (in_array($event->kind, ['order.created', 'payment.succeeded', 'payment.canceled', 'payment.needs_review'], true)) {
            $order = DB::table('orbita_orders')->find($payload['orderId']);
            if ($order === null) {
                throw new RuntimeException('order_missing');
            }
            $items = DB::table('orbita_order_items')->where('order_id', $order->id)->orderBy('id')->get()->all();
            Mail::to($manager)->send(new OrderNotification($order, $items, $event->kind));
            return;
        }
        if ($event->kind === 'subscription.created') {
            $subscription = DB::table('orbita_subscriptions')->find($payload['subscriptionId']);
            if ($subscription === null) {
                throw new RuntimeException('outbox_kind_unknown');
            }
            Mail::raw('Заявка на подписку: '.$subscription->email, static fn ($message) => $message->to($manager)->subject('Новая заявка на подписку'));
            return;
        }
        throw new RuntimeException('outbox_kind_unknown');
    }
}
