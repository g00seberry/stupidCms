<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RefreshToken model for tracking JWT refresh tokens.
 *
 * @property int $id
 * @property int $user_id
 * @property string $jti
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property string|null $parent_jti
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class RefreshToken extends Model
{
    protected $fillable = [
        'user_id',
        'jti',
        'expires_at',
        'used_at',
        'revoked_at',
        'parent_jti',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Get the user that owns the refresh token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * Check if the token has been used or revoked.
     */
    public function isInvalid(): bool
    {
        return ! $this->isValid();
    }
}
