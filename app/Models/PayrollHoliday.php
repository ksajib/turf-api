<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PayrollHoliday extends Model
{
    use HasUlids;

    protected $table = 'payroll_holidays';
    protected $fillable = ['business_id', 'from_date', 'to_date', 'title', 'created_by'];

    protected function casts(): array { return ['from_date' => 'date:Y-m-d', 'to_date' => 'date:Y-m-d']; }
    public function business() { return $this->belongsTo(Business::class, 'business_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
