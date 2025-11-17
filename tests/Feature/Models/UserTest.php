<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Entry;
use App\Models\RefreshToken;
use App\Models\PostType;
use Illuminate\Support\Facades\Hash;

/**
 * Feature-тесты для модели User.
 *
 * Проверяют реальное взаимодействие модели с базой данных,
 * создание, связи и валидацию.
 */

test('user can be created with factory', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->toBe('John Doe')
        ->and($user->email)->toBe('john@example.com')
        ->and($user->exists)->toBeTrue();

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'john@example.com',
    ]);
});

test('admin user can be created', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->is_admin)->toBeTrue();

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
        'is_admin' => true,
    ]);
});

test('user can have multiple entries', function () {
    $user = User::factory()->create();
    $postType = PostType::factory()->create();

    $entry1 = Entry::factory()->create([
        'author_id' => $user->id,
        'post_type_id' => $postType->id,
    ]);

    $entry2 = Entry::factory()->create([
        'author_id' => $user->id,
        'post_type_id' => $postType->id,
    ]);

    $user->load('entries');

    expect($user->entries)->toHaveCount(2)
        ->and($user->entries->pluck('id')->toArray())->toContain($entry1->id, $entry2->id);
});

test('user can have multiple refresh tokens', function () {
    $user = User::factory()->create();

    $token1 = RefreshToken::create([
        'user_id' => $user->id,
        'jti' => 'jti-' . uniqid(),
        'expires_at' => now()->addDays(7),
    ]);

    $token2 = RefreshToken::create([
        'user_id' => $user->id,
        'jti' => 'jti-' . uniqid(),
        'expires_at' => now()->addDays(7),
    ]);

    $user->load('refreshTokens');

    expect($user->refreshTokens)->toHaveCount(2)
        ->and($user->refreshTokens->pluck('id')->toArray())->toContain($token1->id, $token2->id);
});

test('user password is hashed', function () {
    $user = User::factory()->create([
        'password' => 'plain-password',
    ]);

    expect(Hash::check('plain-password', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('plain-password');
});

test('user email is unique', function () {
    User::factory()->create(['email' => 'unique@example.com']);

    $this->expectException(\Illuminate\Database\QueryException::class);

    User::factory()->create(['email' => 'unique@example.com']);
});

test('user can have admin permissions', function () {
    $user = User::factory()->create([
        'is_admin' => false,
        'admin_permissions' => ['manage.entries', 'manage.media'],
    ]);

    $user->refresh();

    expect($user->admin_permissions)->toBe(['manage.entries', 'manage.media'])
        ->and($user->hasAdminPermission('manage.entries'))->toBeTrue()
        ->and($user->hasAdminPermission('manage.media'))->toBeTrue()
        ->and($user->hasAdminPermission('manage.plugins'))->toBeFalse();
});

test('user admin_permissions defaults to empty array', function () {
    $user = User::factory()->create();

    expect($user->admin_permissions)->toBe([])
        ->and($user->adminPermissions())->toBe([]);
});

test('user password and remember_token are hidden from serialization', function () {
    $user = User::factory()->create();

    $array = $user->toArray();

    expect($array)->not->toHaveKey('password')
        ->and($array)->not->toHaveKey('remember_token');
});

test('user email_verified_at is stored as datetime', function () {
    $now = now();
    $user = User::factory()->create([
        'email_verified_at' => $now,
    ]);

    $user->refresh();

    expect($user->email_verified_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($user->email_verified_at->timestamp)->toBe($now->timestamp);
});

