<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use App\Services\AuditLogger;
use App\Services\AuthenticationTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, AuditLogger $auditLogger, AuthenticationTokenService $tokenService)
    {
        $request->merge(['login' => $request->input('login', $request->input('email'))]);
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);
        $identifier = Str::lower(trim($credentials['login']));
        $admin = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? PlatformAdmin::whereRaw('LOWER(email) = ?', [$identifier])->where('role', 'platform-admin')->first()
            : PlatformAdmin::where('username', $identifier)->where('role', 'platform-admin')->first();

        if (! $admin || ! Hash::check($credentials['password'], $admin->password)) {
            $auditLogger->record('admin.login.failed', request: $request, metadata: ['identifier' => $identifier]);
            throw ValidationException::withMessages(['login' => 'The provided credentials are invalid.']);
        }

        if (! $admin->is_active) {
            $auditLogger->record('admin.login.inactive', $admin, $request);
            throw ValidationException::withMessages(['login' => 'The administrator account is inactive.']);
        }

        Auth::guard('platform_admin')->login($admin);
        $request->session()->regenerate();
        $admin->forceFill(['last_login_at' => now()])->save();
        $auditLogger->record('admin.login.succeeded', $admin, $request);

        return $tokenService->addCookies(response()->json(['data' => $this->profile($admin), 'message' => 'Administrator login successful.']), $tokenService->issue($admin));
    }

    public function refresh(Request $request, AuthenticationTokenService $tokenService)
    {
        $result = $tokenService->refreshFromCookie($request->cookie(AuthenticationTokenService::REFRESH_COOKIE));
        if (! $result || ! ($result['user'] instanceof PlatformAdmin)) {
            return $tokenService->forgetCookies(response()->json(['message' => 'Your refresh session is invalid or expired.', 'code' => 'AUTH_REFRESH_INVALID'], 401));
        }

        Auth::guard('platform_admin')->login($result['user']);
        return $tokenService->addCookies(response()->json(['data' => $this->profile($result['user']), 'message' => 'Session refreshed successfully.']), $result['tokens']);
    }

    public function me(Request $request)
    {
        return response()->json(['data' => $this->profile($request->user())]);
    }

    public function logout(Request $request, AuditLogger $auditLogger)
    {
        $admin = $request->user();
        Auth::guard('platform_admin')->logout();
        $tokenService = app(AuthenticationTokenService::class);
        $tokenService->revokeFromRequest($request->cookie(AuthenticationTokenService::ACCESS_COOKIE), $request->cookie(AuthenticationTokenService::REFRESH_COOKIE));
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $auditLogger->record('admin.logout', $admin, $request);

        return $tokenService->forgetCookies(response()->json(['message' => 'Administrator logout successful.']));
    }

    private function profile(PlatformAdmin $admin): array
    {
        return ['id' => $admin->id, 'name' => $admin->name, 'username' => $admin->username, 'email' => $admin->email, 'is_active' => $admin->is_active];
    }
}
