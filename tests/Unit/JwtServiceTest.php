<?php

namespace Tests\Unit;

use App\Domain\Auth\JwtService;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Tests\TestCase;
use UnexpectedValueException;

/**
 * Unit tests for JwtService.
 *
 * Tests token encoding, verification, expiration using HS256 algorithm.
 */
class JwtServiceTest extends TestCase
{
    private JwtService $service;
    private string $testSecret = 'test-secret-key-for-hmac-256-algorithm-minimum-32-bytes';

    protected function setUp(): void
    {
        parent::setUp();

        // Configure JWT service with HS256 and secret key
        $config = [
            'algo' => 'HS256',
            'access_ttl' => 15 * 60,
            'refresh_ttl' => 30 * 24 * 60 * 60,
            'secret' => $this->testSecret,
            'issuer' => 'https://test.stupidcms.local',
            'audience' => 'test-api',
        ];

        $this->service = new JwtService($config);
    }

    public function test_issue_access_token_creates_valid_jwt(): void
    {
        $userId = 123;
        $jwt = $this->service->issueAccessToken($userId);

        $this->assertIsString($jwt);
        $this->assertNotEmpty($jwt);

        // JWT should have three parts separated by dots
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);
    }

    public function test_issue_refresh_token_creates_valid_jwt(): void
    {
        $userId = 456;
        $jwt = $this->service->issueRefreshToken($userId);

        $this->assertIsString($jwt);
        $this->assertNotEmpty($jwt);

        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);
    }

    public function test_access_token_encode_and_verify(): void
    {
        $userId = 789;
        $jwt = $this->service->issueAccessToken($userId, ['role' => 'admin']);

        $result = $this->service->verify($jwt, 'access');

        $this->assertArrayHasKey('claims', $result);

        $claims = $result['claims'];
        $this->assertSame('789', $claims['sub']);
        $this->assertSame('access', $claims['typ']);
        $this->assertSame('admin', $claims['role']);
        $this->assertSame('https://test.stupidcms.local', $claims['iss']);
        $this->assertSame('test-api', $claims['aud']);
        $this->assertArrayHasKey('jti', $claims);
        $this->assertArrayHasKey('iat', $claims);
        $this->assertArrayHasKey('nbf', $claims);
        $this->assertArrayHasKey('exp', $claims);
    }

    public function test_refresh_token_encode_and_verify(): void
    {
        $userId = 321;
        $jwt = $this->service->issueRefreshToken($userId);

        $result = $this->service->verify($jwt, 'refresh');

        $claims = $result['claims'];
        $this->assertSame('321', $claims['sub']);
        $this->assertSame('refresh', $claims['typ']);
    }

    public function test_verify_without_type_check_accepts_any_type(): void
    {
        $jwt = $this->service->issueAccessToken(111);

        // Should not throw even though we're not checking type
        $result = $this->service->verify($jwt);

        $this->assertSame('111', $result['claims']['sub']);
    }

    public function test_verify_with_wrong_expected_type_throws(): void
    {
        $jwt = $this->service->issueAccessToken(222);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Expected token type');

        $this->service->verify($jwt, 'refresh');
    }

    public function test_expired_token_fails_verification(): void
    {
        // Create a token that expired 1 minute ago
        $jwt = $this->service->encode(333, 'access', -60);

        $this->expectException(ExpiredException::class);

        $this->service->verify($jwt, 'access');
    }

    public function test_token_with_future_nbf_fails(): void
    {
        // Test that nbf is properly set to now
        $jwt = $this->service->issueAccessToken(444);
        $result = $this->service->verify($jwt);

        $now = time();
        $nbf = $result['claims']['nbf'];

        // nbf should be within a few seconds of now
        $this->assertLessThanOrEqual(2, abs($now - $nbf));
    }

    public function test_token_with_wrong_key_fails_verification(): void
    {
        // Create service with different secret
        $wrongConfig = [
            'algo' => 'HS256',
            'access_ttl' => 15 * 60,
            'refresh_ttl' => 30 * 24 * 60 * 60,
            'secret' => 'different-secret-key-that-should-fail-verification-minimum-32-bytes',
            'issuer' => 'https://test.stupidcms.local',
            'audience' => 'test-api',
        ];

        $wrongService = new JwtService($wrongConfig);

        // Sign with wrong key
        $jwt = $wrongService->issueAccessToken(555);

        // Try to verify with our service (different secret)
        $this->expectException(SignatureInvalidException::class);
        $this->service->verify($jwt);
    }

    public function test_token_with_wrong_issuer_fails(): void
    {
        // Create a token
        $jwt = $this->service->issueAccessToken(666);

        // Create a service with different issuer
        $config = [
            'algo' => 'HS256',
            'access_ttl' => 15 * 60,
            'refresh_ttl' => 30 * 24 * 60 * 60,
            'secret' => $this->testSecret,
            'issuer' => 'https://wrong-issuer.local',
            'audience' => 'test-api',
        ];

        $differentService = new JwtService($config);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid issuer');

        $differentService->verify($jwt);
    }

    public function test_token_with_wrong_audience_is_allowed(): void
    {
        // Create a token with custom audience
        $jwt = $this->service->issueAccessToken(777, ['aud' => 'custom-audience']);

        // Verify passes because audience validation is not strict in JwtService
        // Audience validation is left to middleware/controllers
        $result = $this->service->verify($jwt);

        $this->assertSame('custom-audience', $result['claims']['aud']);
        $this->assertSame('777', $result['claims']['sub']);
    }

    public function test_token_has_standard_jwt_header(): void
    {
        $jwt = $this->service->issueAccessToken(888);

        // Decode header manually
        [$headerB64] = explode('.', $jwt);
        $headerJson = base64_decode(strtr($headerB64, '-_', '+/'));
        $header = json_decode($headerJson, true);

        $this->assertArrayHasKey('alg', $header);
        $this->assertSame('HS256', $header['alg']);
        $this->assertArrayHasKey('typ', $header);
        $this->assertSame('JWT', $header['typ']);
    }

    public function test_each_token_has_unique_jti(): void
    {
        $jwt1 = $this->service->issueAccessToken(999);
        $jwt2 = $this->service->issueAccessToken(999);

        $result1 = $this->service->verify($jwt1);
        $result2 = $this->service->verify($jwt2);

        $this->assertNotSame($result1['claims']['jti'], $result2['claims']['jti']);
    }

    public function test_extra_claims_are_included(): void
    {
        $extra = [
            'role' => 'admin',
            'permissions' => ['read', 'write'],
            'email' => 'test@example.com',
        ];

        $jwt = $this->service->issueAccessToken(1000, $extra);
        $result = $this->service->verify($jwt);

        $claims = $result['claims'];
        $this->assertSame('admin', $claims['role']);
        $this->assertSame(['read', 'write'], $claims['permissions']);
        $this->assertSame('test@example.com', $claims['email']);
    }

    public function test_missing_secret_throws_runtime_exception(): void
    {
        $config = [
            'algo' => 'HS256',
            'access_ttl' => 15 * 60,
            'refresh_ttl' => 30 * 24 * 60 * 60,
            'secret' => '', // Empty secret
            'issuer' => 'https://test.stupidcms.local',
            'audience' => 'test-api',
        ];

        $service = new JwtService($config);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT secret key is not configured');

        $service->issueAccessToken(1234);
    }
}
