<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthenticationTokenService;
use App\Services\StaffPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UnifiedAuthController extends Controller
{
    public function login(Request $request, AuditLogger $auditLogger, AuthenticationTokenService $tokenService)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $identifier = Str::lower(trim($credentials['login']));
        $admins = $this->matchingAdmins($identifier);
        $owners = $this->matchingOwners($identifier);
        if ($admins->count() + $owners->count() !== 1) {
            $auditLogger->record('auth.login.failed', request: $request, metadata: ['reason' => 'invalid_or_ambiguous_identifier']);
            throw ValidationException::withMessages(['login' => 'Unable to sign in with the provided credentials.']);
        }

        $identity = $admins->first() ?? $owners->first();
        if (! Hash::check($credentials['password'], $identity->password)) {
            $auditLogger->record('auth.login.failed', request: $request, metadata: ['reason' => 'invalid_credentials']);
            throw ValidationException::withMessages(['login' => 'Unable to sign in with the provided credentials.']);
        }

        if ($identity instanceof PlatformAdmin) {
            if (! $identity->is_active) {
                $auditLogger->record('auth.login.inactive', $identity, $request);
                throw ValidationException::withMessages(['login' => 'Unable to sign in with the provided credentials.']);
            }

            $role = 'platform-admin';
            $requiresPasswordChange = false;
            $destination = '/admin';
        } else {
            if ($identity->must_change_password && $identity->temporary_password_expires_at?->isPast()) {
                $auditLogger->record('auth.login.expired_temporary_password', request: $request, metadata: ['user_id' => $identity->id]);
                throw ValidationException::withMessages(['login' => 'Unable to sign in with the provided credentials.']);
            }

            $role = $identity->role ?: 'business-owner';
            $requiresPasswordChange = $identity->must_change_password;
            $destination = $requiresPasswordChange ? '/owner/change-password' : (in_array($role, ['customer', 'staff'], true) ? '/dashboard' : '/owner');
        }

        Auth::guard('platform_admin')->logout();
        Auth::guard('web')->logout();
        if ($identity instanceof PlatformAdmin) {
            Auth::guard('platform_admin')->login($identity);
        } else {
            Auth::guard('web')->login($identity);
        }
        $request->session()->regenerate();

        if ($identity instanceof PlatformAdmin) {
            $identity->forceFill(['last_login_at' => now()])->save();
        }

        $auditLogger->record('auth.login.succeeded', $identity, $request, ['role' => $role, 'requires_password_change' => $requiresPasswordChange]);

        $response = response()->json(['data' => [
            'role' => $role,
            'permissions' => $identity instanceof PlatformAdmin
                ? ($identity->permissions ?? [])
                : ($identity->role === 'staff'
                    ? app(StaffPermissionService::class)->permissionsFor($identity)
                    : ($identity->permissions ?: $identity->roleRecord?->permissions ?: [])),
            'requires_password_change' => $requiresPasswordChange,
            'redirect_to' => $destination,
            'profile' => $identity instanceof PlatformAdmin
                ? ['id' => $identity->id, 'name' => $identity->name, 'username' => $identity->username, 'email' => $identity->email, 'is_active' => $identity->is_active]
                : ['id' => $identity->id, 'name' => $identity->name, 'username' => $identity->username, 'email' => $identity->email, 'requires_password_change' => $identity->must_change_password],
        ], 'message' => 'Login successful.']);
        return $tokenService->addCookies($response, $tokenService->issue($identity));
    }

    public function refresh(Request $request, AuthenticationTokenService $tokenService)
    {
        $result = $tokenService->refreshFromCookie($request->cookie(AuthenticationTokenService::REFRESH_COOKIE));
        if (! $result) return $tokenService->forgetCookies(response()->json(['message' => 'Your refresh session is invalid or expired.', 'code' => 'AUTH_REFRESH_INVALID'], 401));

        return $tokenService->addCookies(response()->json(['message' => 'Session refreshed successfully.']), $result['tokens']);
    }

    public function me(Request $request)
    {
        $identity = $request->user();
        if ($identity instanceof PlatformAdmin) {
            return response()->json(['data' => ['role' => 'platform-admin', 'permissions' => $identity->permissions ?? [], 'requires_password_change' => false, 'profile' => ['id' => $identity->id, 'name' => $identity->name, 'username' => $identity->username, 'email' => $identity->email, 'is_active' => $identity->is_active]]]);
        }
        if ($identity instanceof User) {
            return response()->json(['data' => ['role' => $identity->role ?: 'business-owner', 'permissions' => $identity->role === 'staff' ? app(StaffPermissionService::class)->permissionsFor($identity) : ($identity->permissions ?: $identity->roleRecord?->permissions ?: []), 'requires_password_change' => $identity->must_change_password, 'profile' => ['id' => $identity->id, 'name' => $identity->name, 'username' => $identity->username, 'email' => $identity->email]]]);
        }

        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    public function logout(Request $request, AuditLogger $auditLogger)
    {
        $identity = $request->user();
        if (Auth::guard('platform_admin')->check()) {
            Auth::guard('platform_admin')->logout();
        }
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }
        app(AuthenticationTokenService::class)->revokeFromRequest($request->cookie(AuthenticationTokenService::ACCESS_COOKIE), $request->cookie(AuthenticationTokenService::REFRESH_COOKIE));
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $auditLogger->record('auth.logout', $identity, $request);

        return app(AuthenticationTokenService::class)->forgetCookies(response()->json(['message' => 'Logout successful.']));
    }

    private function matchingAdmins(string $identifier)
    {
        return PlatformAdmin::query()->where(function ($query) use ($identifier): void {
            $query->whereRaw('LOWER(email) = ?', [$identifier])->orWhere('username', $identifier);
        })->where('role', 'platform-admin')->get();
    }

    private function matchingOwners(string $identifier)
    {
        return User::query()->where(function ($query): void { $query->where('role', '!=', 'platform-admin')->orWhereNull('role'); })->where(function ($query) use ($identifier): void {
            $query->whereRaw('LOWER(email) = ?', [$identifier])->orWhere('username', $identifier);
        })->get();
    }
}
