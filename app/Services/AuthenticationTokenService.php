<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cookie;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationTokenService
{
    public const ACCESS_COOKIE = 'access_token';
    public const REFRESH_COOKIE = 'refresh_token';

    public function issue(Authenticatable $user): array
    {
        $this->revokeReferencedRefreshToken($user);

        $access = $user->createToken('access', ['access'], now()->addHour());
        $refresh = $user->createToken('refresh', ['refresh'], now()->addDays(30));

        if (method_exists($user, 'forceFill')) {
            $user->forceFill(['token_reference' => (string) $refresh->accessToken->id])->save();
        }

        return [
            'access_token' => $access->plainTextToken,
            'refresh_token' => $refresh->plainTextToken,
        ];
    }

    public function addCookies(Response $response, array $tokens): Response
    {
        $secure = app()->environment('production');
        $sameSite = 'lax';

        $response->headers->setCookie(Cookie::make(self::ACCESS_COOKIE, $tokens['access_token'], 60, '/', null, $secure, true, false, $sameSite));
        $response->headers->setCookie(Cookie::make(self::REFRESH_COOKIE, $tokens['refresh_token'], 30 * 24 * 60, '/', null, $secure, true, false, $sameSite));
        return $response;
    }

    public function refresh(string $plainTextToken, Authenticatable $expectedUser): ?array
    {
        $token = PersonalAccessToken::findToken($plainTextToken);
        if (! $token || $token->name !== 'refresh' || $token->expires_at?->isPast()) return null;
        if ((string) $token->tokenable_id !== (string) $expectedUser->getAuthIdentifier()) return null;
        if (method_exists($expectedUser, 'getAttribute') && (string) $expectedUser->getAttribute('token_reference') !== (string) $token->id) return null;

        $token->delete();
        return $this->issue($expectedUser);
    }

    public function refreshFromCookie(?string $plainTextToken): ?array
    {
        $token = $plainTextToken ? PersonalAccessToken::findToken($plainTextToken) : null;
        if (! $token || $token->name !== 'refresh' || $token->expires_at?->isPast()) return null;

        $user = $token->tokenable;
        if (! $user || (($user->status ?? 'active') !== 'active') || (($user->is_active ?? true) !== true)) return null;
        if (method_exists($user, 'getAttribute') && $user->getAttribute('token_reference') && (string) $user->getAttribute('token_reference') !== (string) $token->id) return null;

        $token->delete();
        return ['user' => $user, 'tokens' => $this->issue($user)];
    }

    public function revokeFromRequest(?string $accessToken, ?string $refreshToken): void
    {
        foreach ([$accessToken, $refreshToken] as $plainTextToken) {
            if ($plainTextToken) PersonalAccessToken::findToken($plainTextToken)?->delete();
        }
    }

    public function forgetCookies(Response $response): Response
    {
        $response->headers->clearCookie(self::ACCESS_COOKIE);
        $response->headers->clearCookie(self::REFRESH_COOKIE);
        return $response;
    }

    private function revokeReferencedRefreshToken(Authenticatable $user): void
    {
        if (! method_exists($user, 'getAttribute')) return;
        $reference = $user->getAttribute('token_reference');
        if ($reference) PersonalAccessToken::query()->whereKey($reference)->delete();
    }
}
