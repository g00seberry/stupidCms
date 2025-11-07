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

        // Ensure JWT keys exist for tests
        $this->ensureJwtKeysExist();
    }

    private function ensureJwtKeysExist(): void
    {
        $keysDir = storage_path('keys');
        $privateKeyPath = "{$keysDir}/jwt-v1-private.pem";
        $publicKeyPath = "{$keysDir}/jwt-v1-public.pem";

        // Skip if keys already exist
        if (file_exists($privateKeyPath) && file_exists($publicKeyPath)) {
            return;
        }

        // Ensure directory exists
        if (!is_dir($keysDir)) {
            mkdir($keysDir, 0755, true);
        }

        // Try to generate keys using Artisan command
        try {
            $exitCode = \Artisan::call('cms:jwt:keys', [
                'kid' => 'v1',
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                $this->markTestSkipped('Failed to generate JWT keys. OpenSSL might not be properly configured on this system.');
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Failed to generate JWT keys: ' . $e->getMessage());
        }
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

        $response = $this->withCookie(config('jwt.cookies.access'), 'invalid-token')
            ->getJson('/test/admin');

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
        $user = User::factory()->create(['password' => bcrypt('password123'), 'is_admin' => false]);

        // Login to get regular token (aud=api, scp=['api'])
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $loginResponse->assertOk();
        $accessCookie = $loginResponse->getCookie(config('jwt.cookies.access'));
        $this->assertNotNull($accessCookie);

        \Route::middleware(['admin.auth'])->get('/test/admin', function () {
            return response()->json(['message' => 'OK']);
        });

        // Try to access admin route with regular token
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
        $response = $this->withCookie(config('jwt.cookies.access'), $adminToken)
            ->getJson('/test/admin');

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
        $response = $this->withCookie(config('jwt.cookies.access'), $adminToken)
            ->getJson('/test/admin');

        $response->assertOk();
        $response->assertJson([
            'message' => 'OK',
            'user_id' => $adminUser->id,
        ]);
    }
}

