<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('booking_number', 32)->unique();
            $table->foreignUlid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUlid('turf_id')->constrained('turfs')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignUlid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedBigInteger('price_taka');
            $table->string('price_type', 20)->default('regular');
            $table->string('status', 20)->default('pending');
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('payment_method', 30)->nullable();
            $table->string('transaction_reference', 120)->nullable();
            $table->text('customer_note')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['business_id', 'booking_date']);
            $table->index(['turf_id', 'booking_date', 'start_time', 'end_time']);
            $table->index(['customer_id', 'booking_date']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'payment_status']);
        });

        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('pending', 'confirmed', 'rejected', 'cancelled', 'completed'))");
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_payment_status_check CHECK (payment_status IN ('unpaid', 'pending', 'paid', 'failed', 'refunded'))");
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_time_order_check CHECK (start_time < end_time)");
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
