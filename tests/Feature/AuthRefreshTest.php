<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\RefreshToken;
use App\Models\User;
use App\Support\Http\ProblemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // No JWT key generation needed for HS256
    }

    public function test_refresh_with_valid_token_returns_new_tokens(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        // First, login to get initial tokens
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $loginResponse->assertOk();
        $refreshCookie = $this->getUnencryptedCookie($loginResponse, config('jwt.cookies.refresh'));
        $this->assertNotNull($refreshCookie);

        // Verify refresh token was stored in database
        $this->assertDatabaseCount('refresh_tokens', 1);
        $tokenBeforeRefresh = RefreshToken::first();
        $this->assertNull($tokenBeforeRefresh->used_at);
        $this->assertNull($tokenBeforeRefresh->revoked_at);

        // Now refresh the tokens
        $refreshResponse = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ]);

        $refreshResponse->assertOk();
        $refreshResponse->assertJson(['message' => 'Tokens refreshed successfully.']);
        
        // Verify new cookies are set
        $refreshResponse->assertCookie(config('jwt.cookies.access'));
        $refreshResponse->assertCookie(config('jwt.cookies.refresh'));

        // Verify old token is marked as used
        $tokenBeforeRefresh->refresh();
        $this->assertNotNull($tokenBeforeRefresh->used_at);

        // Verify new token was created
        $this->assertDatabaseCount('refresh_tokens', 2);
        $newToken = RefreshToken::orderBy('id', 'desc')->first();
        $this->assertNull($newToken->used_at);
        $this->assertNull($newToken->revoked_at);
        $this->assertEquals($tokenBeforeRefresh->jti, $newToken->parent_jti);
    }

    public function test_refresh_with_reused_token_returns_401(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        // Login to get initial tokens
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $refreshCookie = $this->getUnencryptedCookie($loginResponse, config('jwt.cookies.refresh'));

        // First refresh - should work
        $firstRefresh = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ]);
        $firstRefresh->assertOk();

        // Second refresh with same token - should fail
        $secondRefresh = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ]);

        $secondRefresh->assertStatus(401);
        $secondRefresh->assertHeader('Content-Type', 'application/problem+json');
        $secondRefresh->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
        ]);

        // Verify cookies are cleared (expired) on error
        $accessCookie = $this->getUnencryptedCookie($secondRefresh, config('jwt.cookies.access'));
        $refreshCookie = $this->getUnencryptedCookie($secondRefresh, config('jwt.cookies.refresh'));
        // Cookies may be set but expired for clearing
        if ($accessCookie) {
            $this->assertTrue($accessCookie->getExpiresTime() < time(), 'Access cookie should be expired');
        }
        if ($refreshCookie) {
            $this->assertTrue($refreshCookie->getExpiresTime() < time(), 'Refresh cookie should be expired');
        }

        // Verify reuse attack was logged
        $this->assertDatabaseHas('audits', [
            'user_id' => $user->id,
            'action' => 'refresh_token_reuse',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);
    }

    public function test_refresh_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Missing refresh token.',
        ]);
    }

    public function test_refresh_with_invalid_token_returns_401(): void
    {
        $response = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            config('jwt.cookies.refresh') => 'invalid.jwt.token',
        ]);

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
        ]);
    }

    public function test_refresh_with_expired_token_returns_401(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        // Login to get initial tokens
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $refreshCookie = $this->getUnencryptedCookie($loginResponse, config('jwt.cookies.refresh'));

        // Manually expire the token in the database
        $token = RefreshToken::first();
        $token->expires_at = now('UTC')->subDay();
        $token->save();

        // Try to refresh with expired token
        $response = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Refresh token has expired.',
        ]);
    }

    public function test_refresh_with_revoked_token_returns_401(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        // Login to get initial tokens
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $refreshCookie = $this->getUnencryptedCookie($loginResponse, config('jwt.cookies.refresh'));

        // Manually revoke the token
        $token = RefreshToken::first();
        $token->revoked_at = now('UTC');
        $token->save();

        // Try to refresh with revoked token
        $response = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Refresh token has been revoked or already used.',
        ]);
    }

    public function test_refresh_token_chain_tracking(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        // Login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $refreshCookie = $this->getUnencryptedCookie($loginResponse, config('jwt.cookies.refresh'));

        // First token should have no parent
        $firstToken = RefreshToken::first();
        $this->assertNull($firstToken->parent_jti);

        // Refresh once
        $firstRefresh = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ]);
        $newRefreshCookie = $this->getUnencryptedCookie($firstRefresh, config('jwt.cookies.refresh'));

        // Second token should have first token as parent
        $secondToken = RefreshToken::orderBy('id', 'desc')->first();
        $this->assertEquals($firstToken->jti, $secondToken->parent_jti);

        // Refresh again
        $secondRefresh = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $newRefreshCookie->getName() => $newRefreshCookie->getValue(),
        ]);

        // Third token should have second token as parent
        $thirdToken = RefreshToken::orderBy('id', 'desc')->first();
        $this->assertEquals($secondToken->jti, $thirdToken->parent_jti);

        // Verify we have 3 tokens in chain
        $this->assertDatabaseCount('refresh_tokens', 3);
    }

    public function test_refresh_logs_audit_event(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        // Login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $refreshCookie = $this->getUnencryptedCookie($loginResponse, config('jwt.cookies.refresh'));

        // Refresh tokens
        $refreshResponse = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ]);

        $refreshResponse->assertOk();

        // Verify refresh was logged
        $this->assertDatabaseHas('audits', [
            'user_id' => $user->id,
            'action' => 'refresh',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);
    }

    public function test_refresh_uses_expires_at_from_claims(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        // Login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $refreshCookie = $this->getUnencryptedCookie($loginResponse, config('jwt.cookies.refresh'));

        // Get the JWT service to verify claims
        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $decoded = $jwtService->verify($refreshCookie->getValue(), 'refresh');
        $expectedExpiresAt = \Carbon\Carbon::createFromTimestampUTC($decoded['claims']['exp']);

        // Refresh tokens
        $refreshResponse = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ]);

        $refreshResponse->assertOk();

        // Verify new token has expires_at from claims
        $newToken = RefreshToken::orderBy('id', 'desc')->first();
        $newDecoded = $jwtService->verify($this->getUnencryptedCookie($refreshResponse, config('jwt.cookies.refresh'))->getValue(), 'refresh');
        $expectedNewExpiresAt = \Carbon\Carbon::createFromTimestampUTC($newDecoded['claims']['exp']);

        // Allow 1 second difference for timing
        $this->assertTrue(
            abs($newToken->expires_at->timestamp - $expectedNewExpiresAt->timestamp) <= 1,
            'expires_at should match claims[exp]'
        );
    }

    public function test_reuse_attack_revokes_token_family(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        // Login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $refreshCookie = $this->getUnencryptedCookie($loginResponse, config('jwt.cookies.refresh'));
        $firstToken = RefreshToken::first();

        // First refresh - creates second token
        $firstRefresh = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ]);
        $firstRefresh->assertOk();

        $newRefreshCookie = $this->getUnencryptedCookie($firstRefresh, config('jwt.cookies.refresh'));
        $secondToken = RefreshToken::orderBy('id', 'desc')->first();

        // Second refresh - creates third token
        $secondRefresh = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $newRefreshCookie->getName() => $newRefreshCookie->getValue(),
        ]);
        $secondRefresh->assertOk();

        $thirdToken = RefreshToken::orderBy('id', 'desc')->first();

        // Now try to reuse the first token (should revoke entire family)
        $reuseResponse = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ]);

        $reuseResponse->assertStatus(401);

        // Verify all tokens in chain are revoked
        $firstToken->refresh();
        $secondToken->refresh();
        $thirdToken->refresh();

        $this->assertNotNull($firstToken->revoked_at, 'First token should be revoked');
        $this->assertNotNull($secondToken->revoked_at, 'Second token should be revoked');
        $this->assertNotNull($thirdToken->revoked_at, 'Third token should be revoked');
    }

    public function test_refresh_returns_500_on_infrastructure_error(): void
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

        // Drop the refresh_tokens table to simulate DB infrastructure failure
        \Illuminate\Support\Facades\Schema::drop('refresh_tokens');

        // Attempt refresh (should return 500, not 401)
        $refreshResponse = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ]);

        $refreshResponse->assertStatus(500);
        $refreshResponse->assertHeader('Content-Type', 'application/problem+json');
        $refreshResponse->assertJson([
            'type' => ProblemType::INTERNAL_ERROR->value,
            'title' => 'Internal Server Error',
            'status' => 500,
        ]);
        $refreshResponse->assertJsonFragment(['detail' => 'Failed to refresh token due to server error.']);
    }

    public function test_refresh_cookies_have_correct_security_attributes(): void
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

        // Refresh the tokens
        $refreshResponse = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ]);

        $refreshResponse->assertOk();

        // Verify access cookie attributes
        $accessCookie = $this->getUnencryptedCookie($refreshResponse, config('jwt.cookies.access'));
        $this->assertNotNull($accessCookie);
        $this->assertTrue($accessCookie->isHttpOnly(), 'Access cookie should be HttpOnly');
        
        // In test environment, secure depends on APP_ENV
        if (config('app.env') !== 'local') {
            $this->assertTrue($accessCookie->isSecure(), 'Access cookie should be Secure in production');
        }

        // Verify refresh cookie attributes
        $newRefreshCookie = $this->getUnencryptedCookie($refreshResponse, config('jwt.cookies.refresh'));
        $this->assertNotNull($newRefreshCookie);
        $this->assertTrue($newRefreshCookie->isHttpOnly(), 'Refresh cookie should be HttpOnly');
        
        if (config('app.env') !== 'local') {
            $this->assertTrue($newRefreshCookie->isSecure(), 'Refresh cookie should be Secure in production');
        }

        // Verify SameSite attribute (default is Strict)
        $expectedSameSite = config('jwt.cookies.samesite', 'Strict');
        $this->assertEquals(
            strtolower($expectedSameSite),
            strtolower($accessCookie->getSameSite() ?? 'strict'),
            'Access cookie should have correct SameSite attribute'
        );
        $this->assertEquals(
            strtolower($expectedSameSite),
            strtolower($newRefreshCookie->getSameSite() ?? 'strict'),
            'Refresh cookie should have correct SameSite attribute'
        );
    }

    public function test_reuse_attack_logs_metadata_in_audit(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        // Login to get initial tokens
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $loginResponse->assertOk();
        $refreshCookie = $this->getUnencryptedCookie($loginResponse, config('jwt.cookies.refresh'));

        // Refresh once
        $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ])->assertOk();

        // Try to reuse the first token (should trigger reuse attack detection)
        $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ])
            ->assertStatus(401);

        // Verify audit log was created with metadata
        $audit = Audit::where('action', 'refresh_token_reuse')
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($audit, 'Reuse attack should be logged in audit');
        $this->assertNotNull($audit->meta, 'Audit should have metadata');
        $this->assertIsArray($audit->meta, 'Audit meta should be an array');
        $this->assertArrayHasKey('jti', $audit->meta, 'Audit meta should contain jti');
        $this->assertArrayHasKey('chain_depth', $audit->meta, 'Audit meta should contain chain_depth');
        $this->assertArrayHasKey('revoked_count', $audit->meta, 'Audit meta should contain revoked_count');
        $this->assertGreaterThanOrEqual(0, $audit->meta['chain_depth'], 'Chain depth should be >= 0');
        $this->assertGreaterThanOrEqual(1, $audit->meta['revoked_count'], 'At least 1 token should be revoked');
    }

    public function test_auth_endpoints_have_cache_control_no_store_header(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        // Test login endpoint
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $loginResponse->assertOk();
        // Check that Cache-Control contains important directives (order may vary)
        $cacheControl = $loginResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);

        // Test refresh endpoint
        $refreshCookie = $this->getUnencryptedCookie($loginResponse, config('jwt.cookies.refresh'));
        $refreshResponse = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $refreshCookie->getName() => $refreshCookie->getValue(),
        ]);

        $refreshResponse->assertOk();
        $cacheControl = $refreshResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);

        // Get new access token from refresh response
        $newAccessCookie = $this->getUnencryptedCookie($refreshResponse, config('jwt.cookies.access'));
        $newRefreshCookie = $this->getUnencryptedCookie($refreshResponse, config('jwt.cookies.refresh'));

        // Test logout endpoint (requires JWT auth, not CSRF)
        $logoutResponse = $this->postJsonWithCookies('/api/v1/auth/logout', [], [
            $newAccessCookie->getName() => $newAccessCookie->getValue(),
            $newRefreshCookie->getName() => $newRefreshCookie->getValue(),
        ]);

        $logoutResponse->assertNoContent();
        $cacheControl = $logoutResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
    }

    public function test_race_condition_double_refresh_only_one_succeeds(): void
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

        $cookieName = $refreshCookie->getName();
        $cookieValue = $refreshCookie->getValue();

        // Get initial token state
        $initialToken = RefreshToken::first();
        $this->assertNotNull($initialToken);
        $this->assertNull($initialToken->used_at, 'Initial token should not be used');

        // Simulate race condition: two concurrent refresh requests with the same token
        // In a real scenario, these would be parallel HTTP requests
        // Here we simulate by making two sequential requests that both pass initial validation
        // but only one should succeed in marking the token as used

        $successCount = 0;
        $failureCount = 0;

        // First request - should succeed
        $firstResponse = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $cookieName => $cookieValue,
        ]);

        if ($firstResponse->status() === 200) {
            $successCount++;
        } else {
            $failureCount++;
            $firstResponse->assertStatus(401);
            $firstResponse->assertHeader('Content-Type', 'application/problem+json');
        }

        // Second request with the same token - should fail (token already used)
        $secondResponse = $this->postJsonWithCookies('/api/v1/auth/refresh', [], [
            $cookieName => $cookieValue,
        ]);

        if ($secondResponse->status() === 200) {
            $successCount++;
        } else {
            $failureCount++;
            $secondResponse->assertStatus(401);
            $secondResponse->assertHeader('Content-Type', 'application/problem+json');
            $secondResponse->assertJson([
                'type' => 'https://stupidcms.dev/problems/unauthorized',
                'title' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        // Verify exactly one request succeeded
        $this->assertEquals(1, $successCount, 'Exactly one refresh should succeed');
        $this->assertEquals(1, $failureCount, 'Exactly one refresh should fail');

        // Verify token state: should be marked as used
        $initialToken->refresh();
        $this->assertNotNull($initialToken->used_at, 'Token should be marked as used after successful refresh');

        // Verify exactly one new token was created
        $newTokens = RefreshToken::where('parent_jti', $initialToken->jti)->get();
        $this->assertCount(1, $newTokens, 'Exactly one new token should be created');

        // Verify reuse attack was logged if second request triggered it
        $reuseAudits = Audit::where('action', 'refresh_token_reuse')
            ->where('user_id', $user->id)
            ->get();
        
        // If second request was processed, it should have logged reuse attack
        if ($failureCount > 0) {
            $this->assertGreaterThanOrEqual(1, $reuseAudits->count(), 'Reuse attack should be logged');
        }
    }
}
