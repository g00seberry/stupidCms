<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent модель для JWT refresh токенов (RefreshToken).
 *
 * Отслеживает refresh токены для обновления access токенов.
 * Поддерживает ротацию токенов через parent_jti и отслеживание использования/отзыва.
 *
 * @property int $id
 * @property int $user_id ID пользователя-владельца токена
 * @property string $jti JWT ID (уникальный идентификатор токена)
 * @property \Illuminate\Support\Carbon $expires_at Дата истечения токена (UTC)
 * @property \Illuminate\Support\Carbon|null $used_at Дата использования токена (UTC)
 * @property \Illuminate\Support\Carbon|null $revoked_at Дата отзыва токена (UTC)
 * @property string|null $parent_jti JWT ID родительского токена (для ротации)
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \App\Models\User $user Пользователь-владелец токена
 */
class RefreshToken extends Model
{
    /**
     * Mass-assignable fields.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'jti',
        'expires_at',
        'used_at',
        'revoked_at',
        'parent_jti',
    ];

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Связь с пользователем-владельцем токена.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, \App\Models\RefreshToken>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * Check if the token has been used or revoked.
     *
     * Проверяет, что токен невалиден (использован, отозван или истёк).
     *
     * @return bool true, если токен невалиден
     */
    public function isInvalid(): bool
    {
        return ! $this->isValid();
    }
}
