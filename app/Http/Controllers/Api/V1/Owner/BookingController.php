<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Turf;
use App\Models\User;
use App\Notifications\WorkspaceNotification;
use App\Services\OwnerBusinessScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function __construct(private readonly OwnerBusinessScope $scope) {}

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403, 'Owner authentication required.');
        abort_unless($user->role === 'business-owner' || $user->hasPermission('bookings.view') || $user->hasPermission('bookings.manage'), 403, 'You do not have permission to manage bookings.');
        return $user;
    }

    private function manage(Request $request): User
    {
        $user = $this->user($request);
        abort_unless($user->role === 'business-owner' || $user->hasPermission('bookings.manage'), 403, 'You do not have permission to manage bookings.');
        return $user;
    }

    private function present(Booking $booking): array
    {
        $booking->loadMissing(['turf', 'customer.user']);
        return [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'business_id' => $booking->business_id,
            'turf' => $booking->turf ? ['id' => $booking->turf->id, 'name' => $booking->turf->name, 'sport' => $booking->turf->sport, 'format' => $booking->turf->format, 'location' => $booking->turf->location] : null,
            'customer' => $booking->customer?->user ? ['id' => $booking->customer->id, 'name' => $booking->customer->user->name, 'phone' => $booking->customer->user->phone, 'email' => $booking->customer->user->email] : null,
            'booking_date' => $booking->booking_date?->format('Y-m-d'),
            'start_time' => substr((string) $booking->start_time, 0, 5),
            'end_time' => substr((string) $booking->end_time, 0, 5),
            'duration_minutes' => $booking->duration_minutes,
            'price_taka' => $booking->price_taka,
            'amount_received_taka' => $booking->amount_received_taka,
            'price_type' => $booking->price_type,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'payment_method' => $booking->payment_method,
            'transaction_reference' => $booking->transaction_reference,
            'customer_note' => $booking->customer_note,
            'rejection_reason' => $booking->rejection_reason,
            'cancellation_reason' => $booking->cancellation_reason,
            'confirmed_at' => $booking->confirmed_at?->toISOString(),
            'cancelled_at' => $booking->cancelled_at?->toISOString(),
            'completed_at' => $booking->completed_at?->toISOString(),
            'created_at' => $booking->created_at?->toISOString(),
        ];
    }

    private function bookingNumber(): string
    {
        do { $number = 'BK-'.now()->format('Ymd').'-'.strtoupper(str()->random(6)); } while (Booking::where('booking_number', $number)->exists());
        return $number;
    }

    private function notifyCustomer(Booking $booking, string $title, string $detail, string $type = 'info'): void
    {
        $booking->loadMissing(['customer.user', 'turf']);
        $customer = $booking->customer?->user;
        if ($customer) {
            $customer->notify(new WorkspaceNotification($title, $detail, $type, '/dashboard', ['booking_id' => $booking->id, 'booking_number' => $booking->booking_number]));
        }
    }

    private function notifyBusinessTeam(User $actor, string $title, string $detail, string $type = 'info'): void
    {
        User::query()->whereIn('business_id', $this->scope->ids($actor))->whereIn('role', ['business-owner', 'staff'])->whereKeyNot($actor->id)->get()->each(fn (User $user) => $user->notify(new WorkspaceNotification($title, $detail, $type, '/owner/bookings')));
    }

    private function rules(): array
    {
        return [
            'turf_id' => ['required', 'string', 'exists:turfs,id'],
            'customer_id' => ['required', 'string', 'exists:customers,id'],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function index(Request $request)
    {
        $user = $this->user($request);
        $query = Booking::with(['turf', 'customer.user'])->whereIn('business_id', $this->scope->ids($user))->latest('booking_date')->latest('start_time');
        if ($request->filled('status') && $request->input('status') !== 'all') $query->where('status', $request->input('status'));
        if ($request->filled('booking_date')) $query->whereDate('booking_date', $request->input('booking_date'));
        if ($request->filled('search')) $query->where(function ($q) use ($request): void { $search = $request->input('search'); $q->where('booking_number', 'ilike', "%{$search}%")->orWhereHas('turf', fn ($turf) => $turf->where('name', 'ilike', "%{$search}%"))->orWhereHas('customer.user', fn ($user) => $user->where('name', 'ilike', "%{$search}%")->orWhere('phone', 'ilike', "%{$search}%")); });
        return response()->json(['data' => $query->get()->map(fn (Booking $booking) => $this->present($booking))->values()]);
    }

    public function availability(Request $request)
    {
        $user = $this->user($request);
        $data = $request->validate(['booking_date' => ['required', 'date_format:Y-m-d']]);
        $turfs = Turf::whereIn('business_id', $this->scope->ids($user))->orderBy('name')->get();
        $bookings = Booking::whereIn('turf_id', $turfs->pluck('id'))->whereDate('booking_date', $data['booking_date'])->whereIn('status', ['pending', 'confirmed'])->get(['turf_id', 'start_time', 'end_time', 'status']);
        $result = $turfs->map(function (Turf $turf) use ($bookings, $data): array {
            $busy = $bookings->where('turf_id', $turf->id);
            $slots = [];
            if ($turf->status === 'available' && !in_array(CarbonImmutable::parse($data['booking_date'])->format('D'), $turf->closed_days ?? [], true)) {
                $open = CarbonImmutable::createFromFormat('H:i:s', (string) $turf->opening_time);
                $close = CarbonImmutable::createFromFormat('H:i:s', (string) $turf->closing_time);
                $step = (int) $turf->slot_duration_minutes + (int) $turf->buffer_minutes;
                for ($start = $open; $start->addMinutes((int) $turf->slot_duration_minutes)->lessThanOrEqualTo($close); $start = $start->addMinutes($step)) {
                    $end = $start->addMinutes((int) $turf->slot_duration_minutes);
                    $startTime = $start->format('H:i');
                    $endTime = $end->format('H:i');
                    $match = $busy->first(fn (Booking $booking) => substr((string) $booking->start_time, 0, 5) < $endTime && substr((string) $booking->end_time, 0, 5) > $startTime);
                    $slots[] = ['start_time' => $startTime, 'end_time' => $endTime, 'label' => $start->format('g:i A').' – '.$end->format('g:i A'), 'status' => $match?->status ?? 'available', 'price_taka' => $startTime >= substr((string) $turf->peak_start_time, 0, 5) && $endTime <= substr((string) $turf->peak_end_time, 0, 5) ? $turf->peak_price_taka : $turf->regular_price_taka];
                }
            }
            return ['id' => $turf->id, 'name' => $turf->name, 'status' => $turf->status, 'slots' => $slots];
        })->values();
        return response()->json(['data' => $result]);
    }

    public function slots(Request $request)
    {
        $user = $this->user($request);
        $data = $request->validate([
            'turf_id' => ['required', 'string', 'exists:turfs,id'],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'booking_id' => ['nullable', 'string'],
        ]);
        $turf = Turf::whereKey($data['turf_id'])->whereIn('business_id', $this->scope->ids($user))->firstOrFail();
        if ($turf->status !== 'available' || in_array(CarbonImmutable::parse($data['booking_date'])->format('D'), $turf->closed_days ?? [], true)) {
            return response()->json(['data' => []]);
        }
        $busy = Booking::where('turf_id', $turf->id)->whereDate('booking_date', $data['booking_date'])->whereIn('status', ['pending', 'confirmed'])->when($data['booking_id'] ?? null, fn ($query, $id) => $query->where('id', '!=', $id))->get(['start_time', 'end_time']);
        $open = CarbonImmutable::createFromFormat('H:i:s', (string) $turf->opening_time);
        $close = CarbonImmutable::createFromFormat('H:i:s', (string) $turf->closing_time);
        $step = (int) $turf->slot_duration_minutes + (int) $turf->buffer_minutes;
        $slots = [];
        for ($start = $open; $start->addMinutes((int) $turf->slot_duration_minutes)->lessThanOrEqualTo($close); $start = $start->addMinutes($step)) {
            $end = $start->addMinutes((int) $turf->slot_duration_minutes);
            $startTime = $start->format('H:i');
            $endTime = $end->format('H:i');
            $isBusy = $busy->contains(fn (Booking $booking) => substr((string) $booking->start_time, 0, 5) < $endTime && substr((string) $booking->end_time, 0, 5) > $startTime);
            if (!$isBusy) $slots[] = ['start_time' => $startTime, 'end_time' => $endTime, 'label' => $start->format('g:i A').' – '.$end->format('g:i A'), 'price_taka' => $startTime >= substr((string) $turf->peak_start_time, 0, 5) && $endTime <= substr((string) $turf->peak_end_time, 0, 5) ? $turf->peak_price_taka : $turf->regular_price_taka];
        }
        return response()->json(['data' => $slots]);
    }

    public function store(Request $request)
    {
        $user = $this->manage($request);
        $data = $request->validate($this->rules());
        $businessId = $this->scope->primary($user);
        $booking = DB::transaction(function () use ($data, $businessId, $user): Booking {
            $turf = Turf::whereKey($data['turf_id'])->whereIn('business_id', $this->scope->ids($user))->lockForUpdate()->firstOrFail();
            $customer = Customer::whereKey($data['customer_id'])->firstOrFail();
            $start = CarbonImmutable::createFromFormat('H:i', $data['start_time']);
            $end = CarbonImmutable::createFromFormat('H:i', $data['end_time']);
            $duration = $start->diffInMinutes($end);
            abort_unless($duration >= $turf->slot_duration_minutes, 422, 'Booking duration is shorter than the turf slot duration.');
            abort_unless(!in_array(CarbonImmutable::parse($data['booking_date'])->format('D'), $turf->closed_days ?? [], true), 422, 'This turf is closed on the selected day.');
            abort_unless($turf->status === 'available', 422, 'This turf is not available for booking.');
            $overlap = Booking::where('turf_id', $turf->id)->whereDate('booking_date', $data['booking_date'])->whereIn('status', ['pending', 'confirmed'])->where('start_time', '<', $data['end_time'])->where('end_time', '>', $data['start_time'])->lockForUpdate()->exists();
            abort_if($overlap, 422, 'This turf is already booked for the selected time.');
            $peak = $data['start_time'] >= substr((string) $turf->peak_start_time, 0, 5) && $data['end_time'] <= substr((string) $turf->peak_end_time, 0, 5);
            return Booking::create(['booking_number' => $this->bookingNumber(), 'business_id' => $businessId, 'turf_id' => $turf->id, 'customer_id' => $customer->id, 'created_by_user_id' => $user->id, 'booking_date' => $data['booking_date'], 'start_time' => $data['start_time'], 'end_time' => $data['end_time'], 'duration_minutes' => $duration, 'price_taka' => $peak ? $turf->peak_price_taka : $turf->regular_price_taka, 'price_type' => $peak ? 'peak' : 'regular', 'payment_method' => $data['payment_method'] ?? null, 'transaction_reference' => $data['transaction_reference'] ?? null, 'customer_note' => $data['customer_note'] ?? null]);
        });
        $this->notifyCustomer($booking, 'Booking created', 'Your booking '.$booking->booking_number.' has been created and is awaiting confirmation.', 'info');
        $this->notifyBusinessTeam($user, 'New booking received', 'Booking '.$booking->booking_number.' was created for '.($booking->turf?->name ?? 'a turf').'.', 'success');
        return response()->json(['data' => $this->present($booking), 'message' => 'Booking created successfully.'], 201);
    }

    public function show(Request $request, Booking $booking)
    {
        $user = $this->user($request);
        abort_unless(in_array($booking->business_id, $this->scope->ids($user), true), 404, 'Booking not found.');
        return response()->json(['data' => $this->present($booking)]);
    }

    public function status(Request $request, Booking $booking)
    {
        $this->manage($request);
        abort_unless(in_array($booking->business_id, $this->scope->ids($request->user()), true), 404, 'Booking not found.');
        $data = $request->validate(['status' => ['required', Rule::in(['confirmed', 'rejected', 'cancelled', 'completed'])], 'payment_status' => ['nullable', Rule::in(['unpaid', 'pending', 'paid', 'failed', 'refunded'])], 'rejection_reason' => ['nullable', 'string', 'max:255'], 'cancellation_reason' => ['nullable', 'string', 'max:255']]);
        $data['confirmed_at'] = $data['status'] === 'confirmed' ? now() : $booking->confirmed_at;
        $data['cancelled_at'] = $data['status'] === 'cancelled' ? now() : $booking->cancelled_at;
        $data['completed_at'] = $data['status'] === 'completed' ? now() : $booking->completed_at;
        $booking->update($data);
        $this->notifyCustomer($booking, match ($data['status']) {
            'confirmed' => 'Booking confirmed',
            'cancelled' => 'Booking cancelled',
            'rejected' => 'Booking rejected',
            'completed' => 'Booking completed',
            default => 'Booking updated',
        }, 'Your booking '.$booking->booking_number.' status is now '.$data['status'].'.', $data['status'] === 'cancelled' || $data['status'] === 'rejected' ? 'danger' : 'success');
        return response()->json(['data' => $this->present($booking->refresh()), 'message' => 'Booking status updated successfully.']);
    }

    public function payment(Request $request, Booking $booking)
    {
        $user = $this->manage($request);
        abort_unless(in_array($booking->business_id, $this->scope->ids($user), true), 404, 'Booking not found.');
        $data = $request->validate([
            'amount_received_taka' => ['required', 'integer', 'min:0'],
            'payment_method' => ['required', 'string', 'max:30'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
        ]);
        abort_unless((int) $data['amount_received_taka'] >= (int) $booking->price_taka, 422, 'Received amount must cover the booking amount.');
        $booking->update(['amount_received_taka' => $data['amount_received_taka'], 'payment_method' => $data['payment_method'], 'transaction_reference' => $data['transaction_reference'] ?? null, 'payment_status' => 'paid', 'status' => 'confirmed', 'confirmed_at' => $booking->confirmed_at ?? now()]);
        $this->notifyCustomer($booking, 'Payment received', 'Payment for booking '.$booking->booking_number.' has been received and the booking is confirmed.', 'success');
        $this->notifyBusinessTeam($user, 'Payment completed', 'Payment for booking '.$booking->booking_number.' was completed successfully.', 'success');
        return response()->json(['data' => $this->present($booking->refresh()), 'message' => 'Payment received and booking confirmed successfully.']);
    }

    public function update(Request $request, Booking $booking)
    {
        $user = $this->manage($request);
        abort_unless(in_array($booking->business_id, $this->scope->ids($user), true), 404, 'Booking not found.');
        $data = $request->validate(['booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time']]);
        DB::transaction(function () use ($data, $booking, $user): void {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $turf = Turf::whereKey($booking->turf_id)->whereIn('business_id', $this->scope->ids($user))->lockForUpdate()->firstOrFail();
            $start = CarbonImmutable::createFromFormat('H:i', $data['start_time']);
            $end = CarbonImmutable::createFromFormat('H:i', $data['end_time']);
            $duration = $start->diffInMinutes($end);
            abort_unless($duration >= $turf->slot_duration_minutes, 422, 'Booking duration is shorter than the turf slot duration.');
            abort_unless(!in_array(CarbonImmutable::parse($data['booking_date'])->format('D'), $turf->closed_days ?? [], true), 422, 'This turf is closed on the selected day.');
            $overlap = Booking::where('turf_id', $turf->id)->where('id', '!=', $booking->id)->whereDate('booking_date', $data['booking_date'])->whereIn('status', ['pending', 'confirmed'])->where('start_time', '<', $data['end_time'])->where('end_time', '>', $data['start_time'])->lockForUpdate()->exists();
            abort_if($overlap, 422, 'This turf is already booked for the selected time.');
            $peak = $data['start_time'] >= substr((string) $turf->peak_start_time, 0, 5) && $data['end_time'] <= substr((string) $turf->peak_end_time, 0, 5);
            $booking->update(['booking_date' => $data['booking_date'], 'start_time' => $data['start_time'], 'end_time' => $data['end_time'], 'duration_minutes' => $duration, 'price_taka' => $peak ? $turf->peak_price_taka : $turf->regular_price_taka, 'price_type' => $peak ? 'peak' : 'regular']);
        });
        $this->notifyCustomer($booking, 'Booking rescheduled', 'Your booking '.$booking->booking_number.' was moved to '.$booking->booking_date->format('Y-m-d').' from '.$booking->start_time.' to '.$booking->end_time.'.', 'info');
        return response()->json(['data' => $this->present($booking->refresh()), 'message' => 'Booking schedule updated successfully.']);
    }
}
