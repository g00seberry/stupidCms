<?php

namespace App\Http\Middleware;

use App\Support\JwtCookies;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to verify CSRF token for state-changing API requests.
 *
 * Compares the X-CSRF-Token or X-XSRF-TOKEN header with the CSRF cookie value.
 * Only applies to POST, PUT, PATCH, DELETE methods.
 * Excludes api.auth.login and api.auth.refresh routes from verification.
 * 
 * On 419 error, issues a new CSRF token cookie to help client recover.
 */
final class VerifyApiCsrf
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip idempotent methods (GET, HEAD, OPTIONS)
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Skip preflight requests (OPTIONS with Access-Control-Request-Method)
        if ($request->getMethod() === 'OPTIONS' && $request->header('Access-Control-Request-Method')) {
            return $next($request);
        }

        // Exclude login and refresh endpoints by route name (they don't require CSRF)
        if ($request->routeIs('api.auth.login', 'api.auth.refresh')) {
            return $next($request);
        }

        $csrfConfig = config('security.csrf');
        $cookieName = $csrfConfig['cookie_name'];

        // Accept both X-CSRF-Token and X-XSRF-TOKEN headers
        $headerToken = (string) $request->header('X-CSRF-Token', '');
        if ($headerToken === '') {
            $headerToken = (string) $request->header('X-XSRF-TOKEN', '');
        }

        $cookieToken = (string) $request->cookie($cookieName, '');

        // Use hash_equals for timing-safe comparison
        if ($headerToken === '' || $cookieToken === '' || ! hash_equals($cookieToken, $headerToken)) {
            // Issue a new CSRF token on error to help client recover
            $newToken = Str::random(40);
            
            return response()->json([
                'type' => 'about:blank',
                'title' => 'CSRF Token Mismatch',
                'status' => 419,
                'detail' => 'CSRF token mismatch.',
            ], 419)
                ->header('Content-Type', 'application/problem+json')
                ->withCookie(JwtCookies::csrf($newToken));
        }

        return $next($request);
    }
}
