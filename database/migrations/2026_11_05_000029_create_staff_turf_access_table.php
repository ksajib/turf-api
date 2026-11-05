<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_turf_access', function (Blueprint $table): void {
            $table->foreignUlid('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignUlid('turf_id')->constrained('turfs')->cascadeOnDelete();
            $table->timestampsTz();
            $table->primary(['staff_id', 'turf_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_turf_access');
    }
};
