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

    public function test_logout_without_token_clears_cookies(): void
    {
        // Acquire CSRF token
        $csrf = $this->getJson('/api/v1/auth/csrf');
        $token = $csrf->json('csrf');
        $cookieName = config('security.csrf.cookie_name');

        $response = $this->postJsonWithUnencryptedCookie('/api/v1/auth/logout', $cookieName, $token, [], [
            'X-CSRF-Token' => $token,
        ]);

        $response->assertNoContent();

        // Verify cookies are cleared (expired)
        $accessCookie = $this->getUnencryptedCookie($response, config('jwt.cookies.access'));
        $refreshCookie = $this->getUnencryptedCookie($response, config('jwt.cookies.refresh'));

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
        $refreshCookie = $this->getUnencryptedCookie($loginResponse, config('jwt.cookies.refresh'));
        $this->assertNotNull($refreshCookie);

        // Refresh once to create a token chain
        $refreshResponse = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ]);

        $refreshResponse->assertOk();

        // Get tokens
        $firstToken = RefreshToken::where('parent_jti', null)->first();
        $secondToken = RefreshToken::where('parent_jti', $firstToken->jti)->first();

        $this->assertNotNull($firstToken);
        $this->assertNotNull($secondToken);
        $this->assertNull($firstToken->revoked_at);
        $this->assertNull($secondToken->revoked_at);

        // Logout
        // Acquire CSRF token
        $csrf = $this->getJson('/api/v1/auth/csrf');
        $token = $csrf->json('csrf');
        $cookieName = config('security.csrf.cookie_name');

        // Send both refresh cookie and CSRF cookie/header
        $logoutResponse = $this->postJsonWithCookies('/api/v1/auth/logout', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
            $cookieName => $token,
        ], [
            'X-CSRF-Token' => $token,
        ]);

        $logoutResponse->assertNoContent();

        // Verify cookies are cleared
        $accessCookie = $this->getUnencryptedCookie($logoutResponse, config('jwt.cookies.access'));
        $refreshCookieCleared = $this->getUnencryptedCookie($logoutResponse, config('jwt.cookies.refresh'));

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

        // Logout with ?all=1
        // Acquire CSRF token
        $csrf = $this->getJson('/api/v1/auth/csrf');
        $token = $csrf->json('csrf');
        $cookieName = config('security.csrf.cookie_name');

        $logoutResponse = $this->postJsonWithCookies('/api/v1/auth/logout?all=1', [], [
            $refreshCookie1->getName() => $refreshCookie1->getValue(),
            $cookieName => $token,
        ], [
            'X-CSRF-Token' => $token,
        ]);

        $logoutResponse->assertNoContent();

        // Verify all tokens are revoked
        $userTokens->each->refresh();
        foreach ($userTokens as $token) {
            $this->assertNotNull($token->revoked_at, "Token {$token->jti} should be revoked");
        }
    }

    public function test_logout_with_invalid_token_clears_cookies(): void
    {
        // Logout with invalid token should still clear cookies (idempotent)
        // Acquire CSRF token
        $csrf = $this->getJson('/api/v1/auth/csrf');
        $token = $csrf->json('csrf');
        $cookieName = config('security.csrf.cookie_name');

        $response = $this->postJsonWithCookies('/api/v1/auth/logout', [], [
            config('jwt.cookies.refresh') => 'invalid-token',
            $cookieName => $token,
        ], [
            'X-CSRF-Token' => $token,
        ]);

        $response->assertNoContent();

        // Verify cookies are cleared
        $accessCookie = $this->getUnencryptedCookie($response, config('jwt.cookies.access'));
        $refreshCookie = $this->getUnencryptedCookie($response, config('jwt.cookies.refresh'));

        $this->assertNotNull($accessCookie);
        $this->assertNotNull($refreshCookie);
        $this->assertTrue($accessCookie->getExpiresTime() < time());
        $this->assertTrue($refreshCookie->getExpiresTime() < time());
    }
}

