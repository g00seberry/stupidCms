<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthCurrentUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_current_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'name' => 'Test Admin',
        ]);

        $response = $this->getJsonAsAdmin('/api/v1/admin/auth/current', $user);

        $response->assertOk();
        $response->assertHeader('Vary', 'Cookie');
        
        // Check Cache-Control contains required directives
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        
        $response->assertJson([
            'id' => $user->id,
            'email' => 'admin@example.com',
            'name' => 'Test Admin',
        ]);
    }

    public function test_returns_401_when_not_authenticated(): void
    {
        $response = $this->getJson('/api/v1/admin/auth/current');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }

    public function test_returns_401_with_invalid_token(): void
    {
        $response = $this->getJsonWithUnencryptedCookie(
            '/api/v1/admin/auth/current',
            config('jwt.cookies.access'),
            'invalid-token'
        );

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="admin"');
    }
}

