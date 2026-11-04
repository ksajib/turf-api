<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Services\OwnerBusinessScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private readonly OwnerBusinessScope $scope) {}

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403, 'Owner authentication required.');
        abort_unless($user->role === 'business-owner' || $user->hasPermission('payments.view') || $user->hasPermission('payments.manage'), 403, 'You do not have permission to view payments.');
        return $user;
    }

    private function query(User $user)
    {
        return Booking::query()->with(['turf', 'customer.user'])->whereIn('business_id', $this->scope->ids($user));
    }

    private function present(Booking $booking): array
    {
        $booking->loadMissing(['turf', 'customer.user']);
        return [
            'id' => $booking->id,
            'transaction_id' => $booking->transaction_reference,
            'booking_id' => $booking->booking_number,
            'customer' => $booking->customer?->user?->name,
            'customer_id' => $booking->customer_id,
            'customer_phone' => $booking->customer?->user?->phone,
            'customer_email' => $booking->customer?->user?->email,
            'turf' => $booking->turf?->name,
            'booking_date' => $booking->booking_date?->format('Y-m-d'),
            'start_time' => substr((string) $booking->start_time, 0, 5),
            'end_time' => substr((string) $booking->end_time, 0, 5),
            'method' => $booking->payment_method,
            'amount_taka' => (int) ($booking->amount_received_taka ?? 0),
            'due_taka' => max(0, (int) $booking->price_taka - (int) ($booking->amount_received_taka ?? 0)),
            'booking_amount_taka' => (int) $booking->price_taka,
            'payment_status' => $booking->payment_status,
            'created_at' => $booking->created_at?->toISOString(),
        ];
    }

    public function index(Request $request)
    {
        $user = $this->user($request);
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'paid', 'pending', 'refunded'])],
            'search' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $base = $this->query($user);
        $filtered = clone $base;
        if (($data['status'] ?? 'all') === 'paid') $filtered->where('payment_status', 'paid');
        if (($data['status'] ?? 'all') === 'pending') $filtered->whereIn('payment_status', ['unpaid', 'pending']);
        if (($data['status'] ?? 'all') === 'refunded') $filtered->where('payment_status', 'refunded');
        if (!empty($data['date_from'])) $filtered->whereDate('created_at', '>=', $data['date_from']);
        if (!empty($data['date_to'])) $filtered->whereDate('created_at', '<=', $data['date_to']);
        if (!empty($data['search'])) {
            $search = trim($data['search']);
            $filtered->where(function ($query) use ($search): void {
                $query->where('booking_number', 'ilike', "%{$search}%")
                    ->orWhere('transaction_reference', 'ilike', "%{$search}%")
                    ->orWhereHas('turf', fn ($turf) => $turf->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('customer.user', fn ($user) => $user->where('name', 'ilike', "%{$search}%")->orWhere('phone', 'ilike', "%{$search}%"));
            });
        }

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $month = $this->query($user)->whereDate('created_at', '>=', $monthStart)->whereDate('created_at', '<=', $monthEnd);
        $collected = (clone $month)->where('payment_status', 'paid')->sum('amount_received_taka');
        $pending = (clone $month)->whereIn('payment_status', ['unpaid', 'pending'])->sum('price_taka');
        $successful = (clone $month)->where('payment_status', 'paid')->count();
        $refunded = (clone $month)->where('payment_status', 'refunded')->sum('amount_received_taka');
        $payments = $filtered->latest('created_at')->paginate($data['per_page'] ?? 20);

        return response()->json([
            'data' => collect($payments->items())->map(fn (Booking $booking) => $this->present($booking))->values(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'summary' => [
                    'collected_taka' => (int) $collected,
                    'pending_taka' => (int) $pending,
                    'successful_count' => (int) $successful,
                    'refunded_taka' => (int) $refunded,
                ],
            ],
        ]);
    }

    public function show(Request $request, Booking $booking)
    {
        $user = $this->user($request);
        abort_unless(in_array($booking->business_id, $this->scope->ids($user), true), 404, 'Payment not found.');
        return response()->json(['data' => $this->present($booking)]);
    }
}
