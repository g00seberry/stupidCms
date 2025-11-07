<?php

namespace App\Domain\Auth;

/**
 * Repository interface for managing refresh tokens.
 */
interface RefreshTokenRepository
{
    /**
     * Store a new refresh token in the database.
     *
     * @param array $data Token data: user_id, jti, kid, expires_at, parent_jti?
     * @return void
     */
    public function store(array $data): void;

    /**
     * Conditionally mark a refresh token as used (only if still valid).
     * Returns the number of affected rows (should be 1 for success, 0 if already used/revoked/expired).
     *
     * This is the only safe way to mark a token as used, as it performs an atomic conditional update
     * that prevents race conditions and double-spend attacks.
     *
     * @param string $jti JWT ID
     * @return int Number of affected rows (0 or 1)
     */
    public function markUsedConditionally(string $jti): int;

    /**
     * Revoke a refresh token (logout/admin action).
     *
     * @param string $jti JWT ID
     * @return void
     */
    public function revoke(string $jti): void;

    /**
     * Revoke a token and all its descendants in the refresh chain (token family invalidation).
     * Used when reuse attack is detected.
     *
     * @param string $jti JWT ID of the token to revoke
     * @return int Number of revoked tokens (including the token itself and all descendants)
     */
    public function revokeFamily(string $jti): int;

    /**
     * Find a refresh token by its JTI.
     *
     * @param string $jti JWT ID
     * @return RefreshTokenDto|null Token DTO or null if not found
     */
    public function find(string $jti): ?RefreshTokenDto;

    /**
     * Delete expired refresh tokens (cleanup).
     *
     * @return int Number of deleted tokens
     */
    public function deleteExpired(): int;
}

