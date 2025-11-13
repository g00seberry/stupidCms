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
 * Middleware для проверки CSRF токена для state-changing API запросов.
 *
 * Сравнивает заголовок X-CSRF-Token или X-XSRF-TOKEN со значением CSRF cookie.
 * Применяется только к методам POST, PUT, PATCH, DELETE.
 * Исключает маршруты api.auth.login, api.auth.refresh и api.auth.logout из проверки.
 *
 * При ошибке 419 выдаёт новый CSRF токен в cookie для восстановления клиента.
 *
 * @package App\Http\Middleware
 */
final class VerifyApiCsrf
{
    /**
     * Обработать входящий запрос.
     *
     * Проверяет CSRF токен из заголовка и cookie.
     * Использует timing-safe сравнение (hash_equals).
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Closure $next Следующий middleware
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \App\Support\Errors\HttpErrorException При несовпадении токенов
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
                    $response->headers->set('Vary', 'Origin, Cookie');
                    $response->headers->setCookie(JwtCookies::csrf($newToken));

                    return $response;
                },
            );
        }

        return $next($request);
    }
}
