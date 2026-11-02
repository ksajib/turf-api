<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessionUser = $request->user() ?: Auth::guard('platform_admin')->user() ?: Auth::guard('web')->user();
        if ($sessionUser) {
            $request->setUserResolver(fn () => $sessionUser);
            Auth::setUser($sessionUser);
        } else {
            $plainTextToken = $request->cookie('access_token');
            $token = $plainTextToken ? PersonalAccessToken::findToken($plainTextToken) : null;

            if (! $token || $token->name !== 'access' || $token->expires_at?->isPast()) {
                return response()->json(['message' => 'Your session has expired. Please log in again.', 'code' => 'AUTH_SESSION_EXPIRED'], 401);
            }

            $user = $token->tokenable;
            if (! $user || (($user->status ?? 'active') !== 'active') || (($user->is_active ?? true) !== true)) {
                return response()->json(['message' => 'This account is inactive.', 'code' => 'ACCOUNT_INACTIVE'], 403);
            }

            $token->forceFill(['last_used_at' => now()])->save();
            $request->setUserResolver(fn () => $user);
            Auth::setUser($user);
        }

        return $next($request);
    }
}
