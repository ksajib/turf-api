<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        $credentials = $request->validate(['login' => ['required', 'string', 'max:255'], 'password' => ['required', 'string']]);
        $identifier = Str::lower(trim($credentials['login']));
        $owner = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? User::whereRaw('LOWER(email) = ?', [$identifier])->where(function ($query): void { $query->where('role', 'business-owner')->orWhereNull('role'); })->first()
            : User::where('username', $identifier)->where(function ($query): void { $query->where('role', 'business-owner')->orWhereNull('role'); })->first();

        if (! $owner || ! Hash::check($credentials['password'], $owner->password)) {
            $auditLogger->record('owner.login.failed', request: $request, metadata: ['identifier' => $identifier]);
            throw ValidationException::withMessages(['login' => 'The provided credentials are invalid.']);
        }
        if ($owner->must_change_password && $owner->temporary_password_expires_at?->isPast()) {
            $auditLogger->record('owner.login.expired_temporary_password', request: $request, metadata: ['user_id' => $owner->id]);
            throw ValidationException::withMessages(['login' => 'This temporary password has expired. Contact the platform administrator.']);
        }

        Auth::guard('web')->login($owner);
        $request->session()->regenerate();
        $auditLogger->record('owner.login.succeeded', $owner, $request, ['requires_password_change' => $owner->must_change_password]);

        return $tokenService->addCookies(response()->json(['data' => $this->profile($owner), 'message' => 'Owner login successful.']), $tokenService->issue($owner));
    }

    public function refresh(Request $request, AuthenticationTokenService $tokenService)
    {
        $result = $tokenService->refreshFromCookie($request->cookie(AuthenticationTokenService::REFRESH_COOKIE));
        if (! $result || ! ($result['user'] instanceof User)) {
            return $tokenService->forgetCookies(response()->json(['message' => 'Your refresh session is invalid or expired.', 'code' => 'AUTH_REFRESH_INVALID'], 401));
        }

        Auth::guard('web')->login($result['user']);
        return $tokenService->addCookies(response()->json(['data' => $this->profile($result['user']), 'message' => 'Session refreshed successfully.']), $result['tokens']);
    }

    public function me(Request $request)
    {
        return response()->json(['data' => $this->profile($request->user())]);
    }

    public function changePassword(Request $request, AuditLogger $auditLogger)
    {
        /** @var User $owner */
        $owner = $request->user();
        if (! $owner instanceof User) {
            return response()->json(['message' => 'Owner authentication required.'], 403);
        }
        if (! $owner->must_change_password) {
            throw ValidationException::withMessages(['password' => 'A first-login password change is not pending.']);
        }
        if ($owner->temporary_password_expires_at?->isPast()) {
            throw ValidationException::withMessages(['current_password' => 'This temporary password has expired. Contact the platform administrator.']);
        }

        $data = $request->validate(['current_password' => ['required', 'string'], 'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed']]);
        if (! Hash::check($data['current_password'], $owner->password)) {
            throw ValidationException::withMessages(['current_password' => 'The temporary password is incorrect.']);
        }

        $owner->forceFill(['password' => $data['password'], 'must_change_password' => false, 'temporary_password_expires_at' => null, 'password_changed_at' => now()])->save();
        $auditLogger->record('owner.password_changed', $owner, $request);

        return response()->json(['data' => $this->profile($owner->refresh())]);
    }

    public function logout(Request $request, AuditLogger $auditLogger)
    {
        $owner = $request->user();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $auditLogger->record('owner.logout', $owner, $request);

        app(AuthenticationTokenService::class)->revokeFromRequest($request->cookie(AuthenticationTokenService::ACCESS_COOKIE), $request->cookie(AuthenticationTokenService::REFRESH_COOKIE));
        return app(AuthenticationTokenService::class)->forgetCookies(response()->json(['message' => 'Owner logout successful.']));
    }

    private function profile(User $owner): array
    {
        return ['id' => $owner->id, 'name' => $owner->name, 'username' => $owner->username, 'email' => $owner->email, 'requires_password_change' => $owner->must_change_password];
    }
}
