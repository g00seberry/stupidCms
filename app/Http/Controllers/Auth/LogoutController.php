<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\JwtService;
use App\Domain\Auth\RefreshTokenRepository;
use App\Http\Controllers\Traits\Problems;
use App\Models\RefreshToken;
use App\Support\JwtCookies;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LogoutController
{
    use Problems;

    public function __construct(
        private JwtService $jwt,
        private RefreshTokenRepository $repo,
    ) {
    }

    /**
     * Handle a logout request.
     *
     * Revokes the refresh token family (to prevent reuse attacks) and clears cookies.
     * Supports ?all=1 query parameter to revoke all refresh tokens for the user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $rt = (string) $request->cookie(config('jwt.cookies.refresh'), '');

        if ($rt === '') {
            // No refresh token: just clear cookies (idempotent)
            return response()->json(['message' => 'Logged out successfully.'])
                ->withCookie(JwtCookies::clearAccess())
                ->withCookie(JwtCookies::clearRefresh());
        }

        try {
            $verified = $this->jwt->verify($rt, 'refresh');
            $claims = $verified['claims']; // jti, sub
        } catch (\Throwable $e) {
            // Invalid RT: clear cookies (without 401, to not break UX logout)
            return response()->json(['message' => 'Logged out successfully.'])
                ->withCookie(JwtCookies::clearAccess())
                ->withCookie(JwtCookies::clearRefresh());
        }

        // Standard logout: revokeFamily(jti) to prevent reuse attacks
        DB::transaction(function () use ($claims, $request) {
            $this->repo->revokeFamily($claims['jti']);

            // Optional: support logout_all (by user) via query ?all=1
            if ($request->boolean('all')) {
                RefreshToken::where('user_id', (int) $claims['sub'])
                    ->update(['revoked_at' => now('UTC')]);
            }
        });

        return response()->json(['message' => 'Logged out successfully.'])
            ->withCookie(JwtCookies::clearAccess())
            ->withCookie(JwtCookies::clearRefresh());
    }
}

