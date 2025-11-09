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
        // No JWT key generation needed for HS256
    }

    public function test_logout_without_token_returns_401(): void
    {
        // Logout now requires JWT authentication
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
        $response->assertJson([
            'status' => 401,
            'title' => 'Unauthorized',
        ]);
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
        $accessCookie = $this->getUnencryptedCookie($loginResponse, config('jwt.cookies.access'));
        $refreshCookie = $this->getUnencryptedCookie($loginResponse, config('jwt.cookies.refresh'));
        $this->assertNotNull($accessCookie);
        $this->assertNotNull($refreshCookie);

        // Refresh once to create a token chain
        $refreshResponse = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ]);

        $refreshResponse->assertOk();
        $newAccessCookie = $this->getUnencryptedCookie($refreshResponse, config('jwt.cookies.access'));
        $newRefreshCookie = $this->getUnencryptedCookie($refreshResponse, config('jwt.cookies.refresh'));

        // Get tokens
        $firstToken = RefreshToken::where('parent_jti', null)->first();
        $secondToken = RefreshToken::where('parent_jti', $firstToken->jti)->first();

        $this->assertNotNull($firstToken);
        $this->assertNotNull($secondToken);
        $this->assertNull($firstToken->revoked_at);
        $this->assertNull($secondToken->revoked_at);

        // Logout with JWT access token (no CSRF needed)
        $logoutResponse = $this->postJsonWithCookies('/api/v1/auth/logout', [], [
            $newAccessCookie->getName() => $newAccessCookie->getValue(),
            $newRefreshCookie->getName() => $newRefreshCookie->getValue(),
        ]);

        $logoutResponse->assertNoContent();

        // Verify cookies are cleared
        $accessCookieCleared = $this->getUnencryptedCookie($logoutResponse, config('jwt.cookies.access'));
        $refreshCookieCleared = $this->getUnencryptedCookie($logoutResponse, config('jwt.cookies.refresh'));

        $this->assertNotNull($accessCookieCleared);
        $this->assertNotNull($refreshCookieCleared);
        $this->assertTrue($accessCookieCleared->getExpiresTime() < time());
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
        $accessCookie1 = $this->getUnencryptedCookie($login1, config('jwt.cookies.access'));
        $refreshCookie1 = $this->getUnencryptedCookie($login1, config('jwt.cookies.refresh'));

        // Login again (simulate different device/session)
        $login2 = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $login2->assertOk();
        $refreshCookie2 = $this->getUnencryptedCookie($login2, config('jwt.cookies.refresh'));

        // Verify we have multiple tokens
        $userTokens = RefreshToken::where('user_id', $user->id)->get();
        $this->assertGreaterThanOrEqual(2, $userTokens->count());

        // Logout with ?all=1 using JWT access token (no CSRF needed)
        $logoutResponse = $this->postJsonWithCookies('/api/v1/auth/logout?all=1', [], [
            $accessCookie1->getName() => $accessCookie1->getValue(),
            $refreshCookie1->getName() => $refreshCookie1->getValue(),
        ]);

        $logoutResponse->assertNoContent();

        // Verify all tokens are revoked
        $userTokens->each->refresh();
        foreach ($userTokens as $token) {
            $this->assertNotNull($token->revoked_at, "Token {$token->jti} should be revoked");
        }
    }

    public function test_logout_with_invalid_access_token_returns_401(): void
    {
        // Logout with invalid JWT access token should return 401
        $response = $this->postJsonWithCookies('/api/v1/auth/logout', [], [
            config('jwt.cookies.access') => 'invalid-token',
            config('jwt.cookies.refresh') => 'invalid-refresh-token',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'status' => 401,
            'title' => 'Unauthorized',
        ]);
    }
}

