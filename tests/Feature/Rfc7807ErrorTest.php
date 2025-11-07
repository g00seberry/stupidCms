<?php

namespace Tests\Feature;

use Tests\TestCase;

class Rfc7807ErrorTest extends TestCase
{
    public function test_validation_error_returns_422_problem_json(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'invalid-email',
            'password' => '',
        ]);

        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Unprocessable Entity',
            'status' => 422,
            'detail' => 'Validation failed.',
        ]);
        $response->assertJsonStructure([
            'errors',
        ]);
    }

    public function test_not_found_returns_404_problem_json(): void
    {
        $response = $this->getJson('/api/v1/nonexistent');

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Not Found',
            'status' => 404,
            'detail' => 'Route not found.',
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
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Too Many Requests',
            'status' => 429,
            'detail' => 'Rate limit exceeded.',
        ]);
    }

    public function test_unauthorized_returns_401_problem_json(): void
    {
        // Try to access protected route without token
        \Route::middleware(['admin.auth'])->get('/test/protected', function () {
            return response()->json(['message' => 'OK']);
        });

        $response = $this->getJson('/test/protected');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Unauthorized',
            'status' => 401,
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
        $accessCookie = $loginResponse->getCookie(config('jwt.cookies.access'));

        // Try to access admin route with regular token
        \Route::middleware(['admin.auth'])->get('/test/admin', function () {
            return response()->json(['message' => 'OK']);
        });

        $response = $this->withCookie($accessCookie->getName(), $accessCookie->getValue())
            ->getJson('/test/admin');

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Forbidden',
            'status' => 403,
        ]);
    }
}

