<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'booking_number', 'business_id', 'turf_id', 'customer_id', 'created_by_user_id',
        'booking_date', 'start_time', 'end_time', 'duration_minutes', 'price_taka', 'amount_received_taka',
        'price_type', 'status', 'payment_status', 'payment_method', 'transaction_reference',
        'customer_note', 'rejection_reason', 'cancellation_reason', 'confirmed_at',
        'cancelled_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date:Y-m-d',
            'duration_minutes' => 'integer',
            'price_taka' => 'integer',
            'amount_received_taka' => 'integer',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function business() { return $this->belongsTo(Business::class); }
    public function turf() { return $this->belongsTo(Turf::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
