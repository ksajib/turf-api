<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PayrollSalaryStructure extends Model
{
    use HasUlids;

    protected $table = 'payroll_salary_structures';
    protected $fillable = ['business_id', 'staff_type', 'staff_id', 'shift_id', 'pay_type', 'basic', 'house_rent', 'medical', 'conveyance', 'other_allowance', 'fixed_deduction', 'per_session_rate', 'commission_percent', 'bank_name', 'account_no', 'mobile_banking_no', 'effective_from', 'is_active', 'remark', 'created_by', 'updated_by'];

    protected function casts(): array { return ['basic' => 'decimal:2', 'house_rent' => 'decimal:2', 'medical' => 'decimal:2', 'conveyance' => 'decimal:2', 'other_allowance' => 'decimal:2', 'fixed_deduction' => 'decimal:2', 'per_session_rate' => 'decimal:2', 'commission_percent' => 'decimal:2', 'effective_from' => 'date:Y-m-d', 'is_active' => 'boolean']; }
    public function business() { return $this->belongsTo(Business::class, 'business_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }
    public function shift() { return $this->belongsTo(PayrollShift::class, 'shift_id'); }
}
