<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Auth\JwtService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Trait с общей логикой для JWT аутентификации.
 *
 * Содержит методы для:
 * - Извлечения access токена из cookie/заголовков
 * - Верификации токена и получения claims
 * - Валидации subject claim
 * - Поиска пользователя по claims
 * - Установки аутентифицированного пользователя в guard
 *
 * @package App\Http\Middleware
 */
trait HandlesJwtAuthentication
{
    /**
     * Имя guard для аутентификации.
     *
     * @var string
     */
    private const GUARD = 'api';

    /**
     * Извлечь access токен из запроса.
     *
     * Проверяет cookie и заголовок Cookie (для тестов).
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return string Токен или пустая строка, если не найден
     */
    protected function extractAccessToken(Request $request): string
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

        return $accessToken;
    }

    /**
     * Верифицировать токен и получить claims.
     *
     * @param \App\Domain\Auth\JwtService $jwt Сервис JWT
     * @param string $accessToken Access токен
     * @return array{claims: array<string, mixed>} Результат верификации с claims
     * @throws \Throwable Если токен невалиден
     */
    protected function verifyTokenAndGetClaims(JwtService $jwt, string $accessToken): array
    {
        return $jwt->verify($accessToken, 'access');
    }

    /**
     * Найти пользователя по claims токена.
     *
     * @param array<string, mixed> $claims Claims токена
     * @return \App\Models\User|null Пользователь или null, если не найден
     */
    protected function findUserFromClaims(array $claims): ?User
    {
        $subject = $claims['sub'] ?? null;
        if (! $this->isValidSubject($subject)) {
            return null;
        }

        $userId = (int) $subject;
        return User::query()->find($userId);
    }

    /**
     * Установить аутентифицированного пользователя в guard.
     *
     * @param \App\Models\User $user Пользователь
     * @return void
     */
    protected function setAuthenticatedUser(User $user): void
    {
        Auth::shouldUse(self::GUARD);
        Auth::setUser($user);
    }

    /**
     * Проверить валидность subject claim токена.
     *
     * Subject должен быть положительным целым числом (ID пользователя).
     *
     * @param mixed $subject Subject claim из токена
     * @return bool
     */
    protected function isValidSubject(mixed $subject): bool
    {
        if (! is_numeric($subject)) {
            return false;
        }

        $intVal = (int) $subject;
        
        return $intVal > 0 && (string) $intVal === trim((string) $subject);
    }
}

