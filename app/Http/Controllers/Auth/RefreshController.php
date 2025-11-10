<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\JwtService;
use App\Domain\Auth\RefreshTokenRepository;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\Auth\RefreshRequest;
use App\Http\Resources\Admin\TokenRefreshResource;
use App\Models\Audit;
use App\Models\RefreshToken;
use App\Models\User;
use App\Support\Http\HttpProblemException;
use App\Support\Http\Problems\RefreshTokenInternalProblem;
use App\Support\Http\Problems\RefreshTokenUnauthorizedProblem;
use App\Support\JwtCookies;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

final class RefreshController
{
    use Problems;
    public function __construct(
        private readonly JwtService $jwt,
        private readonly RefreshTokenRepository $repo,
    ) {
    }

    /**
     * Ротация refresh токена и выдача новой пары JWT.
     *
     * @group Auth
     * @subgroup Sessions
     * @name Refresh token
     * @unauthenticated
     * @responseHeader Set-Cookie "access=...; Path=/; HttpOnly; Secure"
     * @responseHeader Set-Cookie "refresh=...; Path=/; HttpOnly; Secure"
     * @response status=200 {
     *   "message": "Tokens refreshed successfully."
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Missing refresh token."
     * }
     * @response status=500 {
     *   "type": "about:blank",
     *   "title": "Internal Server Error",
     *   "status": 500,
     *   "detail": "Failed to refresh token due to server error."
     * }
     */
    public function refresh(RefreshRequest $request): TokenRefreshResource
    {
        $rt = (string) $request->cookie(config('jwt.cookies.refresh'), '');

        if ($rt === '') {
            $this->throwUnauthorized('Missing refresh token.');
        }

        try {
            $verified = $this->jwt->verify($rt, 'refresh');
            $claims = $verified['claims'];
        } catch (Throwable) {
            $this->throwUnauthorized('Invalid or expired refresh token.');
        }

        try {
            $tokenDto = $this->repo->find($claims['jti']);
            if (! $tokenDto) {
                $this->throwUnauthorized('Refresh token not found.');
            }

            if ($tokenDto->user_id !== (int) $claims['sub']) {
                $this->throwUnauthorized('Token user mismatch.');
            }

            if ($tokenDto->used_at || $tokenDto->revoked_at) {
                $this->handleReuseAttack((int) $claims['sub'], $claims['jti'], $request);
                $this->throwUnauthorized('Refresh token has been revoked or already used.');
            }

            if (Carbon::now('UTC')->gte($tokenDto->expires_at)) {
                $this->throwUnauthorized('Refresh token has expired.');
            }

            [$accessToken, $refreshToken] = DB::transaction(function () use ($claims, $request): array {
                $userId = (int) $claims['sub'];

                $updated = $this->repo->markUsedConditionally($claims['jti']);

                if ($updated !== 1) {
                    $this->handleReuseAttack($userId, $claims['jti'], $request);
                    throw new DomainException('Replay/invalid refresh token');
                }

                $access = $this->jwt->issueAccessToken($userId, ['scp' => ['api']]);
                $newRefresh = $this->jwt->issueRefreshToken($userId, ['parent_jti' => $claims['jti']]);

                $decoded = $this->jwt->verify($newRefresh, 'refresh');

                $this->repo->store([
                    'user_id' => $userId,
                    'jti' => $decoded['claims']['jti'],
                    'expires_at' => Carbon::createFromTimestampUTC($decoded['claims']['exp']),
                    'parent_jti' => $claims['jti'],
                ]);

                $this->logAudit($userId, $request);

                return [$access, $newRefresh];
            });
        } catch (DomainException) {
            throw new RefreshTokenUnauthorizedProblem('Refresh token has been revoked or already used.', $this->clearCookies());
        } catch (HttpProblemException $problem) {
            throw $problem;
        } catch (Throwable $e) {
            report($e);

            throw new RefreshTokenInternalProblem();
        }

        return new TokenRefreshResource($accessToken, $refreshToken);
    }

    /**
     * @return never
     */
    private function throwUnauthorized(string $detail): never
    {
        throw new RefreshTokenUnauthorizedProblem($detail, $this->clearCookies());
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

    /**
     * Handle reuse attack: revoke token family and log security event.
     *
     * @param int $userId User ID
     * @param string $jti JWT ID of the reused token
     * @param RefreshRequest $request HTTP request
     * @return void
     */
    private function handleReuseAttack(int $userId, string $jti, RefreshRequest $request): void
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
                    'timestamp' => Carbon::now('UTC')->toIso8601String(),
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
     * @param RefreshRequest $request HTTP request
     * @return void
     */
    private function logAudit(int $userId, RefreshRequest $request): void
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
