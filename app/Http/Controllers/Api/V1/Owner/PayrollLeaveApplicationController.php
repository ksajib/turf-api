<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\PayrollLeaveApplication;
use App\Models\PayrollLeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PayrollLeaveApplicationController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $this->businessId($request, false);
        $applications = PayrollLeaveApplication::query()->with('leaveType')->where('business_id', $businessId)->latest('from_date')->get();
        $staffNames = User::query()->whereIn('id', $applications->pluck('staff_id'))->pluck('name', 'id');

        return response()->json(['data' => $applications->map(fn (PayrollLeaveApplication $application) => $this->present($application, $staffNames[(string) $application->staff_id] ?? 'Unknown staff'))->values()]);
    }

    public function balance(Request $request)
    {
        $businessId = $this->businessId($request, false);
        $year = (int) $request->query('year', now()->year);
        abort_unless($year >= 2000 && $year <= 2100, 422, 'Year must be between 2000 and 2100.');

        $periodStart = Carbon::create($year, 1, 1)->startOfDay();
        $periodEnd = Carbon::create($year, 12, 31)->endOfDay();
        $staff = User::query()->where('business_id', $businessId)->where('role', 'staff')->where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $leaveTypes = PayrollLeaveType::query()->where('business_id', $businessId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'days_per_year', 'is_paid']);
        $applications = PayrollLeaveApplication::query()->where('business_id', $businessId)->where('status', 'approved')->whereDate('from_date', '<=', $periodEnd)->whereDate('to_date', '>=', $periodStart)->get(['staff_id', 'leave_type_id', 'from_date', 'to_date']);
        $used = [];

        foreach ($applications as $application) {
            $from = Carbon::parse($application->from_date)->max($periodStart);
            $to = Carbon::parse($application->to_date)->min($periodEnd);
            $key = $application->staff_id . ':' . $application->leave_type_id;
            $used[$key] = ($used[$key] ?? 0) + $from->diffInDays($to) + 1;
        }

        $data = $staff->crossJoin($leaveTypes)->map(function (array $pair) use ($used) {
            [$member, $leaveType] = $pair;
            $key = $member->id . ':' . $leaveType->id;
            $allocated = (int) $leaveType->days_per_year;
            $usedDays = (int) ($used[$key] ?? 0);

            return [
                'staff_id' => $member->id,
                'staff_name' => $member->name,
                'leave_type_id' => $leaveType->id,
                'leave_type_name' => $leaveType->name,
                'is_paid' => (bool) $leaveType->is_paid,
                'days_per_year' => $allocated,
                'used_days' => $usedDays,
                'remaining_days' => $allocated > 0 ? max(0, $allocated - $usedDays) : null,
            ];
        })->values();

        return response()->json(['data' => $data, 'meta' => ['year' => $year, 'staff_count' => $staff->count(), 'leave_type_count' => $leaveTypes->count()]]);
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $data = $this->validated($request, $businessId);
        $data['business_id'] = $businessId;
        $data['staff_type'] = 'employee';
        $data['days'] = Carbon::parse($data['from_date'])->diffInDays(Carbon::parse($data['to_date'])) + 1;
        $data['status'] = 'approved';
        $data['approved_by'] = $request->user()->id;
        $data['approved_at'] = now();
        $data['created_by'] = $request->user()->id;

        return response()->json(['data' => $this->present(PayrollLeaveApplication::create($data), $this->staffName($data['staff_id'])), 'message' => 'Leave approved and saved successfully.'], 201);
    }

    public function destroy(Request $request, string $application)
    {
        $businessId = $this->businessId($request);
        $record = PayrollLeaveApplication::query()->where('business_id', $businessId)->whereKey($application)->firstOrFail();
        $record->delete();

        return response()->json(['message' => 'Leave application deleted successfully.']);
    }

    private function validated(Request $request, string $businessId): array
    {
        $data = $request->validate([
            'staff_id' => ['required', 'string'],
            'leave_type_id' => ['required', 'string'],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless(User::query()->where('business_id', $businessId)->where('role', 'staff')->whereKey($data['staff_id'])->exists(), 422, 'Staff member not found in this business.');
        abort_unless(PayrollLeaveType::query()->where('business_id', $businessId)->whereKey($data['leave_type_id'])->where('is_active', true)->exists(), 422, 'Leave type not found in this business.');

        return $data;
    }

    private function present(PayrollLeaveApplication $application, string $staffName): array
    {
        return array_merge($application->toArray(), ['staff_name' => $staffName, 'leave_type_name' => $application->leaveType?->name]);
    }

    private function staffName(string $staffId): string
    {
        return (string) User::query()->whereKey($staffId)->value('name');
    }

    private function businessId(Request $request, bool $write = true): string
    {
        $user = $request->user();
        $allowed = $user && $user->role === 'business-owner';
        if ($user && ! $allowed) {
            $allowed = $user->hasPermission($write ? 'payroll.edit' : 'payroll.view') || $user->hasPermission('payroll.edit');
        }
        abort_unless($allowed, 403, 'You do not have permission to manage payroll leave applications.');

        $businessId = $user->business_id ?: $user->businesses()->value('businesses.id');
        abort_unless($businessId, 403, 'No business is assigned to this account.');

        return (string) $businessId;
    }
}
