<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PayrollLeaveType extends Model
{
    use HasUlids;

    protected $table = 'payroll_leave_types';
    protected $fillable = ['gym_id', 'name', 'days_per_year', 'is_paid', 'is_active', 'created_by', 'updated_by'];

    protected function casts(): array { return ['days_per_year' => 'decimal:1', 'is_paid' => 'boolean', 'is_active' => 'boolean']; }
    public function business() { return $this->belongsTo(Business::class, 'gym_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }
    public function applications() { return $this->hasMany(PayrollLeaveApplication::class, 'leave_type_id'); }
}
