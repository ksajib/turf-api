<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PayrollAdvanceDeduction extends Model
{
    use HasUlids;

    protected $table = 'payroll_advance_deductions';
    protected $fillable = ['business_id', 'advance_id', 'payslip_id', 'amount'];

    protected function casts(): array { return ['amount' => 'decimal:2']; }
    public function business() { return $this->belongsTo(Business::class, 'business_id'); }
    public function advance() { return $this->belongsTo(PayrollAdvance::class, 'advance_id'); }
    public function payslip() { return $this->belongsTo(PayrollPayslip::class, 'payslip_id'); }
}
