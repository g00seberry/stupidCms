<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use Carbon\Carbon;

/**
 * Data Transfer Object для RefreshToken.
 *
 * Предоставляет типобезопасный доступ к данным refresh токена
 * без раскрытия Eloquent модели.
 *
 * @package App\Domain\Auth
 */
final readonly class RefreshTokenDto
{
    /**
     * @param int $user_id ID пользователя-владельца токена
     * @param string $jti JWT ID (уникальный идентификатор токена)
     * @param \Carbon\Carbon $expires_at Дата истечения токена (UTC)
     * @param \Carbon\Carbon|null $used_at Дата использования токена (UTC)
     * @param \Carbon\Carbon|null $revoked_at Дата отзыва токена (UTC)
     * @param string|null $parent_jti JWT ID родительского токена (для ротации)
     * @param \Carbon\Carbon $created_at Дата создания
     * @param \Carbon\Carbon $updated_at Дата обновления
     */
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
     *
     * Проверяет, что токен не использован, не отозван и не истёк.
     *
     * @return bool true, если токен валиден
     */
    public function isValid(): bool
    {
        return $this->used_at === null
            && $this->revoked_at === null
            && now('UTC')->lt($this->expires_at);
    }

    /**
     * Check if the token is invalid.
     *
     * Проверяет, что токен невалиден (использован, отозван или истёк).
     *
     * @return bool true, если токен невалиден
     */
    public function isInvalid(): bool
    {
        return !$this->isValid();
    }
}

