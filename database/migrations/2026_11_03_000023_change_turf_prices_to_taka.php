<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('UPDATE turfs SET regular_price_paisa = regular_price_paisa / 100, peak_price_paisa = peak_price_paisa / 100');
        Schema::table('turfs', function ($table): void {
            $table->renameColumn('regular_price_paisa', 'regular_price_taka');
            $table->renameColumn('peak_price_paisa', 'peak_price_taka');
        });
    }

    public function down(): void
    {
        Schema::table('turfs', function ($table): void {
            $table->renameColumn('regular_price_taka', 'regular_price_paisa');
            $table->renameColumn('peak_price_taka', 'peak_price_paisa');
        });
        DB::statement('UPDATE turfs SET regular_price_paisa = regular_price_paisa * 100, peak_price_paisa = peak_price_paisa * 100');
    }
};
