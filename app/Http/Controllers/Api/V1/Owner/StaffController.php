<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Turf;
use App\Models\User;
use App\Services\OwnerCredentialDelivery;
use App\Services\OwnerTemporaryPasswordGenerator;
use App\Services\UserProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StaffController extends Controller
{
    private function businessId(Request $request): string
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'business-owner', 403, 'Only the business owner can manage staff.');
        $businessId = $user->business_id ?: $user->businesses()->value('businesses.id');
        abort_unless($businessId, 403, 'No business is assigned to this account.');
        return (string) $businessId;
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId($request);
        $query = User::query()->with('roleRecord:id,name')->where('business_id', $businessId)->where('role', 'staff')
            ->when($request->filled('search'), function ($builder) use ($request) {
                $search = trim((string) $request->string('search'));
                $builder->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"));
            })
            ->when($request->filled('status') && (string) $request->string('status') !== 'all', fn ($builder) => $builder->where('status', (string) $request->string('status')))
            ->latest('created_at');
        $staff = $query->get()->map(fn (User $member) => $this->present($member))->values();
        $all = User::query()->where('business_id', $businessId)->where('role', 'staff');
        return response()->json(['data' => $staff, 'meta' => ['total' => (clone $all)->count(), 'active' => (clone $all)->where('status', 'active')->count(), 'invited' => (clone $all)->where('status', 'invited')->count(), 'inactive' => (clone $all)->where('status', 'inactive')->count()]]);
    }

    public function store(Request $request, UserProvisioningService $provisioning, OwnerCredentialDelivery $delivery)
    {
        $businessId = $this->businessId($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'], 'username' => ['required', 'string', 'min:3', 'max:80'],
            'email' => ['required', 'email', 'max:255'], 'phone' => ['required', 'string', 'max:30'],
            'role_id' => ['nullable', 'string', 'exists:roles,id'], 'employee_code' => ['nullable', 'string', 'max:50'],
            'turf_ids' => ['nullable', 'array'], 'turf_ids.*' => ['string', 'distinct'],
            'designation' => ['nullable', 'string', 'max:100'], 'joining_date' => ['nullable', 'date'], 'address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'], 'emergency_contact_phone' => ['nullable', 'string', 'max:30'], 'notes' => ['nullable', 'string'],
        ]);
        $roleId = $data['role_id'] ?? Role::query()->where('business_id', $businessId)->where('status', 'active')->where('name', 'Staff')->value('id');
        $turfIds = collect($data['turf_ids'] ?? [])->filter()->values();
        abort_unless($turfIds->every(fn (string $id) => Turf::query()->where('business_id', $businessId)->whereKey($id)->exists()), 422, 'One or more selected turfs do not belong to this business.');
        $result = DB::transaction(function () use ($data, $businessId, $roleId, $turfIds, $provisioning, $delivery) {
            $provisioned = $provisioning->create(['name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'] ?? null, 'phone' => $data['phone']], 'staff', $businessId, true);
            $user = $provisioned['user'];
            $user->forceFill(['role_id' => $roleId])->save();
            $profile = Staff::create(array_merge(['user_id' => $user->id, 'business_id' => $businessId], array_intersect_key($data, array_flip(['employee_code', 'designation', 'joining_date', 'address', 'emergency_contact_name', 'emergency_contact_phone', 'notes']))));
            $profile->turfs()->sync($turfIds->all());
            $delivery->queue($user, Business::findOrFail($businessId), $provisioned['temporary_password'], (string) Str::uuid(), [\App\Models\CredentialDelivery::CHANNEL_SMS]);
            return ['user' => $user->load('roleRecord'), 'profile' => $profile];
        });
        return response()->json(['data' => $this->present($result['user']), 'message' => 'Staff account created and credentials queued for SMS delivery.'], 201);
    }

    public function resetPassword(Request $request, User $staff, OwnerCredentialDelivery $delivery, OwnerTemporaryPasswordGenerator $passwordGenerator)
    {
        $businessId = $this->businessId($request);
        abort_unless((string) $staff->business_id === $businessId && $staff->role === 'staff', 404, 'Staff member not found.');

        DB::transaction(function () use ($staff, $businessId, $delivery, $passwordGenerator): void {
            $temporaryPassword = $passwordGenerator->generate();
            $staff->forceFill([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'temporary_password_expires_at' => now()->addDay(),
                'password_changed_at' => null,
            ])->save();

            \App\Models\CredentialDelivery::query()->where('business_id', $businessId)->where('user_id', $staff->id)->where('status', \App\Models\CredentialDelivery::STATUS_QUEUED)->update([
                'status' => \App\Models\CredentialDelivery::STATUS_SUPERSEDED,
                'encrypted_payload' => null,
                'last_error_code' => 'credential_reissued',
            ]);

            $delivery->queue($staff, Business::findOrFail($businessId), $temporaryPassword, (string) Str::uuid(), [\App\Models\CredentialDelivery::CHANNEL_SMS]);
        });

        return response()->json(['message' => 'Staff temporary password reset and SMS delivery queued.']);
    }

    private function present(User $member): array
    {
        return ['id' => $member->id, 'name' => $member->name, 'username' => $member->username, 'email' => $member->email, 'phone' => $member->phone, 'role' => $member->roleRecord?->name ?? 'Staff', 'status' => $member->status, 'last_active_at' => $member->last_login_at?->toISOString(), 'created_at' => $member->created_at?->toISOString()];
    }
}
