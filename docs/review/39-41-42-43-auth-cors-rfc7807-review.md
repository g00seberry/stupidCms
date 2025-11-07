# Tasks 39-43 - Auth Logout, Admin Auth, CORS, RFC7807 - Code Review

This file contains all code files created or modified for Tasks 39-43 (Logout, Admin Auth Middleware, CORS Configuration, Global RFC7807 Handler).

---

## 1. Controller: LogoutController

**File:** `app/Http/Controllers/Auth/LogoutController.php`

**Changes:** New controller for handling logout with token family revocation.

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\JwtService;
use App\Domain\Auth\RefreshTokenRepository;
use App\Http\Controllers\Traits\Problems;
use App\Models\RefreshToken;
use App\Support\JwtCookies;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LogoutController
{
    use Problems;

    public function __construct(
        private JwtService $jwt,
        private RefreshTokenRepository $repo,
    ) {
    }

    /**
     * Handle a logout request.
     *
     * Revokes the refresh token family (to prevent reuse attacks) and clears cookies.
     * Supports ?all=1 query parameter to revoke all refresh tokens for the user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $rt = (string) $request->cookie(config('jwt.cookies.refresh'), '');

        if ($rt === '') {
            // No refresh token: just clear cookies (idempotent)
            return response()->json(['message' => 'Logged out successfully.'])
                ->withCookie(JwtCookies::clearAccess())
                ->withCookie(JwtCookies::clearRefresh());
        }

        try {
            $verified = $this->jwt->verify($rt, 'refresh');
            $claims = $verified['claims']; // jti, sub
        } catch (\Throwable $e) {
            // Invalid RT: clear cookies (without 401, to not break UX logout)
            return response()->json(['message' => 'Logged out successfully.'])
                ->withCookie(JwtCookies::clearAccess())
                ->withCookie(JwtCookies::clearRefresh());
        }

        // Standard logout: revokeFamily(jti) to prevent reuse attacks
        DB::transaction(function () use ($claims, $request) {
            $this->repo->revokeFamily($claims['jti']);

            // Optional: support logout_all (by user) via query ?all=1
            if ($request->boolean('all')) {
                RefreshToken::where('user_id', (int) $claims['sub'])
                    ->update(['revoked_at' => now('UTC')]);
            }
        });

        return response()->json(['message' => 'Logged out successfully.'])
            ->withCookie(JwtCookies::clearAccess())
            ->withCookie(JwtCookies::clearRefresh());
    }
}
```

---

## 2. Middleware: AdminAuth

**File:** `app/Http/Middleware/AdminAuth.php`

**Changes:** New middleware for protecting admin API endpoints with JWT token, audience, scope, and role checks.

```php
<?php

namespace App\Http\Middleware;

use App\Domain\Auth\JwtService;
use App\Http\Controllers\Traits\Problems;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AdminAuth
{
    use Problems;

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
        $at = (string) $request->cookie(config('jwt.cookies.access'), '');

        if ($at === '') {
            return $this->unauthorized('Missing access token.');
        }

        try {
            $verified = $this->jwt->verify($at, 'access');
            $claims = $verified['claims']; // sub, scp, aud, exp
        } catch (\Throwable $e) {
            return $this->unauthorized('Invalid access token.');
        }

        // Require both audience and scope
        if (($claims['aud'] ?? 'api') !== 'admin' || !in_array('admin', (array) ($claims['scp'] ?? []), true)) {
            return $this->problem(403, 'Forbidden', 'Insufficient scope.');
        }

        // Optional: check role from database
        $user = User::find((int) $claims['sub']);
        if (! $user || ! $user->is_admin) {
            return $this->problem(403, 'Forbidden', 'Admin role required.');
        }

        // Set authenticated user for the request
        Auth::setUser($user);

        return $next($request);
    }
}
```

---

## 3. Middleware: AddCacheVary

**File:** `app/Http/Middleware/AddCacheVary.php`

**Changes:** New middleware for adding Vary headers to responses with cookies.

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Adds Vary: Origin, Cookie headers to responses with cookies.
 *
 * This ensures proper cache behavior when cookies are present,
 * as responses with cookies may vary based on Origin and Cookie headers.
 */
final class AddCacheVary
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Add Vary headers for responses that set cookies
        if ($response->headers->has('Set-Cookie')) {
            $existingVary = $response->headers->get('Vary', '');
            $varyHeaders = array_filter(explode(',', $existingVary));
            $varyHeaders = array_map('trim', $varyHeaders);

            // Add Origin and Cookie if not already present
            if (!in_array('Origin', $varyHeaders, true)) {
                $varyHeaders[] = 'Origin';
            }
            if (!in_array('Cookie', $varyHeaders, true)) {
                $varyHeaders[] = 'Cookie';
            }

            $response->header('Vary', implode(', ', $varyHeaders));
        }

        return $response;
    }
}
```

---

## 4. Configuration: CORS

**File:** `config/cors.php`

**Changes:** New CORS configuration file for cross-origin requests with credentials support.

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'https://app.example.com')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => true,
];
```

---

## 5. Updated: JwtCookies

**File:** `app/Support/JwtCookies.php`

**Changes:** Added `clearAccess()` and `clearRefresh()` methods as aliases for `forgetAccess()` and `forgetRefresh()`.

```php
    /**
     * Alias for forgetAccess() - clears access token cookie.
     *
     * @return Cookie
     */
    public static function clearAccess(): Cookie
    {
        return self::forgetAccess();
    }

    /**
     * Alias for forgetRefresh() - clears refresh token cookie.
     *
     * @return Cookie
     */
    public static function clearRefresh(): Cookie
    {
        return self::forgetRefresh();
    }
```

---

## 6. Updated: Routes API

**File:** `routes/api.php`

**Changes:** Added logout route.

```php
Route::prefix('v1')->group(function () {
    // Authentication endpoints
    Route::post('/auth/login', [LoginController::class, 'login'])
        ->middleware(['throttle:login']);
    
    Route::post('/auth/refresh', [RefreshController::class, 'refresh'])
        ->middleware(['throttle:refresh']);

    Route::post('/auth/logout', [LogoutController::class, 'logout'])
        ->middleware(['throttle:login']); // 5/min is sufficient
});
```

---

## 7. Updated: Routes Admin API

**File:** `routes/api_admin.php`

**Changes:** Updated to use `admin.auth` middleware instead of `auth:admin`.

```php
Route::middleware(['admin.auth', 'throttle:api'])->group(function () {
    Route::get('/utils/slugify', [UtilsController::class, 'slugify']);
    
    // Path reservations
    Route::get('/reservations', [PathReservationController::class, 'index'])
        ->middleware('can:viewAny,' . ReservedRoute::class);
    Route::post('/reservations', [PathReservationController::class, 'store'])
        ->middleware('can:create,' . ReservedRoute::class);
    Route::delete('/reservations/{path}', [PathReservationController::class, 'destroy'])
        ->where('path', '.*')
        ->middleware('can:deleteAny,' . ReservedRoute::class);
});
```

---

## 8. Updated: Bootstrap App

**File:** `bootstrap/app.php`

**Changes:** 
- Registered `admin.auth` middleware alias
- Added `AddCacheVary` middleware to api group
- Added global RFC 7807 exception handlers

```php
    ->withMiddleware(function (Middleware $middleware): void {
        // Rate limiting для API (60 запросов в минуту)
        $middleware->throttleApi();
        
        // Канонизация URL применяется глобально ко всем HTTP-запросам
        $middleware->prepend(\App\Http\Middleware\CanonicalUrl::class);
        
        // Add Vary headers for API responses with cookies (after CORS middleware)
        $middleware->appendToGroup('api', \App\Http\Middleware\AddCacheVary::class);
        
        // Register custom middleware aliases
        $middleware->alias([
            'admin.auth' => \App\Http\Middleware\AdminAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Global RFC 7807 (Problem Details) handler for API routes
        
        // 422 Unprocessable Entity - Validation errors
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'type' => 'about:blank',
                    'title' => 'Unprocessable Entity',
                    'status' => 422,
                    'detail' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422)->header('Content-Type', 'application/problem+json');
            }
        });

        // 401 Unauthorized - Authentication errors
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'type' => 'about:blank',
                    'title' => 'Unauthorized',
                    'status' => 401,
                    'detail' => $e->getMessage() ?: 'Authentication required.',
                ], 401)->header('Content-Type', 'application/problem+json');
            }
        });

        // 403 Forbidden - Authorization errors
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'type' => 'about:blank',
                    'title' => 'Forbidden',
                    'status' => 403,
                    'detail' => 'Forbidden.',
                ], 403)->header('Content-Type', 'application/problem+json');
            }
        });

        // 404 Not Found - Route not found
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'type' => 'about:blank',
                    'title' => 'Not Found',
                    'status' => 404,
                    'detail' => 'Route not found.',
                ], 404)->header('Content-Type', 'application/problem+json');
            }
        });

        // 429 Too Many Requests - Rate limit exceeded
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'type' => 'about:blank',
                    'title' => 'Too Many Requests',
                    'status' => 429,
                    'detail' => 'Rate limit exceeded.',
                ], 429)->header('Content-Type', 'application/problem+json');
            }
        });
    })->create();
```

---

## 9. Tests: AuthLogoutTest

**File:** `tests/Feature/AuthLogoutTest.php`

**Changes:** New test file for logout functionality.

```php
<?php

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLogoutTest extends TestCase
{
    use RefreshDatabase;

    // ... setUp() and ensureJwtKeysExist() methods ...

    public function test_logout_without_token_clears_cookies(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertOk();
        $response->assertJson(['message' => 'Logged out successfully.']);

        // Verify cookies are cleared (expired)
        $accessCookie = $response->getCookie(config('jwt.cookies.access'));
        $refreshCookie = $response->getCookie(config('jwt.cookies.refresh'));

        $this->assertNotNull($accessCookie);
        $this->assertNotNull($refreshCookie);
        $this->assertTrue($accessCookie->getExpiresTime() < time());
        $this->assertTrue($refreshCookie->getExpiresTime() < time());
    }

    public function test_logout_with_valid_token_revokes_family(): void
    {
        // ... test implementation ...
    }

    public function test_logout_all_revokes_all_user_tokens(): void
    {
        // ... test implementation ...
    }

    public function test_logout_with_invalid_token_clears_cookies(): void
    {
        // ... test implementation ...
    }
}
```

---

## 10. Tests: AdminAuthTest

**File:** `tests/Feature/AdminAuthTest.php`

**Changes:** New test file for admin authentication middleware.

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    // ... setUp() and ensureJwtKeysExist() methods ...

    public function test_admin_auth_without_token_returns_401(): void
    {
        \Route::middleware(['admin.auth'])->get('/test/admin', function () {
            return response()->json(['message' => 'OK']);
        });

        $response = $this->getJson('/test/admin');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Unauthorized',
            'status' => 401,
        ]);
    }

    // ... other test methods ...
}
```

---

## 11. Tests: CorsTest

**File:** `tests/Feature/CorsTest.php`

**Changes:** New test file for CORS functionality.

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_preflight_request_returns_204_with_credentials(): void
    {
        $allowedOrigin = config('cors.allowed_origins')[0] ?? 'https://app.example.com';

        $response = $this->optionsJson('/api/v1/auth/login', [], [
            'Origin' => $allowedOrigin,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type',
        ]);

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', $allowedOrigin);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    // ... other test methods ...
}
```

---

## 12. Tests: Rfc7807ErrorTest

**File:** `tests/Feature/Rfc7807ErrorTest.php`

**Changes:** New test file for RFC 7807 error format.

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class Rfc7807ErrorTest extends TestCase
{
    public function test_validation_error_returns_422_problem_json(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'invalid-email',
            'password' => '',
        ]);

        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Unprocessable Entity',
            'status' => 422,
            'detail' => 'Validation failed.',
        ]);
        $response->assertJsonStructure([
            'errors',
        ]);
    }

    // ... other test methods ...
}
```

---

## Summary of Changes

### New Files (9):

1. `app/Http/Controllers/Auth/LogoutController.php` - Logout controller with token family revocation
2. `app/Http/Middleware/AdminAuth.php` - Admin authentication middleware
3. `app/Http/Middleware/AddCacheVary.php` - Middleware for adding Vary headers
4. `config/cors.php` - CORS configuration
5. `tests/Feature/AuthLogoutTest.php` - Logout tests (4 test cases)
6. `tests/Feature/AdminAuthTest.php` - Admin auth tests (5 test cases)
7. `tests/Feature/CorsTest.php` - CORS tests (4 test cases)
8. `tests/Feature/Rfc7807ErrorTest.php` - RFC7807 error format tests (5 test cases)
9. Documentation files in `docs/implemented/`:
   - `39-auth-logout.md`
   - `41-admin-auth-middleware.md`
   - `42-cors-configuration.md`
   - `43-global-rfc7807-handler.md`

### Modified Files (4):

1. `app/Support/JwtCookies.php` - Added `clearAccess()` and `clearRefresh()` methods
2. `routes/api.php` - Added logout route
3. `routes/api_admin.php` - Updated to use `admin.auth` middleware
4. `bootstrap/app.php` - Registered middleware, added exception handlers, added AddCacheVary to api group

### Key Features Implemented:

- ✅ **Logout with token family revocation** - Prevents reuse attacks after logout
- ✅ **Logout all devices** - Support for `?all=1` parameter
- ✅ **Idempotent logout** - Works even without token
- ✅ **Admin authentication middleware** - Multi-level checks (token, aud, scp, role)
- ✅ **CORS configuration** - Whitelist origins, credentials support
- ✅ **Vary headers** - Automatic addition for responses with cookies
- ✅ **Global RFC 7807 handler** - Unified error format for all API errors (401, 403, 404, 422, 429)
- ✅ **Comprehensive test coverage** - 18 test cases across 4 test files

