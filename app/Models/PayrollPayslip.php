<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PayrollPayslip extends Model
{
    use HasUlids;

    protected $table = 'payroll_payslips';
    protected $fillable = ['gym_id', 'payroll_run_id', 'payslip_no', 'staff_type', 'staff_id', 'staff_code', 'staff_name', 'month', 'working_days', 'present_days', 'paid_leave_days', 'unpaid_leave_days', 'absent_days', 'late_count', 'early_leave_count', 'worked_minutes', 'overtime_minutes', 'per_day_rate', 'gross_salary', 'overtime_amount', 'bonus_amount', 'total_earning', 'absent_deduction', 'late_deduction', 'advance_deduction', 'other_deduction', 'total_deduction', 'net_salary', 'paid_amount', 'payment_status', 'payment_method', 'payment_reference', 'paid_date', 'expense_id', 'cash_ledger_id', 'is_manually_edited', 'note', 'paid_by'];

    protected function casts(): array { return ['present_days' => 'decimal:1', 'paid_leave_days' => 'decimal:1', 'unpaid_leave_days' => 'decimal:1', 'absent_days' => 'decimal:1', 'per_day_rate' => 'decimal:2', 'gross_salary' => 'decimal:2', 'overtime_amount' => 'decimal:2', 'bonus_amount' => 'decimal:2', 'total_earning' => 'decimal:2', 'absent_deduction' => 'decimal:2', 'late_deduction' => 'decimal:2', 'advance_deduction' => 'decimal:2', 'other_deduction' => 'decimal:2', 'total_deduction' => 'decimal:2', 'net_salary' => 'decimal:2', 'paid_amount' => 'decimal:2', 'paid_date' => 'date:Y-m-d', 'is_manually_edited' => 'boolean']; }
    public function business() { return $this->belongsTo(Business::class, 'gym_id'); }
    public function payrollRun() { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }
    public function paidBy() { return $this->belongsTo(User::class, 'paid_by'); }
    public function items() { return $this->hasMany(PayrollPayslipItem::class, 'payslip_id'); }
    public function advanceDeductions() { return $this->hasMany(PayrollAdvanceDeduction::class, 'payslip_id'); }
}
