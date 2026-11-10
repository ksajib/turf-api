<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gym_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('weekly_off_days', 50)->default('Friday');
            $table->enum('per_day_basis', ['working_days', 'fixed_30'])->default('fixed_30');
            $table->unsignedSmallInteger('late_grace_minutes')->default(10);
            $table->enum('late_rule', ['none', 'count', 'fine'])->default('none');
            $table->unsignedTinyInteger('lates_per_day_cut')->default(3);
            $table->decimal('late_fine_amount', 12, 2)->default(0);
            $table->boolean('absent_deduction')->default(true);
            $table->boolean('overtime_enabled')->default(false);
            $table->decimal('overtime_rate_per_hour', 12, 2)->default(0);
            $table->unsignedTinyInteger('salary_pay_day')->default(5);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique('gym_id');
        });

        Schema::create('payroll_shifts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gym_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('grace_minutes')->nullable();
            $table->string('weekly_off_days', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index('gym_id');
        });

        Schema::create('payroll_holidays', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gym_id')->constrained('businesses')->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('title', 150);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['gym_id', 'holiday_date']);
        });

        Schema::create('payroll_leave_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gym_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('days_per_year', 5, 1)->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index('gym_id');
        });

        Schema::create('payroll_salary_structures', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gym_id')->constrained('businesses')->cascadeOnDelete();
            $table->enum('staff_type', ['employee', 'trainer']);
            $table->string('staff_id', 26);
            $table->enum('pay_type', ['monthly', 'per_session', 'commission'])->default('monthly');
            $table->decimal('basic', 12, 2)->default(0);
            $table->decimal('house_rent', 12, 2)->default(0);
            $table->decimal('medical', 12, 2)->default(0);
            $table->decimal('conveyance', 12, 2)->default(0);
            $table->decimal('other_allowance', 12, 2)->default(0);
            $table->decimal('fixed_deduction', 12, 2)->default(0);
            $table->decimal('per_session_rate', 12, 2)->default(0);
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->string('bank_name', 100)->nullable();
            $table->string('account_no', 50)->nullable();
            $table->string('mobile_banking_no', 20)->nullable();
            $table->date('effective_from');
            $table->boolean('is_active')->default(true);
            $table->string('remark')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index('gym_id');
            $table->index(['staff_type', 'staff_id', 'effective_from']);
        });

        Schema::create('payroll_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gym_id')->constrained('businesses')->cascadeOnDelete();
            $table->char('month', 7);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedTinyInteger('working_days')->default(0);
            $table->enum('status', ['draft', 'finalized', 'paid'])->default('draft');
            $table->unsignedInteger('total_staff')->default(0);
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deduction', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->decimal('total_paid', 14, 2)->default(0);
            $table->string('note')->nullable();
            $table->foreignUlid('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampsTz();
            $table->unique(['gym_id', 'month']);
        });

        Schema::create('payroll_payslips', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gym_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUlid('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->string('payslip_no', 40)->unique();
            $table->enum('staff_type', ['employee', 'trainer']);
            $table->string('staff_id', 26);
            $table->string('staff_code', 50)->nullable();
            $table->string('staff_name', 150)->nullable();
            $table->char('month', 7);
            $table->unsignedTinyInteger('working_days')->default(0);
            $table->decimal('present_days', 5, 1)->default(0);
            $table->decimal('paid_leave_days', 5, 1)->default(0);
            $table->decimal('unpaid_leave_days', 5, 1)->default(0);
            $table->decimal('absent_days', 5, 1)->default(0);
            $table->unsignedSmallInteger('late_count')->default(0);
            $table->unsignedSmallInteger('early_leave_count')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->decimal('per_day_rate', 12, 2)->default(0);
            $table->decimal('gross_salary', 12, 2)->default(0);
            $table->decimal('overtime_amount', 12, 2)->default(0);
            $table->decimal('bonus_amount', 12, 2)->default(0);
            $table->decimal('total_earning', 12, 2)->default(0);
            $table->decimal('absent_deduction', 12, 2)->default(0);
            $table->decimal('late_deduction', 12, 2)->default(0);
            $table->decimal('advance_deduction', 12, 2)->default(0);
            $table->decimal('other_deduction', 12, 2)->default(0);
            $table->decimal('total_deduction', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->enum('payment_method', ['cash', 'bank', 'bkash', 'nagad', 'rocket', 'card', 'other'])->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->date('paid_date')->nullable();
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->unsignedBigInteger('cash_ledger_id')->nullable();
            $table->boolean('is_manually_edited')->default(false);
            $table->string('note')->nullable();
            $table->foreignUlid('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['payroll_run_id', 'staff_type', 'staff_id']);
            $table->index(['gym_id', 'month']);
            $table->index(['staff_type', 'staff_id']);
        });

        Schema::create('payroll_payslip_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gym_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUlid('payslip_id')->constrained('payroll_payslips')->cascadeOnDelete();
            $table->enum('item_type', ['earning', 'deduction']);
            $table->string('code', 40);
            $table->string('title', 150);
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('is_manual')->default(false);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index('payslip_id');
            $table->index('gym_id');
        });

        Schema::create('payroll_advances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gym_id')->constrained('businesses')->cascadeOnDelete();
            $table->enum('staff_type', ['employee', 'trainer']);
            $table->string('staff_id', 26);
            $table->enum('advance_type', ['advance', 'loan'])->default('advance');
            $table->decimal('amount', 12, 2);
            $table->date('given_date');
            $table->decimal('monthly_installment', 12, 2)->default(0);
            $table->char('deduct_from_month', 7);
            $table->decimal('paid_back', 12, 2)->default(0);
            $table->decimal('remaining', 12, 2)->default(0);
            $table->enum('status', ['running', 'closed'])->default('running');
            $table->enum('payment_method', ['cash', 'bank', 'bkash', 'nagad', 'rocket', 'card', 'other'])->default('cash');
            $table->unsignedBigInteger('cash_ledger_id')->nullable();
            $table->string('note')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['gym_id', 'status']);
            $table->index(['staff_type', 'staff_id']);
        });

        Schema::create('payroll_leave_applications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gym_id')->constrained('businesses')->cascadeOnDelete();
            $table->enum('staff_type', ['employee', 'trainer']);
            $table->string('staff_id', 26);
            $table->foreignUlid('leave_type_id')->constrained('payroll_leave_types')->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 5, 1)->default(1);
            $table->string('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['gym_id', 'status']);
            $table->index(['staff_type', 'staff_id', 'from_date', 'to_date']);
        });

        Schema::create('payroll_advance_deductions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gym_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUlid('advance_id')->constrained('payroll_advances')->cascadeOnDelete();
            $table->foreignUlid('payslip_id')->constrained('payroll_payslips')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestampsTz();
            $table->unique(['advance_id', 'payslip_id']);
            $table->index('payslip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_advance_deductions');
        Schema::dropIfExists('payroll_leave_applications');
        Schema::dropIfExists('payroll_advances');
        Schema::dropIfExists('payroll_payslip_items');
        Schema::dropIfExists('payroll_payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_salary_structures');
        Schema::dropIfExists('payroll_leave_types');
        Schema::dropIfExists('payroll_holidays');
        Schema::dropIfExists('payroll_shifts');
        Schema::dropIfExists('payroll_settings');
    }
};
