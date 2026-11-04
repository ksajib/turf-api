<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Turf;
use App\Services\OwnerBusinessScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(private readonly OwnerBusinessScope $scope) {}

    public function index(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor && ($actor->role === 'business-owner' || $actor->hasPermission('reports.view')), 403, 'You do not have permission to view reports.');
        $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'], 'turf_id' => ['nullable', 'string'], 'sport' => ['nullable', 'string', 'max:40'], 'range' => ['nullable', Rule::in(['7 days', '30 days'])]]);
        $businessIds = $this->scope->ids($actor);
        $today = CarbonImmutable::today();
        $from = $request->date('date_from') ? CarbonImmutable::parse($request->date('date_from'))->startOfDay() : $today->subDays(6)->startOfDay();
        $to = $request->date('date_to') ? CarbonImmutable::parse($request->date('date_to'))->endOfDay() : $today->endOfDay();
        abort_if($from->greaterThan($to), 422, 'The report start date must be before the end date.');
        abort_if($from->diffInDays($to) > 366, 422, 'Report range cannot exceed one year.');

        $turfQuery = Turf::query()->whereIn('business_id', $businessIds);
        if ($request->filled('turf_id')) $turfQuery->where(fn ($query) => $query->whereKey($request->string('turf_id')->toString())->orWhere('name', $request->string('turf_id')->toString()));
        if ($request->filled('sport')) $turfQuery->where('sport', $request->string('sport')->toString());
        $turfs = $turfQuery->get();
        $turfIds = $turfs->modelKeys();
        $bookings = Booking::query()->with('turf')->whereIn('business_id', $businessIds)->whereIn('turf_id', $turfIds)->whereBetween('booking_date', [$from->toDateString(), $to->toDateString()])->get();
        $activeBookings = $bookings->whereNotIn('status', ['rejected', 'cancelled']);
        $revenueBookings = $bookings->whereIn('status', ['confirmed', 'completed']);
        $totalRevenue = (int) $revenueBookings->sum('price_taka');
        $customerCounts = $activeBookings->groupBy('customer_id');
        $repeatRate = $customerCounts->count() ? (int) round($customerCounts->filter(fn ($items) => $items->count() > 1)->count() / $customerCounts->count() * 100) : 0;

        $trend = $this->trend($revenueBookings, $from, $to, $request->input('range', '7 days'));
        $topTurfs = $turfs->map(function (Turf $turf) use ($activeBookings, $revenueBookings, $from, $to): array {
            $items = $activeBookings->where('turf_id', $turf->id);
            $revenue = (int) $revenueBookings->where('turf_id', $turf->id)->sum('price_taka');
            $availableMinutes = $this->availableMinutes($turf, $from, $to);
            $bookedMinutes = (int) $items->sum('duration_minutes');
            return ['id' => $turf->id, 'name' => $turf->name, 'bookings' => $items->count(), 'revenue' => '৳'.number_format($revenue), 'utilization' => ($availableMinutes ? (int) round(min(100, $bookedMinutes / $availableMinutes * 100)) : 0).'%'];
        })->sortByDesc('revenue')->values();

        $statusTotal = max(1, $bookings->count());
        $bookingMix = [['label' => 'Confirmed', 'value' => (int) round($bookings->where('status', 'confirmed')->count() / $statusTotal * 100), 'color' => 'bg-[#15803D]'], ['label' => 'Payment pending', 'value' => (int) round($bookings->where('payment_status', 'pending')->count() / $statusTotal * 100), 'color' => 'bg-[#F59E0B]'], ['label' => 'Cancelled', 'value' => (int) round($bookings->where('status', 'cancelled')->count() / $statusTotal * 100), 'color' => 'bg-[#CBD5E1]']];
        $peakHours = $activeBookings->groupBy(fn (Booking $booking) => substr((string) $booking->start_time, 0, 2))->map(fn ($items, $hour) => ['label' => CarbonImmutable::createFromFormat('H', $hour)->format('g:00 A').' – '.CarbonImmutable::createFromFormat('H', $hour)->addHour()->format('g:00 A'), 'value' => $items->count()])->sortByDesc('value')->take(4)->values();
        $maxPeak = max(1, (int) ($peakHours->max('value') ?? 1));
        $peakHours = $peakHours->map(fn (array $item) => [...$item, 'value' => (int) round($item['value'] / $maxPeak * 100)])->values();

        return response()->json(['data' => ['summary' => ['total_revenue' => $totalRevenue, 'total_bookings' => $activeBookings->count(), 'average_booking_value' => $activeBookings->count() ? (int) round($totalRevenue / $activeBookings->count()) : 0, 'customer_return_rate' => $repeatRate], 'trend' => $trend, 'booking_mix' => $bookingMix, 'top_turfs' => $topTurfs, 'peak_hours' => $peakHours, 'best_peak_label' => $peakHours->first()['label'] ?? 'No peak hour yet', 'turfs' => $turfs->map(fn (Turf $turf) => ['id' => $turf->id, 'name' => $turf->name])->values(), 'sports' => $turfs->pluck('sport')->filter()->unique()->values(), 'notes' => $this->notes($topTurfs, $bookings)] ]);
    }

    private function trend($bookings, CarbonImmutable $from, CarbonImmutable $to, string $range): array
    {
        if ($range === '30 days') {
            return collect(range(0, 4))->map(function (int $index) use ($bookings, $from): array { $start = $from->addDays($index * 6); $end = $start->addDays(5); $items = $bookings->filter(fn (Booking $booking) => CarbonImmutable::parse($booking->booking_date)->betweenIncluded($start, $end)); return ['day' => 'Week '.($index + 1), 'revenue' => (int) $items->sum('price_taka'), 'bookings' => $items->count()]; })->all();
        }
        return collect(range(0, $from->diffInDays($to)))->map(function (int $index) use ($bookings, $from): array { $date = $from->addDays($index); $items = $bookings->filter(fn (Booking $booking) => CarbonImmutable::parse($booking->booking_date)->isSameDay($date)); return ['day' => $date->format('D'), 'revenue' => (int) $items->sum('price_taka'), 'bookings' => $items->count()]; })->all();
    }

    private function availableMinutes(Turf $turf, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $daily = CarbonImmutable::createFromFormat('H:i:s', (string) $turf->opening_time)->diffInMinutes(CarbonImmutable::createFromFormat('H:i:s', (string) $turf->closing_time));
        $minutes = 0;
        foreach (range(0, $from->diffInDays($to)) as $index) { $date = $from->addDays($index); if (! in_array($date->format('D'), $turf->closed_days ?? [], true) && $turf->status === 'available') $minutes += $daily; }
        return $minutes;
    }

    private function notes($topTurfs, $bookings): array
    {
        $lowest = $topTurfs->sortBy(fn (array $item) => (int) rtrim($item['utilization'], '%'))->first();
        return array_values(array_filter([$lowest && (int) rtrim($lowest['utilization'], '%') < 50 ? "{$lowest['name']} utilization is below 50%. Consider a weekday offer." : null, $bookings->where('payment_status', 'pending')->count() ? $bookings->where('payment_status', 'pending')->count().' booking payments are still pending.' : null, $bookings->count() ? 'Report metrics are calculated from the selected booking period.' : 'No booking records are available for the selected period.']));
    }
}
