<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orbita_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('session_hash', 64);
            $table->string('idempotency_key', 128);
            $table->char('request_hash', 64);
            $table->char('quote_hash', 64);
            $table->string('customer_name', 200);
            $table->string('customer_phone', 32);
            $table->text('address')->nullable();
            $table->text('comments')->nullable();
            $table->unsignedBigInteger('total_minor');
            $table->char('currency', 3)->default('RUB');
            $table->boolean('online_payment');
            $table->string('status', 32);
            $table->timestamps();
            $table->unique(['session_hash', 'idempotency_key']);
        });
        Schema::create('orbita_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('order_id')->constrained('orbita_orders');
            // Legacy catalog owns its own schema; do not add a cross-schema foreign key.
            $table->unsignedBigInteger('product_id');
            $table->string('product_slug', 512);
            $table->text('product_name');
            $table->string('unit', 200);
            $table->unsignedBigInteger('quantity_milli');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();
        });
        Schema::create('orbita_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->unique()->constrained('orbita_orders');
            $table->string('provider', 32);
            $table->string('provider_id', 128)->nullable()->unique();
            $table->string('idempotency_key', 128)->unique();
            $table->string('status', 32)->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->text('confirmation_url')->nullable();
            $table->json('provider_request')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('first_requested_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('orbita_outbox', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 64);
            $table->string('aggregate_id', 128);
            $table->json('payload');
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('locked_at')->nullable();
            $table->uuid('locked_by')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'aggregate_id']);
            $table->index(['status', 'available_at']);
        });
        Schema::create('orbita_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 254)->unique();
            $table->string('status', 32)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Production commerce data must be preserved during application rollback.');
        }
        foreach (['orbita_subscriptions', 'orbita_outbox', 'orbita_payments', 'orbita_order_items', 'orbita_orders'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
