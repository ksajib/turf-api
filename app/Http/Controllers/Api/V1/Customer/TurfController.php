<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Turf;
use App\Services\SslCommerzService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TurfController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today']]);
        $date = $request->input('date', now()->toDateString());
        $query = Turf::query()->where('status', 'available')->orderBy('name');
        if ($request->filled('search')) $query->where(fn ($q) => $q->where('name', 'ilike', '%'.$request->input('search').'%')->orWhere('sport', 'ilike', '%'.$request->input('search').'%')->orWhere('location', 'ilike', '%'.$request->input('search').'%'));
        if ($request->filled('sport') && $request->input('sport') !== 'all') $query->where('sport', $request->input('sport'));
        if ($request->filled('location') && $request->input('location') !== 'all') $query->where('location', $request->input('location'));
        $turfs = $query->get();
        return response()->json(['data' => $turfs->map(fn (Turf $turf) => $this->presentTurf($turf, $date))->values(), 'meta' => ['date' => $date, 'sports' => Turf::where('status', 'available')->distinct()->orderBy('sport')->pluck('sport')->values(), 'locations' => Turf::where('status', 'available')->distinct()->orderBy('location')->pluck('location')->values()]]);
    }

    public function slots(Request $request)
    {
        $data = $request->validate(['turf_id' => ['required', 'string', 'exists:turfs,id'], 'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today']]);
        $turf = Turf::whereKey($data['turf_id'])->where('status', 'available')->firstOrFail();
        return response()->json(['data' => $this->availableSlots($turf, $data['date'])]);
    }

    public function storeBooking(Request $request, SslCommerzService $sslCommerz)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'customer', 403, 'Customer authentication required.');
        $customer = $user->customerProfile;
        abort_unless($customer instanceof Customer, 422, 'Customer profile is not available.');
        $data = $request->validate(['turf_id' => ['required', 'string', 'exists:turfs,id'], 'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time'], 'payment_method' => ['nullable', 'string', 'max:30'], 'transaction_reference' => ['nullable', 'string', 'max:120'], 'customer_note' => ['nullable', 'string', 'max:1000']]);
        $booking = DB::transaction(function () use ($data, $customer, $user): Booking {
            $turf = Turf::whereKey($data['turf_id'])->where('status', 'available')->lockForUpdate()->firstOrFail();
            $date = CarbonImmutable::parse($data['booking_date']);
            abort_unless(! in_array($date->format('D'), $turf->closed_days ?? [], true), 422, 'This turf is closed on the selected day.');
            $start = CarbonImmutable::createFromFormat('H:i', $data['start_time']);
            $end = CarbonImmutable::createFromFormat('H:i', $data['end_time']);
            $duration = $start->diffInMinutes($end);
            abort_unless($duration >= $turf->slot_duration_minutes, 422, 'Booking duration is shorter than the turf slot duration.');
            $overlap = Booking::where('turf_id', $turf->id)->whereDate('booking_date', $data['booking_date'])->whereIn('status', ['pending', 'confirmed'])->where('start_time', '<', $data['end_time'])->where('end_time', '>', $data['start_time'])->lockForUpdate()->exists();
            abort_if($overlap, 422, 'This turf is already booked for the selected time.');
            $peak = $data['start_time'] >= substr((string) $turf->peak_start_time, 0, 5) && $data['end_time'] <= substr((string) $turf->peak_end_time, 0, 5);
            return Booking::create(['booking_number' => 'BK-'.now()->format('Ymd').'-'.str()->upper(str()->random(6)), 'business_id' => $turf->business_id, 'turf_id' => $turf->id, 'customer_id' => $customer->id, 'created_by_user_id' => $user->id, 'booking_date' => $data['booking_date'], 'start_time' => $data['start_time'], 'end_time' => $data['end_time'], 'duration_minutes' => $duration, 'price_taka' => $peak ? $turf->peak_price_taka : $turf->regular_price_taka, 'price_type' => $peak ? 'peak' : 'regular', 'payment_method' => $data['payment_method'] ?? null, 'transaction_reference' => $data['transaction_reference'] ?? null, 'customer_note' => $data['customer_note'] ?? null]);
        });
        $booking->load(['business:id,name', 'turf:id,name,location']);
        $booking->update(['payment_status' => 'pending', 'payment_method' => 'sslcommerz']);
        try {
            $gateway = $sslCommerz->initiate([
                'total_amount' => $booking->price_taka,
                'tran_id' => $booking->booking_number,
                'booking_id' => $booking->id,
                'success_url' => config('services.sslcommerz.callback_url').'/success',
                'fail_url' => config('services.sslcommerz.callback_url').'/fail',
                'cancel_url' => config('services.sslcommerz.callback_url').'/cancel',
                'ipn_url' => config('services.sslcommerz.callback_url').'/ipn',
                'cus_name' => $user->name,
                'cus_email' => $user->email ?: 'customer@example.com',
                'cus_phone' => $user->phone ?: '01700000000',
                'cus_add1' => $booking->turf?->location ?: 'Bangladesh',
                'product_name' => ($booking->turf?->name ?: 'Turf booking').' - '.$booking->booking_number,
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
        return response()->json(['data' => ['booking_id' => $booking->id, 'booking_number' => $booking->booking_number, 'amount_taka' => (int) $booking->price_taka, 'gateway_url' => $gateway['GatewayPageURL']], 'message' => 'Payment session created.'], 201);
    }

    private function presentTurf(Turf $turf, string $date): array
    {
        $slots = $this->availableSlots($turf, $date);
        return ['id' => $turf->id, 'name' => $turf->name, 'sport' => $turf->sport, 'format' => $turf->format, 'location' => $turf->location, 'regular_price_taka' => (int) $turf->regular_price_taka, 'peak_price_taka' => (int) $turf->peak_price_taka, 'status' => $turf->status, 'available_slots' => count($slots), 'feature_image_url' => $turf->feature_image_path ? asset('storage/'.$turf->feature_image_path) : null];
    }

    private function availableSlots(Turf $turf, string $date): array
    {
        if (in_array(CarbonImmutable::parse($date)->format('D'), $turf->closed_days ?? [], true)) return [];
        $busy = Booking::where('turf_id', $turf->id)->whereDate('booking_date', $date)->whereIn('status', ['pending', 'confirmed'])->get(['start_time', 'end_time']);
        $open = CarbonImmutable::createFromFormat('H:i:s', (string) $turf->opening_time);
        $close = CarbonImmutable::createFromFormat('H:i:s', (string) $turf->closing_time);
        $step = (int) $turf->slot_duration_minutes + (int) $turf->buffer_minutes;
        $slots = [];
        for ($start = $open; $start->addMinutes((int) $turf->slot_duration_minutes)->lessThanOrEqualTo($close); $start = $start->addMinutes($step)) {
            $end = $start->addMinutes((int) $turf->slot_duration_minutes);
            $startTime = $start->format('H:i'); $endTime = $end->format('H:i');
            if ($busy->contains(fn (Booking $booking) => substr((string) $booking->start_time, 0, 5) < $endTime && substr((string) $booking->end_time, 0, 5) > $startTime)) continue;
            $peak = $startTime >= substr((string) $turf->peak_start_time, 0, 5) && $endTime <= substr((string) $turf->peak_end_time, 0, 5);
            $slots[] = ['start_time' => $startTime, 'end_time' => $endTime, 'label' => $start->format('g:i A').' – '.$end->format('g:i A'), 'price_taka' => $peak ? (int) $turf->peak_price_taka : (int) $turf->regular_price_taka];
        }
        return $slots;
    }
}
