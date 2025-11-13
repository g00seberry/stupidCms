<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Auth\Exceptions\JwtAuthenticationException;
use App\Domain\Auth\JwtService;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware для базовой JWT аутентификации.
 *
 * Проверяет JWT access токен из cookie без требования admin scope.
 * Используется для аутентифицированных эндпоинтов, не требующих прав администратора.
 *
 * @package App\Http\Middleware
 */
final class JwtAuth
{
    use ThrowsErrors;

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
        private JwtService $jwt
    ) {
    }

    /**
     * Обработать входящий запрос.
     *
     * Проверяет JWT access токен из cookie и валидирует:
     * - Токен валиден (подпись, срок действия)
     * - Пользователь существует в базе данных
     *
     * При ошибке выбрасывает HttpErrorException с кодом UNAUTHORIZED.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @param \Closure $next Следующий middleware
     * @return mixed
     * @throws \App\Support\Errors\HttpErrorException
     */
    public function handle(Request $request, Closure $next)
    {
        $accessToken = (string) $request->cookie(config('jwt.cookies.access'), '');

        if ($accessToken === '') {
            $this->respondUnauthorized('missing_token');
        }

        try {
            $verified = $this->jwt->verify($accessToken, 'access');
            $claims = $verified['claims'];
        } catch (\Throwable $e) {
            $this->respondUnauthorized('invalid_token');
        }

        $subject = $claims['sub'] ?? null;
        if (! $this->isValidSubject($subject)) {
            $this->respondUnauthorized('invalid_subject');
        }

        $userId = (int) $subject;
        $user = User::query()->find($userId);
        if (! $user) {
            $this->respondUnauthorized('user_not_found');
        }

        Auth::shouldUse(self::GUARD);
        Auth::setUser($user);

        return $next($request);
    }

    /**
     * Маппинг причин ошибок на детали для логирования.
     *
     * @var array<string, array{log_detail: string}>
     */
    private const FAILURE_RESPONSES = [
        'missing_token' => [
            'log_detail' => 'Access token cookie is missing.',
        ],
        'invalid_token' => [
            'log_detail' => 'Access token is invalid.',
        ],
        'invalid_subject' => [
            'log_detail' => 'Token subject claim is invalid.',
        ],
        'user_not_found' => [
            'log_detail' => 'Authenticated user was not found.',
        ],
    ];

    /**
     * Вернуть ответ 401 Unauthorized с деталями ошибки.
     *
     * Логирует ошибку и выбрасывает HttpErrorException с кодом UNAUTHORIZED.
     *
     * @param string $reason Причина ошибки (missing_token, invalid_token, invalid_subject, user_not_found)
     * @return never
     * @throws \App\Support\Errors\HttpErrorException
     */
    private function respondUnauthorized(string $reason): never
    {
        $response = self::FAILURE_RESPONSES[$reason] ?? [
            'log_detail' => 'Unknown JWT authentication failure.',
        ];

        report(new JwtAuthenticationException($reason, $response['log_detail']));

        $this->throwErrorWithHeaders(
            ErrorCode::UNAUTHORIZED,
            detail: null,
            meta: [
                'reason' => $reason,
                'message' => $response['log_detail'],
            ],
            headers: [
                'WWW-Authenticate' => 'Bearer',
                'Pragma' => 'no-cache',
            ],
        );
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

