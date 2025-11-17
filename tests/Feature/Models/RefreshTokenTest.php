<?php

declare(strict_types=1);

use App\Models\RefreshToken;
use App\Models\User;

/**
 * Feature-тесты для модели RefreshToken.
 */

test('refresh token can be created', function () {
    $user = User::factory()->create();
    
    $token = RefreshToken::create([
        'user_id' => $user->id,
        'jti' => 'test-jti-' . uniqid(),
        'expires_at' => now()->addDays(7),
    ]);

    expect($token)->toBeInstanceOf(RefreshToken::class)
        ->and($token->exists)->toBeTrue();

    $this->assertDatabaseHas('refresh_tokens', [
        'id' => $token->id,
        'user_id' => $user->id,
    ]);
});

test('refresh token belongs to user', function () {
    $user = User::factory()->create();
    $token = RefreshToken::create([
        'user_id' => $user->id,
        'jti' => 'test-jti-' . uniqid(),
        'expires_at' => now()->addDays(7),
    ]);

    $token->load('user');

    expect($token->user)->toBeInstanceOf(User::class)
        ->and($token->user->id)->toBe($user->id);
});

test('refresh token can be used once', function () {
    $user = User::factory()->create();
    $token = RefreshToken::create([
        'user_id' => $user->id,
        'jti' => 'test-jti-' . uniqid(),
        'expires_at' => now()->addDays(7),
    ]);

    expect($token->isValid())->toBeTrue();

    $token->update(['used_at' => now()]);

    expect($token->isValid())->toBeFalse();
});

test('refresh token can be revoked', function () {
    $user = User::factory()->create();
    $token = RefreshToken::create([
        'user_id' => $user->id,
        'jti' => 'test-jti-' . uniqid(),
        'expires_at' => now()->addDays(7),
    ]);

    $token->update(['revoked_at' => now()]);

    expect($token->isValid())->toBeFalse();
});

test('refresh token supports rotation', function () {
    $user = User::factory()->create();
    
    $oldToken = RefreshToken::create([
        'user_id' => $user->id,
        'jti' => 'old-jti',
        'expires_at' => now()->addDays(7),
    ]);

    $newToken = RefreshToken::create([
        'user_id' => $user->id,
        'jti' => 'new-jti',
        'expires_at' => now()->addDays(7),
        'parent_jti' => 'old-jti',
    ]);

    expect($newToken->parent_jti)->toBe('old-jti');
});

