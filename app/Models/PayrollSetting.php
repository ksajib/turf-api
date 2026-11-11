<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    use HasUlids;

    protected $table = 'payroll_settings';

    protected $fillable = ['business_id', 'weekly_off_days', 'per_day_basis', 'late_grace_minutes', 'late_rule', 'lates_per_day_cut', 'late_fine_amount', 'absent_deduction', 'overtime_enabled', 'overtime_rate_per_hour', 'salary_pay_day', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['absent_deduction' => 'boolean', 'overtime_enabled' => 'boolean', 'late_fine_amount' => 'decimal:2', 'overtime_rate_per_hour' => 'decimal:2'];
    }

    public function business() { return $this->belongsTo(Business::class, 'business_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }
}
