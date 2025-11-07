<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use Carbon\Carbon;

/**
 * Data Transfer Object for RefreshToken.
 *
 * Provides type-safe access to refresh token data without exposing Eloquent model.
 */
final readonly class RefreshTokenDto
{
    public function __construct(
        public int $user_id,
        public string $jti,
        public Carbon $expires_at,
        public ?Carbon $used_at,
        public ?Carbon $revoked_at,
        public ?string $parent_jti,
        public Carbon $created_at,
        public Carbon $updated_at,
    ) {
    }

    /**
     * Check if the token is valid (not used, not revoked, not expired).
     */
    public function isValid(): bool
    {
        return $this->used_at === null
            && $this->revoked_at === null
            && now('UTC')->lt($this->expires_at);
    }

    /**
     * Check if the token is invalid.
     */
    public function isInvalid(): bool
    {
        return !$this->isValid();
    }
}

