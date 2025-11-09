<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\JwtService;
use App\Domain\Auth\RefreshTokenRepository;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Resources\Admin\LogoutResource;
use App\Models\RefreshToken;
use App\Support\JwtCookies;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Cookie;

final class LogoutController
{
    use Problems;

    public function __construct(
        private readonly JwtService $jwt,
        private readonly RefreshTokenRepository $repo,
    ) {
    }

    /**
     * Завершение сессии и отзыв refresh токенов.
     *
     * Требует валидный JWT access token. CSRF защита не требуется,
     * так как JWT guard проверяет токен из HttpOnly cookie.
     *
     * @group Auth
     * @subgroup Sessions
     * @name Logout
     * @authenticated
     * @bodyParam all boolean Отозвать все refresh токены пользователя (значение true). Example: true
     * @responseHeader Set-Cookie "access=; Path=/; HttpOnly; Secure; Max-Age=0"
     * @responseHeader Set-Cookie "refresh=; Path=/; HttpOnly; Secure; Max-Age=0"
     * @response status=204 {}
     */
    public function logout(LogoutRequest $request): LogoutResource
    {
        $cookies = $this->clearCookies();

        $rt = (string) $request->cookie(config('jwt.cookies.refresh'), '');

        if ($rt === '') {
            return new LogoutResource($cookies);
        }

        try {
            $verified = $this->jwt->verify($rt, 'refresh');
            $claims = $verified['claims'];
        } catch (\Throwable) {
            return new LogoutResource($cookies);
        }

        DB::transaction(function () use ($claims, $request): void {
            $this->repo->revokeFamily($claims['jti']);

            if ($request->boolean('all')) {
                RefreshToken::where('user_id', (int) $claims['sub'])
                    ->update(['revoked_at' => now('UTC')]);
            }
        });

        return new LogoutResource($cookies);
    }

    /**
     * @return array<int, Cookie>
     */
    private function clearCookies(): array
    {
        return [
            JwtCookies::clearAccess(),
            JwtCookies::clearRefresh(),
        ];
    }
}

