<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $foreignKeys = [
        'payroll_settings_gym_id_foreign',
        'payroll_shifts_gym_id_foreign',
        'payroll_holidays_gym_id_foreign',
        'payroll_leave_types_gym_id_foreign',
        'payroll_salary_structures_gym_id_foreign',
        'payroll_runs_gym_id_foreign',
        'payroll_payslips_gym_id_foreign',
        'payroll_payslip_items_gym_id_foreign',
        'payroll_advances_gym_id_foreign',
        'payroll_leave_applications_gym_id_foreign',
        'payroll_advance_deductions_gym_id_foreign',
    ];

    private array $indexes = [
        'payroll_shifts_gym_id_index',
        'payroll_leave_types_gym_id_index',
        'payroll_salary_structures_gym_id_index',
        'payroll_payslips_gym_id_month_index',
        'payroll_payslip_items_gym_id_index',
        'payroll_advances_gym_id_status_index',
        'payroll_leave_applications_gym_id_status_index',
    ];

    private array $uniqueConstraints = [
        ['table' => 'payroll_settings', 'old' => 'payroll_settings_gym_id_unique', 'new' => 'payroll_settings_business_id_unique'],
        ['table' => 'payroll_runs', 'old' => 'payroll_runs_gym_id_month_unique', 'new' => 'payroll_runs_business_id_month_unique'],
        ['table' => 'payroll_holidays', 'old' => 'payroll_holidays_gym_id_from_date_to_date_unique', 'new' => 'payroll_holidays_business_id_from_date_to_date_unique'],
    ];

    public function up(): void
    {
        foreach ($this->foreignKeys as $constraint) {
            DB::statement(sprintf('ALTER TABLE "%s" RENAME CONSTRAINT "%s" TO "%s"', $this->tableFromConstraint($constraint), $constraint, str_replace('_gym_id_', '_business_id_', $constraint)));
        }

        foreach ($this->indexes as $index) {
            DB::statement(sprintf('ALTER INDEX "%s" RENAME TO "%s"', $index, str_replace('_gym_id_', '_business_id_', $index)));
        }

        foreach ($this->uniqueConstraints as $constraint) {
            DB::statement(sprintf('ALTER TABLE "%s" RENAME CONSTRAINT "%s" TO "%s"', $constraint['table'], $constraint['old'], $constraint['new']));
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->uniqueConstraints) as $constraint) {
            DB::statement(sprintf('ALTER TABLE "%s" RENAME CONSTRAINT "%s" TO "%s"', $constraint['table'], $constraint['new'], $constraint['old']));
        }

        foreach (array_reverse($this->indexes) as $index) {
            DB::statement(sprintf('ALTER INDEX "%s" RENAME TO "%s"', str_replace('_gym_id_', '_business_id_', $index), $index));
        }

        foreach (array_reverse($this->foreignKeys) as $constraint) {
            DB::statement(sprintf('ALTER TABLE "%s" RENAME CONSTRAINT "%s" TO "%s"', $this->tableFromConstraint($constraint), str_replace('_gym_id_', '_business_id_', $constraint), $constraint));
        }
    }

    private function tableFromConstraint(string $constraint): string
    {
        return str($constraint)->before('_gym_id_foreign')->toString();
    }
};
