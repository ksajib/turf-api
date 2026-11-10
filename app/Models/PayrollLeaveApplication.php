<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PayrollLeaveApplication extends Model
{
    use HasUlids;

    protected $table = 'payroll_leave_applications';
    protected $fillable = ['gym_id', 'staff_type', 'staff_id', 'leave_type_id', 'from_date', 'to_date', 'days', 'reason', 'status', 'approved_by', 'approved_at', 'reject_reason', 'created_by'];

    protected function casts(): array { return ['from_date' => 'date:Y-m-d', 'to_date' => 'date:Y-m-d', 'days' => 'decimal:1', 'approved_at' => 'datetime']; }
    public function business() { return $this->belongsTo(Business::class, 'gym_id'); }
    public function leaveType() { return $this->belongsTo(PayrollLeaveType::class, 'leave_type_id'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
