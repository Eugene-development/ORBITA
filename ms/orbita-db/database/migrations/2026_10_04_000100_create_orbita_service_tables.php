<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orbita_users', function (Blueprint $table): void {
            $table->id(); $table->string('name', 200); $table->string('email', 254)->unique();
            $table->string('password'); $table->string('role', 16)->default('editor');
            $table->boolean('is_active')->default(true); $table->rememberToken(); $table->timestamps();
        });
        Schema::create('orbita_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email', 254)->primary(); $table->string('token'); $table->timestamp('created_at')->nullable();
        });
        Schema::create('orbita_legacy_sequences', function (Blueprint $table): void {
            $table->string('table_name', 64)->primary(); $table->unsignedBigInteger('next_id'); $table->timestamps();
        });
        Schema::create('orbita_schema_approvals', function (Blueprint $table): void {
            $table->id(); $table->char('fingerprint', 64)->unique(); $table->json('report');
            $table->boolean('writers_coordinated'); $table->timestamp('approved_at'); $table->timestamps();
        });
        Schema::create('orbita_media', function (Blueprint $table): void {
            $table->id(); $table->string('hash', 128)->unique(); $table->string('path', 255); $table->string('mime', 64);
            $table->unsignedBigInteger('bytes'); $table->unsignedBigInteger('uploaded_by'); $table->timestamps();
        });
        Schema::create('orbita_audit', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('actor_id')->nullable(); $table->string('action', 64);
            $table->string('entity_type', 64); $table->string('entity_id', 128)->nullable();
            $table->json('changes')->nullable(); $table->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['orbita_audit', 'orbita_media', 'orbita_schema_approvals', 'orbita_legacy_sequences', 'orbita_password_reset_tokens', 'orbita_users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
