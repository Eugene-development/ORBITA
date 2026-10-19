<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('orbita_media', fn (Blueprint $table) => $table->string('disk', 16)->default('s3')); }
    public function down(): void { Schema::table('orbita_media', fn (Blueprint $table) => $table->dropColumn('disk')); }
};
