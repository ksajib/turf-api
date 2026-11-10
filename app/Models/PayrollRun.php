<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    use HasUlids;

    protected $table = 'payroll_runs';
    protected $fillable = ['gym_id', 'month', 'period_start', 'period_end', 'working_days', 'status', 'total_staff', 'total_gross', 'total_deduction', 'total_net', 'total_paid', 'note', 'generated_by', 'finalized_by', 'finalized_at'];

    protected function casts(): array { return ['period_start' => 'date:Y-m-d', 'period_end' => 'date:Y-m-d', 'total_gross' => 'decimal:2', 'total_deduction' => 'decimal:2', 'total_net' => 'decimal:2', 'total_paid' => 'decimal:2', 'finalized_at' => 'datetime']; }
    public function business() { return $this->belongsTo(Business::class, 'gym_id'); }
    public function generatedBy() { return $this->belongsTo(User::class, 'generated_by'); }
    public function finalizedBy() { return $this->belongsTo(User::class, 'finalized_by'); }
    public function payslips() { return $this->hasMany(PayrollPayslip::class, 'payroll_run_id'); }
}
