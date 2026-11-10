<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PayrollPayslipItem extends Model
{
    use HasUlids;

    protected $table = 'payroll_payslip_items';
    protected $fillable = ['gym_id', 'payslip_id', 'item_type', 'code', 'title', 'amount', 'is_manual', 'created_by'];

    protected function casts(): array { return ['amount' => 'decimal:2', 'is_manual' => 'boolean']; }
    public function business() { return $this->belongsTo(Business::class, 'gym_id'); }
    public function payslip() { return $this->belongsTo(PayrollPayslip::class, 'payslip_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
