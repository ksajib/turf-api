<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->boolean('pause_at_period_end')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('pause_at_period_end');
        });
    }
};
