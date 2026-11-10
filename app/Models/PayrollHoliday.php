<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PayrollHoliday extends Model
{
    use HasUlids;

    protected $table = 'payroll_holidays';
    protected $fillable = ['gym_id', 'holiday_date', 'title', 'created_by'];

    protected function casts(): array { return ['holiday_date' => 'date:Y-m-d']; }
    public function business() { return $this->belongsTo(Business::class, 'gym_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
