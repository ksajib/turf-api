<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserProvisioningService
{
    public function __construct(private readonly OwnerTemporaryPasswordGenerator $temporaryPasswordGenerator) {}

    public function create(array $attributes, string $role, ?string $businessId = null, bool $temporaryPassword = false): array
    {
        $email = Str::lower(trim((string) ($attributes['email'] ?? '')));
        $username = isset($attributes['username']) ? Str::lower(trim((string) $attributes['username'])) : null;
        $phone = isset($attributes['phone']) ? trim((string) $attributes['phone']) : null;

        $duplicate = User::query()->where(function ($query) use ($email, $username, $phone): void {
            $query->where('email', $email);
            if ($username) $query->orWhere('username', $username);
            if ($phone) $query->orWhere('phone', $phone);
        })->exists();
        if ($duplicate) throw ValidationException::withMessages(['user' => 'A user with the same email, username, or phone already exists.']);

        $plainPassword = $temporaryPassword ? $this->temporaryPasswordGenerator->generate() : ($attributes['password'] ?? null);
        if (! $plainPassword) throw ValidationException::withMessages(['password' => 'A password is required to create this user.']);

        $roleId = Role::query()->where('name', $role)->whereNull('business_id')->value('id');
        $user = User::create([
            'business_id' => $businessId,
            'role_id' => $roleId,
            'role' => $role,
            'permissions' => $attributes['permissions'] ?? null,
            'name' => trim((string) ($attributes['name'] ?? '')),
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'password' => $plainPassword,
            'status' => 'active',
            'must_change_password' => $temporaryPassword,
            'temporary_password_expires_at' => $temporaryPassword ? now()->addDay() : null,
        ]);

        return ['user' => $user, 'temporary_password' => $temporaryPassword ? $plainPassword : null];
    }
}
