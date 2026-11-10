<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PayrollAdvance extends Model
{
    use HasUlids;

    protected $table = 'payroll_advances';
    protected $fillable = ['gym_id', 'staff_type', 'staff_id', 'advance_type', 'amount', 'given_date', 'monthly_installment', 'deduct_from_month', 'paid_back', 'remaining', 'status', 'payment_method', 'cash_ledger_id', 'note', 'created_by'];

    protected function casts(): array { return ['amount' => 'decimal:2', 'given_date' => 'date:Y-m-d', 'monthly_installment' => 'decimal:2', 'paid_back' => 'decimal:2', 'remaining' => 'decimal:2']; }
    public function business() { return $this->belongsTo(Business::class, 'gym_id'); }
    public function paidBy() { return $this->belongsTo(User::class, 'paid_by'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function deductions() { return $this->hasMany(PayrollAdvanceDeduction::class, 'advance_id'); }
}
