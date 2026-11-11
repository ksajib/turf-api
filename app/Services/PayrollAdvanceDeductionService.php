<?php

namespace App\Services;

use App\Models\PayrollAdvance;
use App\Models\PayrollAdvanceDeduction;
use App\Models\PayrollPayslip;
use Illuminate\Support\Facades\DB;

class PayrollAdvanceDeductionService
{
    /**
     * Apply eligible advance/loan installments to a payslip.
     * The operation is idempotent for a payslip and never lets net salary go below zero.
     */
    public function apply(PayrollPayslip $payslip): PayrollPayslip
    {
        return DB::transaction(function () use ($payslip): PayrollPayslip {
            /** @var PayrollPayslip $lockedPayslip */
            $lockedPayslip = PayrollPayslip::query()
                ->where('business_id', $payslip->business_id)
                ->whereKey($payslip->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyDeducted = (float) $lockedPayslip->advanceDeductions()->sum('amount');
            $nonAdvanceDeductions = (float) $lockedPayslip->absent_deduction
                + (float) $lockedPayslip->late_deduction
                + (float) $lockedPayslip->other_deduction;
            $availableForAdvance = max(0, round((float) $lockedPayslip->total_earning - $nonAdvanceDeductions - $alreadyDeducted, 2));

            if ($availableForAdvance > 0) {
                $advances = PayrollAdvance::query()
                    ->where('business_id', $lockedPayslip->business_id)
                    ->where('staff_type', $lockedPayslip->staff_type)
                    ->where('staff_id', $lockedPayslip->staff_id)
                    ->where('status', 'running')
                    ->where('deduct_from_month', '<=', $lockedPayslip->month)
                    ->where('remaining', '>', 0)
                    ->orderBy('deduct_from_month')
                    ->orderBy('created_at')
                    ->lockForUpdate()
                    ->get();

                foreach ($advances as $advance) {
                    if ($availableForAdvance <= 0) break;

                    $alreadyForThisPayslip = (float) PayrollAdvanceDeduction::query()
                        ->where('advance_id', $advance->getKey())
                        ->where('payslip_id', $lockedPayslip->getKey())
                        ->value('amount');
                    if ($alreadyForThisPayslip > 0) continue;

                    $remaining = round((float) $advance->remaining, 2);
                    $installment = (float) $advance->monthly_installment > 0
                        ? (float) $advance->monthly_installment
                        : $remaining;
                    $deduction = round(min($remaining, $installment, $availableForAdvance), 2);
                    if ($deduction <= 0) continue;

                    PayrollAdvanceDeduction::create([
                        'business_id' => $lockedPayslip->business_id,
                        'advance_id' => $advance->getKey(),
                        'payslip_id' => $lockedPayslip->getKey(),
                        'amount' => $deduction,
                    ]);

                    $newPaidBack = round((float) $advance->paid_back + $deduction, 2);
                    $newRemaining = round(max(0, $remaining - $deduction), 2);
                    $advance->update([
                        'paid_back' => $newPaidBack,
                        'remaining' => $newRemaining,
                        'status' => $newRemaining <= 0 ? 'closed' : 'running',
                    ]);

                    $alreadyDeducted += $deduction;
                    $availableForAdvance = round($availableForAdvance - $deduction, 2);
                }
            }

            $totalDeduction = round($nonAdvanceDeductions + $alreadyDeducted, 2);
            $lockedPayslip->update([
                'advance_deduction' => round($alreadyDeducted, 2),
                'total_deduction' => $totalDeduction,
                'net_salary' => round(max(0, (float) $lockedPayslip->total_earning - $totalDeduction), 2),
            ]);

            return $lockedPayslip->fresh(['advanceDeductions']);
        });
    }
}
