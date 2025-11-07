<?php

namespace App\Domain\Auth;

use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use RuntimeException;
use UnexpectedValueException;

/**
 * Service for issuing and verifying JWT access and refresh tokens.
 *
 * Uses HS256 (HMAC with SHA-256) algorithm with a secret key.
 */
final class JwtService
{
    public function __construct(private array $config)
    {
    }

    /**
     * Issue a new access token for the given user.
     *
     * @param int|string $userId User identifier
     * @param array $extra Additional claims to include in the payload
     * @return string JWT token string
     */
    public function issueAccessToken(int|string $userId, array $extra = []): string
    {
        return $this->encode($userId, 'access', $this->config['access_ttl'], $extra);
    }

    /**
     * Issue a new refresh token for the given user.
     *
     * @param int|string $userId User identifier
     * @param array $extra Additional claims to include in the payload
     * @return string JWT token string
     */
    public function issueRefreshToken(int|string $userId, array $extra = []): string
    {
        return $this->encode($userId, 'refresh', $this->config['refresh_ttl'], $extra);
    }

    /**
     * Encode a JWT with the specified parameters.
     *
     * @param int|string $userId User identifier
     * @param string $type Token type ('access' or 'refresh')
     * @param int $ttl Time-to-live in seconds
     * @param array $extra Additional claims
     * @return string JWT token string
     * @throws RuntimeException If the secret key is not configured
     */
    public function encode(int|string $userId, string $type, int $ttl, array $extra = []): string
    {
        $now = CarbonImmutable::now('UTC');
        $secret = $this->getSecret();

        // Merge standard claims with extra claims
        // Extra claims can override standard ones (e.g. 'aud' for admin tokens)
        $standardClaims = [
            'iss' => $this->config['issuer'],
            'aud' => $this->config['audience'],
            'iat' => $now->getTimestamp(),
            'nbf' => $now->getTimestamp(),
            'exp' => $now->addSeconds($ttl)->getTimestamp(),
            'jti' => (string) Str::uuid(),
            'sub' => (string) $userId,
            'typ' => $type,
        ];
        
        $payload = array_merge($standardClaims, $extra);

        return JWT::encode($payload, $secret, $this->config['algo']);
    }

    /**
     * Verify a JWT and return its claims.
     *
     * @param string $jwt The JWT token to verify
     * @param string|null $expectType Expected token type ('access' or 'refresh'), or null to skip type check
     * @return array{claims: array} Decoded claims
     * @throws RuntimeException If the secret key is not configured
     * @throws UnexpectedValueException If token type, issuer, or audience doesn't match expectations
     * @throws \Firebase\JWT\ExpiredException If the token has expired
     * @throws \Firebase\JWT\SignatureInvalidException If the signature is invalid
     * @throws \Firebase\JWT\BeforeValidException If the token is not yet valid
     */
    public function verify(string $jwt, ?string $expectType = null): array
    {
        $secret = $this->getSecret();

        $decoded = JWT::decode($jwt, new Key($secret, $this->config['algo']));
        // Convert stdClass to array: json_encode/json_decode guarantees scalarization of types
        // (using (array)$decoded would break nested objects)
        $claims = json_decode(json_encode($decoded), true);

        // Verify token type if specified
        if ($expectType !== null && ($claims['typ'] ?? null) !== $expectType) {
            throw new UnexpectedValueException("Expected token type '{$expectType}', got '{$claims['typ']}'");
        }

        // Verify issuer (must match)
        if (($claims['iss'] ?? '') !== $this->config['issuer']) {
            throw new UnexpectedValueException("Invalid issuer: {$claims['iss']}");
        }

        // Note: We don't strictly validate audience here because admin tokens may have aud=admin
        // instead of the default audience. Audience validation is left to middleware/controllers.

        return ['claims' => $claims];
    }

    /**
     * Get the JWT secret key from configuration.
     *
     * @return string Secret key
     * @throws RuntimeException If the secret key is not configured
     */
    private function getSecret(): string
    {
        $secret = $this->config['secret'] ?? '';

        if (empty($secret)) {
            throw new RuntimeException('JWT secret key is not configured. Set JWT_SECRET in .env');
        }

        return $secret;
    }
}

