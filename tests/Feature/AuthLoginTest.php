<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use Tests\Support\FeatureTestCase;

class AuthLoginTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // No JWT key generation needed for HS256
    }

    public function test_login_success_sets_cookies(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secretPass123')]);

        // Login endpoint is excluded from CSRF verification
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secretPass123',
        ]);

        $response->assertOk();
        
        // Проверка наличия cookies
        $response->assertCookie(config('jwt.cookies.access'));
        $response->assertCookie(config('jwt.cookies.refresh'));
        
        // Проверка атрибутов cookies
        $accessCookie = $this->getUnencryptedCookie($response, config('jwt.cookies.access'));
        $refreshCookie = $this->getUnencryptedCookie($response, config('jwt.cookies.refresh'));
        
        $this->assertTrue($accessCookie->isHttpOnly(), 'Access cookie must be HttpOnly');
        $this->assertTrue($refreshCookie->isHttpOnly(), 'Refresh cookie must be HttpOnly');
        
        // Secure зависит от окружения (false в local, true в production)
        // SameSite проверяем через конфиг
        $expectedSameSite = config('jwt.cookies.samesite', 'Strict');
        $this->assertSame(
            strtolower($expectedSameSite),
            strtolower($accessCookie->getSameSite() ?? 'Strict'),
            'Access cookie SameSite must match config'
        );
        $this->assertSame(
            strtolower($expectedSameSite),
            strtolower($refreshCookie->getSameSite() ?? 'Strict'),
            'Refresh cookie SameSite must match config'
        );
        
        $response->assertJsonStructure([
            'user' => ['id', 'email', 'name'],
        ]);
        $response->assertJson([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
        ]);
        
        // Проверка аудита успешного входа
        $this->assertDatabaseHas('audits', [
            'user_id' => $user->id,
            'action' => 'login',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);
    }

    public function test_login_failure_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'no@user.tld',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertCookieMissing(config('jwt.cookies.access'));
        $response->assertCookieMissing(config('jwt.cookies.refresh'));
        $this->assertErrorResponse($response, ErrorCode::UNAUTHORIZED, [
            'detail' => 'Invalid credentials.',
        ]);
        
        // Проверка аудита неуспешного входа
        $this->assertDatabaseHas('audits', [
            'user_id' => null,
            'action' => 'login_failed',
            'subject_type' => User::class,
            'subject_id' => 0,
        ]);
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correctPassword')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrongPassword',
        ]);

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertCookieMissing(config('jwt.cookies.access'));
        $response->assertCookieMissing(config('jwt.cookies.refresh'));
        $this->assertErrorResponse($response, ErrorCode::UNAUTHORIZED, [
            'detail' => 'Invalid credentials.',
        ]);
        
        // Проверка аудита неуспешного входа
        $this->assertDatabaseHas('audits', [
            'user_id' => null,
            'action' => 'login_failed',
            'subject_type' => User::class,
            'subject_id' => 0,
        ]);
    }

    public function test_login_validation_requires_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['email']);
    }

    public function test_login_validation_requires_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['password']);
    }

    public function test_login_validation_email_must_be_valid(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['email']);
    }

    public function test_login_validation_password_min_length(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'short',
        ]);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['password']);
    }

    public function test_login_email_is_case_insensitive(): void
    {
        $user = User::factory()->create([
            'email' => 'Test@Example.com',
            'password' => bcrypt('secretPass123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'secretPass123',
        ]);

        $response->assertOk();
        $response->assertCookie(config('jwt.cookies.access'));
    }

    private function typeUri(ErrorCode $code): string
    {
        return config('errors.types.' . $code->value . '.uri');
    }
}

