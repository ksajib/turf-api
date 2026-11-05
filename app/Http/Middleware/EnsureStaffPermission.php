<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\StaffPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffPermission
{
    public function __construct(private StaffPermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string ...$requiredPermissions): Response
    {
        $user = $request->user();
        if (! $user instanceof User || $user->role !== 'staff') return $next($request);

        $requiredPermissions = collect($requiredPermissions)
            ->flatMap(fn (string $permission) => explode(',', $permission))
            ->map(fn (string $permission) => trim($permission))
            ->filter()
            ->values()
            ->all();

        $allowed = collect($requiredPermissions)->contains(fn (string $permission) => $this->permissions->allows($user, $permission));
        if (! $allowed) {
            return response()->json([
                'message' => 'You do not have permission for this action.',
                'code' => 'PERMISSION_DENIED',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
