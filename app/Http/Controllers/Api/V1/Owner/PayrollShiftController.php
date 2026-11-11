<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\PayrollShift;
use Illuminate\Http\Request;

class PayrollShiftController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $this->businessId($request, false);
        $shifts = PayrollShift::query()->where('business_id', $businessId)->orderBy('name')->get();

        return response()->json(['data' => $shifts]);
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $data = $this->validated($request);
        $data['business_id'] = $businessId;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        return response()->json(['data' => PayrollShift::create($data), 'message' => 'Shift created successfully.'], 201);
    }

    public function update(Request $request, string $shift)
    {
        $businessId = $this->businessId($request);
        $record = PayrollShift::query()->where('business_id', $businessId)->whereKey($shift)->firstOrFail();
        $record->fill($this->validated($request));
        $record->updated_by = $request->user()->id;
        $record->save();

        return response()->json(['data' => $record->fresh(), 'message' => 'Shift updated successfully.']);
    }

    public function destroy(Request $request, string $shift)
    {
        $businessId = $this->businessId($request);
        $record = PayrollShift::query()->where('business_id', $businessId)->whereKey($shift)->firstOrFail();
        $record->delete();

        return response()->json(['message' => 'Shift deleted successfully.']);
    }

    private function validated(Request $request): array
    {
        $payload = $request->all();
        if (isset($payload['weekly_off_days']) && is_array($payload['weekly_off_days'])) {
            $payload['weekly_off_days'] = implode(', ', $payload['weekly_off_days']);
        }

        return validator($payload, [
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'weekly_off_days' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ])->validate();
    }

    private function businessId(Request $request, bool $write = true): string
    {
        $user = $request->user();
        $allowed = $user && $user->role === 'business-owner';
        if ($user && ! $allowed) {
            $allowed = $user->hasPermission($write ? 'payroll.edit' : 'payroll.view') || $user->hasPermission('payroll.edit');
        }
        abort_unless($allowed, 403, 'You do not have permission to manage payroll shifts.');

        $businessId = $user->business_id ?: $user->businesses()->value('businesses.id');
        abort_unless($businessId, 403, 'No business is assigned to this account.');

        return (string) $businessId;
    }
}
