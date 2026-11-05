<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Booking;
use App\Models\Turf;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OwnerWorkspaceController extends Controller
{
    public function profile(Request $request)
    {
        $user = $this->user($request);
        $business = $this->business($user);
        return response()->json(['data' => $this->workspace($user, $business)]);
    }

    public function dashboard(Request $request)
    {
        $user = $this->user($request);
        $business = $this->business($user);
        $subscription = $business->activeSubscription?->load('planVersion');
        $today = now()->toDateString();
        $bookingQuery = Booking::query()->where('business_id', $business->id);
        $upcomingBookings = (clone $bookingQuery)
            ->with(['turf:id,name', 'customer:id,user_id', 'customer.user:id,name'])
            ->whereDate('booking_date', '>=', $today)
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->limit(5)
            ->get()
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'booking_date' => $booking->booking_date?->format('Y-m-d'),
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
                'price_taka' => $booking->price_taka,
                'status' => $booking->status,
                'turf' => $booking->turf?->name,
                'customer' => $booking->customer?->user?->name,
            ])
            ->values();
        return response()->json(['data' => [
            'business' => $this->workspace($user, $business),
            'metrics' => [
                'staff_count' => User::query()->where('business_id', $business->id)->where('role', '!=', 'business-owner')->where('status', 'active')->count(),
                'turf_count' => Turf::query()->where('business_id', $business->id)->count(),
                'booking_count' => (clone $bookingQuery)->count(),
                'today_booking_count' => (clone $bookingQuery)->whereDate('booking_date', $today)->whereNotIn('status', ['rejected', 'cancelled'])->count(),
                'revenue_taka' => (clone $bookingQuery)->whereIn('status', ['confirmed', 'completed'])->sum('price_taka'),
                'pending_payments' => (clone $bookingQuery)->whereIn('payment_status', ['unpaid', 'pending'])->count(),
                'available_turf_count' => Turf::query()->where('business_id', $business->id)->where('status', 'available')->count(),
            ],
            'upcoming_bookings' => $upcomingBookings,
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'expires_at' => $subscription->expires_at?->toISOString(),
                'plan' => $subscription->planVersion?->name,
                'limits' => ['turfs' => $subscription->planVersion?->max_turfs, 'branches' => $subscription->planVersion?->max_branches, 'staff' => $subscription->planVersion?->max_staff],
            ] : null,
        ]]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN, 'Owner authentication required.');
        return $user;
    }

    private function business(User $user): Business
    {
        $business = $user->business_id
            ? Business::query()->whereKey($user->business_id)->with('activeSubscription.planVersion')->first()
            : $user->businesses()->with('activeSubscription.planVersion')->first();
        abort_unless($business, Response::HTTP_FORBIDDEN, 'No business is assigned to this account.');
        return $business;
    }

    private function workspace(User $user, Business $business): array
    {
        $subscription = $business->activeSubscription;
        $plan = $subscription?->planVersion;
        return [
            'owner' => ['id' => $user->id, 'name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'phone' => $user->phone],
            'business' => ['id' => $business->id, 'name' => $business->name, 'slug' => $business->slug, 'phone' => $business->phone, 'email' => $business->email, 'address' => $business->address, 'district_city' => $business->district_city, 'status' => $business->status],
            'subscription' => $subscription ? ['id' => $subscription->id, 'plan' => $plan?->name, 'billing_cycle' => $subscription->billing_cycle, 'amount_paisa' => $subscription->price_paisa, 'currency' => $subscription->currency, 'expires_at' => $subscription->expires_at?->toISOString(), 'limits' => ['turfs' => $plan?->max_turfs, 'branches' => $plan?->max_branches, 'staff' => $plan?->max_staff]] : null,
        ];
    }
}
