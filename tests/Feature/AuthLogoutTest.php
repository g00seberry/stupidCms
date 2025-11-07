<?php

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLogoutTest extends TestCase
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

    public function test_logout_without_token_clears_cookies(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertOk();
        $response->assertJson(['message' => 'Logged out successfully.']);

        // Verify cookies are cleared (expired)
        $accessCookie = $response->getCookie(config('jwt.cookies.access'));
        $refreshCookie = $response->getCookie(config('jwt.cookies.refresh'));

        $this->assertNotNull($accessCookie);
        $this->assertNotNull($refreshCookie);
        $this->assertTrue($accessCookie->getExpiresTime() < time());
        $this->assertTrue($refreshCookie->getExpiresTime() < time());
    }

    public function test_logout_with_valid_token_revokes_family(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        // Login to get initial tokens
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $loginResponse->assertOk();
        $refreshCookie = $loginResponse->getCookie(config('jwt.cookies.refresh'));
        $this->assertNotNull($refreshCookie);

        // Refresh once to create a token chain
        $refreshResponse = $this->withCookie($refreshCookie->getName(), $refreshCookie->getValue())
            ->postJson('/api/v1/auth/refresh');

        $refreshResponse->assertOk();

        // Get tokens
        $firstToken = RefreshToken::where('parent_jti', null)->first();
        $secondToken = RefreshToken::where('parent_jti', $firstToken->jti)->first();

        $this->assertNotNull($firstToken);
        $this->assertNotNull($secondToken);
        $this->assertNull($firstToken->revoked_at);
        $this->assertNull($secondToken->revoked_at);

        // Logout
        $logoutResponse = $this->withCookie($refreshCookie->getName(), $refreshCookie->getValue())
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertOk();
        $logoutResponse->assertJson(['message' => 'Logged out successfully.']);

        // Verify cookies are cleared
        $accessCookie = $logoutResponse->getCookie(config('jwt.cookies.access'));
        $refreshCookieCleared = $logoutResponse->getCookie(config('jwt.cookies.refresh'));

        $this->assertNotNull($accessCookie);
        $this->assertNotNull($refreshCookieCleared);
        $this->assertTrue($accessCookie->getExpiresTime() < time());
        $this->assertTrue($refreshCookieCleared->getExpiresTime() < time());

        // Verify token family is revoked
        $firstToken->refresh();
        $secondToken->refresh();

        $this->assertNotNull($firstToken->revoked_at, 'First token should be revoked');
        $this->assertNotNull($secondToken->revoked_at, 'Second token should be revoked');
    }

    public function test_logout_all_revokes_all_user_tokens(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        // Login multiple times to create multiple token chains
        $login1 = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $login1->assertOk();
        $refreshCookie1 = $login1->getCookie(config('jwt.cookies.refresh'));

        // Login again (simulate different device/session)
        $login2 = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $login2->assertOk();
        $refreshCookie2 = $login2->getCookie(config('jwt.cookies.refresh'));

        // Verify we have multiple tokens
        $userTokens = RefreshToken::where('user_id', $user->id)->get();
        $this->assertGreaterThanOrEqual(2, $userTokens->count());

        // Logout with ?all=1
        $logoutResponse = $this->withCookie($refreshCookie1->getName(), $refreshCookie1->getValue())
            ->postJson('/api/v1/auth/logout?all=1');

        $logoutResponse->assertOk();

        // Verify all tokens are revoked
        $userTokens->each->refresh();
        foreach ($userTokens as $token) {
            $this->assertNotNull($token->revoked_at, "Token {$token->jti} should be revoked");
        }
    }

    public function test_logout_with_invalid_token_clears_cookies(): void
    {
        // Logout with invalid token should still clear cookies (idempotent)
        $response = $this->withCookie(config('jwt.cookies.refresh'), 'invalid-token')
            ->postJson('/api/v1/auth/logout');

        $response->assertOk();
        $response->assertJson(['message' => 'Logged out successfully.']);

        // Verify cookies are cleared
        $accessCookie = $response->getCookie(config('jwt.cookies.access'));
        $refreshCookie = $response->getCookie(config('jwt.cookies.refresh'));

        $this->assertNotNull($accessCookie);
        $this->assertNotNull($refreshCookie);
        $this->assertTrue($accessCookie->getExpiresTime() < time());
        $this->assertTrue($refreshCookie->getExpiresTime() < time());
    }
}

