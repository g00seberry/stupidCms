<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * Helper class for creating HttpOnly, Secure JWT cookies.
 *
 * These cookies store access and refresh tokens with proper security settings:
 * - HttpOnly: prevents JavaScript access
 * - Secure: only sent over HTTPS (in production)
 * - SameSite: CSRF protection (Strict, Lax, or None)
 */
final class JwtCookies
{
    /**
     * Normalize SameSite value to Symfony Cookie constants.
     *
     * @param string $samesite Raw SameSite value from config
     * @return string Normalized SameSite value (Cookie::SAMESITE_*)
     */
    private static function normalizeSameSite(string $samesite): string
    {
        $samesite = strtolower(trim($samesite));

        return match ($samesite) {
            'none' => Cookie::SAMESITE_NONE,
            'lax' => Cookie::SAMESITE_LAX,
            'strict' => Cookie::SAMESITE_STRICT,
            default => Cookie::SAMESITE_STRICT,
        };
    }

    /**
     * Create an access token cookie.
     *
     * @param string $jwt The JWT access token
     * @return Cookie
     */
    public static function access(string $jwt): Cookie
    {
        $config = config('jwt.cookies');
        $minutes = (int) ceil(config('jwt.access_ttl') / 60);
        $samesite = self::normalizeSameSite((string) $config['samesite']);

        // If SameSite=None, secure must be true
        $secure = $samesite === Cookie::SAMESITE_NONE ? true : $config['secure'];

        return Cookie::create($config['access'], $jwt, now()->addMinutes($minutes))
            ->withSecure($secure)
            ->withHttpOnly(true)
            ->withSameSite($samesite)
            ->withPath($config['path'])
            ->withDomain($config['domain']);
    }

    /**
     * Create a refresh token cookie.
     *
     * @param string $jwt The JWT refresh token
     * @return Cookie
     */
    public static function refresh(string $jwt): Cookie
    {
        $config = config('jwt.cookies');
        $minutes = (int) ceil(config('jwt.refresh_ttl') / 60);
        $samesite = self::normalizeSameSite((string) $config['samesite']);

        // If SameSite=None, secure must be true
        $secure = $samesite === Cookie::SAMESITE_NONE ? true : $config['secure'];

        return Cookie::create($config['refresh'], $jwt, now()->addMinutes($minutes))
            ->withSecure($secure)
            ->withHttpOnly(true)
            ->withSameSite($samesite)
            ->withPath($config['path'])
            ->withDomain($config['domain']);
    }

    /**
     * Create an expired access token cookie (for logout).
     *
     * @return Cookie
     */
    public static function forgetAccess(): Cookie
    {
        $config = config('jwt.cookies');
        $samesite = self::normalizeSameSite((string) $config['samesite']);

        // If SameSite=None, secure must be true
        $secure = $samesite === Cookie::SAMESITE_NONE ? true : $config['secure'];

        return Cookie::create($config['access'], '', now()->subMinutes(1))
            ->withSecure($secure)
            ->withHttpOnly(true)
            ->withSameSite($samesite)
            ->withPath($config['path'])
            ->withDomain($config['domain']);
    }

    /**
     * Create an expired refresh token cookie (for logout).
     *
     * @return Cookie
     */
    public static function forgetRefresh(): Cookie
    {
        $config = config('jwt.cookies');
        $samesite = self::normalizeSameSite((string) $config['samesite']);

        // If SameSite=None, secure must be true
        $secure = $samesite === Cookie::SAMESITE_NONE ? true : $config['secure'];

        return Cookie::create($config['refresh'], '', now()->subMinutes(1))
            ->withSecure($secure)
            ->withHttpOnly(true)
            ->withSameSite($samesite)
            ->withPath($config['path'])
            ->withDomain($config['domain']);
    }
}

