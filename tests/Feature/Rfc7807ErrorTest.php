<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use App\Support\Errors\ErrorCode;
use Tests\TestCase;

class Rfc7807ErrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['jwt.auth'])->get('/test/protected', function () {
            return response()->json(['message' => 'OK']);
        });

        Route::middleware(['jwt.auth', 'can:plugins.sync'])->get('/test/forbidden', function () {
            return response()->json(['message' => 'FORBIDDEN ROUTE']);
        });
    }
    public function test_validation_error_returns_422_problem_json(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'invalid-email',
            'password' => '',
        ]);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR, [
            'detail' => 'The email field must be a valid email address.',
        ]);
        $this->assertValidationErrors($response, [
            'email' => 'The email field must be a valid email address.',
            'password' => 'The password field is required.',
        ]);
    }

    public function test_not_found_returns_404_problem_json(): void
    {
        $response = $this->getJson('/api/v1/nonexistent');

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $this->assertErrorResponse($response, ErrorCode::NOT_FOUND, [
            'detail' => 'The requested resource was not found.',
        ]);
    }

    public function test_rate_limit_returns_429_problem_json(): void
    {
        // Make enough requests to trigger rate limit
        for ($i = 0; $i < 11; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password',
            ]);
        }

        // Last request should be rate limited
        $response->assertStatus(429);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        $this->assertErrorResponse($response, ErrorCode::RATE_LIMIT_EXCEEDED, [
            'detail' => 'Rate limit exceeded.',
        ]);
    }

    public function test_unauthorized_returns_401_problem_json(): void
    {
        $response = $this->getJson('/test/protected');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('WWW-Authenticate', 'Bearer');
        $this->assertErrorResponse($response, ErrorCode::UNAUTHORIZED, [
            'detail' => 'Authentication is required to access this resource.',
            'meta.reason' => 'missing_token',
        ]);
    }

    public function test_forbidden_returns_403_problem_json(): void
    {
        $user = \App\Models\User::factory()->create(['password' => bcrypt('password123'), 'is_admin' => false]);

        // Login to get token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $loginResponse->assertOk();
        $accessCookie = $this->getUnencryptedCookie($loginResponse, config('jwt.cookies.access'));

        $response = $this->call('GET', '/test/forbidden', [], [
            $accessCookie->getName() => $accessCookie->getValue(),
        ], [], $this->transformHeadersToServerVars([
            'Accept' => 'application/json',
        ]));

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertErrorResponse($response, ErrorCode::FORBIDDEN, [
            'detail' => 'This action is unauthorized.',
        ]);
    }
}

