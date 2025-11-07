<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\JwtService;
use App\Domain\Auth\RefreshTokenRepository;
use App\Http\Controllers\Traits\Problems;
use App\Models\Audit;
use App\Models\RefreshToken;
use App\Models\User;
use App\Support\JwtCookies;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RefreshController
{
    use Problems;
    public function __construct(
        private JwtService $jwt,
        private RefreshTokenRepository $repo,
    ) {
    }

    /**
     * Handle a token refresh request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        // Получить refresh token из cookie
        $rt = (string) $request->cookie(config('jwt.cookies.refresh'), '');
        if ($rt === '') {
            return $this->unauthorized('Missing refresh token.')
                ->withCookie(JwtCookies::clearAccess())
                ->withCookie(JwtCookies::clearRefresh());
        }

        // Верифицировать JWT токен
        try {
            $verified = $this->jwt->verify($rt, 'refresh');
            $claims = $verified['claims'];
        } catch (Throwable $e) {
            return $this->unauthorized('Invalid or expired refresh token.')
                ->withCookie(JwtCookies::clearAccess())
                ->withCookie(JwtCookies::clearRefresh());
        }

        // Проверить токен в БД
        $tokenDto = $this->repo->find($claims['jti']);
        if (! $tokenDto) {
            return $this->unauthorized('Refresh token not found.')
                ->withCookie(JwtCookies::clearAccess())
                ->withCookie(JwtCookies::clearRefresh());
        }

        // Проверить соответствие user_id
        if ($tokenDto->user_id !== (int) $claims['sub']) {
            return $this->unauthorized('Token user mismatch.')
                ->withCookie(JwtCookies::clearAccess())
                ->withCookie(JwtCookies::clearRefresh());
        }

        // Проверить, что токен не использован, не отозван и не истёк
        if ($tokenDto->used_at || $tokenDto->revoked_at) {
            // Попытка повторного использования - возможна атака
            $this->handleReuseAttack($userId = (int) $claims['sub'], $claims['jti'], $request);
            return $this->unauthorized('Refresh token has been revoked or already used.')
                ->withCookie(JwtCookies::clearAccess())
                ->withCookie(JwtCookies::clearRefresh());
        }

        if (now('UTC')->gte($tokenDto->expires_at)) {
            return $this->unauthorized('Refresh token has expired.')
                ->withCookie(JwtCookies::clearAccess())
                ->withCookie(JwtCookies::clearRefresh());
        }

        // Транзакция для атомарности: условно пометить старый токен + создать новый
        try {
            return DB::transaction(function () use ($claims, $request) {
                $userId = (int) $claims['sub'];
                
                // Условное обновление: пометить как использованный только если ещё валиден
                $updated = $this->repo->markUsedConditionally($claims['jti']);
                
                if ($updated !== 1) {
                    // Токен уже был использован/отозван между проверкой и обновлением (race condition)
                    // Или истёк - это тоже reuse-атака
                    $this->handleReuseAttack($userId, $claims['jti'], $request);
                    throw new \DomainException('Replay/invalid refresh token');
                }

                // Выпустить новую пару токенов
                $access = $this->jwt->issueAccessToken($userId, ['scp' => ['api']]);
                $newRefresh = $this->jwt->issueRefreshToken($userId, ['parent_jti' => $claims['jti']]);

                // Верифицировать новый refresh токен для получения claims
                $decoded = $this->jwt->verify($newRefresh, 'refresh');

                // Сохранить новый refresh token в БД (используем expires_at из claims['exp'])
                $this->repo->store([
                    'user_id' => $userId,
                    'jti' => $decoded['claims']['jti'],
                    'expires_at' => Carbon::createFromTimestampUTC($decoded['claims']['exp']),
                    'parent_jti' => $claims['jti'],
                ]);

                // Логировать успешный refresh
                $this->logAudit($userId, $request);

                // Вернуть успешный ответ с новыми cookies
                return response()->json(['message' => 'Tokens refreshed successfully.'])
                    ->withCookie(JwtCookies::access($access))
                    ->withCookie(JwtCookies::refresh($newRefresh));
            });
        } catch (\DomainException $e) {
            // Replay/invalid token (domain-level error) - уже обработано в handleReuseAttack
            return $this->unauthorized('Refresh token has been revoked or already used.')
                ->withCookie(JwtCookies::clearAccess())
                ->withCookie(JwtCookies::clearRefresh());
        } catch (Throwable $e) {
            // Infrastructure errors (DB/IO) - внутренняя ошибка сервера
            report($e);
            return $this->internalError('Failed to refresh token due to server error.');
        }
    }

    /**
     * Handle reuse attack: revoke token family and log security event.
     *
     * @param int $userId User ID
     * @param string $jti JWT ID of the reused token
     * @param Request $request HTTP request
     * @return void
     */
    private function handleReuseAttack(int $userId, string $jti, Request $request): void
    {
        // Calculate chain depth (distance from root token)
        $chainDepth = $this->calculateChainDepth($jti);

        // Revoke entire token family (token + all descendants)
        $revokedCount = $this->repo->revokeFamily($jti);

        // Log security event with detailed metadata
        try {
            Audit::create([
                'user_id' => $userId,
                'action' => 'refresh_token_reuse',
                'subject_type' => User::class,
                'subject_id' => $userId,
                'ip' => $request->ip(),
                'ua' => $request->userAgent(),
                'meta' => [
                    'jti' => $jti,
                    'chain_depth' => $chainDepth,
                    'revoked_count' => $revokedCount,
                    'timestamp' => now('UTC')->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            // Не прерываем выполнение при ошибке аудита
            report($e);
        }
    }

    /**
     * Calculate the depth of the token chain (distance from root token).
     *
     * @param string $jti JWT ID
     * @return int Chain depth (0 for root token)
     */
    private function calculateChainDepth(string $jti): int
    {
        $depth = 0;
        $currentJti = $jti;
        $visited = [];

        // Traverse up the chain via parent_jti
        while ($currentJti !== null && !in_array($currentJti, $visited, true)) {
            $visited[] = $currentJti;
            
            $token = RefreshToken::where('jti', $currentJti)->first();
            if (!$token || $token->parent_jti === null) {
                break;
            }
            
            $currentJti = $token->parent_jti;
            $depth++;
            
            // Safety limit to prevent infinite loops
            if ($depth > 1000) {
                break;
            }
        }

        return $depth;
    }

    /**
     * Log audit event for refresh operation.
     *
     * @param int $userId User ID
     * @param Request $request HTTP request
     * @return void
     */
    private function logAudit(int $userId, Request $request): void
    {
        try {
            Audit::create([
                'user_id' => $userId,
                'action' => 'refresh',
                'subject_type' => User::class,
                'subject_id' => $userId,
                'ip' => $request->ip(),
                'ua' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Не прерываем выполнение при ошибке аудита
            report($e);
        }
    }
}
