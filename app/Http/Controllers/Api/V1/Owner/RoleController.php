<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    private const MODULES = ['dashboard', 'turfs', 'bookings', 'customers', 'payments', 'staff', 'reports', 'subscription', 'settings'];
    private const ACTIONS = ['view', 'edit', 'update', 'delete'];

    private function businessId(Request $request): string
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'business-owner' && $user->business_id, 403, 'Only the business owner can manage roles.');
        return (string) $user->business_id;
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return [
            'name' => [$required, 'string', 'min:2', 'max:80'],
            'permissions' => [$required, 'array'],
            'permissions.*' => ['string', Rule::in($this->permissionKeys())],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    private function permissionKeys(): array
    {
        return collect(self::MODULES)->flatMap(fn (string $module) => collect(self::ACTIONS)->map(fn (string $action) => $module.'.'.$action))->values()->all();
    }

    private function present(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'business_id' => $role->business_id,
            'permissions' => array_values(array_intersect($role->permissions ?? [], $this->permissionKeys())),
            'status' => $role->status,
            'members_count' => (int) ($role->users_count ?? 0),
            'can_manage' => (string) $role->business_id !== '',
            'created_at' => $role->created_at?->toISOString(),
            'updated_at' => $role->updated_at?->toISOString(),
        ];
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId($request);
        $roles = Role::query()
            ->withCount('users')
            ->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', $businessId))
            ->orderByRaw('business_id is null asc')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $roles->map(fn (Role $role) => $this->present($role))->values()]);
    }

    public function show(Request $request, Role $role)
    {
        $this->businessId($request);
        abort_unless($role->business_id === $request->user()->business_id || $role->business_id === null, 404, 'Role not found.');
        $role->loadCount('users');
        return response()->json(['data' => $this->present($role)]);
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $data = $request->validate($this->rules());
        $role = Role::create(['business_id' => $businessId, 'name' => trim($data['name']), 'permissions' => array_values(array_unique($data['permissions'])), 'status' => $data['status'] ?? 'active']);
        return response()->json(['data' => $this->present($role), 'message' => 'Role created successfully.'], 201);
    }

    public function update(Request $request, Role $role)
    {
        $businessId = $this->businessId($request);
        abort_unless((string) $role->business_id === $businessId, 404, 'Role not found.');
        $data = $request->validate($this->rules(true));
        if (array_key_exists('name', $data)) $role->name = trim($data['name']);
        if (array_key_exists('permissions', $data)) $role->permissions = array_values(array_unique($data['permissions']));
        if (array_key_exists('status', $data)) $role->status = $data['status'];
        $role->save();
        return response()->json(['data' => $this->present($role->loadCount('users')), 'message' => 'Role updated successfully.']);
    }

    public function duplicate(Request $request, Role $role)
    {
        $businessId = $this->businessId($request);
        abort_unless($role->business_id === null || (string) $role->business_id === $businessId, 404, 'Role not found.');
        $base = trim($role->name).' copy';
        $name = $base;
        $suffix = 2;
        while (Role::where('business_id', $businessId)->where('name', $name)->exists()) $name = $base.' '.$suffix++;
        $copy = Role::create(['business_id' => $businessId, 'name' => $name, 'permissions' => $role->permissions ?? [], 'status' => 'active']);
        return response()->json(['data' => $this->present($copy), 'message' => 'Role duplicated successfully.'], 201);
    }

    public function status(Request $request, Role $role)
    {
        $businessId = $this->businessId($request);
        abort_unless((string) $role->business_id === $businessId, 404, 'Role not found.');
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);
        $role->update(['status' => $data['status']]);
        return response()->json(['data' => $this->present($role->loadCount('users')), 'message' => 'Role status updated successfully.']);
    }

    public function destroy(Request $request, Role $role)
    {
        $businessId = $this->businessId($request);
        abort_unless((string) $role->business_id === $businessId, 404, 'Role not found.');
        abort_if($role->users()->exists(), 422, 'Reassign users before deleting this role.');
        $role->delete();
        return response()->json(['message' => 'Role deleted successfully.']);
    }
}
