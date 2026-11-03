<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\Turf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TurfController extends Controller
{
    private function businessIds(Request $request): array
    {
        $user = $request->user();
        abort_unless($user, 401, 'Authentication is required.');
        $ids = $user->businesses()->pluck('businesses.id')->map(fn ($id) => (string) $id)->all();
        if ($user->business_id) $ids[] = (string) $user->business_id;
        $ids = array_values(array_unique(array_filter($ids)));
        abort_unless($ids, 403, 'No business is assigned to this account.');
        return $ids;
    }

    private function businessId(Request $request): string
    {
        return $this->businessIds($request)[0];
    }

    private function canView(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->role === 'business-owner' || $user->hasPermission('turf.view') || $user->hasPermission('turf.manage')), 403, 'You do not have permission to view turfs.');
    }

    private function canManage(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->role === 'business-owner' || $user->hasPermission('turf.manage')), 403, 'You do not have permission to manage turfs.');
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return [
            'name' => [$required, 'string', 'max:100'],
            'sport' => [$required, 'string', 'max:40'],
            'format' => [$required, 'string', 'max:40'],
            'capacity' => [$required, 'integer', 'min:1'],
            'location' => [$required, 'string', 'max:160'],
            'regular_price_taka' => [$required, 'integer', 'min:0'],
            'peak_price_taka' => [$required, 'integer', 'min:0'],
            'peak_start_time' => [$required, 'date_format:H:i'],
            'peak_end_time' => [$required, 'date_format:H:i', 'after:peak_start_time'],
            'slot_duration_minutes' => [$required, 'integer', 'min:15', 'max:1440'],
            'buffer_minutes' => [$required, 'integer', 'min:0', 'max:240'],
            'opening_time' => [$required, 'date_format:H:i'],
            'closing_time' => [$required, 'date_format:H:i', 'after:opening_time'],
            'closed_days' => [$required, 'array'],
            'closed_days.*' => ['string', Rule::in(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])],
            'status' => [$partial ? 'sometimes' : 'nullable', Rule::in(['available', 'maintenance', 'closed'])],
            'status_reason' => ['nullable', 'string', 'max:255'],
            'feature_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    private function present(Turf $turf): array
    {
        return array_merge($turf->toArray(), ['feature_image_url' => $turf->feature_image_path ? Storage::disk('public')->url($turf->feature_image_path) : null]);
    }

    public function index(Request $request)
    {
        $this->canView($request);
        $query = Turf::query()->whereIn('business_id', $this->businessIds($request));
        if ($request->filled('search')) $query->where(fn ($q) => $q->where('name', 'ilike', '%'.$request->input('search').'%')->orWhere('location', 'ilike', '%'.$request->input('search').'%'));
        if ($request->filled('status') && $request->input('status') !== 'all') $query->where('status', $request->input('status'));
        return response()->json(['data' => $query->latest()->get()->map(fn (Turf $turf) => $this->present($turf))->values()]);
    }

    public function store(Request $request)
    {
        $this->canManage($request);
        if (is_string($request->input('closed_days'))) $request->merge(['closed_days' => json_decode($request->input('closed_days'), true) ?: []]);
        $data = $request->validate($this->rules());
        $data['business_id'] = $this->businessId($request);
        $data['regular_price_taka'] = (int) $data['regular_price_taka'];
        $data['peak_price_taka'] = (int) $data['peak_price_taka'];
        if ($request->hasFile('feature_image')) $data['feature_image_path'] = $request->file('feature_image')->store('turfs', 'public');
        unset($data['feature_image']);
        $turf = Turf::create($data);
        return response()->json(['data' => $this->present($turf), 'message' => 'Turf created successfully.'], 201);
    }

    public function show(Request $request, Turf $turf)
    {
        $this->canView($request);
        abort_unless(in_array($turf->business_id, $this->businessIds($request), true), 404, 'Turf not found.');
        return response()->json(['data' => $this->present($turf)]);
    }

    public function update(Request $request, Turf $turf)
    {
        $this->canManage($request);
        abort_unless(in_array($turf->business_id, $this->businessIds($request), true), 404, 'Turf not found.');
        if (is_string($request->input('closed_days'))) $request->merge(['closed_days' => json_decode($request->input('closed_days'), true) ?: []]);
        $data = $request->validate($this->rules(true));
        if ($request->hasFile('feature_image')) {
            if ($turf->feature_image_path) Storage::disk('public')->delete($turf->feature_image_path);
            $data['feature_image_path'] = $request->file('feature_image')->store('turfs', 'public');
        }
        unset($data['feature_image']);
        $turf->update($data);
        return response()->json(['data' => $this->present($turf->refresh()), 'message' => 'Turf updated successfully.']);
    }

    public function status(Request $request, Turf $turf)
    {
        $this->canManage($request);
        abort_unless(in_array($turf->business_id, $this->businessIds($request), true), 404, 'Turf not found.');
        $data = $request->validate(['status' => ['required', Rule::in(['available', 'maintenance', 'closed'])], 'status_reason' => ['nullable', 'string', 'max:255']]);
        $turf->update($data);
        return response()->json(['data' => $this->present($turf->refresh()), 'message' => 'Turf status updated successfully.']);
    }

    public function destroy(Request $request, Turf $turf)
    {
        $this->canManage($request);
        abort_unless(in_array($turf->business_id, $this->businessIds($request), true), 404, 'Turf not found.');
        $turf->delete();
        return response()->json(['message' => 'Turf deleted successfully.']);
    }
}
