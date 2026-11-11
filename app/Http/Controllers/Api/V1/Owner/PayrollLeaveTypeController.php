<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\PayrollLeaveType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollLeaveTypeController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $this->businessId($request, false);

        return response()->json(['data' => PayrollLeaveType::query()->where('business_id', $businessId)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $data = $this->validated($request, $businessId);
        $data['business_id'] = $businessId;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        return response()->json(['data' => PayrollLeaveType::create($data), 'message' => 'Leave type created successfully.'], 201);
    }

    public function update(Request $request, string $leaveType)
    {
        $businessId = $this->businessId($request);
        $record = PayrollLeaveType::query()->where('business_id', $businessId)->whereKey($leaveType)->firstOrFail();
        $record->fill($this->validated($request, $businessId, $record->id));
        $record->updated_by = $request->user()->id;
        $record->save();

        return response()->json(['data' => $record->fresh(), 'message' => 'Leave type updated successfully.']);
    }

    public function destroy(Request $request, string $leaveType)
    {
        $businessId = $this->businessId($request);
        $record = PayrollLeaveType::query()->where('business_id', $businessId)->whereKey($leaveType)->firstOrFail();

        abort_if($record->applications()->exists(), 422, 'This leave type is already used by a leave application.');
        $record->delete();

        return response()->json(['message' => 'Leave type deleted successfully.']);
    }

    private function validated(Request $request, string $businessId, ?string $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('payroll_leave_types', 'name')->where(fn ($query) => $query->where('business_id', $businessId))->ignore($ignoreId)],
            'days_per_year' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_paid' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function businessId(Request $request, bool $write = true): string
    {
        $user = $request->user();
        $allowed = $user && $user->role === 'business-owner';
        if ($user && ! $allowed) {
            $allowed = $user->hasPermission($write ? 'payroll.edit' : 'payroll.view') || $user->hasPermission('payroll.edit');
        }
        abort_unless($allowed, 403, 'You do not have permission to manage payroll leave types.');

        $businessId = $user->business_id ?: $user->businesses()->value('businesses.id');
        abort_unless($businessId, 403, 'No business is assigned to this account.');

        return (string) $businessId;
    }
}
