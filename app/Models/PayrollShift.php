<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PayrollShift extends Model
{
    use HasUlids;

    protected $table = 'payroll_shifts';
    protected $fillable = ['gym_id', 'name', 'start_time', 'end_time', 'grace_minutes', 'weekly_off_days', 'is_active', 'created_by', 'updated_by'];

    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function business() { return $this->belongsTo(Business::class, 'gym_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }
}
