<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Turf extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'business_id', 'name', 'sport', 'format', 'capacity', 'location',
        'regular_price_taka', 'peak_price_taka', 'peak_start_time', 'peak_end_time',
        'slot_duration_minutes', 'buffer_minutes', 'opening_time', 'closing_time',
        'closed_days', 'status', 'status_reason', 'feature_image_path',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'regular_price_taka' => 'integer',
            'peak_price_taka' => 'integer',
            'slot_duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'closed_days' => 'array',
        ];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
