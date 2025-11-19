<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\JwtService;
use App\Domain\Auth\RefreshTokenRepository;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Admin\LoginResource;
use App\Models\Audit;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Контроллер для аутентификации администратора.
 *
 * Обрабатывает вход в систему, выдаёт JWT токены (access и refresh),
 * сохраняет refresh токен в БД и логирует события аудита.
 *
 * @package App\Http\Controllers\Auth
 */
final class LoginController
{
    use ThrowsErrors;

    /**
     * @param \App\Domain\Auth\JwtService $jwt Сервис для работы с JWT токенами
     * @param \App\Domain\Auth\RefreshTokenRepository $repo Репозиторий refresh токенов
     */
    public function __construct(
        private readonly JwtService $jwt,
        private readonly RefreshTokenRepository $repo,
    ) {
    }

    /**
     * Аутентификация администратора и выдача JWT.
     *
     * @group Auth
     * @subgroup Sessions
     * @name Login
     * @unauthenticated
     * @bodyParam email string required RFC 5322 email в нижнем регистре. Example: admin@stupidcms.dev
     * @bodyParam password string required 8-200 символов. Example: Secret123!
     * @responseHeader Set-Cookie "access=...; Path=/; HttpOnly; Secure"
     * @responseHeader Set-Cookie "refresh=...; Path=/; HttpOnly; Secure"
     * @response status=200 {
     *   "user": {
     *     "id": 1,
     *     "email": "admin@stupidcms.dev",
     *     "name": "Admin"
     *   }
     * }
     * @response status=401 {
     *   "type": "about:blank",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Invalid credentials."
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The given data was invalid.",
     *   "meta": {
     *     "request_id": "dddddddd-dddd-dddd-dddd-dddddddddddd",
     *     "errors": {
     *       "email": [
     *         "The email field is required."
     *       ],
     *       "password": [
     *         "The password field is required."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-dddddddddddddddddddddddddddddddd-dddddddddddd-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee-eeeeeeeeeeee-01"
     * }
     */
    public function login(LoginRequest $request): LoginResource
    {
        $email = strtolower($request->input('email'));
        $password = (string) $request->input('password');

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user || ! Hash::check($password, $user->password)) {
            $this->logAudit('login_failed', null, $request);

            $this->throwError(ErrorCode::UNAUTHORIZED, 'Invalid credentials.');
        }

        $this->logAudit('login', (int) $user->id, $request);

        $access = $this->jwt->issueAccessToken($user->getKey(), ['scp' => ['api']]);
        $refresh = $this->jwt->issueRefreshToken($user->getKey());

        $decoded = $this->jwt->verify($refresh, 'refresh');
        $this->repo->store([
            'user_id' => $user->getKey(),
            'jti' => $decoded['claims']['jti'],
            'expires_at' => Carbon::createFromTimestampUTC($decoded['claims']['exp']),
            'parent_jti' => null,
        ]);

        return new LoginResource($user, $access, $refresh);
    }

    /**
     * Записать событие аудита.
     *
     * Игнорирует ошибки записи аудита, чтобы не прерывать процесс аутентификации.
     *
     * @param string $action Действие (login, login_failed)
     * @param int|null $userId ID пользователя (null для неудачных попыток)
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return void
     */
    private function logAudit(string $action, ?int $userId, Request $request): void
    {
        try {
            Audit::create([
                'user_id' => $userId,
                'action' => $action,
                'subject_type' => User::class,
                'subject_id' => $userId ?? 0,
                'ip' => $request->ip(),
                'ua' => $request->userAgent(),
            ]);
        } catch (\Throwable) {
            // Игнорируем ошибки аудита: безопасность важнее логирования
        }
    }
}

