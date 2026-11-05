<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'staff';

    protected $fillable = [
        'user_id', 'business_id', 'employee_code', 'designation', 'joining_date',
        'address', 'emergency_contact_name', 'emergency_contact_phone', 'avatar_path', 'notes',
    ];

    protected function casts(): array
    {
        return ['joining_date' => 'date:Y-m-d'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function business() { return $this->belongsTo(Business::class); }

    public function turfs() { return $this->belongsToMany(Turf::class, 'staff_turf_access'); }
}
