<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\PayrollPayslip;
use App\Services\PayrollAdvanceDeductionService;
use Illuminate\Http\Request;

class PayrollPayslipController extends Controller
{
    public function applyAdvanceDeductions(Request $request, string $payslip, PayrollAdvanceDeductionService $service)
    {
        $businessId = $this->businessId($request);
        $record = PayrollPayslip::query()
            ->where('business_id', $businessId)
            ->whereKey($payslip)
            ->firstOrFail();

        abort_if(in_array($record->payrollRun?->status, ['finalized', 'paid'], true), 422, 'Finalized payroll cannot be recalculated.');

        return response()->json([
            'data' => $service->apply($record),
            'message' => 'Advance deductions applied to the payslip.',
        ]);
    }

    private function businessId(Request $request): string
    {
        $user = $request->user();
        $allowed = $user && $user->role === 'business-owner';
        if ($user && ! $allowed) {
            $allowed = $user->hasPermission('payroll.edit') || $user->hasPermission('payroll.view');
        }
        abort_unless($allowed, 403, 'You do not have permission to manage payroll payslips.');
        $businessId = $user->business_id ?: $user->businesses()->value('businesses.id');
        abort_unless($businessId, 403, 'No business is assigned to this account.');
        return (string) $businessId;
    }
}
