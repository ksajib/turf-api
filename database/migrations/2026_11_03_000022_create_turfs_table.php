<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('turfs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('sport', 40);
            $table->string('format', 40);
            $table->unsignedInteger('capacity');
            $table->string('location', 160);
            $table->unsignedBigInteger('regular_price_paisa');
            $table->unsignedBigInteger('peak_price_paisa');
            $table->time('peak_start_time');
            $table->time('peak_end_time');
            $table->unsignedSmallInteger('slot_duration_minutes')->default(60);
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->time('opening_time');
            $table->time('closing_time');
            $table->jsonb('closed_days')->default('[]');
            $table->string('status', 20)->default('available');
            $table->string('status_reason', 255)->nullable();
            $table->string('feature_image_path')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'sport']);
            $table->index('deleted_at');
        });

        DB::statement('CREATE UNIQUE INDEX turfs_business_name_active_unique ON turfs (business_id, name) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS turfs_business_name_active_unique');
        Schema::dropIfExists('turfs');
    }
};
