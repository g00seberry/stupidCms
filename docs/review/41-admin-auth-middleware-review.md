# Review: Admin Auth Middleware (Task 41)

## app/Support/ProblemDetails.php

```php
<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralized RFC7807 problem definitions used across the API layer.
 */
final class ProblemDetails
{
    public const TYPE_UNAUTHORIZED = 'https://stupidcms.dev/problems/unauthorized';
    public const TYPE_FORBIDDEN = 'https://stupidcms.dev/problems/forbidden';

    public const TITLE_UNAUTHORIZED = 'Unauthorized';
    public const TITLE_FORBIDDEN = 'Forbidden';

    public const DETAIL_UNAUTHORIZED = 'Authentication is required to access this resource.';
    public const DETAIL_FORBIDDEN = 'Admin privileges are required.';

    /**
     * @return array{type: string, title: string, detail: string, status: int}
     */
    public static function unauthorized(?string $detail = null): array
    {
        return [
            'type' => self::TYPE_UNAUTHORIZED,
            'title' => self::TITLE_UNAUTHORIZED,
            'status' => 401,
            'detail' => $detail ?? self::DETAIL_UNAUTHORIZED,
        ];
    }

    /**
     * @return array{type: string, title: string, detail: string, status: int}
     */
    public static function forbidden(?string $detail = null): array
    {
        return [
            'type' => self::TYPE_FORBIDDEN,
            'title' => self::TITLE_FORBIDDEN,
            'status' => 403,
            'detail' => $detail ?? self::DETAIL_FORBIDDEN,
        ];
    }
}
```

## app/Http/Controllers/Traits/Problems.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Traits;

use Illuminate\Http\JsonResponse;

/**
 * RFC 7807 (Problem Details for HTTP APIs) helper trait.
 *
 * Provides unified error response formatting across all controllers.
 */
trait Problems
{
    /**
     * Generate a standardized RFC 7807 problem+json response.
     *
     * @param int $status HTTP status code
     * @param string $title Short, human-readable summary
     * @param string $detail Human-readable explanation specific to this occurrence
     * @param array<string, mixed> $ext Additional problem-specific extension fields
     * @param array<string, string> $headers Additional headers to append to the response
     * @return JsonResponse
     */
    protected function problem(int $status, string $title, string $detail, array $ext = [], array $headers = []): JsonResponse
    {
        $payload = array_merge([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ], $ext);

        $response = response()->json($payload, $status);
        $response->headers->set('Content-Type', 'application/problem+json');

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * Render a standardized problem response using a preset array.
     *
     * @param array{type: string, title: string, detail: string, status: int} $preset
     * @param array<string, mixed> $ext
     * @param array<string, string> $headers
     */
    protected function problemFromPreset(array $preset, array $ext = [], array $headers = []): JsonResponse
    {
        $extWithType = array_merge(['type' => $preset['type']], $ext);

        return $this->problem(
            $preset['status'],
            $preset['title'],
            $preset['detail'],
            $extWithType,
            $headers
        );
    }

    /**
     * Shorthand for 401 Unauthorized problem using centralized preset.
     *
     * @param string|null $detail
     * @param array<string, mixed> $ext
     * @param array<string, string> $headers
     * @return JsonResponse
     */
    protected function unauthorized(?string $detail = null, array $ext = [], array $headers = []): JsonResponse
    {
        $preset = \App\Support\ProblemDetails::unauthorized($detail);
        return $this->problemFromPreset($preset, $ext, $headers);
    }

    /**
     * Shorthand for 403 Forbidden problem using centralized preset.
     *
     * @param string|null $detail
     * @param array<string, mixed> $ext
     * @param array<string, string> $headers
     * @return JsonResponse
     */
    protected function forbidden(?string $detail = null, array $ext = [], array $headers = []): JsonResponse
    {
        $preset = \App\Support\ProblemDetails::forbidden($detail);
        return $this->problemFromPreset($preset, $ext, $headers);
    }

    /**
     * Shorthand for 500 Internal Server Error problem.
     *
     * @param string $detail
     * @param array<string, mixed> $ext
     * @return JsonResponse
     */
    protected function internalError(string $detail, array $ext = []): JsonResponse
    {
        return $this->problem(500, 'Internal Server Error', $detail, $ext);
    }

    /**
     * Shorthand for 429 Too Many Requests problem.
     *
     * @param string $detail
     * @param array<string, mixed> $ext
     * @return JsonResponse
     */
    protected function tooManyRequests(string $detail, array $ext = []): JsonResponse
    {
        return $this->problem(429, 'Too Many Requests', $detail, $ext);
    }
}
```

## app/Http/Middleware/AdminAuth.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Auth\JwtService;
use App\Http\Controllers\Traits\Problems;
use App\Models\User;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class AdminAuth
{
    use Problems;

    private const REALM = 'admin';
    private const GUARD = 'admin';

    public function __construct(
        private JwtService $jwt
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * Verifies JWT access token from cookie and checks:
     * - Token is valid (signature, expiration)
     * - Audience (aud) is 'admin'
     * - Scope (scp) includes 'admin'
     * - User has 'admin' role in database
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $accessToken = (string) $request->cookie(config('jwt.cookies.access'), '');

        if ($accessToken === '') {
            return $this->respondUnauthorized($request, 'missing_token');
        }

        try {
            $verified = $this->jwt->verify($accessToken, 'access');
            $claims = $verified['claims'];
        } catch (\Throwable $e) {
            return $this->respondUnauthorized($request, 'invalid_token', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        if (($claims['aud'] ?? null) !== 'admin') {
            return $this->respondForbidden($request, 'invalid_audience');
        }

        $scopes = $claims['scp'] ?? [];
        if (! is_array($scopes)) {
            $scopes = (array) $scopes;
        }

        if (! in_array('admin', $scopes, true)) {
            return $this->respondForbidden($request, 'missing_scope');
        }

        $subject = $claims['sub'] ?? null;
        if (! $this->isValidSubject($subject)) {
            return $this->respondUnauthorized($request, 'invalid_subject', [
                'sub' => $subject,
            ]);
        }

        $userId = (int) $subject;
        $user = User::query()->find($userId);
        if (! $user) {
            return $this->respondUnauthorized($request, 'user_not_found', [
                'user_id' => $userId,
            ]);
        }

        if (! $user->is_admin) {
            return $this->respondForbidden($request, 'not_admin', [
                'user_id' => $userId,
            ]);
        }

        Auth::shouldUse(self::GUARD);
        Auth::setUser($user);

        return $next($request);
    }

    private function respondUnauthorized(Request $request, string $reason, array $context = []): JsonResponse
    {
        $this->logFailure(401, $reason, $request, $context);

        return $this->problemFromPreset(
            ProblemDetails::unauthorized(),
            headers: [
                'WWW-Authenticate' => sprintf('Bearer realm="%s"', self::REALM),
                'Cache-Control' => 'no-store, private',
                'Pragma' => 'no-cache',
            ]
        );
    }

    private function respondForbidden(Request $request, string $reason, array $context = []): JsonResponse
    {
        $this->logFailure(403, $reason, $request, $context);

        return $this->problemFromPreset(ProblemDetails::forbidden());
    }

    private function logFailure(int $status, string $reason, Request $request, array $context = []): void
    {
        $level = $status === 401 ? 'warning' : 'notice';

        $logContext = [
            'status' => $status,
            'reason' => $reason,
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        // Remove sensitive exception details in production
        if (isset($context['exception'])) {
            $logContext['exception_class'] = $context['exception'];
            unset($context['exception'], $context['message']);
        }

        Log::log($level, sprintf('[AdminAuth] %s: %s', $status, $reason), array_merge($logContext, $context));
    }

    private function isValidSubject(mixed $subject): bool
    {
        if (! is_int($subject) && ! is_string($subject)) {
            return false;
        }

        if (is_string($subject)) {
            $subject = trim($subject);
            if ($subject === '' || ! ctype_digit($subject)) {
                return false;
            }

            $subject = (int) $subject;
        }

        return is_int($subject) && $subject > 0;
    }
}
```

## tests/Feature/AdminAuthTest.php

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['admin.auth'])->get('/test/admin', function () {
            return response()->json([
                'message' => 'OK',
                'user_id' => Auth::id(),
                'guard' => Auth::getDefaultDriver(),
            ]);
        });
    }

    public function test_admin_auth_without_token_returns_401(): void
    {
        $response = $this->getJson('/test/admin');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="admin"');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }

    public function test_admin_auth_with_invalid_token_returns_401(): void
    {
        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), 'invalid-token');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="admin"');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }

    public function test_admin_auth_with_regular_token_returns_403(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $regularToken = $jwtService->issueAccessToken($user->id);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $regularToken);

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/forbidden',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => 'Admin privileges are required.',
        ]);
    }

    public function test_admin_auth_with_admin_token_but_non_admin_user_returns_403(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123'), 'is_admin' => false]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $adminToken = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $adminToken);

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/forbidden',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => 'Admin privileges are required.',
        ]);
    }

    public function test_admin_auth_with_valid_admin_token_succeeds(): void
    {
        $adminUser = User::factory()->create(['password' => bcrypt('password123'), 'is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $adminToken = $jwtService->issueAccessToken($adminUser->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $adminToken);

        $response->assertOk();
        $response->assertJson([
            'message' => 'OK',
            'user_id' => $adminUser->id,
            'guard' => 'admin',
        ]);
    }

    public function test_admin_auth_with_missing_user_returns_401(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $adminToken = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
        ]);

        $user->delete();

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $adminToken);

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="admin"');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }

    public function test_admin_auth_with_audience_mismatch_returns_403(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'aud' => 'api',
            'scp' => ['admin'],
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(403);
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/forbidden',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => 'Admin privileges are required.',
        ]);
    }

    public function test_admin_auth_with_missing_admin_scope_returns_403(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['cms'],
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(403);
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/forbidden',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => 'Admin privileges are required.',
        ]);
    }

    public function test_admin_auth_with_missing_subject_returns_401(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
            'sub' => null,
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(401);
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="admin"');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }

    public function test_admin_auth_with_non_integer_subject_returns_401(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
            'sub' => 'abc',
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(401);
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="admin"');
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }

    public function test_problem_json_header_is_stable(): void
    {
        $response = $this->getJson('/test/admin');

        $response->assertStatus(401);
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
    }

    public function test_admin_scope_scalar_string_is_accepted(): void
    {
        $adminUser = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $adminToken = $jwtService->issueAccessToken($adminUser->id, [
            'aud' => 'admin',
            'scp' => 'admin', // scalar string instead of array
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $adminToken);

        $response->assertOk();
        $response->assertJson([
            'message' => 'OK',
            'user_id' => $adminUser->id,
        ]);
    }

    public function test_scope_is_case_sensitive(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['Admin'], // wrong case
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(403);
        $response->assertJson([
            'type' => 'https://stupidcms.dev/problems/forbidden',
            'title' => 'Forbidden',
            'status' => 403,
        ]);
    }

    public function test_subject_zero_returns_401(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
            'sub' => 0,
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(401);
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="admin"');
    }

    public function test_subject_negative_returns_401(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
            'sub' => -1,
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(401);
        $response->assertHeader('WWW-Authenticate', 'Bearer realm="admin"');
    }

    public function test_subject_with_spaces_is_trimmed(): void
    {
        $adminUser = User::factory()->create(['is_admin' => true]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($adminUser->id, [
            'aud' => 'admin',
            'scp' => ['admin'],
            'sub' => ' ' . $adminUser->id . ' ',
        ]);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertOk();
        $response->assertJson([
            'message' => 'OK',
            'user_id' => $adminUser->id,
        ]);
    }

    public function test_www_authenticate_only_on_401(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id);

        $response = $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        $response->assertStatus(403);
        $this->assertNull($response->headers->get('WWW-Authenticate'));
    }

    public function test_adds_no_store_on_401(): void
    {
        $response = $this->getJson('/test/admin');

        $response->assertStatus(401);
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Pragma', 'no-cache');
    }

    public function test_logs_reason_for_unauthorized(): void
    {
        \Log::spy();

        $this->getJson('/test/admin');

        \Log::shouldHaveReceived('log')
            ->once()
            ->with('warning', \Mockery::pattern('/\[AdminAuth\] 401: missing_token/'), \Mockery::type('array'));
    }

    public function test_logs_reason_for_forbidden(): void
    {
        \Log::spy();

        $user = User::factory()->create(['is_admin' => false]);
        $jwtService = app(\App\Domain\Auth\JwtService::class);
        $token = $jwtService->issueAccessToken($user->id);

        $this->getJsonWithUnencryptedCookie('/test/admin', config('jwt.cookies.access'), $token);

        \Log::shouldHaveReceived('log')
            ->once()
            ->with('notice', \Mockery::pattern('/\[AdminAuth\] 403: invalid_audience/'), \Mockery::type('array'));
    }
}
```
