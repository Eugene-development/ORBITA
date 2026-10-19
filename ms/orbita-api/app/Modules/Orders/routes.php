<?php

use App\Modules\Notifications\SubscriptionController;
use App\Modules\Orders\OrderController;
use App\Modules\Payments\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('api')->group(function (): void {
    Route::post('checkout/quote', [OrderController::class, 'quote'])->middleware('throttle:60,1');
    Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:10,1');
    Route::get('orders/{id}', [OrderController::class, 'show'])->whereUuid('id')->middleware('throttle:60,1');
    Route::get('admin/orders', [OrderController::class, 'index'])->middleware(['auth', 'auth.session', 'throttle:60,1']);
    Route::post('subscriptions', SubscriptionController::class)->middleware('throttle:5,1');
});

// Provider notifications have no browser session; authenticate their state by API GET.
Route::post('api/payments/yookassa/webhook', WebhookController::class)->middleware('throttle:120,1');
