<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Entry;
use App\Models\RefreshToken;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Unit-тесты для модели User.
 *
 * Проверяют структуру модели, атрибуты, отношения и бизнес-логику
 * без взаимодействия с БД (используем моки где необходимо).
 */

test('has fillable attributes', function () {
    $user = new User();

    $fillable = $user->getFillable();

    expect($fillable)->toContain('name')
        ->and($fillable)->toContain('email')
        ->and($fillable)->toContain('password')
        ->and($fillable)->toContain('email_verified_at');
});

test('guarded is_admin attribute', function () {
    $user = new User();

    $guarded = $user->getGuarded();

    expect($guarded)->toContain('is_admin');
});

test('has notifications relationship', function () {
    $user = new User();

    expect($user->notifications())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
});

test('has entries relationship', function () {
    $user = new User();

    $relation = $user->entries();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Entry::class);
});

test('has refresh tokens relationship', function () {
    $user = new User();

    $relation = $user->refreshTokens();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(RefreshToken::class);
});

test('can check if user is admin', function () {
    $user = new User();
    $user->setAttribute('is_admin', true);

    expect($user->is_admin)->toBeTrue();
});

test('can check if user is regular user', function () {
    $user = new User();
    $user->setAttribute('is_admin', false);

    expect($user->is_admin)->toBeFalse();
});

test('password is cast to hashed', function () {
    $user = new User();

    $casts = $user->getCasts();

    expect($casts)->toHaveKey('password')
        ->and($casts['password'])->toBe('hashed');
});

test('is_admin is cast to boolean', function () {
    $user = new User();

    $casts = $user->getCasts();

    expect($casts)->toHaveKey('is_admin')
        ->and($casts['is_admin'])->toBe('boolean');
});

test('admin_permissions is cast to array', function () {
    $user = new User();

    $casts = $user->getCasts();

    expect($casts)->toHaveKey('admin_permissions')
        ->and($casts['admin_permissions'])->toBe('array');
});

test('email_verified_at is cast to datetime', function () {
    $user = new User();

    $casts = $user->getCasts();

    expect($casts)->toHaveKey('email_verified_at')
        ->and($casts['email_verified_at'])->toBe('datetime');
});

test('returns normalized admin permissions', function () {
    $user = new User();
    $user->setAttribute('admin_permissions', ['manage.entries', 'manage.entries', '', 'manage.media', null, 123]);

    $permissions = $user->adminPermissions();

    expect($permissions)->toBe(['manage.entries', 'manage.media']);
});

test('returns empty array when admin_permissions is null', function () {
    $user = new User();
    $user->setAttribute('admin_permissions', null);

    $permissions = $user->adminPermissions();

    expect($permissions)->toBe([]);
});

test('admin always has any permission', function () {
    $user = new User();
    $user->setAttribute('is_admin', true);

    expect($user->hasAdminPermission('any.permission'))->toBeTrue()
        ->and($user->hasAdminPermission('another.permission'))->toBeTrue();
});

test('regular user has permission if it is in the list', function () {
    $user = new User();
    $user->setAttribute('is_admin', false);
    $user->setAttribute('admin_permissions', ['manage.entries', 'manage.media']);

    expect($user->hasAdminPermission('manage.entries'))->toBeTrue()
        ->and($user->hasAdminPermission('manage.media'))->toBeTrue()
        ->and($user->hasAdminPermission('manage.plugins'))->toBeFalse();
});

test('can grant admin permissions', function () {
    $user = new User();
    $user->setAttribute('is_admin', false);
    $user->setAttribute('admin_permissions', ['manage.entries']);

    $user->grantAdminPermissions('manage.media', 'manage.plugins');

    expect($user->adminPermissions())->toBe(['manage.entries', 'manage.media', 'manage.plugins']);
});

test('grant admin permissions does not create duplicates', function () {
    $user = new User();
    $user->setAttribute('is_admin', false);
    $user->setAttribute('admin_permissions', ['manage.entries']);

    $user->grantAdminPermissions('manage.entries', 'manage.media');

    expect($user->adminPermissions())->toBe(['manage.entries', 'manage.media']);
});

