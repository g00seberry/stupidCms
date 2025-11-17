<?php

declare(strict_types=1);

use App\Domain\Auth\RefreshTokenRepositoryImpl;
use App\Models\RefreshToken;
use App\Models\User;

/**
 * Feature-тесты для RefreshTokenRepositoryImpl.
 */

beforeEach(function () {
    $this->repository = new RefreshTokenRepositoryImpl();
    $this->user = User::factory()->create();
});

test('stores refresh token', function () {
    $data = [
        'user_id' => $this->user->id,
        'jti' => 'test-jti-' . uniqid(),
        'expires_at' => now()->addDays(7),
    ];

    $this->repository->store($data);

    $this->assertDatabaseHas('refresh_tokens', [
        'user_id' => $this->user->id,
        'jti' => $data['jti'],
    ]);
});

test('finds refresh token by jti', function () {
    $jti = 'test-jti-' . uniqid();
    
    RefreshToken::create([
        'user_id' => $this->user->id,
        'jti' => $jti,
        'expires_at' => now()->addDays(7),
    ]);

    $dto = $this->repository->find($jti);

    expect($dto)->not->toBeNull()
        ->and($dto->jti)->toBe($jti)
        ->and($dto->user_id)->toBe($this->user->id);
});

test('returns null when token not found', function () {
    $dto = $this->repository->find('non-existent-jti');

    expect($dto)->toBeNull();
});

test('marks token as used conditionally', function () {
    $jti = 'test-jti-' . uniqid();
    
    RefreshToken::create([
        'user_id' => $this->user->id,
        'jti' => $jti,
        'expires_at' => now()->addDays(7),
    ]);

    $affected = $this->repository->markUsedConditionally($jti);

    expect($affected)->toBe(1);
    
    $token = RefreshToken::where('jti', $jti)->first();
    expect($token->used_at)->not->toBeNull();
});

test('does not mark already used token', function () {
    $jti = 'test-jti-' . uniqid();
    
    RefreshToken::create([
        'user_id' => $this->user->id,
        'jti' => $jti,
        'expires_at' => now()->addDays(7),
        'used_at' => now(),
    ]);

    $affected = $this->repository->markUsedConditionally($jti);

    expect($affected)->toBe(0);
});

test('does not mark revoked token', function () {
    $jti = 'test-jti-' . uniqid();
    
    RefreshToken::create([
        'user_id' => $this->user->id,
        'jti' => $jti,
        'expires_at' => now()->addDays(7),
        'revoked_at' => now(),
    ]);

    $affected = $this->repository->markUsedConditionally($jti);

    expect($affected)->toBe(0);
});

test('does not mark expired token', function () {
    $jti = 'test-jti-' . uniqid();
    
    RefreshToken::create([
        'user_id' => $this->user->id,
        'jti' => $jti,
        'expires_at' => now()->subHour(),
    ]);

    $affected = $this->repository->markUsedConditionally($jti);

    expect($affected)->toBe(0);
});

test('revokes refresh token', function () {
    $jti = 'test-jti-' . uniqid();
    
    RefreshToken::create([
        'user_id' => $this->user->id,
        'jti' => $jti,
        'expires_at' => now()->addDays(7),
    ]);

    $this->repository->revoke($jti);

    $token = RefreshToken::where('jti', $jti)->first();
    expect($token->revoked_at)->not->toBeNull();
});

test('revoke family revokes token and all descendants', function () {
    // Create token family: root -> child1 -> grandchild
    $rootJti = 'root-' . uniqid();
    $child1Jti = 'child1-' . uniqid();
    $grandchildJti = 'grandchild-' . uniqid();

    RefreshToken::create([
        'user_id' => $this->user->id,
        'jti' => $rootJti,
        'expires_at' => now()->addDays(7),
        'parent_jti' => null,
    ]);

    RefreshToken::create([
        'user_id' => $this->user->id,
        'jti' => $child1Jti,
        'expires_at' => now()->addDays(7),
        'parent_jti' => $rootJti,
    ]);

    RefreshToken::create([
        'user_id' => $this->user->id,
        'jti' => $grandchildJti,
        'expires_at' => now()->addDays(7),
        'parent_jti' => $child1Jti,
    ]);

    // Revoke the child (should revoke child and grandchild, and find root to revoke it too)
    $revoked = $this->repository->revokeFamily($child1Jti);

    expect($revoked)->toBeGreaterThanOrEqual(1);

    // All tokens in family should be revoked
    $root = RefreshToken::where('jti', $rootJti)->first();
    $child1 = RefreshToken::where('jti', $child1Jti)->first();
    $grandchild = RefreshToken::where('jti', $grandchildJti)->first();

    expect($root->revoked_at)->not->toBeNull()
        ->and($child1->revoked_at)->not->toBeNull()
        ->and($grandchild->revoked_at)->not->toBeNull();
});

test('revoke family returns zero for non existent token', function () {
    $revoked = $this->repository->revokeFamily('non-existent-jti');

    expect($revoked)->toBe(0);
});

test('deletes expired tokens', function () {
    // Create expired token
    $expiredJti = 'expired-' . uniqid();
    RefreshToken::create([
        'user_id' => $this->user->id,
        'jti' => $expiredJti,
        'expires_at' => now()->subDays(1),
    ]);

    // Create valid token
    $validJti = 'valid-' . uniqid();
    RefreshToken::create([
        'user_id' => $this->user->id,
        'jti' => $validJti,
        'expires_at' => now()->addDays(7),
    ]);

    $deleted = $this->repository->deleteExpired();

    expect($deleted)->toBeGreaterThanOrEqual(1);

    $this->assertDatabaseMissing('refresh_tokens', ['jti' => $expiredJti]);
    $this->assertDatabaseHas('refresh_tokens', ['jti' => $validJti]);
});

test('supports token rotation with parent jti', function () {
    $parentJti = 'parent-' . uniqid();
    $childJti = 'child-' . uniqid();

    // Create parent token
    $this->repository->store([
        'user_id' => $this->user->id,
        'jti' => $parentJti,
        'expires_at' => now()->addDays(7),
    ]);

    // Create child token with parent reference
    $this->repository->store([
        'user_id' => $this->user->id,
        'jti' => $childJti,
        'expires_at' => now()->addDays(7),
        'parent_jti' => $parentJti,
    ]);

    $childDto = $this->repository->find($childJti);

    expect($childDto->parent_jti)->toBe($parentJti);
});

test('dto is valid when token is valid', function () {
    $jti = 'test-jti-' . uniqid();
    
    RefreshToken::create([
        'user_id' => $this->user->id,
        'jti' => $jti,
        'expires_at' => now()->addDays(7),
    ]);

    $dto = $this->repository->find($jti);

    expect($dto->isValid())->toBeTrue();
});

test('dto is invalid when token is used', function () {
    $jti = 'test-jti-' . uniqid();
    
    RefreshToken::create([
        'user_id' => $this->user->id,
        'jti' => $jti,
        'expires_at' => now()->addDays(7),
        'used_at' => now(),
    ]);

    $dto = $this->repository->find($jti);

    expect($dto->isInvalid())->toBeTrue();
});

