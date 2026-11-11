<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\PayrollAdvance;
use App\Models\User;
use Illuminate\Http\Request;

class PayrollAdvanceController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $this->businessId($request, false);
        $advances = PayrollAdvance::query()->where('business_id', $businessId)->latest('given_date')->get();
        $staffNames = User::query()->whereIn('id', $advances->pluck('staff_id'))->pluck('name', 'id');

        return response()->json([
            'data' => $advances->map(fn (PayrollAdvance $advance) => $this->present($advance, $staffNames[(string) $advance->staff_id] ?? 'Unknown staff'))->values(),
            'meta' => ['outstanding' => round((float) $advances->where('status', 'running')->sum('remaining'), 2)],
        ]);
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $data = $request->validate([
            'staff_id' => ['required', 'string'],
            'advance_type' => ['required', 'in:advance,loan'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'monthly_installment' => ['nullable', 'numeric', 'min:0'],
            'deduct_from_month' => ['required', 'date_format:Y-m'],
            'given_date' => ['required', 'date_format:Y-m-d'],
            'payment_method' => ['required', 'in:cash,bank,bkash,nagad,rocket,card,other'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        abort_unless(User::query()->where('business_id', $businessId)->where('role', 'staff')->whereKey($data['staff_id'])->exists(), 422, 'Staff member not found in this business.');

        $amount = round((float) $data['amount'], 2);
        $installment = round((float) ($data['monthly_installment'] ?? 0), 2);
        abort_if($installment > $amount, 422, 'Monthly installment cannot be greater than the advance amount.');

        $data['business_id'] = $businessId;
        $data['staff_type'] = 'employee';
        $data['amount'] = $amount;
        $data['monthly_installment'] = $installment;
        $data['paid_back'] = 0;
        $data['remaining'] = $amount;
        $data['status'] = 'running';
        $data['created_by'] = $request->user()->id;
        $advance = PayrollAdvance::create($data);

        return response()->json(['data' => $this->present($advance, (string) User::query()->whereKey($data['staff_id'])->value('name')), 'message' => 'Advance / loan created successfully.'], 201);
    }

    public function destroy(Request $request, string $advance)
    {
        $businessId = $this->businessId($request);
        $record = PayrollAdvance::query()->where('business_id', $businessId)->whereKey($advance)->firstOrFail();
        abort_if($record->deductions()->exists(), 422, 'This advance already has payroll deductions and cannot be deleted.');
        $record->delete();

        return response()->json(['message' => 'Advance / loan deleted successfully.']);
    }

    private function present(PayrollAdvance $advance, string $staffName): array
    {
        return array_merge($advance->toArray(), ['staff_name' => $staffName]);
    }

    private function businessId(Request $request, bool $write = true): string
    {
        $user = $request->user();
        $allowed = $user && $user->role === 'business-owner';
        if ($user && ! $allowed) {
            $allowed = $user->hasPermission($write ? 'payroll.edit' : 'payroll.view') || $user->hasPermission('payroll.edit');
        }
        abort_unless($allowed, 403, 'You do not have permission to manage payroll advances.');
        $businessId = $user->business_id ?: $user->businesses()->value('businesses.id');
        abort_unless($businessId, 403, 'No business is assigned to this account.');
        return (string) $businessId;
    }
}
