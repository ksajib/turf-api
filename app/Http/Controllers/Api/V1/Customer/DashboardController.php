<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function overview(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'customer', 403, 'Customer authentication required.');

        $customer = $user->customerProfile;
        if (! $customer) {
            return response()->json(['data' => $this->emptyOverview()]);
        }

        $query = Booking::query()->where('customer_id', $customer->id);
        $today = now()->toDateString();
        $present = fn (Booking $booking): array => [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'business' => $booking->business?->name,
            'turf' => $booking->turf?->name,
            'location' => $booking->turf?->location,
            'booking_date' => $booking->booking_date?->format('Y-m-d'),
            'start_time' => substr((string) $booking->start_time, 0, 5),
            'end_time' => substr((string) $booking->end_time, 0, 5),
            'price_taka' => (int) $booking->price_taka,
            'amount_received_taka' => (int) ($booking->amount_received_taka ?? 0),
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'payment_method' => $booking->payment_method,
        ];

        $upcoming = (clone $query)->with(['business:id,name', 'turf:id,name,location'])
            ->whereDate('booking_date', '>=', $today)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->orderBy('booking_date')->orderBy('start_time')->limit(5)->get()
            ->map($present)->values();

        $recent = (clone $query)->with(['business:id,name', 'turf:id,name,location'])
            ->latest('booking_date')->latest('start_time')->limit(8)->get()
            ->map($present)->values();

        return response()->json(['data' => [
            'stats' => [
                'total_bookings' => (clone $query)->count(),
                'upcoming_bookings' => (clone $query)->whereDate('booking_date', '>=', $today)->whereNotIn('status', ['cancelled', 'rejected'])->count(),
                'pending_payments' => (clone $query)->whereIn('payment_status', ['unpaid', 'pending'])->count(),
                'total_spent_taka' => (int) (clone $query)->whereIn('payment_status', ['paid'])->sum('price_taka'),
            ],
            'upcoming_bookings' => $upcoming,
            'recent_bookings' => $recent,
        ]]);
    }

    private function emptyOverview(): array
    {
        return [
            'stats' => ['total_bookings' => 0, 'upcoming_bookings' => 0, 'pending_payments' => 0, 'total_spent_taka' => 0],
            'upcoming_bookings' => [],
            'recent_bookings' => [],
        ];
    }
}
