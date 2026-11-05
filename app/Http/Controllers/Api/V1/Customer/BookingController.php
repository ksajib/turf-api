<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Turf;
use App\Services\SslCommerzService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'customer', 403, 'Customer authentication required.');
        $customer = $user->customerProfile;
        if (! $customer) return response()->json(['data' => []]);
        $query = Booking::query()->with(['business:id,name', 'turf:id,name,location'])->where('customer_id', $customer->id);
        if ($request->filled('status') && $request->input('status') !== 'all') $query->where('status', $request->input('status'));
        if ($request->filled('search')) $query->where(function ($builder) use ($request): void { $term = trim((string) $request->input('search')); $builder->where('booking_number', 'ilike', '%'.$term.'%')->orWhereHas('turf', fn ($turf) => $turf->where('name', 'ilike', '%'.$term.'%')); });
        return response()->json(['data' => $query->latest('booking_date')->latest('start_time')->get()->map(fn (Booking $booking) => $this->present($booking))->values()]);
    }

    public function payments(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'customer', 403, 'Customer authentication required.');
        $customer = $user->customerProfile;
        if (! $customer) return response()->json(['data' => [], 'meta' => ['total' => 0]]);

        $query = Booking::query()->with(['business:id,name', 'turf:id,name,location'])->where('customer_id', $customer->id);
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $status = $request->input('status');
            $status === 'pending' ? $query->whereIn('payment_status', ['unpaid', 'pending']) : $query->where('payment_status', $status);
        }
        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($term): void {
                $builder->where('booking_number', 'ilike', '%'.$term.'%')->orWhereHas('turf', fn ($turf) => $turf->where('name', 'ilike', '%'.$term.'%'));
            });
        }
        $payments = $query->latest('created_at')->get();
        return response()->json(['data' => $payments->map(fn (Booking $booking) => $this->presentPayment($booking))->values(), 'meta' => ['total' => $payments->count()]]);
    }

    public function show(Request $request, Booking $booking)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'customer', 403, 'Customer authentication required.');
        abort_unless($booking->customer?->user_id === $user->id, 404, 'Booking not found.');
        return response()->json(['data' => $this->present($booking->load(['business:id,name', 'turf:id,name,location']))]);
    }

    public function cancel(Request $request, Booking $booking)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'customer', 403, 'Customer authentication required.');
        abort_unless($booking->customer?->user_id === $user->id, 404, 'Booking not found.');
        abort_unless(in_array($booking->status, ['pending', 'confirmed'], true), 422, 'This booking cannot be cancelled.');
        $booking->update(['status' => 'cancelled', 'cancellation_reason' => 'Cancelled by customer', 'cancelled_at' => now()]);
        return response()->json(['message' => 'Booking cancelled successfully.']);
    }

    public function reschedule(Request $request, Booking $booking)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'customer', 403, 'Customer authentication required.');
        abort_unless($booking->customer?->user_id === $user->id, 404, 'Booking not found.');
        abort_unless(in_array($booking->status, ['pending', 'confirmed'], true), 422, 'This booking cannot be rescheduled.');
        $data = $request->validate(['booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time']]);
        DB::transaction(function () use ($data, $booking): void {
            $current = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $turf = Turf::query()->whereKey($current->turf_id)->where('status', 'available')->lockForUpdate()->firstOrFail();
            $date = CarbonImmutable::parse($data['booking_date']);
            abort_unless(! in_array($date->format('D'), $turf->closed_days ?? [], true), 422, 'This turf is closed on the selected day.');
            $start = CarbonImmutable::createFromFormat('H:i', $data['start_time']);
            $end = CarbonImmutable::createFromFormat('H:i', $data['end_time']);
            $duration = $start->diffInMinutes($end);
            abort_unless($duration >= $turf->slot_duration_minutes, 422, 'Booking duration is shorter than the turf slot duration.');
            $overlap = Booking::query()->where('turf_id', $turf->id)->where('id', '!=', $current->id)->whereDate('booking_date', $data['booking_date'])->whereIn('status', ['pending', 'confirmed'])->where('start_time', '<', $data['end_time'])->where('end_time', '>', $data['start_time'])->lockForUpdate()->exists();
            abort_if($overlap, 422, 'This turf is already booked for the selected time.');
            $peak = $data['start_time'] >= substr((string) $turf->peak_start_time, 0, 5) && $data['end_time'] <= substr((string) $turf->peak_end_time, 0, 5);
            $current->update(['booking_date' => $data['booking_date'], 'start_time' => $data['start_time'], 'end_time' => $data['end_time'], 'duration_minutes' => $duration, 'price_taka' => $peak ? $turf->peak_price_taka : $turf->regular_price_taka, 'price_type' => $peak ? 'peak' : 'regular', 'payment_status' => 'unpaid', 'amount_received_taka' => null, 'payment_method' => null, 'transaction_reference' => null]);
        });
        return response()->json(['data' => $this->present($booking->refresh()->load(['business:id,name', 'turf:id,name,location'])), 'message' => 'Booking rescheduled successfully.']);
    }

    public function payment(Request $request, Booking $booking, SslCommerzService $sslCommerz)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'customer', 403, 'Customer authentication required.');
        abort_unless($booking->customer?->user_id === $user->id, 404, 'Booking not found.');
        abort_unless(! in_array($booking->status, ['cancelled', 'rejected'], true), 422, 'This booking cannot be paid.');
        abort_unless($booking->payment_status !== 'paid', 422, 'This booking is already paid.');
        $booking->load(['business:id,name', 'turf:id,name,location']);
        $booking->update(['payment_status' => 'pending', 'payment_method' => 'sslcommerz']);
        try {
            $gateway = $sslCommerz->initiate(['total_amount' => $booking->price_taka, 'tran_id' => $booking->booking_number, 'booking_id' => $booking->id, 'success_url' => config('services.sslcommerz.callback_url').'/success', 'fail_url' => config('services.sslcommerz.callback_url').'/fail', 'cancel_url' => config('services.sslcommerz.callback_url').'/cancel', 'ipn_url' => config('services.sslcommerz.callback_url').'/ipn', 'cus_name' => $user->name, 'cus_email' => $user->email ?: 'customer@example.com', 'cus_phone' => $user->phone ?: '01700000000', 'cus_add1' => $booking->turf?->location ?: 'Bangladesh', 'product_name' => ($booking->turf?->name ?: 'Turf booking').' - '.$booking->booking_number]);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
        return response()->json(['data' => ['booking_id' => $booking->id, 'booking_number' => $booking->booking_number, 'amount_taka' => (int) $booking->price_taka, 'gateway_url' => $gateway['GatewayPageURL']], 'message' => 'Payment session created.']);
    }

    private function present(Booking $booking): array
    {
        return ['id' => $booking->id, 'booking_number' => $booking->booking_number, 'business' => $booking->business?->name, 'turf_id' => $booking->turf_id, 'turf' => $booking->turf?->name, 'location' => $booking->turf?->location, 'booking_date' => $booking->booking_date?->format('Y-m-d'), 'start_time' => substr((string) $booking->start_time, 0, 5), 'end_time' => substr((string) $booking->end_time, 0, 5), 'price_taka' => (int) $booking->price_taka, 'amount_received_taka' => (int) ($booking->amount_received_taka ?? 0), 'status' => $booking->status, 'payment_status' => $booking->payment_status, 'payment_method' => $booking->payment_method, 'created_at' => $booking->created_at?->toISOString()];
    }

    private function presentPayment(Booking $booking): array
    {
        return ['id' => $booking->id, 'booking_number' => $booking->booking_number, 'business' => $booking->business?->name, 'turf' => $booking->turf?->name, 'location' => $booking->turf?->location, 'booking_date' => $booking->booking_date?->format('Y-m-d'), 'price_taka' => (int) $booking->price_taka, 'amount_received_taka' => (int) ($booking->amount_received_taka ?? 0), 'payment_status' => $booking->payment_status, 'payment_method' => $booking->payment_method, 'transaction_reference' => $booking->transaction_reference, 'created_at' => $booking->created_at?->toISOString()];
    }
}
