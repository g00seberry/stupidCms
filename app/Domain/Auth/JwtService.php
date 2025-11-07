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
 * Uses RS256 (asymmetric RSA) algorithm with key rotation support via 'kid' header.
 */
final class JwtService
{
    /** @var array<string, string> Cached private keys by kid */
    private array $privateKeys = [];

    /** @var array<string, string> Cached public keys by kid */
    private array $publicKeys = [];

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
     * @throws RuntimeException If the key ID is not configured
     */
    public function encode(int|string $userId, string $type, int $ttl, array $extra = []): string
    {
        $now = CarbonImmutable::now('UTC');
        $kid = $this->config['current_kid'];
        $private = $this->loadPrivateKey($kid);

        $payload = array_merge([
            'iss' => $this->config['issuer'],
            'aud' => $this->config['audience'],
            'iat' => $now->getTimestamp(),
            'nbf' => $now->getTimestamp(),
            'exp' => $now->addSeconds($ttl)->getTimestamp(),
            'jti' => (string) Str::uuid(),
            'sub' => (string) $userId,
            'typ' => $type,
        ], $extra);

        $headers = ['kid' => $kid, 'typ' => 'JWT'];
        return JWT::encode($payload, $private, $this->config['algo'], null, $headers);
    }

    /**
     * Verify a JWT and return its claims.
     *
     * @param string $jwt The JWT token to verify
     * @param string|null $expectType Expected token type ('access' or 'refresh'), or null to skip type check
     * @return array{claims: array, kid: string} Decoded claims and key ID
     * @throws RuntimeException If the key ID is not configured
     * @throws UnexpectedValueException If token type, issuer, or audience doesn't match expectations
     * @throws \Firebase\JWT\ExpiredException If the token has expired
     * @throws \Firebase\JWT\SignatureInvalidException If the signature is invalid
     * @throws \Firebase\JWT\BeforeValidException If the token is not yet valid
     */
    public function verify(string $jwt, ?string $expectType = null): array
    {
        $kid = $this->readKid($jwt) ?? $this->config['current_kid'];
        $public = $this->loadPublicKey($kid);

        $decoded = JWT::decode($jwt, new Key($public, $this->config['algo']));
        // Convert stdClass to array: json_encode/json_decode guarantees scalarization of types
        // (using (array)$decoded would break nested objects)
        $claims = json_decode(json_encode($decoded), true);

        // Verify token type if specified
        if ($expectType !== null && ($claims['typ'] ?? null) !== $expectType) {
            throw new UnexpectedValueException("Expected token type '{$expectType}', got '{$claims['typ']}'");
        }

        // Verify issuer and audience
        if (($claims['iss'] ?? '') !== $this->config['issuer']) {
            throw new UnexpectedValueException("Invalid issuer: {$claims['iss']}");
        }

        if (($claims['aud'] ?? '') !== $this->config['audience']) {
            throw new UnexpectedValueException("Invalid audience: {$claims['aud']}");
        }

        return ['claims' => $claims, 'kid' => $kid];
    }

    /**
     * Extract the 'kid' (key ID) from a JWT header.
     *
     * @param string $jwt The JWT token
     * @return string|null The key ID, or null if not present
     */
    private function readKid(string $jwt): ?string
    {
        $parts = explode('.', $jwt, 2);
        if (count($parts) < 2) {
            return null;
        }

        [$header] = $parts;
        $json = $this->b64urlDecode($header);
        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);
        return $decoded['kid'] ?? null;
    }

    /**
     * Decode base64url string with proper padding.
     *
     * @param string $s Base64url-encoded string
     * @return string|false Decoded string or false on failure
     */
    private function b64urlDecode(string $s): string|false
    {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) {
            $s .= str_repeat('=', 4 - $pad);
        }
        return base64_decode($s);
    }

    /**
     * Load and cache a private key for the given kid.
     *
     * @param string $kid Key ID
     * @return string Private key content
     * @throws RuntimeException If the key ID is not configured or cannot be read
     */
    private function loadPrivateKey(string $kid): string
    {
        if (isset($this->privateKeys[$kid])) {
            return $this->privateKeys[$kid];
        }

        $paths = $this->config['keys'][$kid] ?? throw new RuntimeException("Unknown key ID: {$kid}");
        $private = file_get_contents($paths['private_path']);

        if ($private === false) {
            throw new RuntimeException("Failed to read private key at: {$paths['private_path']}");
        }

        return $this->privateKeys[$kid] = $private;
    }

    /**
     * Load and cache a public key for the given kid.
     *
     * @param string $kid Key ID
     * @return string Public key content
     * @throws RuntimeException If the key ID is not configured or cannot be read
     */
    private function loadPublicKey(string $kid): string
    {
        if (isset($this->publicKeys[$kid])) {
            return $this->publicKeys[$kid];
        }

        $paths = $this->config['keys'][$kid] ?? throw new RuntimeException("Unknown key ID: {$kid}");
        $public = file_get_contents($paths['public_path']);

        if ($public === false) {
            throw new RuntimeException("Failed to read public key at: {$paths['public_path']}");
        }

        return $this->publicKeys[$kid] = $public;
    }
}

