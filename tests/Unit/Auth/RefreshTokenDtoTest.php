<?php

declare(strict_types=1);

use App\Domain\Auth\RefreshTokenDto;
use Carbon\Carbon;

/**
 * Unit-тесты для RefreshTokenDto.
 */

test('creates dto with all properties', function () {
    $userId = 1;
    $jti = 'test-jti-123';
    $expiresAt = Carbon::now()->addDays(7);
    $usedAt = null;
    $revokedAt = null;
    $parentJti = 'parent-jti-456';
    $createdAt = Carbon::now();
    $updatedAt = Carbon::now();

    $dto = new RefreshTokenDto(
        user_id: $userId,
        jti: $jti,
        expires_at: $expiresAt,
        used_at: $usedAt,
        revoked_at: $revokedAt,
        parent_jti: $parentJti,
        created_at: $createdAt,
        updated_at: $updatedAt,
    );

    expect($dto->user_id)->toBe($userId)
        ->and($dto->jti)->toBe($jti)
        ->and($dto->expires_at)->toBe($expiresAt)
        ->and($dto->used_at)->toBeNull()
        ->and($dto->revoked_at)->toBeNull()
        ->and($dto->parent_jti)->toBe($parentJti)
        ->and($dto->created_at)->toBe($createdAt)
        ->and($dto->updated_at)->toBe($updatedAt);
});

test('is valid when not used not revoked and not expired', function () {
    $dto = new RefreshTokenDto(
        user_id: 1,
        jti: 'test-jti',
        expires_at: Carbon::now('UTC')->addHour(),
        used_at: null,
        revoked_at: null,
        parent_jti: null,
        created_at: Carbon::now('UTC'),
        updated_at: Carbon::now('UTC'),
    );

    expect($dto->isValid())->toBeTrue()
        ->and($dto->isInvalid())->toBeFalse();
});

test('is invalid when used', function () {
    $dto = new RefreshTokenDto(
        user_id: 1,
        jti: 'test-jti',
        expires_at: Carbon::now('UTC')->addHour(),
        used_at: Carbon::now('UTC'),
        revoked_at: null,
        parent_jti: null,
        created_at: Carbon::now('UTC'),
        updated_at: Carbon::now('UTC'),
    );

    expect($dto->isValid())->toBeFalse()
        ->and($dto->isInvalid())->toBeTrue();
});

test('is invalid when revoked', function () {
    $dto = new RefreshTokenDto(
        user_id: 1,
        jti: 'test-jti',
        expires_at: Carbon::now('UTC')->addHour(),
        used_at: null,
        revoked_at: Carbon::now('UTC'),
        parent_jti: null,
        created_at: Carbon::now('UTC'),
        updated_at: Carbon::now('UTC'),
    );

    expect($dto->isValid())->toBeFalse()
        ->and($dto->isInvalid())->toBeTrue();
});

test('is invalid when expired', function () {
    $dto = new RefreshTokenDto(
        user_id: 1,
        jti: 'test-jti',
        expires_at: Carbon::now('UTC')->subHour(),
        used_at: null,
        revoked_at: null,
        parent_jti: null,
        created_at: Carbon::now('UTC'),
        updated_at: Carbon::now('UTC'),
    );

    expect($dto->isValid())->toBeFalse()
        ->and($dto->isInvalid())->toBeTrue();
});

test('is readonly', function () {
    $dto = new RefreshTokenDto(
        user_id: 1,
        jti: 'test-jti',
        expires_at: Carbon::now('UTC')->addHour(),
        used_at: null,
        revoked_at: null,
        parent_jti: null,
        created_at: Carbon::now('UTC'),
        updated_at: Carbon::now('UTC'),
    );

    // Try to modify a property (should fail in PHP 8.1+)
    $reflection = new ReflectionClass($dto);
    expect($reflection->isReadOnly())->toBeTrue();
});

