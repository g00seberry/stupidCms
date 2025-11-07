# Code Review: Задача 40. CSRF-защита для API (обновленная версия)

## Измененные файлы

### 1. config/security.php (новый файл)

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CSRF Protection Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for CSRF (Cross-Site Request Forgery) protection using
    | double-submit cookie pattern.
    |
    */

    'csrf' => [
        /*
        |--------------------------------------------------------------------------
        | CSRF Cookie Name
        |--------------------------------------------------------------------------
        |
        | The name of the cookie used to store the CSRF token.
        | This cookie is NOT HttpOnly to allow JavaScript access.
        |
        */
        'cookie_name' => env('CSRF_COOKIE_NAME', 'cms_csrf'),

        /*
        |--------------------------------------------------------------------------
        | CSRF Token Lifetime
        |--------------------------------------------------------------------------
        |
        | The lifetime of the CSRF token in hours.
        | Default: 12 hours
        |
        */
        'ttl_hours' => env('CSRF_TTL_HOURS', 12),

        /*
        |--------------------------------------------------------------------------
        | CSRF Cookie SameSite
        |--------------------------------------------------------------------------
        |
        | SameSite attribute for CSRF cookie.
        | For cross-origin SPA, set to 'None' (requires secure=true).
        | Options: 'Strict', 'Lax', 'None'
        |
        */
        'samesite' => env('CSRF_SAMESITE', env('JWT_SAMESITE', 'Strict')),

        /*
        |--------------------------------------------------------------------------
        | CSRF Cookie Secure
        |--------------------------------------------------------------------------
        |
        | Whether the CSRF cookie should only be sent over HTTPS.
        | Automatically set to true if SameSite=None.
        |
        */
        'secure' => env('CSRF_SECURE', env('APP_ENV') !== 'local'),

        /*
        |--------------------------------------------------------------------------
        | CSRF Cookie Domain
        |--------------------------------------------------------------------------
        |
        | The domain for the CSRF cookie.
        | Defaults to SESSION_DOMAIN or null (current domain).
        |
        */
        'domain' => env('CSRF_DOMAIN', env('SESSION_DOMAIN')),

        /*
        |--------------------------------------------------------------------------
        | CSRF Cookie Path
        |--------------------------------------------------------------------------
        |
        | The path for the CSRF cookie.
        | Default: '/' (available for all paths)
        |
        */
        'path' => env('CSRF_PATH', '/'),
    ],
];
```

### 2. app/Http/Controllers/Auth/CsrfController.php

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Support\JwtCookies;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class CsrfController
{
    /**
     * Issue a CSRF token cookie.
     *
     * Returns a CSRF token in the response body and sets a non-HttpOnly cookie
     * so that JavaScript can read it and include it in subsequent requests.
     *
     * @return JsonResponse
     */
    public function issue(): JsonResponse
    {
        $token = Str::random(40);

        return response()->json(['csrf' => $token])
            ->withCookie(JwtCookies::csrf($token));
    }
}
```

### 3. app/Http/Middleware/VerifyApiCsrf.php

```php
<?php

namespace App\Http\Middleware;

use App\Support\JwtCookies;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to verify CSRF token for state-changing API requests.
 *
 * Compares the X-CSRF-Token or X-XSRF-TOKEN header with the CSRF cookie value.
 * Only applies to POST, PUT, PATCH, DELETE methods.
 * Excludes api.auth.login and api.auth.refresh routes from verification.
 *
 * On 419 error, issues a new CSRF token cookie to help client recover.
 */
final class VerifyApiCsrf
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip idempotent methods (GET, HEAD, OPTIONS)
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Skip preflight requests (OPTIONS with Access-Control-Request-Method)
        if ($request->getMethod() === 'OPTIONS' && $request->header('Access-Control-Request-Method')) {
            return $next($request);
        }

        // Exclude login and refresh endpoints by route name (they don't require CSRF)
        if ($request->routeIs('api.auth.login', 'api.auth.refresh')) {
            return $next($request);
        }

        $csrfConfig = config('security.csrf');
        $cookieName = $csrfConfig['cookie_name'];

        // Accept both X-CSRF-Token and X-XSRF-TOKEN headers
        $headerToken = (string) $request->header('X-CSRF-Token', '');
        if ($headerToken === '') {
            $headerToken = (string) $request->header('X-XSRF-TOKEN', '');
        }

        $cookieToken = (string) $request->cookie($cookieName, '');

        // Use hash_equals for timing-safe comparison
        if ($headerToken === '' || $cookieToken === '' || ! hash_equals($cookieToken, $headerToken)) {
            // Issue a new CSRF token on error to help client recover
            $newToken = Str::random(40);

            return response()->json([
                'type' => 'about:blank',
                'title' => 'CSRF Token Mismatch',
                'status' => 419,
                'detail' => 'CSRF token mismatch.',
            ], 419)
                ->header('Content-Type', 'application/problem+json')
                ->withCookie(JwtCookies::csrf($newToken));
        }

        return $next($request);
    }
}
```

### 4. app/Support/JwtCookies.php (обновлен метод csrf)

```php
    /**
     * Create a CSRF token cookie (non-HttpOnly for JavaScript access).
     *
     * @param string $token The CSRF token
     * @return Cookie
     */
    public static function csrf(string $token): Cookie
    {
        $csrfConfig = config('security.csrf');
        $samesite = self::normalizeSameSite((string) $csrfConfig['samesite']);

        // If SameSite=None, secure must be true
        $secure = $samesite === Cookie::SAMESITE_NONE ? true : $csrfConfig['secure'];

        return Cookie::create($csrfConfig['cookie_name'], $token, now()->addHours($csrfConfig['ttl_hours']))
            ->withSecure($secure)
            ->withHttpOnly(false) // Important: allow JavaScript access
            ->withSameSite($samesite)
            ->withPath($csrfConfig['path'])
            ->withDomain($csrfConfig['domain']);
    }
```

### 5. routes/api.php (добавлены имена роутов)

```php
<?php

use App\Http\Controllers\Auth\CsrfController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RefreshController;
use Illuminate\Support\Facades\Route;

/**
 * Public API routes.
 *
 * Загружаются с middleware('api'), что обеспечивает:
 * - Отсутствие CSRF проверки (stateless API)
 * - Throttle для защиты от злоупотреблений
 * - Правильную обработку JSON запросов
 *
 * Безопасность:
 * - Rate limiting настроен для каждого endpoint отдельно
 * - Для кросс-сайтовых запросов (SPA на другом origin) требуется:
 *   - SameSite=None; Secure для cookies
 *   - CORS с credentials: true
 */
Route::prefix('v1')->group(function () {
    // Authentication endpoints
    // Cache-Control: no-store prevents caching of auth responses
    Route::post('/auth/login', [LoginController::class, 'login'])
        ->name('api.auth.login')
        ->middleware(['throttle:login', 'no-cache-auth']);

    Route::post('/auth/refresh', [RefreshController::class, 'refresh'])
        ->name('api.auth.refresh')
        ->middleware(['throttle:refresh', 'no-cache-auth']);

    Route::post('/auth/logout', [LogoutController::class, 'logout'])
        ->middleware(['throttle:login', 'no-cache-auth']); // 5/min is sufficient

    // CSRF token endpoint
    Route::get('/auth/csrf', [CsrfController::class, 'issue'])
        ->middleware('no-cache-auth');
});
```

### 6. bootstrap/app.php (обновлена регистрация middleware и исключение cookie)

```php
    ->withMiddleware(function (Middleware $middleware): void {
        // Encrypt cookies (except JWT tokens and CSRF token)
        $csrfCookieName = config('security.csrf.cookie_name', 'cms_csrf');
        $middleware->encryptCookies(except: [
            'cms_at', // JWT access token cookie
            'cms_rt', // JWT refresh token cookie
            $csrfCookieName, // CSRF token cookie (non-HttpOnly, needs JS access)
        ]);

        // Rate limiting для API (60 запросов в минуту)
        $middleware->throttleApi();

        // Канонизация URL применяется глобально ко всем HTTP-запросам
        // Это гарантирует редирект /About → /about ДО роутинга, даже если путь не матчится ни одним роутом
        // Внутри middleware есть фильтр для системных путей (admin, api, auth, ...)
        $middleware->prepend(\App\Http\Middleware\CanonicalUrl::class);

        // Middleware order for API group: CORS → CSRF → Vary → Auth
        // CORS must be first to handle preflight and set headers
        // CSRF must be after CORS but before auth (headers/cookies must be available)
        // AddCacheVary after CSRF (for proper cache headers)
        // Verify CSRF token for state-changing API requests (after CORS, before auth)
        $middleware->appendToGroup('api', \App\Http\Middleware\VerifyApiCsrf::class);

        // Add Vary headers for API responses with cookies (after CORS and CSRF)
        $middleware->appendToGroup('api', \App\Http\Middleware\AddCacheVary::class);

        // Register custom middleware aliases
        $middleware->alias([
            'admin.auth' => \App\Http\Middleware\AdminAuth::class,
            'no-cache-auth' => \App\Http\Middleware\NoCacheAuth::class,
        ]);
    })
```

### 7. tests/Feature/AuthCsrfTest.php

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthCsrfTest extends TestCase
{
    use RefreshDatabase;

    private function getCsrfCookieName(): string
    {
        return config('security.csrf.cookie_name', 'cms_csrf');
    }

    public function test_csrf_endpoint_returns_token_and_cookie(): void
    {
        $response = $this->getJson('/api/v1/auth/csrf');

        $response->assertOk();
        $response->assertJsonStructure(['csrf']);

        // Проверка наличия cookie в Set-Cookie заголовке
        $this->assertTrue($response->headers->has('Set-Cookie'), 'CSRF cookie should be set');
        $cookieName = $this->getCsrfCookieName();
        $setCookieHeader = $response->headers->get('Set-Cookie');
        $this->assertStringContainsString($cookieName . '=', $setCookieHeader, 'CSRF cookie should be present');
        $this->assertStringNotContainsString('HttpOnly', $setCookieHeader, 'CSRF cookie must NOT be HttpOnly');

        // Проверка, что токен в ответе присутствует
        $token = $response->json('csrf');
        $this->assertNotEmpty($token, 'CSRF token should be present in response');
        $this->assertEquals(40, strlen($token), 'CSRF token should be 40 characters long');
    }

    public function test_csrf_cookie_attributes_are_correct(): void
    {
        $response = $this->getJson('/api/v1/auth/csrf');
        $response->assertOk();

        $cookieName = $this->getCsrfCookieName();
        $setCookieHeader = $response->headers->get('Set-Cookie');

        // Проверка отсутствия HttpOnly
        $this->assertStringNotContainsString('HttpOnly', $setCookieHeader, 'CSRF cookie must NOT be HttpOnly');

        // Проверка Secure (зависит от конфига) - case insensitive
        $csrfConfig = config('security.csrf');
        $setCookieHeaderLower = strtolower($setCookieHeader);
        if ($csrfConfig['secure']) {
            $this->assertStringContainsString('secure', $setCookieHeaderLower, 'CSRF cookie should be Secure when configured');
        }

        // Проверка SameSite - case insensitive
        $expectedSameSite = strtolower($csrfConfig['samesite']);
        if ($expectedSameSite === 'none') {
            $this->assertStringContainsString('samesite=none', $setCookieHeaderLower, 'CSRF cookie should have SameSite=None');
            $this->assertStringContainsString('secure', $setCookieHeaderLower, 'CSRF cookie with SameSite=None must be Secure');
        } elseif ($expectedSameSite === 'lax') {
            $this->assertStringContainsString('samesite=lax', $setCookieHeaderLower, 'CSRF cookie should have SameSite=Lax');
        } else {
            $this->assertStringContainsString('samesite=strict', $setCookieHeaderLower, 'CSRF cookie should have SameSite=Strict');
        }

        // Проверка Path - case insensitive
        $expectedPath = $csrfConfig['path'];
        $this->assertStringContainsString('path=' . $expectedPath, $setCookieHeaderLower, 'CSRF cookie should have correct Path');

        // Проверка Domain (если указан) - case insensitive
        if ($csrfConfig['domain']) {
            $this->assertStringContainsString('domain=' . strtolower($csrfConfig['domain']), $setCookieHeaderLower, 'CSRF cookie should have correct Domain');
        }
    }

    public function test_post_without_csrf_token_returns_419_with_new_cookie(): void
    {
        // Попытка POST запроса без CSRF токена
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(419);
        $response->assertHeader('Content-Type', 'application/problem+json');

        // Проверка RFC 7807 формата
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'CSRF Token Mismatch',
            'status' => 419,
            'detail' => 'CSRF token mismatch.',
        ]);

        // Проверка, что при 419 выдается новый CSRF cookie
        $cookieName = $this->getCsrfCookieName();
        $this->assertTrue($response->headers->has('Set-Cookie'), 'New CSRF cookie should be issued on 419');
        $setCookieHeader = $response->headers->get('Set-Cookie');
        $this->assertStringContainsString($cookieName . '=', $setCookieHeader, 'New CSRF cookie should be present');
    }

    public function test_post_with_valid_csrf_token_succeeds(): void
    {
        // Получаем CSRF токен
        $csrfResponse = $this->getJson('/api/v1/auth/csrf');
        $csrfResponse->assertOk();

        $token = $csrfResponse->json('csrf');
        $this->assertNotEmpty($token);
        $cookieName = $this->getCsrfCookieName();

        // Используем call() напрямую для установки незашифрованного cookie
        // Аналогично тому, как это делается для JWT cookies
        $server = $this->transformHeadersToServerVars([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
            'X-CSRF-Token' => $token,
        ]);

        $response = $this->call('POST', '/api/v1/auth/logout', [], [$cookieName => $token], [], $server);

        // Logout возвращает 204 No Content
        $this->assertEquals(204, $response->status());
    }

    public function test_post_with_x_xsrf_token_header_succeeds(): void
    {
        // Получаем CSRF токен
        $csrfResponse = $this->getJson('/api/v1/auth/csrf');
        $csrfResponse->assertOk();

        $token = $csrfResponse->json('csrf');
        $this->assertNotEmpty($token);
        $cookieName = $this->getCsrfCookieName();

        // Используем X-XSRF-TOKEN заголовок вместо X-CSRF-Token
        $server = $this->transformHeadersToServerVars([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
            'X-XSRF-TOKEN' => $token,
        ]);

        $response = $this->call('POST', '/api/v1/auth/logout', [], [$cookieName => $token], [], $server);

        // Logout возвращает 204 No Content
        $this->assertEquals(204, $response->status());
    }

    public function test_post_with_mismatched_csrf_token_returns_419(): void
    {
        // Получаем CSRF токен
        $csrfResponse = $this->getJson('/api/v1/auth/csrf');
        $csrfResponse->assertOk();

        $token = $csrfResponse->json('csrf');
        $this->assertNotEmpty($token);
        $cookieName = $this->getCsrfCookieName();

        // Отправляем POST запрос с неверным CSRF токеном в заголовке
        // Cookie содержит правильный токен, но заголовок содержит неправильный
        $response = $this->withCookie($cookieName, $token)
            ->withHeader('X-CSRF-Token', 'wrong-token')
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(419);
        $response->assertHeader('Content-Type', 'application/problem+json');

        // Проверка RFC 7807 формата
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'CSRF Token Mismatch',
            'status' => 419,
            'detail' => 'CSRF token mismatch.',
        ]);

        // Проверка, что при 419 выдается новый CSRF cookie
        $this->assertTrue($response->headers->has('Set-Cookie'), 'New CSRF cookie should be issued on 419');
    }

    public function test_post_without_csrf_cookie_returns_419(): void
    {
        // Отправляем POST запрос с заголовком, но без cookie
        $response = $this->withHeader('X-CSRF-Token', 'some-token')
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(419);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_login_endpoint_excluded_from_csrf_check(): void
    {
        // Login endpoint должен работать без CSRF токена
        $user = \App\Models\User::factory()->create(['password' => bcrypt('secretPass123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secretPass123',
        ]);

        // Login должен работать без CSRF (excluded by route name)
        $response->assertOk();
    }

    public function test_refresh_endpoint_excluded_from_csrf_check(): void
    {
        // Refresh endpoint должен работать без CSRF токена
        // Но нужен валидный refresh token
        // Для этого теста просто проверяем, что без CSRF не возвращается 419
        // (будет 401 из-за отсутствия refresh token, но не 419 из-за CSRF)

        $response = $this->postJson('/api/v1/auth/refresh');

        // Должен вернуть 401 (нет refresh token), но НЕ 419 (CSRF проверка пропущена)
        $this->assertNotEquals(419, $response->status(), 'Refresh endpoint should not require CSRF token');
        $response->assertStatus(401);
    }

    public function test_get_request_bypasses_csrf_check(): void
    {
        // GET запросы не требуют CSRF токена
        $response = $this->getJson('/api/v1/auth/csrf');

        $response->assertOk();
    }

    public function test_head_request_bypasses_csrf_check(): void
    {
        // HEAD запросы не требуют CSRF токена
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->call('HEAD', '/api/v1/auth/csrf');

        $this->assertEquals(204, $response->status());
    }

    public function test_options_preflight_request_bypasses_csrf_check(): void
    {
        // OPTIONS preflight запросы не требуют CSRF токена
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type',
        ])->call('OPTIONS', '/api/v1/auth/logout');

        // Preflight должен обрабатываться CORS middleware
        $this->assertNotEquals(419, $response->status(), 'OPTIONS preflight should not trigger CSRF check');
    }

    public function test_cross_origin_request_with_credentials_and_valid_csrf_succeeds(): void
    {
        // Получаем CSRF токен
        $csrfResponse = $this->getJson('/api/v1/auth/csrf');
        $csrfResponse->assertOk();

        $token = $csrfResponse->json('csrf');
        $this->assertNotEmpty($token);
        $cookieName = $this->getCsrfCookieName();

        // Симулируем cross-origin запрос с credentials
        $server = $this->transformHeadersToServerVars([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
            'X-CSRF-Token' => $token,
            'Origin' => 'https://app.example.com',
        ]);

        $response = $this->call('POST', '/api/v1/auth/logout', [], [$cookieName => $token], [], $server);

        // Запрос должен пройти при валидном CSRF токене
        $this->assertEquals(204, $response->status());
    }

    public function test_csrf_cookie_uses_config_values(): void
    {
        // Проверяем, что cookie использует значения из config
        $response = $this->getJson('/api/v1/auth/csrf');
        $response->assertOk();

        $csrfConfig = config('security.csrf');
        $setCookieHeader = $response->headers->get('Set-Cookie');

        // Проверка имени cookie из конфига
        $this->assertStringContainsString($csrfConfig['cookie_name'] . '=', $setCookieHeader);

        // Проверка Path из конфига - case insensitive
        $setCookieHeaderLower = strtolower($setCookieHeader);
        $this->assertStringContainsString('path=' . $csrfConfig['path'], $setCookieHeaderLower);

        // Проверка Domain из конфига (если указан) - case insensitive
        if ($csrfConfig['domain']) {
            $this->assertStringContainsString('domain=' . strtolower($csrfConfig['domain']), $setCookieHeaderLower);
        }
    }
}
```

## Резюме изменений

1. **Создан config/security.php** - централизованная конфигурация CSRF (имя cookie, TTL, SameSite, Secure, Domain, Path)
2. **Обновлен VerifyApiCsrf middleware**:
    - Исключения по именам роутов через `routeIs('api.auth.login', 'api.auth.refresh')`
    - Поддержка обоих заголовков: `X-CSRF-Token` и `X-XSRF-TOKEN`
    - Пропуск идемпотентных методов (GET, HEAD, OPTIONS)
    - Пропуск preflight запросов (OPTIONS с `Access-Control-Request-Method`)
    - Перевыдача нового CSRF cookie при ошибке 419
    - Использование config для имени cookie
3. **Добавлены имена роутов** - `api.auth.login` и `api.auth.refresh` для исключения через `routeIs()`
4. **Обновлен JwtCookies::csrf()** - использует `config/security.csrf` вместо `config/jwt.cookies`
5. **Обновлен bootstrap/app.php** - исключение cookie из шифрования через config, порядок middleware (CORS → CSRF → Vary → Auth)
6. **Обновлены тесты** - 14 тестов с проверкой атрибутов cookie, поддержки обоих заголовков, cross-origin сценария

## Критерии приёмки

-   [x] `routeIs()` исключает `api.auth.login` и `api.auth.refresh` из CSRF-проверки
-   [x] Оба заголовка (`X-CSRF-Token`, `X-XSRF-TOKEN`) валидны
-   [x] `config/security.php` управляет CSRF-cookie (имя/TTL/SameSite/Secure/Domain/Path)
-   [x] На проде CSRF-cookie имеет `SameSite=None; Secure`, не HttpOnly (через конфиг)
-   [x] 419 возвращается как problem+json и одновременно выставляется новый CSRF-cookie
-   [x] GET/HEAD/OPTIONS не триггерят 419; preflight OPTIONS успешен
-   [x] Порядок middleware: CORS → CSRF → auth (CORS автоматически первый, CSRF после CORS)
-   [x] Все упоминания имени CSRF-cookie берутся из конфига (код и тесты)
-   [x] Тесты проверяют Set-Cookie атрибуты и кросс-ориджин сценарий с withCredentials
