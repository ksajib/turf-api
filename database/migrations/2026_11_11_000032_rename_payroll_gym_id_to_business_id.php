<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'payroll_settings',
        'payroll_shifts',
        'payroll_holidays',
        'payroll_leave_types',
        'payroll_salary_structures',
        'payroll_runs',
        'payroll_payslips',
        'payroll_payslip_items',
        'payroll_advances',
        'payroll_leave_applications',
        'payroll_advance_deductions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            DB::statement(sprintf('ALTER TABLE "%s" RENAME COLUMN "gym_id" TO "business_id"', $table));
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            DB::statement(sprintf('ALTER TABLE "%s" RENAME COLUMN "business_id" TO "gym_id"', $table));
        }
    }
};
