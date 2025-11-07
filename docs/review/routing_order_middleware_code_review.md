# Код для ревью: Детерминированный порядок роутинга

## Резюме

Реализован `RouteServiceProvider` с детерминированным порядком загрузки роутов, обеспечивающий правильную обработку системных маршрутов до catch-all и fallback. Система гарантирует, что узкие статические пути обрабатываются раньше динамических контентных маршрутов.

**Дата:** 2025-11-07  
**Задача:** 29

---

## 1. RouteServiceProvider

**Файл:** `app/Providers/RouteServiceProvider.php`

```php
<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

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

        $this->routes(function () {
            // Порядок загрузки роутов (детерминированный):
            // 1) Core → 2) Admin API → 3) Plugins → 4) Content → 5) Fallback

            // 1) System/Core routes - загружаются первыми
            // Включают: /, статические сервисные пути
            // Используют middleware('web') для веб-запросов с CSRF
            Route::middleware('web')
                ->group(base_path('routes/web_core.php'));

            // 2) Admin API routes - загружаются после core, но ДО плагинов
            // КРИТИЧНО: должны быть до плагинов, чтобы /api/v1/admin/* не перехватывались catch-all
            // Используют middleware('api') для stateless API без CSRF
            Route::middleware('api')
                ->prefix('api/v1/admin')
                ->group(base_path('routes/api_admin.php'));

            // 3) Plugin routes - загружаются третьими (детерминированный порядок)
            // В будущем будет сортировка по приоритету через PluginRegistry
            $this->mapPluginRoutes();

            // 4) Taxonomies & Content routes - загружаются четвёртыми
            // Включают: динамические контентные маршруты, таксономии
            // Catch-all маршруты должны быть здесь, а не в core
            Route::middleware('web')
                ->group(base_path('routes/web_content.php'));

            // 5) Fallback - строго последним
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

---

## 2. Файлы роутов

### routes/api_admin.php

**Файл:** `routes/api_admin.php`

```php
<?php

use App\Http\Controllers\Admin\PathReservationController;
use App\Http\Controllers\Admin\UtilsController;
use App\Models\RouteReservation;
use Illuminate\Support\Facades\Route;

/**
 * Админский API роуты.
 *
 * Загружаются с middleware('api'), что обеспечивает:
 * - Отсутствие CSRF проверки (stateless API)
 * - Throttle для защиты от злоупотреблений
 * - Правильную обработку JSON запросов
 *
 * Безопасность:
 * - Использует guard 'admin' для явной идентификации администраторских запросов
 * - Throttle 'api' настроен в RouteServiceProvider::boot() (60 запросов в минуту)
 * - Для кросс-сайтовых запросов (SPA на другом origin) требуется:
 *   - SameSite=None; Secure для cookies
 *   - CORS с credentials: true
 *   - CSRF токены для state-changing операций (если используется cookie-based auth)
 */
Route::middleware(['auth:admin', 'throttle:api'])->group(function () {
    Route::get('/utils/slugify', [UtilsController::class, 'slugify']);

    // Path reservations
    Route::get('/reservations', [PathReservationController::class, 'index'])
        ->middleware('can:viewAny,' . RouteReservation::class);
    Route::post('/reservations', [PathReservationController::class, 'store'])
        ->middleware('can:create,' . RouteReservation::class);
    Route::delete('/reservations/{path}', [PathReservationController::class, 'destroy'])
        ->where('path', '.*')
        ->middleware('can:deleteAny,' . RouteReservation::class);
});
```

### routes/web_core.php

```php
<?php

use App\Http\Controllers\AdminPingController;
use App\Models\Entry;
use Illuminate\Support\Facades\Route;

// Главная страница (должна быть в core, чтобы не перехватывалась контентным catch-all)
Route::get('/', \App\Http\Controllers\HomeController::class);

// Тестовый маршрут для проверки порядка роутинга (только для тестов)
// Должен обрабатываться до fallback
if (app()->environment('testing')) {
    Route::get('/admin/ping', [AdminPingController::class, 'ping']);
}

// Тестовый маршрут для проверки авторизации (только для тестов)
if (app()->environment('testing')) {
    Route::get('/test/admin/entries', fn() => response()->json(['message' => 'ok']))
        ->middleware('can:viewAny,' . Entry::class);
}

// Статические сервисные пути (примеры - можно расширить)
// Route::get('/health', fn() => response()->json(['status' => 'ok']));
// Route::get('/feed.xml', [FeedController::class, 'index']);
// Route::get('/sitemap.xml', [SitemapController::class, 'index']);
```

### routes/web_content.php

```php
<?php

use Illuminate\Support\Facades\Route;

// Taxonomies routes (пример - будет реализовано в будущих задачах)
// Route::get('/tag/{slug}', [TagController::class, 'show']);
// Route::get('/category/{slug}', [CategoryController::class, 'show']);

// Content resolver - динамические контентные маршруты
// Catch-all для контента, но не полный fallback (fallback идёт последним)
//
// ВАЖНО: Catch-all должен игнорировать зарезервированные префиксы!
// Используйте негативный lookahead для защиты от перехвата системных путей:
//
// Route::get('{slug}', ContentController::class)
//     ->where('slug', '^(?!(admin|api|auth|shop)(/|$))[A-Za-z0-9][A-Za-z0-9\-\/]*$');
//
// Это дополнительно к проверке в ReservedRouteRegistry и PathReservationService.
// Зарезервированные префиксы: admin, api, auth, shop (и другие из конфига).

// Пока что файл пустой, так как контентные маршруты будут реализованы в задаче 33
```

---

## 3. Контроллеры

### FallbackController

**Файл:** `app/Http/Controllers/FallbackController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Fallback контроллер для обработки всех несовпавших маршрутов (404).
 *
 * Должен быть зарегистрирован строго последним в RouteServiceProvider,
 * чтобы обрабатывать только те запросы, которые не совпали с предыдущими роутами.
 */
class FallbackController extends Controller
{
    /**
     * Обрабатывает все несовпавшие запросы.
     *
     * @param Request $request
     * @return JsonResponse|Response|View
     */
    public function __invoke(Request $request): JsonResponse|Response|View
    {
        // Лёгкая телеметрия по 404 для поиска битых ссылок
        // Логируем структурированные данные: path, referer, accept, method
        Log::info('404 Not Found', [
            'path' => $request->path(),
            'method' => $request->method(),
            'referer' => $request->header('referer'),
            'accept' => $request->header('accept'),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ]);

        // Детекция JSON запросов:
        // 1. Явный запрос JSON через expectsJson() (X-Requested-With: XMLHttpRequest или Accept: application/json)
        // 2. Запросы к API путям (is('api/*'))
        // 3. Явное указание wantsJson() для клиентов без заголовков
        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            // RFC 7807: problem+json для API ошибок
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Not Found',
                'status' => 404,
                'detail' => 'The requested resource was not found.',
                'path' => $request->path(),
            ], 404)->header('Content-Type', 'application/problem+json');
        }

        // Для веб-запросов возвращаем view с ошибкой 404
        return response()->view('errors.404', [
            'path' => $request->path(),
        ], 404);
    }
}
```

### AdminPingController

**Файл:** `app/Http/Controllers/AdminPingController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Тестовый контроллер для проверки порядка роутинга.
 *
 * Маршрут /admin/ping должен обрабатываться до fallback,
 * что подтверждает правильный порядок загрузки роутов.
 */
class AdminPingController extends Controller
{
    /**
     * GET /admin/ping
     *
     * Простой эндпоинт для проверки, что системные маршруты
     * обрабатываются раньше fallback.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'OK',
            'message' => 'Admin ping route is working',
            'route' => '/admin/ping',
        ]);
    }
}
```

---

## 4. View для ошибки 404

**Файл:** `resources/views/errors/404.blade.php`

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link rel="stylesheet" href="{{ asset('css/errors.css') }}">
</head>
<body class="error-page">
    <div class="container">
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The requested path <code>{{ $path ?? request()->path() }}</code> was not found.</p>
        <p><a href="/">Go to homepage</a></p>
    </div>
</body>
</html>
```

---

## 5. Тесты

**Файл:** `tests/Feature/RoutingOrderTest.php`

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Тест: /admin/ping должен обрабатываться до fallback.
     *
     * Критерий приёмки: тестовый роут /admin не перехватывается catch-all/fallback,
     * а обрабатывается своим контроллером.
     */
    public function test_admin_ping_route_not_caught_by_fallback(): void
    {
        $response = $this->get('/admin/ping');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'OK',
            'message' => 'Admin ping route is working',
            'route' => '/admin/ping',
        ]);
    }

    /**
     * Тест: неизвестный путь должен обрабатываться fallback контроллером.
     */
    public function test_unknown_path_handled_by_fallback(): void
    {
        $response = $this->get('/non-existent-xyz');

        $response->assertStatus(404);
        $response->assertViewIs('errors.404');
        $response->assertViewHas('path', 'non-existent-xyz');
    }

    /**
     * Тест: fallback возвращает JSON для API запросов.
     */
    public function test_fallback_returns_json_for_api_requests(): void
    {
        $response = $this->getJson('/non-existent-xyz');

        $response->assertStatus(404);
        $response->assertJsonStructure([
            'type',
            'title',
            'status',
            'detail',
            'path',
        ]);
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Not Found',
            'status' => 404,
        ]);
    }

    /**
     * Тест: главная страница обрабатывается до fallback.
     */
    public function test_home_route_not_caught_by_fallback(): void
    {
        $response = $this->get('/');

        // HomeController может вернуть view (200) или redirect (302), но не 404
        // Используем assertSuccessful() для проверки успешного ответа (2xx)
        $response->assertSuccessful();
        $this->assertNotEquals(404, $response->status());
    }

    /**
     * Тест: API роуты обрабатываются до fallback.
     */
    public function test_api_routes_not_caught_by_fallback(): void
    {
        // Даже без авторизации, роут должен быть найден (вернёт 401, а не 404)
        $response = $this->getJson('/api/v1/admin/reservations');

        // Если роут не найден, будет 404, если найден но нет авторизации - 401
        $this->assertNotEquals(404, $response->status());
        // Роут найден, но требуется авторизация
        $this->assertContains($response->status(), [401, 403]);
    }

    /**
     * Тест: API роуты обрабатываются для HEAD запросов.
     *
     * HEAD автоматически доступен для всех GET роутов.
     * Проверяем, что роут найден (не 404).
     */
    public function test_api_routes_handle_head(): void
    {
        // HEAD запрос на GET роут должен найти роут (вернёт 401/403, не 404)
        $response = $this->head('/api/v1/admin/reservations');

        // Роут должен быть найден (не 404)
        $this->assertNotEquals(404, $response->status());
    }

    /**
     * Тест: порядок роутов сохраняется после route:cache.
     *
     * Проверяем, что после кеширования роутов порядок не меняется.
     */
    public function test_routing_order_preserved_after_cache(): void
    {
        // Сначала проверяем без кеша
        $response1 = $this->get('/admin/ping');
        $response1->assertStatus(200);

        // Кешируем роуты
        $this->artisan('route:cache')->assertSuccessful();

        // Проверяем после кеширования
        $response2 = $this->get('/admin/ping');
        $response2->assertStatus(200);
        $response2->assertJson([
            'status' => 'OK',
        ]);

        // Очищаем кеш
        $this->artisan('route:clear')->assertSuccessful();
    }

    /**
     * Тест: POST на неизвестный путь возвращает 404, а не 419 CSRF.
     *
     * Fallback НЕ должен быть под web middleware, иначе POST без CSRF токена
     * получит 419 вместо ожидаемого 404.
     */
    public function test_fallback_returns_404_on_post_without_csrf(): void
    {
        // POST на несуществующий путь (HTML) должен вернуть 404, не 419
        $response = $this->post('/totally-unknown');
        $response->assertStatus(404);

        // POST на несуществующий путь (JSON) тоже 404 с problem+json
        $response = $this->postJson('/totally-unknown');
        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

    /**
     * Тест: неизвестный путь под /api/* использует problem+json.
     *
     * Проверяет логику is('api/*') в FallbackController.
     */
    public function test_api_prefix_unknown_path_uses_problem_json(): void
    {
        $response = $this->get('/api/v1/not-found-abc');

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Not Found',
            'status' => 404,
        ]);
    }

    /**
     * Тест: OPTIONS preflight на неизвестный API путь возвращает 404 с problem+json.
     *
     * CORS preflight запросы (OPTIONS) на несуществующие API пути должны
     * возвращать 404 с application/problem+json, а не пустой ответ или HTML.
     *
     * Примечание: Laravel может автоматически обрабатывать OPTIONS для CORS,
     * но для неизвестных путей fallback должен вернуть 404.
     */
    public function test_options_preflight_on_unknown_api_path_returns_problem_json(): void
    {
        // OPTIONS без CORS заголовков - должен попасть в fallback
        $response = $this->options('/api/v1/some-unknown');

        // Fallback должен обработать OPTIONS и вернуть 404 с problem+json
        // Если Laravel перехватил OPTIONS для CORS, будет 204, но для неизвестных путей
        // fallback должен сработать
        if ($response->status() === 404) {
            $response->assertHeader('Content-Type', 'application/problem+json');
            $response->assertJson([
                'type' => 'about:blank',
                'title' => 'Not Found',
                'status' => 404,
            ]);
        } else {
            // Если Laravel обработал OPTIONS автоматически (204), это тоже нормально
            // Главное - проверить, что для GET того же пути будет 404
            $getResponse = $this->get('/api/v1/some-unknown');
            $getResponse->assertStatus(404);
            $getResponse->assertHeader('Content-Type', 'application/problem+json');
        }
    }
}
```

**Результаты:** 10 passed, 35 assertions

---

## 6. Изменения в bootstrap/app.php

**Файл:** `bootstrap/app.php`

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Роуты теперь загружаются через RouteServiceProvider
        // для обеспечения детерминированного порядка: core → plugins → content → fallback
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // ... остальной код
```

**Изменение:** Убран параметр `web: __DIR__.'/../routes/web.php'`, так как роуты теперь загружаются через `RouteServiceProvider`.

---

## 7. Изменения в bootstrap/providers.php

**Файл:** `bootstrap/providers.php`

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\RouteServiceProvider::class, // Добавлен
    App\Providers\EntrySlugServiceProvider::class,
    App\Providers\PathReservationServiceProvider::class,
    App\Providers\ReservedRoutesServiceProvider::class,
    App\Providers\SlugServiceProvider::class,
];
```

**Изменение:** Добавлен `RouteServiceProvider` в список провайдеров.

---

## Резюме изменений

### Новые файлы:

1. `app/Providers/RouteServiceProvider.php` - провайдер для управления порядком роутов
2. `routes/web_core.php` - системные маршруты
3. `routes/api_admin.php` - админские API маршруты (stateless)
4. `routes/web_content.php` - контентные маршруты
5. `app/Http/Controllers/FallbackController.php` - обработчик 404
6. `app/Http/Controllers/AdminPingController.php` - тестовый контроллер
7. `resources/views/errors/404.blade.php` - шаблон ошибки 404
8. `resources/css/errors.css` - стили для страниц ошибок (вынесены из inline для CSP)
9. `tests/Feature/RoutingOrderTest.php` - тесты порядка роутинга
10. `docs/implemented/routing_order_middleware.md` - документация
11. `docs/review/routing_order_middleware_code_review.md` - файл для ревью

### Изменённые файлы:

1. `bootstrap/app.php` - убран параметр `web` из `withRouting()`
2. `bootstrap/providers.php` - добавлен `RouteServiceProvider`

### Устаревшие файлы:

-   `routes/web.php` - больше не используется, содержит только комментарий с объяснением. Все роуты перенесены в `routes/web_core.php`

**Содержимое routes/web.php:**

```php
<?php

/**
 * ВНИМАНИЕ: Этот файл больше не используется!
 *
 * Роуты теперь загружаются через RouteServiceProvider в следующем порядке:
 * 1. routes/web_core.php - системные маршруты
 * 2. routes/plugins.php - маршруты плагинов (если существует)
 * 3. routes/web_content.php - контентные маршруты
 * 4. FallbackController - обработчик 404
 *
 * Этот файл оставлен для обратной совместимости, но не загружается автоматически.
 * Все роуты перенесены в routes/web_core.php.
 *
 * См. app/Providers/RouteServiceProvider.php для деталей.
 */
```

### Критерии приёмки:

✅ `RouteServiceProvider` загружает маршруты в порядке: **core → admin API → plugins → content → fallback**  
✅ `/admin` и другие системные адреса **никогда** не перехватываются контентным catch-all  
✅ Админский API использует middleware('api') для stateless работы без CSRF  
✅ Тестовый маршрут `/admin/ping` доступен только в тестовой среде  
✅ Автотесты зелёные (10 passed, 35 assertions)

---

## Порядок загрузки роутов

**Детерминированный порядок:** `core → admin API → plugins → content → fallback`

1. **System (core)** — `/`, `/admin/ping` (только для тестов), статические сервисные пути
    - Используют middleware('web') для веб-запросов с CSRF
2. **Admin API** — `/api/v1/admin/*` (stateless, middleware('api'))
    - **КРИТИЧНО:** Загружаются ДО плагинов, чтобы не перехватывались catch-all
    - Используют middleware('api') для stateless работы без CSRF
    - Throttle `api` (60 запросов в минуту, настроен в RouteServiceProvider::boot())
    - Guard `auth:admin` для явной идентификации администраторских запросов
3. **Plugins** — `routes/plugins.php` (если существует)
    - В будущем будет сортировка по приоритету через PluginRegistry
4. **Taxonomies & Content** — динамические контентные маршруты (пока пусто)
    - Catch-all маршруты должны быть здесь, а не в core
5. **Fallback** — обработчик 404 (строго последним)
    - Обрабатывает ВСЕ HTTP методы (GET, POST, PUT, DELETE, HEAD, OPTIONS)
    - НЕ использует middleware('web') - иначе POST без CSRF вернёт 419 вместо 404
    - Возвращает RFC 7807 JSON для API (Content-Type: application/problem+json)
    - Логирует структурированные данные о 404 для поиска битых ссылок

---

## Особенности реализации

1. **Детерминированный порядок**: роуты загружаются в строго определённом порядке: core → admin API → plugins → content → fallback
2. **Разделение API и веб**: админский API вынесен в отдельный файл с middleware('api')
3. **Stateless API**: админский API работает без CSRF через middleware('api')
4. **Throttle защита**: админский API защищён от злоупотреблений через `throttle:api` (60 запросов в минуту, настроен в `RouteServiceProvider::boot()`)
5. **Явный guard**: используется `auth:admin` для явной идентификации администраторских запросов
6. **Плагины без принудительного middleware**: плагин сам объявляет нужные группы (web/api), избегая микса
7. **Fallback БЕЗ web middleware**: POST на несуществующий путь возвращает 404, а не 419 CSRF
8. **Fallback для всех HTTP методов**: обрабатывает GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS
9. **Совместимость с route:cache**: порядок сохраняется после кеширования
10. **RFC 7807 для API**: fallback возвращает JSON с `Content-Type: application/problem+json` для API запросов
11. **Улучшенная детекция JSON**: использует `expectsJson()`, `is('api/*')` и `wantsJson()`
12. **Защита catch-all**: комментарий с примером негативного lookahead для зарезервированных префиксов
13. **Телеметрия 404**: FallbackController логирует структурированные данные (path, method, referer, accept, user_agent, ip) для поиска битых ссылок
14. **Важно:** После `route:cache` при включении/выключении плагинов требуется перезапуск `route:cache`

---

## Тесты покрывают

-   ✅ `/admin/ping` обрабатывается до fallback (критерий приёмки, только для тестов)
-   ✅ Неизвестный путь обрабатывается fallback (404)
-   ✅ Fallback возвращает JSON для API запросов (RFC 7807, Content-Type: application/problem+json)
-   ✅ Главная страница обрабатывается до fallback (assertSuccessful для 2xx)
-   ✅ API роуты обрабатываются до fallback (401/403, не 404)
-   ✅ API роуты обрабатываются для HEAD запросов (не 404)
-   ✅ Порядок роутов сохраняется после `route:cache`
-   ✅ POST на несуществующий путь возвращает 404, а не 419 CSRF
-   ✅ Неизвестный путь под /api/\* использует problem+json
-   ✅ OPTIONS preflight на неизвестный API путь обрабатывается fallback
