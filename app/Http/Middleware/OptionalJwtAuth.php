<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Auth\JwtService;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
    /**
     * Имя guard для аутентификации.
     *
     * @var string
     */
    private const GUARD = 'api';

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
        $cookieName = config('jwt.cookies.access');
        $accessToken = (string) $request->cookie($cookieName, '');

        // В тестах и некоторых случаях cookie может передаваться через заголовок Cookie
        // Это необходимо для корректной работы в Laravel тестах с API запросами
        if ($accessToken === '' && $request->hasHeader('Cookie')) {
            $cookieHeader = $request->header('Cookie', '');
            if (preg_match('/' . preg_quote($cookieName, '/') . '=([^;]+)/', $cookieHeader, $matches)) {
                $accessToken = urldecode($matches[1]);
            }
        }

        if ($accessToken === '') {
            return $next($request);
        }

        try {
            $verified = $this->jwt->verify($accessToken, 'access');
            $claims = $verified['claims'];
        } catch (\Throwable) {
            // Токен невалиден - пропускаем запрос дальше без установки пользователя
            return $next($request);
        }

        $subject = $claims['sub'] ?? null;
        if (! $this->isValidSubject($subject)) {
            return $next($request);
        }

        $userId = (int) $subject;
        $user = User::query()->find($userId);
        if (! $user) {
            return $next($request);
        }

        // Устанавливаем пользователя в guard для последующих проверок прав
        Auth::shouldUse(self::GUARD);
        Auth::setUser($user);

        return $next($request);
    }

    /**
     * Проверить валидность subject claim токена.
     *
     * Subject должен быть положительным целым числом (ID пользователя).
     *
     * @param mixed $subject Subject claim из токена
     * @return bool
     */
    private function isValidSubject(mixed $subject): bool
    {
        if (! is_numeric($subject)) {
            return false;
        }

        $intVal = (int) $subject;
        
        return $intVal > 0 && (string) $intVal === trim((string) $subject);
    }
}

