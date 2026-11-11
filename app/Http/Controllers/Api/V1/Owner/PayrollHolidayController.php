<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\PayrollHoliday;
use Illuminate\Http\Request;

class PayrollHolidayController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $this->businessId($request, false);
        $query = PayrollHoliday::query()->where('business_id', $businessId);
        if ($request->filled('year')) {
            $query->whereYear('from_date', (int) $request->integer('year'));
        }

        return response()->json(['data' => $query->orderBy('from_date')->get()]);
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $data = $this->validated($request, $businessId);
        $data['business_id'] = $businessId;
        $data['created_by'] = $request->user()->id;

        return response()->json(['data' => PayrollHoliday::create($data), 'message' => 'Holiday created successfully.'], 201);
    }

    public function update(Request $request, string $holiday)
    {
        $businessId = $this->businessId($request);
        $record = PayrollHoliday::query()->where('business_id', $businessId)->whereKey($holiday)->firstOrFail();
        $record->fill($this->validated($request, $businessId, $record->id));
        $record->save();

        return response()->json(['data' => $record->fresh(), 'message' => 'Holiday updated successfully.']);
    }

    public function destroy(Request $request, string $holiday)
    {
        $businessId = $this->businessId($request);
        $record = PayrollHoliday::query()->where('business_id', $businessId)->whereKey($holiday)->firstOrFail();
        $record->delete();

        return response()->json(['message' => 'Holiday deleted successfully.']);
    }

    private function validated(Request $request, string $businessId, ?string $ignoreId = null): array
    {
        $data = $request->validate([
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'title' => ['required', 'string', 'max:150'],
        ]);

        $overlap = PayrollHoliday::query()
            ->where('business_id', $businessId)
            ->where('from_date', '<=', $data['to_date'])
            ->where('to_date', '>=', $data['from_date'])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        abort_if($overlap, 422, 'This holiday range overlaps an existing holiday.');

        return $data;
    }

    private function businessId(Request $request, bool $write = true): string
    {
        $user = $request->user();
        $allowed = $user && $user->role === 'business-owner';
        if ($user && ! $allowed) {
            $allowed = $user->hasPermission($write ? 'payroll.edit' : 'payroll.view') || $user->hasPermission('payroll.edit');
        }
        abort_unless($allowed, 403, 'You do not have permission to manage payroll holidays.');

        $businessId = $user->business_id ?: $user->businesses()->value('businesses.id');
        abort_unless($businessId, 403, 'No business is assigned to this account.');

        return (string) $businessId;
    }
}
