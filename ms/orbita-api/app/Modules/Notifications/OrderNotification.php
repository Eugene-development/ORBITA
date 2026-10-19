<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Orders\Decimal;
use Illuminate\Mail\Mailable;

final class OrderNotification extends Mailable
{
    public function __construct(public readonly object $order, public readonly array $items, public readonly string $event) {}

    public function build(): self
    {
        $title = match ($this->event) {
            'payment.succeeded' => 'Оплачен заказ', 'payment.canceled' => 'Отменён платёж заказа',
            'payment.needs_review' => 'Требуется проверка платежа заказа', default => 'Новый заказ',
        };
        $lines = [
            $title.' '.$this->order->id,
            'Статус: '.$this->order->status,
            'Имя: '.$this->order->customer_name,
            'Телефон: '.$this->order->customer_phone,
            'Адрес: '.($this->order->address ?: 'не указан'),
            'Комментарий: '.($this->order->comments ?: 'нет'),
            '', 'Товары:',
        ];
        foreach ($this->items as $item) {
            $lines[] = $item->product_name.' — '.Decimal::format((int) $item->quantity_milli, 3).' '.$item->unit
                .' × '.Decimal::format((int) $item->unit_price_minor).' руб. = '.Decimal::format((int) $item->line_total_minor).' руб.';
        }
        $lines[] = '';
        $lines[] = 'Стоимость товаров: '.Decimal::format((int) $this->order->total_minor).' руб.';
        $lines[] = 'Доставка и разгрузка согласуются и оплачиваются отдельно.';
        $text = implode("\n", $lines);
        // Inline escaped HTML avoids a second source of order formatting in view files.
        return $this->subject($title.' '.$this->order->id)->html('<pre style="white-space:pre-wrap">'.e($text).'</pre>');
    }
}
