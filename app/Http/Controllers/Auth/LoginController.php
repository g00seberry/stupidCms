<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\JwtService;
use App\Domain\Auth\RefreshTokenRepository;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Admin\LoginResource;
use App\Models\Audit;
use App\Models\User;
use App\Support\Http\ProblemType;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

final class LoginController
{
    use Problems;

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
     */
    public function login(LoginRequest $request): LoginResource
    {
        $email = strtolower($request->input('email'));
        $password = (string) $request->input('password');

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user || ! Hash::check($password, $user->password)) {
            $this->logAudit('login_failed', null, $request);

            throw new HttpResponseException(
                $this->problem(
                    ProblemType::UNAUTHORIZED,
                    detail: 'Invalid credentials.'
                )
            );
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

