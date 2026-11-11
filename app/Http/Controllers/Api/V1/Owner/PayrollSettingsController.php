<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollSettingsController extends Controller
{
    public function show(Request $request)
    {
        $businessId = $this->businessId($request);
        $setting = PayrollSetting::query()->firstOrCreate(
            ['gym_id' => $businessId],
            ['created_by' => $request->user()->id]
        );

        return response()->json(['data' => $setting->fresh()]);
    }

    public function update(Request $request)
    {
        $businessId = $this->businessId($request);
        $payload = $request->all();

        if (isset($payload['weekly_off_days']) && is_array($payload['weekly_off_days'])) {
            $payload['weekly_off_days'] = implode(', ', $payload['weekly_off_days']);
        }

        $data = validator($payload, [
            'weekly_off_days' => ['sometimes', 'string', 'max:50'],
            'per_day_basis' => ['sometimes', Rule::in(['working_days', 'fixed_30'])],
            'late_grace_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'late_rule' => ['sometimes', Rule::in(['none', 'count', 'fine'])],
            'lates_per_day_cut' => ['sometimes', 'integer', 'min:1', 'max:255'],
            'late_fine_amount' => ['sometimes', 'numeric', 'min:0'],
            'absent_deduction' => ['sometimes', 'boolean'],
            'overtime_enabled' => ['sometimes', 'boolean'],
            'overtime_rate_per_hour' => ['sometimes', 'numeric', 'min:0'],
            'salary_pay_day' => ['sometimes', 'integer', 'min:1', 'max:31'],
        ])->validate();

        $setting = PayrollSetting::query()->firstOrNew(['gym_id' => $businessId]);
        if (! $setting->exists) {
            $data['created_by'] = $request->user()->id;
        }
        $setting->fill($data);
        $setting->updated_by = $request->user()->id;
        $setting->save();

        return response()->json(['data' => $setting->fresh(), 'message' => 'Payroll settings saved.']);
    }

    private function businessId(Request $request): string
    {
        $user = $request->user();
        abort_unless($user && ($user->role === 'business-owner' || $user->hasPermission('payroll.edit')), 403, 'You do not have permission to manage payroll settings.');

        $businessId = $user->business_id ?: $user->businesses()->value('businesses.id');
        abort_unless($businessId, 403, 'No business is assigned to this account.');

        return (string) $businessId;
    }
}
