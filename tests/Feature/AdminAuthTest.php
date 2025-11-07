<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // No JWT key generation needed for HS256
    }

    public function test_admin_auth_without_token_returns_401(): void
    {
        // Create a simple admin route for testing
        \Route::middleware(['admin.auth'])->get('/test/admin', function () {
            return response()->json(['message' => 'OK']);
        });

        $response = $this->getJson('/test/admin');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Unauthorized',
            'status' => 401,
        ]);
    }

    public function test_admin_auth_with_invalid_token_returns_401(): void
    {
        \Route::middleware(['admin.auth'])->get('/test/admin', function () {
            return response()->json(['message' => 'OK']);
        });

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), 'invalid-token');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Unauthorized',
            'status' => 401,
        ]);
    }

    public function test_admin_auth_with_regular_token_returns_403(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        // Issue regular token directly (aud=api, no admin scope)
        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $regularToken = $jwtService->issueAccessToken($user->id);

        \Route::middleware(['admin.auth'])->get('/test/admin', function () {
            return response()->json(['message' => 'OK']);
        });

        // Try to access admin route with regular token (missing aud=admin and scp=['admin'])
        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $regularToken);

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Forbidden',
            'status' => 403,
        ]);
    }

    public function test_admin_auth_with_admin_token_but_non_admin_user_returns_403(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123'), 'is_admin' => false]);

        // Issue admin token manually (aud=admin, scp=['admin'])
        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $adminToken = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
        ]);

        \Route::middleware(['admin.auth'])->get('/test/admin', function () {
            return response()->json(['message' => 'OK']);
        });

        // Try to access admin route with admin token but non-admin user
        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $adminToken);

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => 'Admin role required.',
        ]);
    }

    public function test_admin_auth_with_valid_admin_token_succeeds(): void
    {
        $adminUser = User::factory()->create(['password' => bcrypt('password123'), 'is_admin' => true]);

        // Issue admin token manually (aud=admin, scp=['admin'])
        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $adminToken = $jwtService->issueAccessToken($adminUser->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
        ]);

        \Route::middleware(['admin.auth'])->get('/test/admin', function () {
            return response()->json([
                'message' => 'OK',
                'user_id' => \Auth::id(),
            ]);
        });

        // Access admin route with valid admin token
        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $adminToken);

        $response->assertOk();
        $response->assertJson([
            'message' => 'OK',
            'user_id' => $adminUser->id,
        ]);
    }
}

