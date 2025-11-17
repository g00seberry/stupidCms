<?php

declare(strict_types=1);

use App\Models\User;
use function Pest\Laravel\getJson;
use function Pest\Laravel\actingAs;

/**
 * Feature-тесты для CurrentUserController.
 * 
 * Тестирует GET /api/v1/admin/auth/current
 * 
 * Примечание: JWT middleware отключен в тестах, так как он уже протестирован отдельно.
 */

test('authenticated user can get current user info', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);

    $response = actingAs($user)
        ->withoutMiddleware(\App\Http\Middleware\JwtAuth::class)
        ->getJson('/api/v1/admin/auth/current');

    $response->assertOk()
        ->assertJsonStructure([
            'id',
            'email',
            'name',
        ])
        ->assertJson([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ]);
});

test('unauthenticated request returns 401', function () {
    $response = getJson('/api/v1/admin/auth/current');

    $response->assertUnauthorized();
});

test('returns correct user data structure', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'name' => 'Admin User',
        'is_admin' => true,
    ]);

    $response = actingAs($user)
        ->withoutMiddleware(\App\Http\Middleware\JwtAuth::class)
        ->getJson('/api/v1/admin/auth/current');

    $response->assertOk()
        ->assertJsonStructure([
            'id',
            'email',
            'name',
            'is_admin',
            'created_at',
            'updated_at',
        ]);
});

test('does not expose sensitive fields', function () {
    $user = User::factory()->create([
        'password' => bcrypt('secret123'),
    ]);

    $response = actingAs($user)
        ->withoutMiddleware(\App\Http\Middleware\JwtAuth::class)
        ->getJson('/api/v1/admin/auth/current');

    $response->assertOk()
        ->assertJsonMissing(['password']);
});

test('works with admin user', function () {
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'is_admin' => true,
    ]);

    $response = actingAs($admin)
        ->withoutMiddleware(\App\Http\Middleware\JwtAuth::class)
        ->getJson('/api/v1/admin/auth/current');

    $response->assertOk()
        ->assertJson([
            'id' => $admin->id,
            'email' => $admin->email,
            'is_admin' => true,
        ]);
});

test('works with regular user', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'is_admin' => false,
    ]);

    $response = actingAs($user)
        ->withoutMiddleware(\App\Http\Middleware\JwtAuth::class)
        ->getJson('/api/v1/admin/auth/current');

    $response->assertOk()
        ->assertJson([
            'id' => $user->id,
            'email' => $user->email,
            'is_admin' => false,
        ]);
});

