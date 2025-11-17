<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\RefreshToken;
use App\Models\Audit;
use function Pest\Laravel\postJson;

/**
 * Feature-тесты для LoginController.
 * 
 * Тестирует POST /api/v1/admin/auth/login
 */

test('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'user' => ['id', 'email', 'name'],
        ])
        ->assertJson([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ]);
});

test('user receives access and refresh tokens on login', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk();
    
    // Check cookies are set
    $response->assertCookie(config('jwt.cookies.access'))
        ->assertCookie(config('jwt.cookies.refresh'));
});

test('refresh token is stored in database on login', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
    ]);

    postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $this->assertDatabaseHas('refresh_tokens', [
        'user_id' => $user->id,
    ]);
    
    expect(RefreshToken::where('user_id', $user->id)->count())->toBe(1);
});

test('login fails with invalid email', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = postJson('/api/v1/auth/login', [
        'email' => 'wrong@example.com',
        'password' => 'password123',
    ]);

    $response->assertUnauthorized()
        ->assertJson([
            'detail' => 'Invalid credentials.',
        ]);
});

test('login fails with invalid password', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertUnauthorized()
        ->assertJson([
            'detail' => 'Invalid credentials.',
        ]);
});

test('login fails with missing credentials', function () {
    $response = postJson('/api/v1/auth/login', []);

    $response->assertStatus(422);
});

test('login is case insensitive for email', function () {
    $user = User::factory()->create([
        'email' => 'Test@Example.COM',
        'password' => bcrypt('password123'),
    ]);

    $response = postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJson([
            'user' => [
                'id' => $user->id,
            ],
        ]);
});

test('successful login is logged in audit', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
    ]);

    postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $this->assertDatabaseHas('audits', [
        'user_id' => $user->id,
        'action' => 'login',
        'subject_type' => User::class,
    ]);
});

test('failed login is logged in audit', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
    ]);

    postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrongpassword',
    ]);

    $this->assertDatabaseHas('audits', [
        'user_id' => null,
        'action' => 'login_failed',
    ]);
});

test('login validates email format', function () {
    $response = postJson('/api/v1/auth/login', [
        'email' => 'not-an-email',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('login requires password', function () {
    $response = postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('multiple logins create multiple refresh tokens', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
    ]);

    // First login
    postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    // Second login
    postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    expect(RefreshToken::where('user_id', $user->id)->count())->toBe(2);
});

