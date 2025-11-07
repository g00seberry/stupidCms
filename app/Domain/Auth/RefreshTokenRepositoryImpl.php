<?php

namespace App\Domain\Auth;

use App\Models\RefreshToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Implementation of RefreshTokenRepository using Eloquent.
 */
final class RefreshTokenRepositoryImpl implements RefreshTokenRepository
{
    public function store(array $data): void
    {
        RefreshToken::create($data);
    }

    public function markUsedConditionally(string $jti): int
    {
        return RefreshToken::where('jti', $jti)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now('UTC'))
            ->update(['used_at' => now('UTC')]);
    }

    public function revoke(string $jti): void
    {
        RefreshToken::where('jti', $jti)
            ->update(['revoked_at' => now('UTC')]);
    }

    public function revokeFamily(string $jti): int
    {
        // Wrap in transaction to ensure atomicity
        return DB::transaction(function () use ($jti) {
            // Recursively revoke the token and all its descendants
            // 
            // Current implementation: iterative approach (N+1 queries)
            // This is acceptable for rare reuse attacks, but can be optimized for high-load scenarios.
            //
            // Future optimization (MySQL 8.0+/PostgreSQL):
            // Use recursive CTE for single-query revocation:
            // WITH RECURSIVE fam(jti) AS (
            //     SELECT ? AS jti
            //     UNION ALL
            //     SELECT rt.jti FROM refresh_tokens rt JOIN fam ON rt.parent_jti = fam.jti
            // )
            // UPDATE refresh_tokens SET revoked_at = UTC_TIMESTAMP() 
            // WHERE jti IN (SELECT jti FROM fam) AND revoked_at IS NULL;
            
            $revoked = 0;
            $tokensToRevoke = [$jti];
            $processed = [];

            while (!empty($tokensToRevoke)) {
                $currentJti = array_shift($tokensToRevoke);
                
                if (in_array($currentJti, $processed, true)) {
                    continue;
                }

                // Revoke current token
                $affected = RefreshToken::where('jti', $currentJti)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now('UTC')]);
                
                if ($affected > 0) {
                    $revoked += $affected;
                }

                $processed[] = $currentJti;

                // Find all children (tokens with parent_jti = currentJti)
                $children = RefreshToken::where('parent_jti', $currentJti)
                    ->whereNull('revoked_at')
                    ->pluck('jti')
                    ->toArray();

                $tokensToRevoke = array_merge($tokensToRevoke, $children);
            }

            return $revoked;
        });
    }

    public function find(string $jti): ?RefreshTokenDto
    {
        $token = RefreshToken::where('jti', $jti)->first();

        if (! $token) {
            return null;
        }

        return new RefreshTokenDto(
            user_id: $token->user_id,
            jti: $token->jti,
            kid: $token->kid,
            expires_at: $token->expires_at,
            used_at: $token->used_at,
            revoked_at: $token->revoked_at,
            parent_jti: $token->parent_jti,
            created_at: $token->created_at,
            updated_at: $token->updated_at,
        );
    }

    public function deleteExpired(): int
    {
        return RefreshToken::where('expires_at', '<', now('UTC'))->delete();
    }
}

