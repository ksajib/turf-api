<?php

namespace App\Http\Middleware;

use App\Models\PlatformAdmin;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! ($user instanceof PlatformAdmin || ($user instanceof User && $user->hasRole('platform-admin')))) {
            return response()->json(['message' => 'Only platform administrators can access this area.', 'code' => 'ADMIN_ACCESS_REQUIRED'], 403);
        }

        if (($user instanceof PlatformAdmin && ! $user->is_active) || ($user instanceof User && $user->status !== 'active')) {
            auth('platform_admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'Your administrator account is inactive.'], 403);
        }

        return $next($request);
    }
}
