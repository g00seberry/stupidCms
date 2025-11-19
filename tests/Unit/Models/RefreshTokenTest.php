<?php

declare(strict_types=1);

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

/**
 * Unit-тесты для модели RefreshToken.
 */

uses(TestCase::class);

test('has fillable attributes', function () {
    $token = new RefreshToken();

    $fillable = $token->getFillable();

    expect($fillable)->toContain('user_id')
        ->and($fillable)->toContain('jti')
        ->and($fillable)->toContain('expires_at')
        ->and($fillable)->toContain('used_at')
        ->and($fillable)->toContain('revoked_at')
        ->and($fillable)->toContain('parent_jti');
});

test('casts timestamps to datetime', function () {
    $token = new RefreshToken();

    $casts = $token->getCasts();

    expect($casts)->toHaveKey('expires_at')
        ->and($casts['expires_at'])->toBe('datetime')
        ->and($casts)->toHaveKey('used_at')
        ->and($casts['used_at'])->toBe('datetime')
        ->and($casts)->toHaveKey('revoked_at')
        ->and($casts['revoked_at'])->toBe('datetime');
});

test('belongs to user', function () {
    $token = new RefreshToken();

    $relation = $token->user();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(User::class);
});

test('is valid when not used not revoked and not expired', function () {
    $token = new RefreshToken([
        'used_at' => null,
        'revoked_at' => null,
        'expires_at' => now()->addHour(),
    ]);

    expect($token->isValid())->toBeTrue();
});

test('is invalid when used', function () {
    $token = new RefreshToken([
        'used_at' => now(),
        'revoked_at' => null,
        'expires_at' => now()->addHour(),
    ]);

    expect($token->isValid())->toBeFalse()
        ->and($token->isInvalid())->toBeTrue();
});

test('is invalid when revoked', function () {
    $token = new RefreshToken([
        'used_at' => null,
        'revoked_at' => now(),
        'expires_at' => now()->addHour(),
    ]);

    expect($token->isValid())->toBeFalse()
        ->and($token->isInvalid())->toBeTrue();
});

test('is invalid when expired', function () {
    $token = new RefreshToken([
        'used_at' => null,
        'revoked_at' => null,
        'expires_at' => now()->subHour(),
    ]);

    expect($token->isValid())->toBeFalse()
        ->and($token->isInvalid())->toBeTrue();
});

