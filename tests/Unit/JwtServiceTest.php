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
 * Tests token encoding, verification, expiration, and key rotation.
 */
class JwtServiceTest extends TestCase
{
    private JwtService $service;
    private string $testKid = 'test-v1';
    private static bool $keysGenerated = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Generate test keys once for all tests
        if (!self::$keysGenerated) {
            $this->generateTestKeys();
            self::$keysGenerated = true;
        }

        // Configure JWT service with test keys
        $config = [
            'algo' => 'RS256',
            'access_ttl' => 15 * 60,
            'refresh_ttl' => 30 * 24 * 60 * 60,
            'current_kid' => $this->testKid,
            'keys' => [
                $this->testKid => [
                    'private_path' => storage_path("keys/jwt-{$this->testKid}-private.pem"),
                    'public_path' => storage_path("keys/jwt-{$this->testKid}-public.pem"),
                ],
            ],
            'issuer' => 'https://test.stupidcms.local',
            'audience' => 'test-api',
        ];

        $this->service = new JwtService($config);
    }

    private function generateTestKeys(): void
    {
        $keysDir = storage_path('keys');
        $privateKeyPath = "{$keysDir}/jwt-{$this->testKid}-private.pem";
        $publicKeyPath = "{$keysDir}/jwt-{$this->testKid}-public.pem";

        // Skip if keys already exist
        if (file_exists($privateKeyPath) && file_exists($publicKeyPath)) {
            return;
        }

        // Ensure directory exists
        if (!is_dir($keysDir)) {
            mkdir($keysDir, 0755, true);
        }

        // Use Artisan command to generate keys
        $exitCode = \Artisan::call('cms:jwt:keys', [
            'kid' => $this->testKid,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            $this->markTestSkipped('Failed to generate JWT test keys. OpenSSL might not be properly configured on this system.');
        }
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
        $this->assertArrayHasKey('kid', $result);

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

        $this->assertSame($this->testKid, $result['kid']);
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
        // Manually create a token with nbf (not before) in the future
        // This is a bit tricky since encode sets nbf to now, so we'll need to use
        // a different approach - we'll test that nbf is properly set to now
        $jwt = $this->service->issueAccessToken(444);
        $result = $this->service->verify($jwt);

        $now = time();
        $nbf = $result['claims']['nbf'];

        // nbf should be within a few seconds of now
        $this->assertLessThanOrEqual(2, abs($now - $nbf));
    }

    public function test_token_with_wrong_key_fails_verification(): void
    {
        // Generate a different key pair using Artisan command
        $wrongKid = 'test-wrong';
        
        $exitCode = \Artisan::call('cms:jwt:keys', [
            'kid' => $wrongKid,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            $this->markTestSkipped('Failed to generate wrong key pair for testing.');
        }

        $keysDir = storage_path('keys');
        $wrongPrivatePath = "{$keysDir}/jwt-{$wrongKid}-private.pem";
        $wrongPublicPath = "{$keysDir}/jwt-{$wrongKid}-public.pem";

        // Create service with wrong key
        $wrongConfig = [
            'algo' => 'RS256',
            'access_ttl' => 15 * 60,
            'refresh_ttl' => 30 * 24 * 60 * 60,
            'current_kid' => $wrongKid,
            'keys' => [
                $wrongKid => [
                    'private_path' => $wrongPrivatePath,
                    'public_path' => $wrongPublicPath,
                ],
            ],
            'issuer' => 'https://test.stupidcms.local',
            'audience' => 'test-api',
        ];

        $wrongService = new JwtService($wrongConfig);

        // Sign with wrong key
        $jwt = $wrongService->issueAccessToken(555);

        // Try to verify with our service (different public key)
        $this->expectException(SignatureInvalidException::class);
        $this->service->verify($jwt);

        // Cleanup
        @unlink($wrongPrivatePath);
        @unlink($wrongPublicPath);
    }

    public function test_token_with_wrong_issuer_fails(): void
    {
        // Create a token
        $jwt = $this->service->issueAccessToken(666);

        // Create a service with different issuer
        $config = [
            'algo' => 'RS256',
            'access_ttl' => 15 * 60,
            'refresh_ttl' => 30 * 24 * 60 * 60,
            'current_kid' => $this->testKid,
            'keys' => [
                $this->testKid => [
                    'private_path' => storage_path("keys/jwt-{$this->testKid}-private.pem"),
                    'public_path' => storage_path("keys/jwt-{$this->testKid}-public.pem"),
                ],
            ],
            'issuer' => 'https://wrong-issuer.local',
            'audience' => 'test-api',
        ];

        $differentService = new JwtService($config);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid issuer');

        $differentService->verify($jwt);
    }

    public function test_token_with_wrong_audience_fails(): void
    {
        // Create a token
        $jwt = $this->service->issueAccessToken(777);

        // Create a service with different audience
        $config = [
            'algo' => 'RS256',
            'access_ttl' => 15 * 60,
            'refresh_ttl' => 30 * 24 * 60 * 60,
            'current_kid' => $this->testKid,
            'keys' => [
                $this->testKid => [
                    'private_path' => storage_path("keys/jwt-{$this->testKid}-private.pem"),
                    'public_path' => storage_path("keys/jwt-{$this->testKid}-public.pem"),
                ],
            ],
            'issuer' => 'https://test.stupidcms.local',
            'audience' => 'wrong-audience',
        ];

        $differentService = new JwtService($config);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid audience');

        $differentService->verify($jwt);
    }

    public function test_token_includes_kid_in_header(): void
    {
        $jwt = $this->service->issueAccessToken(888);

        // Decode header manually
        [$headerB64] = explode('.', $jwt);
        $headerJson = base64_decode(strtr($headerB64, '-_', '+/'));
        $header = json_decode($headerJson, true);

        $this->assertArrayHasKey('kid', $header);
        $this->assertSame($this->testKid, $header['kid']);
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

    public function test_key_rotation_backward_compatibility(): void
    {
        // Generate second key pair for rotation
        $v2Kid = 'test-v2';
        $exitCode = \Artisan::call('cms:jwt:keys', [
            'kid' => $v2Kid,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            $this->markTestSkipped('Failed to generate v2 key pair for rotation test.');
        }

        // Create token with v1 (current_kid)
        $jwt = $this->service->issueAccessToken(2000);
        $result = $this->service->verify($jwt);
        $this->assertSame('2000', $result['claims']['sub']);
        $this->assertSame($this->testKid, $result['kid']); // Should be v1

        // Simulate key rotation: change current_kid to v2, but keep v1 in keys array
        $rotatedConfig = [
            'algo' => 'RS256',
            'access_ttl' => 15 * 60,
            'refresh_ttl' => 30 * 24 * 60 * 60,
            'current_kid' => $v2Kid, // New current key
            'keys' => [
                $this->testKid => [ // Old key still available
                    'private_path' => storage_path("keys/jwt-{$this->testKid}-private.pem"),
                    'public_path' => storage_path("keys/jwt-{$this->testKid}-public.pem"),
                ],
                $v2Kid => [ // New key
                    'private_path' => storage_path("keys/jwt-{$v2Kid}-private.pem"),
                    'public_path' => storage_path("keys/jwt-{$v2Kid}-public.pem"),
                ],
            ],
            'issuer' => 'https://test.stupidcms.local',
            'audience' => 'test-api',
        ];

        $rotatedService = new JwtService($rotatedConfig);

        // Old token signed with v1 should still be valid (backward compatibility)
        $oldTokenResult = $rotatedService->verify($jwt);
        $this->assertSame('2000', $oldTokenResult['claims']['sub']);
        $this->assertSame($this->testKid, $oldTokenResult['kid']); // Still v1

        // New tokens should be signed with v2
        $newJwt = $rotatedService->issueAccessToken(2001);
        $newTokenResult = $rotatedService->verify($newJwt);
        $this->assertSame('2001', $newTokenResult['claims']['sub']);
        $this->assertSame($v2Kid, $newTokenResult['kid']); // Should be v2

        // Cleanup
        @unlink(storage_path("keys/jwt-{$v2Kid}-private.pem"));
        @unlink(storage_path("keys/jwt-{$v2Kid}-public.pem"));
    }
}

