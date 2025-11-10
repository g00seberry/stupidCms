<?php

namespace App\Http\Middleware;

use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\HttpErrorException;
use App\Support\JwtCookies;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to verify CSRF token for state-changing API requests.
 *
 * Compares the X-CSRF-Token or X-XSRF-TOKEN header with the CSRF cookie value.
 * Only applies to POST, PUT, PATCH, DELETE methods.
 * Excludes api.auth.login, api.auth.refresh, and api.auth.logout routes from verification.
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

        // Exclude login, refresh, and logout endpoints by route name
        // - login/refresh: don't require CSRF (credentials-based, not cookie-based state)
        // - logout: uses JWT auth middleware, CSRF redundant
        if ($request->routeIs('api.auth.login', 'api.auth.refresh', 'api.auth.logout')) {
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

            /** @var ErrorFactory $factory */
            $factory = app(ErrorFactory::class);

            $payload = $factory->for(ErrorCode::CSRF_TOKEN_MISMATCH)->build();

            throw new HttpErrorException(
                $payload,
                static function (JsonResponse $response) use ($newToken): JsonResponse {
                    $response->headers->set('Vary', 'Origin');
                    $response->headers->setCookie(JwtCookies::csrf($newToken));

                    return $response;
                },
            );
        }

        return $next($request);
    }
}
