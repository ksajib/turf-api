<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

class StaffPermissionService
{
    private const MODULE_ALIASES = [
        'turfs' => 'turf',
        'subscription' => 'subscriptions',
        'settings' => 'business',
    ];

    private const ACTION_ALIASES = [
        'update' => 'create',
    ];

    public function permissionsFor(User $user): array
    {
        if ($user->role !== 'staff') return [];

        $role = $user->roleRecord;
        if (! $role instanceof Role || $role->status !== 'active') return [];
        if ($role->business_id !== null && (string) $role->business_id !== (string) $user->business_id) return [];

        return $this->normalize($role->permissions ?? []);
    }

    public function normalize(array $permissions): array
    {
        return collect($permissions)
            ->filter(fn ($permission) => is_string($permission) && str_contains($permission, '.'))
            ->map(function (string $permission): string {
                [$module, $action] = explode('.', strtolower(trim($permission)), 2);
                $module = self::MODULE_ALIASES[$module] ?? $module;
                $action = self::ACTION_ALIASES[$action] ?? $action;
                return $module.'.'.$action;
            })
            ->unique()
            ->values()
            ->all();
    }

    public function allows(User $user, string $required): bool
    {
        if ($user->role !== 'staff') return true;

        $required = $this->normalize([$required])[0] ?? $required;
        $permissions = $this->permissionsFor($user);
        if (in_array($required, $permissions, true)) return true;

        [$module, $action] = array_pad(explode('.', $required, 2), 2, null);
        if (! $module || ! $action) return false;
        if (in_array($action, ['view', 'create', 'edit', 'delete'], true) && in_array($module.'.manage', $permissions, true)) return true;
        if ($action === 'manage') {
            return in_array($module.'.manage', $permissions, true)
                || collect(['create', 'update', 'delete'])->every(fn (string $item) => in_array($module.'.'.$item, $permissions, true));
        }

        return false;
    }
}
