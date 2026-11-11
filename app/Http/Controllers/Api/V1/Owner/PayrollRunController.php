<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\PayrollAdvance;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\PayrollSalaryStructure;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Services\PayrollAdvanceDeductionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollRunController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $this->businessId($request);
        $runs = PayrollRun::query()->where('business_id', $businessId)->latest('month')->get();

        return response()->json(['data' => $runs->map(fn (PayrollRun $run) => $this->presentRun($run))->values()]);
    }

    public function show(Request $request, string $run)
    {
        $businessId = $this->businessId($request);
        $record = PayrollRun::query()->where('business_id', $businessId)->whereKey($run)->with('payslips.advanceDeductions')->firstOrFail();

        return response()->json(['data' => array_merge($this->presentRun($record), ['payslips' => $record->payslips->map(fn (PayrollPayslip $payslip) => $this->presentPayslip($payslip))->values()])]);
    }

    public function generate(Request $request, PayrollAdvanceDeductionService $deductionService)
    {
        $businessId = $this->businessId($request);
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $month = $data['month'];
        $periodStart = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $run = DB::transaction(function () use ($businessId, $month, $periodStart, $periodEnd, $request, $deductionService): PayrollRun {
            $existing = PayrollRun::query()->where('business_id', $businessId)->where('month', $month)->first();
            abort_if($existing && in_array($existing->status, ['finalized', 'paid'], true), 422, 'Finalized payroll cannot be regenerated.');
            if ($existing) {
                $existing->payslips()->delete();
                $run = $existing;
            } else {
                $run = PayrollRun::create([
                    'business_id' => $businessId,
                    'month' => $month,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'status' => 'draft',
                    'generated_by' => $request->user()->id,
                ]);
            }

            $structures = PayrollSalaryStructure::query()
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', $periodEnd->toDateString())
                ->orderByDesc('effective_from')
                ->get()
                ->unique(fn (PayrollSalaryStructure $structure) => $structure->staff_type.'-'.$structure->staff_id)
                ->values();
            $names = User::query()->whereIn('id', $structures->pluck('staff_id'))->get(['id', 'name', 'username'])->keyBy('id');

            $workingDays = $this->workingDays($businessId, $periodStart, $periodEnd);
            foreach ($structures as $index => $structure) {
                $user = $names->get($structure->staff_id);
                if (! $user) continue;
                $gross = round((float) $structure->basic + (float) $structure->house_rent + (float) $structure->medical + (float) $structure->conveyance + (float) $structure->other_allowance, 2);
                $fixedDeduction = round((float) $structure->fixed_deduction, 2);
                $payslip = PayrollPayslip::create([
                    'business_id' => $businessId,
                    'payroll_run_id' => $run->getKey(),
                    'payslip_no' => sprintf('PS-%s-%04d-%s', str_replace('-', '', $month), $index + 1, substr($structure->staff_id, -4)),
                    'staff_type' => $structure->staff_type,
                    'staff_id' => $structure->staff_id,
                    'staff_code' => $user->username,
                    'staff_name' => $user->name,
                    'month' => $month,
                    'working_days' => $workingDays,
                    'gross_salary' => $gross,
                    'total_earning' => $gross,
                    'other_deduction' => $fixedDeduction,
                    'total_deduction' => $fixedDeduction,
                    'net_salary' => max(0, round($gross - $fixedDeduction, 2)),
                    'payment_status' => 'unpaid',
                ]);
                $deductionService->apply($payslip);
            }

            $run->load('payslips');
            $run->update([
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'working_days' => $workingDays,
                'total_staff' => $run->payslips->count(),
                'total_gross' => $run->payslips->sum('gross_salary'),
                'total_deduction' => $run->payslips->sum('total_deduction'),
                'total_net' => $run->payslips->sum('net_salary'),
                'total_paid' => $run->payslips->sum('paid_amount'),
            ]);

            return $run->fresh();
        });

        return response()->json(['data' => $this->presentRun($run), 'message' => 'Payroll generated successfully.']);
    }

    private function presentRun(PayrollRun $run): array
    {
        return ['id' => $run->getKey(), 'month' => $run->month, 'status' => ucfirst($run->status), 'staff' => (int) $run->total_staff, 'net_salary' => (float) $run->total_net, 'paid' => (float) $run->total_paid, 'due' => round((float) $run->total_net - (float) $run->total_paid, 2), 'working_days' => (int) $run->working_days, 'period_start' => $run->period_start?->toDateString(), 'period_end' => $run->period_end?->toDateString()];
    }

    private function presentPayslip(PayrollPayslip $payslip): array
    {
        return ['id' => $payslip->getKey(), 'payslip_no' => $payslip->payslip_no, 'staff_name' => $payslip->staff_name, 'staff_code' => $payslip->staff_code, 'gross_salary' => (float) $payslip->gross_salary, 'advance_deduction' => (float) $payslip->advance_deduction, 'total_deduction' => (float) $payslip->total_deduction, 'net_salary' => (float) $payslip->net_salary, 'paid_amount' => (float) $payslip->paid_amount, 'payment_status' => $payslip->payment_status, 'advance_deductions' => $payslip->advanceDeductions->map(fn ($deduction) => ['advance_id' => $deduction->advance_id, 'amount' => (float) $deduction->amount])->values()];
    }

    private function workingDays(string $businessId, Carbon $start, Carbon $end): int
    {
        $settings = PayrollSetting::query()->where('business_id', $businessId)->first();
        $offDays = collect(explode(',', (string) ($settings?->weekly_off_days ?: 'Friday')))
            ->map(fn ($day) => $this->dayNumber(trim($day)))
            ->filter(fn ($day) => $day !== null)
            ->values()
            ->all();
        $holidays = DB::table('payroll_holidays')->where('business_id', $businessId)->whereDate('from_date', '<=', $end)->whereDate('to_date', '>=', $start)->get(['from_date', 'to_date']);
        $holidayDates = [];
        foreach ($holidays as $holiday) {
            $date = Carbon::parse($holiday->from_date);
            $last = Carbon::parse($holiday->to_date);
            while ($date->lte($last)) { $holidayDates[$date->toDateString()] = true; $date->addDay(); }
        }
        $days = 0;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (! in_array($date->dayOfWeek, $offDays, true) && ! isset($holidayDates[$date->toDateString()])) $days++;
        }
        return $days;
    }

    private function dayNumber(string $day): ?int
    {
        $days = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
        return is_numeric($day) ? (int) $day : ($days[strtolower($day)] ?? null);
    }

    private function businessId(Request $request): string
    {
        $user = $request->user();
        $allowed = $user && $user->role === 'business-owner';
        if ($user && ! $allowed) $allowed = $user->hasPermission('payroll.edit') || $user->hasPermission('payroll.view');
        abort_unless($allowed, 403, 'You do not have permission to manage payroll.');
        $businessId = $user->business_id ?: $user->businesses()->value('businesses.id');
        abort_unless($businessId, 403, 'No business is assigned to this account.');
        return (string) $businessId;
    }
}
