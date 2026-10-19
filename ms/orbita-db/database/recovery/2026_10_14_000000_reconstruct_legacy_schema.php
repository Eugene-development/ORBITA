<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Explicit disaster recovery path. Never registered with service migrations.
// Evidence and intentional differences from the archive: ../../README.md.
return new class extends Migration
{
    private const TABLES = [
        'users',
        'password_resets',
        'failed_jobs',
        'personal_access_tokens',
        'property',
        'category',
        'product',
        'project',
        'price',
        'unit',
        'text',
        'image',
        'team',
        'title',
        'description',
        'menu',
        'head',
        'rubric',
        'age',
        'service',
        'phone',
        'mail',
        'address',
        'seotitle',
        'seodescription',
        'file',
        'video',
        'link',
        'post',
        'date',
        'feedback',
        'permission',
        'role',
        'partner',
        'client',
        'rule',
        'seoquery',
        'position',
        'resource',
        'tag',
        'jobs',
        'catalog',
        'discount',
        'business',
        'key',
        'cart',
        'brand',
        'visit',
        'technology',
        'migrations'
    ];

    public function up(): void
    {
        // Check every target before any DDL: never silently accept a partial/foreign schema.
        foreach (self::TABLES as $name) {
            if (Schema::hasTable($name)) {
                throw new RuntimeException("Recovery requires absent legacy tables; found: {$name}");
            }
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 36)->default('1');
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->timestamp('email_verified_at')->nullable();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('password_resets', function (Blueprint $table): void {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->numericMorphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('property', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value')->nullable();
            $table->string('slug', 255)->nullable();
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('category', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('product', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255)->nullable();
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('project', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('price', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->decimal('value', 20, 2);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('unit', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('text', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->text('value');
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('image', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('hash', 255);
            $table->string('alt', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('team', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('title', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('description', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value');
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('menu', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('head', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('rubric', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('age', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('service', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('phone', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('mail', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('address', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('seotitle', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value')->nullable();
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('seodescription', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value');
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('file', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255)->nullable();
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('video', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('link', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('post', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('date', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('feedback', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('permission', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('role', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('partner', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('client', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('rule', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('seoquery', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('position', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('value')->nullable()->default(0);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('resource', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('tag', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('catalog', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('discount', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('business', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value')->nullable();
            $table->string('slug', 255)->nullable();
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('key', function (Blueprint $table): void {
            $table->increments('id');
            $table->boolean('is_active')->default(false);
            $table->string('value')->nullable();
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cart', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->timestamps();
            $table->string('product_id', 55);
            $table->string('session_user', 55);
        });

        Schema::create('brand', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('visit', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        Schema::create('technology', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 36)->default('1');
            $table->boolean('is_active')->default(true);
            $table->string('value', 255);
            $table->string('slug', 255);
            $table->nullableNumericMorphs('parentable');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['key', 'parentable_type', 'parentable_id']);
        });

        // Historical journal only; Laravel uses orbita_migrations in the API project.
        Schema::create('migrations', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            Schema::dropIfExists($name);
        }
    }
};
