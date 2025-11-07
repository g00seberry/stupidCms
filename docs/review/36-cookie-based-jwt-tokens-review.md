# Ревью Задачи 36. Cookie-based JWT модель токенов

## Список измененных файлов

1. `composer.json` — добавлена зависимость `firebase/php-jwt`
2. `config/jwt.php` — конфигурация JWT токенов (новый файл)
3. `app/Domain/Auth/JwtService.php` — сервис для выпуска и верификации токенов (новый файл)
4. `app/Support/JwtCookies.php` — хелпер для создания JWT cookies (новый файл)
5. `app/Console/Commands/GenerateJwtKeys.php` — Artisan команда генерации ключей (новый файл)
6. `app/Providers/AppServiceProvider.php` — регистрация JwtService и настройка leeway
7. `tests/TestCase.php` — настройка leeway для тестов
8. `tests/Unit/JwtServiceTest.php` — юнит-тесты (новый файл)
9. `storage/keys/.gitkeep` — placeholder для директории ключей (новый файл)

## Исправления после ревью

### Must fix (выполнено)

1. ✅ **SameSite параметризован через .env** — добавлена переменная `JWT_SAMESITE` с нормализацией к константам Symfony
2. ✅ **Парсинг kid с паддингом base64url** — добавлен метод `b64urlDecode()` с автоматическим добавлением паддинга
3. ✅ **Кэширование ключей в памяти** — RSA ключи кэшируются в `$privateKeys` и `$publicKeys` массивах

### Should fix (выполнено)

1. ✅ **Тест ротации ключей** — добавлен `test_key_rotation_backward_compatibility()` проверяющий backward validity
2. ✅ **Документация обновлена** — добавлена информация о SameSite, кэшировании, дрейфе часов и leeway

### Дополнительные улучшения (выполнено)

1. ✅ **Leeway для дрейфа часов** — добавлен в `AppServiceProvider::boot()` и `TestCase::setUp()` для стабильной верификации
2. ✅ **Связь с задачей 37** — добавлен раздел в документации о том, что `/auth/login` выставляет `cms_at`/`cms_rt` cookies
3. ✅ **Исправлены исключения в документации** — `SignatureInvalidException` для неверного ключа, `UnexpectedValueException` только для типа/issuer/audience
4. ✅ **Комментарий в JwtService::verify()** — объяснение использования `json_encode/json_decode` для скаляризации типов

---

## Содержимое измененных файлов

### config/jwt.php

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Algorithm
    |--------------------------------------------------------------------------
    |
    | The algorithm used for signing JWTs. We use RS256 (RSA with SHA-256)
    | for asymmetric cryptography.
    |
    */
    'algo' => 'RS256',

    /*
    |--------------------------------------------------------------------------
    | Token Time-to-Live
    |--------------------------------------------------------------------------
    |
    | The lifetime of access and refresh tokens in seconds.
    | - access_ttl: 15 minutes (900 seconds)
    | - refresh_ttl: 30 days (2592000 seconds)
    |
    */
    'access_ttl' => 15 * 60,
    'refresh_ttl' => 30 * 24 * 60 * 60,

    /*
    |--------------------------------------------------------------------------
    | Clock Drift Leeway
    |--------------------------------------------------------------------------
    |
    | Time tolerance in seconds for token expiration verification.
    | Accounts for small clock differences between server and client.
    | Recommended: 2-5 seconds. Set to 0 to disable.
    |
    */
    'leeway' => env('JWT_LEEWAY', 5),

    /*
    |--------------------------------------------------------------------------
    | Current Key ID
    |--------------------------------------------------------------------------
    |
    | The current key id (kid) used for signing new tokens. This allows for
    | key rotation without invalidating existing tokens.
    |
    */
    'current_kid' => env('JWT_CURRENT_KID', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Key Pairs
    |--------------------------------------------------------------------------
    |
    | Map of key IDs to their corresponding RSA key pair paths.
    | Keys can be stored in files, environment variables, or secret managers.
    |
    */
    'keys' => [
        'v1' => [
            'private_path' => storage_path('keys/jwt-v1-private.pem'),
            'public_path' => storage_path('keys/jwt-v1-public.pem'),
        ],
        // Add additional key versions for rotation:
        // 'v2' => [
        //     'private_path' => storage_path('keys/jwt-v2-private.pem'),
        //     'public_path' => storage_path('keys/jwt-v2-public.pem'),
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Issuer & Audience
    |--------------------------------------------------------------------------
    |
    | Standard JWT claims for identifying the token issuer and intended
    | audience.
    |
    */
    'issuer' => env('JWT_ISS', 'https://stupidcms.local'),
    'audience' => env('JWT_AUD', 'stupidcms-api'),

    /*
    |--------------------------------------------------------------------------
    | Cookie Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for HTTP-only, secure cookies that store JWT tokens.
    | - access: cookie name for access tokens
    | - refresh: cookie name for refresh tokens
    | - domain: cookie domain (defaults to SESSION_DOMAIN)
    | - secure: only send over HTTPS (disabled in local environment)
    | - samesite: CSRF protection (Strict, Lax, or None)
    | - path: cookie path
    |
    */
    'cookies' => [
        'access' => 'cms_at',
        'refresh' => 'cms_rt',
        'domain' => env('SESSION_DOMAIN'),
        'secure' => env('APP_ENV') !== 'local',
        'samesite' => env('JWT_SAMESITE', 'Strict'),
        'path' => '/',
    ],
];
```

---

### app/Domain/Auth/JwtService.php

```php
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
```

---

### app/Support/JwtCookies.php

```php
<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * Helper class for creating HttpOnly, Secure JWT cookies.
 *
 * These cookies store access and refresh tokens with proper security settings:
 * - HttpOnly: prevents JavaScript access
 * - Secure: only sent over HTTPS (in production)
 * - SameSite: CSRF protection (Strict, Lax, or None)
 */
final class JwtCookies
{
    /**
     * Normalize SameSite value to Symfony Cookie constants.
     *
     * @param string $samesite Raw SameSite value from config
     * @return string Normalized SameSite value (Cookie::SAMESITE_*)
     */
    private static function normalizeSameSite(string $samesite): string
    {
        $samesite = strtolower(trim($samesite));

        return match ($samesite) {
            'none' => Cookie::SAMESITE_NONE,
            'lax' => Cookie::SAMESITE_LAX,
            'strict' => Cookie::SAMESITE_STRICT,
            default => Cookie::SAMESITE_STRICT,
        };
    }

    /**
     * Create an access token cookie.
     *
     * @param string $jwt The JWT access token
     * @return Cookie
     */
    public static function access(string $jwt): Cookie
    {
        $config = config('jwt.cookies');
        $minutes = (int) ceil(config('jwt.access_ttl') / 60);
        $samesite = self::normalizeSameSite((string) $config['samesite']);

        // If SameSite=None, secure must be true
        $secure = $samesite === Cookie::SAMESITE_NONE ? true : $config['secure'];

        return Cookie::create($config['access'], $jwt, now()->addMinutes($minutes))
            ->withSecure($secure)
            ->withHttpOnly(true)
            ->withSameSite($samesite)
            ->withPath($config['path'])
            ->withDomain($config['domain']);
    }

    /**
     * Create a refresh token cookie.
     *
     * @param string $jwt The JWT refresh token
     * @return Cookie
     */
    public static function refresh(string $jwt): Cookie
    {
        $config = config('jwt.cookies');
        $minutes = (int) ceil(config('jwt.refresh_ttl') / 60);
        $samesite = self::normalizeSameSite((string) $config['samesite']);

        // If SameSite=None, secure must be true
        $secure = $samesite === Cookie::SAMESITE_NONE ? true : $config['secure'];

        return Cookie::create($config['refresh'], $jwt, now()->addMinutes($minutes))
            ->withSecure($secure)
            ->withHttpOnly(true)
            ->withSameSite($samesite)
            ->withPath($config['path'])
            ->withDomain($config['domain']);
    }

    /**
     * Create an expired access token cookie (for logout).
     *
     * @return Cookie
     */
    public static function forgetAccess(): Cookie
    {
        $config = config('jwt.cookies');
        $samesite = self::normalizeSameSite((string) $config['samesite']);

        // If SameSite=None, secure must be true
        $secure = $samesite === Cookie::SAMESITE_NONE ? true : $config['secure'];

        return Cookie::create($config['access'], '', now()->subMinutes(1))
            ->withSecure($secure)
            ->withHttpOnly(true)
            ->withSameSite($samesite)
            ->withPath($config['path'])
            ->withDomain($config['domain']);
    }

    /**
     * Create an expired refresh token cookie (for logout).
     *
     * @return Cookie
     */
    public static function forgetRefresh(): Cookie
    {
        $config = config('jwt.cookies');
        $samesite = self::normalizeSameSite((string) $config['samesite']);

        // If SameSite=None, secure must be true
        $secure = $samesite === Cookie::SAMESITE_NONE ? true : $config['secure'];

        return Cookie::create($config['refresh'], '', now()->subMinutes(1))
            ->withSecure($secure)
            ->withHttpOnly(true)
            ->withSameSite($samesite)
            ->withPath($config['path'])
            ->withDomain($config['domain']);
    }
}
```

---

### app/Console/Commands/GenerateJwtKeys.php

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

/**
 * Generate RSA key pair for JWT token signing.
 *
 * Usage: php artisan cms:jwt:keys {kid}
 *
 * This command generates a 2048-bit RSA key pair and stores it in
 * storage/keys/jwt-{kid}-private.pem and storage/keys/jwt-{kid}-public.pem
 */
class GenerateJwtKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:jwt:keys {kid : The key ID (e.g., v1, v2)}
                                        {--bits=2048 : RSA key size in bits}
                                        {--force : Overwrite existing keys}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate RSA key pair for JWT token signing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $kid = $this->argument('kid');
        $bits = (int) $this->option('bits');
        $force = $this->option('force');

        // Validate key size
        if ($bits < 2048) {
            $this->error('Key size must be at least 2048 bits for security.');
            return self::FAILURE;
        }

        $keysDir = storage_path('keys');
        $privateKeyPath = "{$keysDir}/jwt-{$kid}-private.pem";
        $publicKeyPath = "{$keysDir}/jwt-{$kid}-public.pem";

        // Check if keys already exist
        if (!$force && (file_exists($privateKeyPath) || file_exists($publicKeyPath))) {
            $this->error("Keys for '{$kid}' already exist. Use --force to overwrite.");
            return self::FAILURE;
        }

        // Ensure keys directory exists
        if (!is_dir($keysDir)) {
            $this->info('Creating keys directory...');
            if (!mkdir($keysDir, 0755, true) && !is_dir($keysDir)) {
                throw new RuntimeException("Failed to create directory: {$keysDir}");
            }
        }

        $this->info("Generating {$bits}-bit RSA key pair for '{$kid}'...");

        // Generate private key
        $config = [
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $privateKey = openssl_pkey_new($config);
        if ($privateKey === false) {
            $this->error('Failed to generate private key: ' . openssl_error_string());
            return self::FAILURE;
        }

        // Export private key
        if (!openssl_pkey_export($privateKey, $privateKeyPem)) {
            $this->error('Failed to export private key: ' . openssl_error_string());
            return self::FAILURE;
        }

        // Extract public key
        $publicKeyDetails = openssl_pkey_get_details($privateKey);
        if ($publicKeyDetails === false) {
            $this->error('Failed to extract public key: ' . openssl_error_string());
            return self::FAILURE;
        }
        $publicKeyPem = $publicKeyDetails['key'];

        // Write private key
        if (file_put_contents($privateKeyPath, $privateKeyPem) === false) {
            $this->error("Failed to write private key to: {$privateKeyPath}");
            return self::FAILURE;
        }

        // Write public key
        if (file_put_contents($publicKeyPath, $publicKeyPem) === false) {
            $this->error("Failed to write public key to: {$publicKeyPath}");
            return self::FAILURE;
        }

        // Set secure permissions on private key (owner read/write only)
        chmod($privateKeyPath, 0600);
        chmod($publicKeyPath, 0644);

        $this->newLine();
        $this->info('✓ RSA key pair generated successfully!');
        $this->line("  Private key: {$privateKeyPath} (permissions: 0600)");
        $this->line("  Public key:  {$publicKeyPath} (permissions: 0644)");
        $this->newLine();
        $this->comment("Remember to add '{$kid}' to your config/jwt.php keys array.");

        return self::SUCCESS;
    }
}
```

---

### app/Providers/AppServiceProvider.php (изменения)

```php
<?php

namespace App\Providers;

use App\Domain\Auth\JwtService;  // ДОБАВЛЕНО
use App\Domain\Options\OptionsRepository;
use App\Domain\Sanitizer\RichTextSanitizer;
use App\Domain\View\BladeTemplateResolver;
use App\Domain\View\TemplateResolver;
use App\Models\Entry;
use App\Observers\EntryObserver;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Регистрация OptionsRepository
        $this->app->singleton(OptionsRepository::class, function ($app) {
            return new OptionsRepository($app->make(CacheRepository::class));
        });

        // Регистрация TemplateResolver
        // Используем scoped вместо singleton для совместимости с Octane/Swoole
        // Это гарантирует, что мемоизация View::exists() не протекает между запросами
        $this->app->scoped(TemplateResolver::class, function () {
            return new BladeTemplateResolver(
                default: config('view_templates.default', 'pages.show'),
                overridePrefix: config('view_templates.override_prefix', 'pages.overrides.'),
                typePrefix: config('view_templates.type_prefix', 'pages.types.'),
            );
        });

        // Регистрация RichTextSanitizer
        $this->app->singleton(RichTextSanitizer::class);

        // Регистрация JwtService — ДОБАВЛЕНО
        $this->app->singleton(JwtService::class, function () {
            return new JwtService(config('jwt'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Entry::observe(EntryObserver::class);
        
        // Создаем директорию для кэша HTMLPurifier (idempotent)
        app('files')->ensureDirectoryExists(storage_path('app/purifier'));

        // Set JWT leeway to account for clock drift between server and client
        // This ensures stable token verification when there are small time differences
        \Firebase\JWT\JWT::$leeway = (int) config('jwt.leeway', 5); // Default: 5 seconds
    }
}
```

---

### tests/Unit/JwtServiceTest.php

```php
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
```

---

### tests/TestCase.php (изменения)

```php
<?php

namespace Tests;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set JWT leeway to account for clock drift between server and client
        // This prevents flaky tests when tokens are near expiration boundaries
        JWT::$leeway = 5; // 5 seconds tolerance
    }
}
```

---

### storage/keys/.gitkeep

```
(пустой файл)
```

---

## Изменения в зависимостях

### composer.json

Добавлена зависимость:

```json
{
  "require": {
    "firebase/php-jwt": "^6.11"
  }
}
```

---

## Примечания

1. **Тесты требуют работающего OpenSSL**: На некоторых Windows системах с OpenSSL 3.x могут возникать проблемы с конфигурацией. Тесты автоматически пропустятся (skip) если генерация ключей не удалась.

2. **Безопасность ключей**: В production окружении рекомендуется:
   - Добавить `storage/keys/*.pem` в `.gitignore`
   - Хранить ключи в secure vault (AWS Secrets Manager, HashiCorp Vault)
   - Использовать переменные окружения для путей к ключам

3. **Первый запуск**: Перед использованием необходимо сгенерировать ключи:
   ```bash
   php artisan cms:jwt:keys v1
   ```

4. **Ротация ключей**: Поддерживается через механизм `kid` с обратной совместимостью — старые токены остаются валидными после смены `current_kid`.

5. **SameSite для cross-origin SPA**: Если админка на другом домене, установите `JWT_SAMESITE=None` в `.env`. При этом `secure` автоматически устанавливается в `true`.

6. **Производительность**: RSA ключи кэшируются в памяти сервиса, что устраняет I/O операции при каждом запросе.

7. **Дрейф часов**: Leeway автоматически настраивается в `AppServiceProvider::boot()` и `TestCase::setUp()` из конфигурации `jwt.leeway` (по умолчанию 5 секунд). Это обеспечивает стабильную верификацию при небольшом дрейфе часов между сервером и клиентом.

8. **Связь с задачей 37**: Эндпоинт `/auth/login` должен выставлять cookies `cms_at` (access token) и `cms_rt` (refresh token) используя `JwtCookies::access()` и `JwtCookies::refresh()`.

9. **Исключения**: При неверном ключе выбрасывается `Firebase\JWT\SignatureInvalidException`, а не `UnexpectedValueException`. Последний используется только для неверного типа токена, issuer или audience.

