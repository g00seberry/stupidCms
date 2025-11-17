<?php

declare(strict_types=1);

use App\Domain\Auth\JwtService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Unit-тесты для JwtService.
 */

beforeEach(function () {
    $this->config = [
        'secret' => 'test-secret-key-for-testing',
        'algo' => 'HS256',
        'issuer' => 'stupidcms-test',
        'audience' => 'stupidcms-api',
        'access_ttl' => 900, // 15 minutes
        'refresh_ttl' => 604800, // 7 days
    ];
    
    $this->service = new JwtService($this->config);
});

test('issues access token with correct claims', function () {
    $userId = 1;
    $token = $this->service->issueAccessToken($userId);

    expect($token)->toBeString()->not->toBeEmpty();

    $decoded = JWT::decode($token, new Key($this->config['secret'], 'HS256'));
    
    expect($decoded->sub)->toBe((string) $userId)
        ->and($decoded->typ)->toBe('access')
        ->and($decoded->iss)->toBe('stupidcms-test')
        ->and($decoded->aud)->toBe('stupidcms-api')
        ->and($decoded)->toHaveKeys(['jti', 'iat', 'nbf', 'exp']);
});

test('issues refresh token with correct claims', function () {
    $userId = 2;
    $token = $this->service->issueRefreshToken($userId);

    expect($token)->toBeString()->not->toBeEmpty();

    $decoded = JWT::decode($token, new Key($this->config['secret'], 'HS256'));
    
    expect($decoded->sub)->toBe((string) $userId)
        ->and($decoded->typ)->toBe('refresh')
        ->and($decoded->iss)->toBe('stupidcms-test')
        ->and($decoded->aud)->toBe('stupidcms-api');
});

test('includes extra claims in token', function () {
    $userId = 3;
    $extra = ['role' => 'admin', 'permissions' => ['edit', 'delete']];
    
    $token = $this->service->issueAccessToken($userId, $extra);

    $decoded = JWT::decode($token, new Key($this->config['secret'], 'HS256'));
    
    expect($decoded->role)->toBe('admin')
        ->and($decoded->permissions)->toBe(['edit', 'delete']);
});

test('verifies valid access token', function () {
    $userId = 1;
    $token = $this->service->issueAccessToken($userId);

    $result = $this->service->verify($token, 'access');

    expect($result)->toHaveKey('claims')
        ->and($result['claims']['sub'])->toBe((string) $userId)
        ->and($result['claims']['typ'])->toBe('access');
});

test('verifies valid refresh token', function () {
    $userId = 2;
    $token = $this->service->issueRefreshToken($userId);

    $result = $this->service->verify($token, 'refresh');

    expect($result)->toHaveKey('claims')
        ->and($result['claims']['sub'])->toBe((string) $userId)
        ->and($result['claims']['typ'])->toBe('refresh');
});

test('rejects token with wrong type', function () {
    $token = $this->service->issueAccessToken(1);

    $this->service->verify($token, 'refresh');
})->throws(UnexpectedValueException::class, "Expected token type 'refresh', got 'access'");

test('rejects token with invalid issuer', function () {
    // Create token with different issuer
    $badConfig = array_merge($this->config, ['issuer' => 'bad-issuer']);
    $badService = new JwtService($badConfig);
    $token = $badService->issueAccessToken(1);

    $this->service->verify($token);
})->throws(UnexpectedValueException::class, 'Invalid issuer');

test('rejects token with invalid signature', function () {
    $userId = 1;
    $token = $this->service->issueAccessToken($userId);

    // Try to verify with different secret
    $badConfig = array_merge($this->config, ['secret' => 'wrong-secret']);
    $badService = new JwtService($badConfig);

    $badService->verify($token);
})->throws(Firebase\JWT\SignatureInvalidException::class);

test('extracts user id from token', function () {
    $userId = 42;
    $token = $this->service->issueAccessToken($userId);

    $result = $this->service->verify($token);

    expect($result['claims']['sub'])->toBe((string) $userId);
});

test('extracts jti from token', function () {
    $token = $this->service->issueAccessToken(1);

    $result = $this->service->verify($token);

    expect($result['claims']['jti'])->toBeString()->not->toBeEmpty();
});

test('token includes correct expiration time for access token', function () {
    $before = now()->addSeconds($this->config['access_ttl'])->getTimestamp();
    $token = $this->service->issueAccessToken(1);
    $after = now()->addSeconds($this->config['access_ttl'])->getTimestamp();

    $result = $this->service->verify($token);

    expect($result['claims']['exp'])->toBeGreaterThanOrEqual($before)
        ->and($result['claims']['exp'])->toBeLessThanOrEqual($after);
});

test('token includes correct expiration time for refresh token', function () {
    $before = now()->addSeconds($this->config['refresh_ttl'])->getTimestamp();
    $token = $this->service->issueRefreshToken(1);
    $after = now()->addSeconds($this->config['refresh_ttl'])->getTimestamp();

    $result = $this->service->verify($token);

    expect($result['claims']['exp'])->toBeGreaterThanOrEqual($before)
        ->and($result['claims']['exp'])->toBeLessThanOrEqual($after);
});

test('throws exception when secret is not configured', function () {
    $badConfig = array_merge($this->config, ['secret' => '']);
    $service = new JwtService($badConfig);

    $service->issueAccessToken(1);
})->throws(RuntimeException::class, 'JWT secret key is not configured');

test('verify without type check accepts any token type', function () {
    $accessToken = $this->service->issueAccessToken(1);
    $refreshToken = $this->service->issueRefreshToken(2);

    $accessResult = $this->service->verify($accessToken, null);
    $refreshResult = $this->service->verify($refreshToken, null);

    expect($accessResult['claims']['typ'])->toBe('access')
        ->and($refreshResult['claims']['typ'])->toBe('refresh');
});

test('encode includes all standard jwt claims', function () {
    $token = $this->service->encode(1, 'custom', 3600);

    $result = $this->service->verify($token);

    expect($result['claims'])->toHaveKeys([
        'iss', 'aud', 'iat', 'nbf', 'exp', 'jti', 'sub', 'typ'
    ]);
});

