<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\JwtService;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Audit;
use App\Models\User;
use App\Support\JwtCookies;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

final class LoginController
{
    public function __construct(private JwtService $jwt)
    {
    }

    /**
     * Handle a login request.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $email = strtolower($request->input('email'));
        $password = (string) $request->input('password');

        // Case-insensitive email search
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user || ! Hash::check($password, $user->password)) {
            // Аудит неуспешного входа
            $this->logAudit('login_failed', null, $request);

            // RFC 7807: problem+json для ошибок аутентификации
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Invalid credentials.',
            ], 401)->header('Content-Type', 'application/problem+json');
        }

        // Аудит успешного входа
        $this->logAudit('login', $user->id, $request);

        // Выпуск токенов
        $access = $this->jwt->issueAccessToken($user->getKey(), ['scp' => ['api']]);
        $refresh = $this->jwt->issueRefreshToken($user->getKey());

        // Ответ + cookies
        return response()->json([
            'user' => [
                'id' => (int) $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
        ])->withCookie(JwtCookies::access($access))
          ->withCookie(JwtCookies::refresh($refresh));
    }

    /**
     * Логирует действие входа в таблицу audits.
     *
     * @param string $action 'login' или 'login_failed'
     * @param int|null $userId ID пользователя (null для неуспешного входа)
     * @param \Illuminate\Http\Request $request
     */
    private function logAudit(string $action, ?int $userId, $request): void
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
        } catch (\Exception $e) {
            // Не прерываем выполнение при ошибке аудита
            // В production можно логировать в отдельный канал
        }
    }
}

