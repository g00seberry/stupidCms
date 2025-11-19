<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Auth\JwtService;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware для опциональной JWT аутентификации.
 *
 * Проверяет JWT access токен из cookie и устанавливает пользователя в guard,
 * если токен валиден. Если токен отсутствует или невалиден, пропускает запрос
 * дальше без установки пользователя (не выбрасывает ошибку).
 *
 * Используется для публичных эндпоинтов, которые должны работать как для
 * аутентифицированных, так и для неаутентифицированных пользователей.
 *
 * @package App\Http\Middleware
 */
final class OptionalJwtAuth
{
    use HandlesJwtAuthentication;

    /**
     * @param \App\Domain\Auth\JwtService $jwt Сервис JWT
     */
    public function __construct(
        private readonly JwtService $jwt
    ) {
    }

    /**
     * Обработать входящий запрос.
     *
     * Проверяет JWT access токен из cookie и валидирует:
     * - Токен валиден (подпись, срок действия)
     * - Пользователь существует в базе данных
     *
     * Если токен валиден, устанавливает пользователя в guard 'api'.
     * Если токен отсутствует или невалиден, пропускает запрос дальше без ошибки.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Closure $next Следующий middleware
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $accessToken = $this->extractAccessToken($request);

        if ($accessToken === '') {
            return $next($request);
        }

        try {
            $verified = $this->verifyTokenAndGetClaims($this->jwt, $accessToken);
            $claims = $verified['claims'];
        } catch (\Throwable) {
            // Токен невалиден - пропускаем запрос дальше без установки пользователя
            return $next($request);
        }

        $user = $this->findUserFromClaims($claims);
        if (! $user) {
            return $next($request);
        }

        $this->setAuthenticatedUser($user);

        return $next($request);
    }
}

