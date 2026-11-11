<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\PayrollSalaryStructure;
use App\Models\PayrollShift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollSalaryStructureController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $this->businessId($request, false);
        $today = now()->toDateString();
        $staff = User::query()->with('roleRecord:id,name')->where('business_id', $businessId)->where('role', 'staff')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'username', 'email', 'phone', 'role_id', 'role']);
        $structures = PayrollSalaryStructure::query()->with('shift')->where('business_id', $businessId)->where('staff_type', 'employee')->orderByDesc('effective_from')->get();
        $grouped = $structures->groupBy('staff_id');

        return response()->json(['data' => $staff->map(function (User $member) use ($grouped, $today) {
            $history = $grouped->get($member->id, collect());
            $current = $history->first(fn (PayrollSalaryStructure $salary) => $salary->effective_from?->toDateString() <= $today);

            return $this->staffSummary($member, $current, $history->count());
        })->values()]);
    }

    public function show(Request $request, string $staffId)
    {
        $businessId = $this->businessId($request, false);
        $staff = $this->staff($businessId, $staffId);
        $history = PayrollSalaryStructure::query()->with('shift')->where('business_id', $businessId)->where('staff_type', 'employee')->where('staff_id', $staff->id)->orderByDesc('effective_from')->get();

        return response()->json(['data' => ['staff' => ['id' => $staff->id, 'name' => $staff->name, 'username' => $staff->username, 'email' => $staff->email, 'phone' => $staff->phone], 'current' => $history->first(fn (PayrollSalaryStructure $salary) => $salary->effective_from?->toDateString() <= now()->toDateString()), 'history' => $history->values()]]);
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $data = $this->validated($request);
        $staff = $this->staff($businessId, $data['staff_id']);
        if (! empty($data['shift_id'])) {
            abort_unless(PayrollShift::query()->where('business_id', $businessId)->whereKey($data['shift_id'])->exists(), 422, 'Shift not found in this business.');
        }
        $data['business_id'] = $businessId;
        $data['staff_type'] = 'employee';
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $data['is_active'] = true;

        $salary = DB::transaction(function () use ($data) {
            $existing = PayrollSalaryStructure::query()->where('business_id', $data['business_id'])->where('staff_type', $data['staff_type'])->where('staff_id', $data['staff_id'])->whereDate('effective_from', $data['effective_from'])->first();
            if ($existing) {
                $existing->fill($data);
                $existing->save();
                return $existing->fresh();
            }

            return PayrollSalaryStructure::create($data);
        });

        return response()->json(['data' => $salary, 'staff' => ['id' => $staff->id, 'name' => $staff->name], 'message' => 'Salary saved successfully.'], 201);
    }

    public function update(Request $request, string $salaryStructure)
    {
        $businessId = $this->businessId($request);
        $salary = PayrollSalaryStructure::query()->where('business_id', $businessId)->whereKey($salaryStructure)->firstOrFail();
        $data = $this->validated($request, true);
        unset($data['staff_id']);
        $data['updated_by'] = $request->user()->id;
        $salary->fill($data);
        $salary->save();

        return response()->json(['data' => $salary->fresh(), 'message' => 'Salary updated successfully.']);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? ['sometimes'] : ['required'];
        return $request->validate([
            'staff_id' => [...$required, 'string'],
            'shift_id' => ['nullable', 'string'],
            'pay_type' => [...$required, 'in:monthly,per_session,commission'],
            'basic' => [...$required, 'numeric', 'min:0'],
            'house_rent' => ['sometimes', 'numeric', 'min:0'],
            'medical' => ['sometimes', 'numeric', 'min:0'],
            'conveyance' => ['sometimes', 'numeric', 'min:0'],
            'other_allowance' => ['sometimes', 'numeric', 'min:0'],
            'fixed_deduction' => ['sometimes', 'numeric', 'min:0'],
            'per_session_rate' => ['sometimes', 'numeric', 'min:0'],
            'commission_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_no' => ['nullable', 'string', 'max:50'],
            'mobile_banking_no' => ['nullable', 'string', 'max:20'],
            'effective_from' => [...$required, 'date_format:Y-m-d'],
            'is_active' => ['sometimes', 'boolean'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function staff(string $businessId, string $staffId): User
    {
        return User::query()->where('business_id', $businessId)->where('role', 'staff')->whereKey($staffId)->firstOrFail();
    }

    private function staffSummary(User $member, ?PayrollSalaryStructure $current, int $historyCount): array
    {
        return ['staff_id' => $member->id, 'name' => $member->name, 'username' => $member->username, 'email' => $member->email, 'phone' => $member->phone, 'type' => $member->roleRecord?->name ?? ucfirst((string) $member->role), 'salary_set' => (bool) $current, 'current_salary' => $current, 'history_count' => $historyCount];
    }

    private function businessId(Request $request, bool $write = true): string
    {
        $user = $request->user();
        $allowed = $user && $user->role === 'business-owner';
        if ($user && ! $allowed) {
            $allowed = $user->hasPermission($write ? 'payroll.edit' : 'payroll.view') || $user->hasPermission('payroll.edit');
        }
        abort_unless($allowed, 403, 'You do not have permission to manage payroll salary structures.');
        $businessId = $user->business_id ?: $user->businesses()->value('businesses.id');
        abort_unless($businessId, 403, 'No business is assigned to this account.');
        return (string) $businessId;
    }
}
