<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('manual_subscription_payments', function (Blueprint $table): void {
            $table->string('invoice_number', 80)->nullable()->unique()->after('id');
            $table->string('invoice_status', 20)->nullable()->index()->after('status');
            $table->timestampTz('invoice_issued_at')->nullable()->after('invoice_status');
        });
    }

    public function down(): void
    {
        Schema::table('manual_subscription_payments', function (Blueprint $table): void {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn(['invoice_number', 'invoice_status', 'invoice_issued_at']);
        });
    }
};
