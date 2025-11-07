# Ревью Задачи 37. Аутентификация: `POST /api/v1/auth/login`

## Список измененных файлов

1. `routes/api.php` — публичные API маршруты (новый файл)
2. `app/Providers/RouteServiceProvider.php` — добавлен rate limiter для login и регистрация публичных API роутов
3. `app/Http/Requests/Auth/LoginRequest.php` — FormRequest для валидации входных данных (новый файл)
4. `app/Http/Controllers/Auth/LoginController.php` — контроллер обработки входа (новый файл)
5. `tests/Feature/AuthLoginTest.php` — feature-тесты для login endpoint (новый файл)

---

## Содержимое измененных файлов

### routes/api.php

```php
<?php

use App\Http\Controllers\Auth\LoginController;
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
    Route::post('/auth/login', [LoginController::class, 'login'])
        ->middleware(['throttle:login']);
});
```

### app/Providers/RouteServiceProvider.php

```php
<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Настройка rate limiter для API (60 запросов в минуту)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter для login (5 попыток в минуту на связку email+IP)
        RateLimiter::for('login', function (Request $request) {
            $key = 'login:'.Str::lower($request->input('email')).'|'.$request->ip();
            return Limit::perMinute(5)->by($key);
        });

        $this->routes(function () {
            // Порядок загрузки роутов (детерминированный):
            // 1) Core → 2) Public API → 3) Admin API → 4) Plugins → 5) Content → 6) Fallback
            
            // 1) System/Core routes - загружаются первыми
            // Включают: /, статические сервисные пути
            // Используют middleware('web') для веб-запросов с CSRF
            Route::middleware('web')
                ->group(base_path('routes/web_core.php'));

            // 2) Public API routes - загружаются после core, но ДО admin API
            // Включают: /api/v1/auth/login и другие публичные API endpoints
            // Используют middleware('api') для stateless API без CSRF
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // 3) Admin API routes - загружаются после public API, но ДО плагинов
            // КРИТИЧНО: должны быть до плагинов, чтобы /api/v1/admin/* не перехватывались catch-all
            // Используют middleware('api') для stateless API без CSRF
            Route::middleware('api')
                ->prefix('api/v1/admin')
                ->group(base_path('routes/api_admin.php'));

            // 4) Plugin routes - загружаются четвёртыми (детерминированный порядок)
            // В будущем будет сортировка по приоритету через PluginRegistry
            $this->mapPluginRoutes();

            // 5) Taxonomies & Content routes - загружаются пятыми
            // Включают: динамические контентные маршруты, таксономии
            // Catch-all маршруты должны быть здесь, а не в core
            // Middleware CanonicalUrl применяется в глобальной web-группе (см. bootstrap/app.php)
            // и выполняет 301 редиректы ДО роутинга
            Route::middleware('web')
                ->group(base_path('routes/web_content.php'));

            // 6) Fallback - строго последним
            // Обрабатывает все несовпавшие запросы (404) для ВСЕХ HTTP методов
            // ВАЖНО: Fallback НЕ должен быть под web middleware!
            // Иначе POST на несуществующий путь получит 419 CSRF вместо 404.
            // Контроллер сам определяет формат ответа (HTML/JSON) по типу запроса.
            // 
            // Регистрируем fallback для каждого метода отдельно, т.к. Route::fallback()
            // по умолчанию только для GET/HEAD
            $fallbackController = \App\Http\Controllers\FallbackController::class;
            Route::fallback($fallbackController); // GET, HEAD
            Route::match(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '{any?}', $fallbackController)
                ->where('any', '.*')
                ->fallback();
        });
    }

    /**
     * Загружает маршруты плагинов в детерминированном порядке.
     * 
     * Плагины сортируются по приоритету (если указан) или по имени для стабильности.
     * Это гарантирует, что порядок загрузки роутов не меняется между запросами.
     * 
     * ВАЖНО: НЕ навешиваем middleware('web') сверху - пусть плагин сам решает,
     * какие middleware группы использовать (web|api). Иначе получится микс web+api,
     * что ломает семантику stateless API.
     */
    protected function mapPluginRoutes(): void
    {
        // Упрощённая версия: пока PluginRegistry не реализован, используем заглушку
        // В будущем здесь будет:
        // $plugins = app(\App\Domain\Plugins\PluginRegistry::class)->enabled();
        // $plugins = collect($plugins)->sortBy('priority')->values();
        // foreach ($plugins as $plugin) {
        //     require $plugin->routesFile();
        // }
        
        // Пока что просто проверяем наличие файла routes/plugins.php
        // Если он существует, загружаем его (плагин сам объявляет нужные группы)
        $pluginRoutesFile = base_path('routes/plugins.php');
        if (file_exists($pluginRoutesFile)) {
            require $pluginRoutesFile;
        }
    }
}
```

### app/Http/Requests/Auth/LoginRequest.php

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:strict', 'lowercase', 'max:254'],
            'password' => ['required', 'string', 'min:8', 'max:200'],
        ];
    }
}
```

### app/Http/Controllers/Auth/LoginController.php

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\JwtService;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Audit;
use App\Models\User;
use App\Support\JwtCookies;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

final class LoginController
{
    public function __construct(private JwtService $jwt)
    {
    }

    /**
     * Handle a login request.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $email = strtolower($request->input('email'));
        $password = (string) $request->input('password');

        // Case-insensitive email search
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user || ! Hash::check($password, $user->password)) {
            // Аудит неуспешного входа
            $this->logAudit('login_failed', null, $request);

            // RFC 7807: problem+json для ошибок аутентификации
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Invalid credentials.',
            ], 401)->header('Content-Type', 'application/problem+json');
        }

        // Аудит успешного входа
        $this->logAudit('login', $user->id, $request);

        // Выпуск токенов
        $access = $this->jwt->issueAccessToken($user->getKey(), ['scp' => ['api']]);
        $refresh = $this->jwt->issueRefreshToken($user->getKey());

        // Ответ + cookies
        return response()->json([
            'user' => [
                'id' => (int) $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
        ])->withCookie(JwtCookies::access($access))
          ->withCookie(JwtCookies::refresh($refresh));
    }

    /**
     * Логирует действие входа в таблицу audits.
     *
     * @param string $action 'login' или 'login_failed'
     * @param int|null $userId ID пользователя (null для неуспешного входа)
     * @param \Illuminate\Http\Request $request
     */
    private function logAudit(string $action, ?int $userId, $request): void
    {
        try {
            Audit::create([
                'user_id' => $userId,
                'action' => $action,
                'subject_type' => User::class,
                'subject_id' => $userId ?? 0,
                'ip' => $request->ip(),
                'ua' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Не прерываем выполнение при ошибке аудита
            // В production можно логировать в отдельный канал
        }
    }
}
```

### tests/Feature/AuthLoginTest.php

```php
<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure JWT keys exist for tests
        $this->ensureJwtKeysExist();
    }

    private function ensureJwtKeysExist(): void
    {
        $keysDir = storage_path('keys');
        $privateKeyPath = "{$keysDir}/jwt-v1-private.pem";
        $publicKeyPath = "{$keysDir}/jwt-v1-public.pem";

        // Skip if keys already exist
        if (file_exists($privateKeyPath) && file_exists($publicKeyPath)) {
            return;
        }

        // Ensure directory exists
        if (!is_dir($keysDir)) {
            mkdir($keysDir, 0755, true);
        }

        // Try to generate keys using Artisan command
        try {
            $exitCode = \Artisan::call('cms:jwt:keys', [
                'kid' => 'v1',
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                $this->markTestSkipped('Failed to generate JWT keys. OpenSSL might not be properly configured on this system.');
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Failed to generate JWT keys: ' . $e->getMessage());
        }
    }

    public function test_login_success_sets_cookies(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secretPass123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secretPass123',
        ]);

        $response->assertOk();
        
        // Проверка наличия cookies
        $response->assertCookie(config('jwt.cookies.access'));
        $response->assertCookie(config('jwt.cookies.refresh'));
        
        // Проверка атрибутов cookies
        $accessCookie = $response->getCookie(config('jwt.cookies.access'));
        $refreshCookie = $response->getCookie(config('jwt.cookies.refresh'));
        
        $this->assertTrue($accessCookie->isHttpOnly(), 'Access cookie must be HttpOnly');
        $this->assertTrue($refreshCookie->isHttpOnly(), 'Refresh cookie must be HttpOnly');
        
        // Secure зависит от окружения (false в local, true в production)
        // SameSite проверяем через конфиг
        $expectedSameSite = config('jwt.cookies.samesite', 'Strict');
        $this->assertSame(
            strtolower($expectedSameSite),
            strtolower($accessCookie->getSameSite() ?? 'Strict'),
            'Access cookie SameSite must match config'
        );
        $this->assertSame(
            strtolower($expectedSameSite),
            strtolower($refreshCookie->getSameSite() ?? 'Strict'),
            'Refresh cookie SameSite must match config'
        );
        
        $response->assertJsonStructure([
            'user' => ['id', 'email', 'name'],
        ]);
        $response->assertJson([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
        ]);
        
        // Проверка аудита успешного входа
        $this->assertDatabaseHas('audits', [
            'user_id' => $user->id,
            'action' => 'login',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);
    }

    public function test_login_failure_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'no@user.tld',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertCookieMissing(config('jwt.cookies.access'));
        $response->assertCookieMissing(config('jwt.cookies.refresh'));
        
        // Проверка RFC 7807 формата
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Invalid credentials.',
        ]);
        
        // Проверка аудита неуспешного входа
        $this->assertDatabaseHas('audits', [
            'user_id' => null,
            'action' => 'login_failed',
            'subject_type' => User::class,
            'subject_id' => 0,
        ]);
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correctPassword')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrongPassword',
        ]);

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertCookieMissing(config('jwt.cookies.access'));
        $response->assertCookieMissing(config('jwt.cookies.refresh'));
        
        // Проверка RFC 7807 формата
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Invalid credentials.',
        ]);
        
        // Проверка аудита неуспешного входа
        $this->assertDatabaseHas('audits', [
            'user_id' => null,
            'action' => 'login_failed',
            'subject_type' => User::class,
            'subject_id' => 0,
        ]);
    }

    public function test_login_validation_requires_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_login_validation_requires_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_login_validation_email_must_be_valid(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_login_validation_password_min_length(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_login_email_is_case_insensitive(): void
    {
        $user = User::factory()->create([
            'email' => 'Test@Example.com',
            'password' => bcrypt('secretPass123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'secretPass123',
        ]);

        $response->assertOk();
        $response->assertCookie(config('jwt.cookies.access'));
    }
}
```

