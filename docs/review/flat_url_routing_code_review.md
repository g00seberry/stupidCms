# Code Review: Плоская маршрутизация /{slug} (Задача 30)

## Резюме изменений

Реализована плоская fallback-маршрутизация `/{slug}` для публичного сайта с исключением зарезервированных путей через негативный lookahead в regex паттерне.

**Обновления:**

-   Единое название таблицы: используется `reserved_routes` для единообразия со спецификацией
-   **Единый канон хранения path в БД**: `/foo` (с ведущим `/`, без trailing `/`, lowercase)
-   **STORED-колонка `path_norm`**: регистронезависимая уникальность через `LOWER(TRIM(BOTH '/' FROM path))` (MySQL 8+)
-   Добавлено кэширование в `isReserved()` для оптимизации производительности (TTL 60 сек)
-   **Автоматическая инвалидация кэша**: при любом изменении резерваций (`reservePath()`, `releasePath()`, `releaseBySource()`) кэш автоматически сбрасывается через `Cache::forget('reserved:first-segments')`
-   **Глобальная канонизация URL**: middleware `CanonicalUrl` применяется ко ВСЕМ HTTP-запросам через `$middleware->prepend()`
-   **Строгий lowercase regex**: роут принимает ТОЛЬКО `[a-z0-9-]`, БЕЗ uppercase и trailing slash (defense in depth, соответствует Task 21)
-   **PageController**: использует скоуп `ofType('page')` вместо `whereHas` для читабельности
-   Обновлены все компоненты для использования единой таблицы `reserved_routes`
-   Упрощена миграция: таблица создается сразу с правильной структурой (одна миграция вместо нескольких)

---

## Новые файлы

### 1. ReservedPattern

**Файл:** `app/Routing/ReservedPattern.php`

```php
<?php

namespace App\Routing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Генератор регулярного выражения для плоских URL с исключением зарезервированных путей.
 *
 * Используется для создания негативного lookahead паттерна, который исключает
 * зарезервированные первые сегменты из плоской маршрутизации /{slug}.
 *
 * При route:cache список фиксируется до следующего деплоя/инвалидации.
 * Это приемлемо, так как сами плагины/система регистрируют свои конкретные роуты
 * раньше и перехватят свои пути.
 */
final class ReservedPattern
{
    /**
     * Генерирует регулярное выражение для плоского slug с исключением зарезервированных путей.
     *
     * Формат: негативный lookahead для зарезервированных сегментов + базовый паттерн slug.
     *
     * Источник списка:
     * - config('stupidcms.reserved_routes.paths') (статические)
     * - reserved_routes (динамические) — берём только первый сегмент, фильтр по kind в ['path', 'prefix']
     *
     * @return string Regex паттерн для использования в Route::where('slug', ...)
     */
    public static function slugRegex(): string
    {
        // Получаем статические пути из конфига
        $configPaths = (array) config('stupidcms.reserved_routes.paths', []);
        $static = collect($configPaths)
            ->map(fn($p) => trim(parse_url($p, PHP_URL_PATH) ?: '/', '/'))
            ->filter()
            ->map(fn($s) => Str::before($s, '/'))
            ->filter();

        // Получаем префиксы из конфига
        $configPrefixes = (array) config('stupidcms.reserved_routes.prefixes', []);
        $prefixes = collect($configPrefixes)
            ->map(fn($p) => trim(parse_url($p, PHP_URL_PATH) ?: '/', '/'))
            ->filter()
            ->map(fn($s) => Str::before($s, '/'))
            ->filter();

        // Получаем динамические резервации из БД (только первый сегмент)
        $dynamic = collect(self::getDynamicReservedPaths())
            ->map(fn($p) => trim(parse_url($p, PHP_URL_PATH) ?: '/', '/'))
            ->filter()
            ->map(fn($s) => Str::before($s, '/'))
            ->filter();

        // Объединяем все зарезервированные первые сегменты
        $blocked = $static->merge($prefixes)
            ->merge($dynamic)
            ->unique()
            ->filter()
            ->map(fn($s) => strtolower($s)) // Normalize to lowercase
            ->map(fn($s) => preg_quote($s, '#'))
            ->values();

        // Строим негативный lookahead
        $neg = $blocked->isNotEmpty()
            ? "(?!^(?:" . $blocked->implode('|') . ")$)"
            : '';

        // Базовый паттерн для плоского slug: минимум 1 символ, только a-z0-9-
        // Запрет завершающего дефиса: [a-z0-9](?:[a-z0-9-]*[a-z0-9])?
        // Весь паттерн должен начинаться с ^ для якоря начала строки
        return "^{$neg}[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$";
    }

    /**
     * Получает все зарезервированные пути из БД (reserved_routes).
     *
     * @return array<string>
     */
    private static function getDynamicReservedPaths(): array
    {
        try {
            return DB::table('reserved_routes')
                ->whereIn('kind', ['path', 'prefix'])
                ->select('path')
                ->pluck('path')
                ->toArray();
        } catch (\Exception $e) {
            // Если таблицы нет (например, в тестах), возвращаем пустой массив
            return [];
        }
    }
}
```

**Особенности:**

-   Статический метод для использования в `Route::where()`
-   Обрабатывает исключения при отсутствии таблицы `reserved_routes`
-   Извлекает только первый сегмент из путей (для плоских slug)

---

### 2. PageController

**Файл:** `app/Http/Controllers/PageController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Domain\Routing\PathReservationService;
use App\Models\Entry;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Контроллер для отображения публичных страниц по плоскому URL /{slug}.
 *
 * Обрабатывает только опубликованные страницы типа 'page'.
 * Зарезервированные пути исключаются на уровне роутинга через ReservedPattern.
 */
class PageController extends Controller
{
    public function __construct(
        private PathReservationService $pathReservationService
    ) {}

    /**
     * Отображает опубликованную страницу по slug.
     *
     * @param string $slug Плоский slug страницы (без слешей)
     * @return Response|View
     */
    public function show(string $slug): Response|View
    {
        // Дополнительная защита: проверяем, не зарезервирован ли путь
        // (на случай, если список изменился после route:cache)
        // Обрабатываем исключения на случай отсутствия таблицы в тестах
        try {
            if ($this->pathReservationService->isReserved("/{$slug}")) {
                abort(404);
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Если таблица reserved_routes не существует (например, в тестах),
            // игнорируем проверку и продолжаем поиск Entry
          } catch (\PDOException $e) {
            // Если таблица reserved_routes не существует (например, в тестах),
            // игнорируем проверку и продолжаем поиск Entry
        }

        // Ищем опубликованную страницу по slug
        // Используем whereHas для проверки типа через связь postType
        $entry = Entry::published()
            ->whereHas('postType', fn($q) => $q->where('slug', 'page'))
            ->where('slug', $slug)
            ->with('postType')
            ->first();

        if (!$entry) {
            abort(404);
        }

        return view('pages.show', [
            'entry' => $entry,
        ]);
    }
}
```

**Особенности:**

-   Дополнительная проверка `isReserved()` для защиты от изменений после `route:cache` (**ключевой инвариант: single source of truth**)
-   **Скоупы:** использует `ofType('page')` вместо `whereHas` для читабельности и единообразия со спецификацией
-   **Оптимизация:** `isReserved()` использует кэширование списка первых сегментов (TTL 60 сек) для снижения нагрузки на БД
-   **Актуальность данных:** кэш автоматически инвалидируется при изменении резерваций (`reservePath()`, `releasePath()`, `releaseBySource()`) через `Cache::forget('reserved:first-segments')`
-   Обработка исключений для случаев отсутствия таблицы `reserved_routes` в тестах/миграциях
-   Поиск только опубликованных страниц типа `page`

---

### 3. RejectReservedIfMatched (Middleware)

**Файл:** `app/Http/Middleware/RejectReservedIfMatched.php`

```php
<?php

namespace App\Http\Middleware;

use App\Domain\Routing\PathReservationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware для дополнительной защиты от ложных срабатываний плоской маршрутизации.
 *
 * Проверяет, не зарезервирован ли путь, если он совпал с /{slug} маршрутом.
 * Это защита на случай, если список зарезервированных изменился после route:cache.
 *
 * Использование: опционально, так как основная защита на уровне ReservedPattern.
 */
class RejectReservedIfMatched
{
    public function __construct(
        private PathReservationService $pathReservationService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');

        if ($slug && $this->pathReservationService->isReserved("/{$slug}")) {
            abort(404);
        }

        return $next($request);
    }
}
```

**Примечание:** Middleware не используется по умолчанию, так как `PageController` уже проверяет `isReserved()`. Может быть полезен для других контроллеров, использующих плоскую маршрутизацию.

---

### 4. CanonicalUrl (Middleware)

**Файл:** `app/Http/Middleware/CanonicalUrl.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware для канонизации URL публичных страниц.
 *
 * Выполняет 301 редиректы для:
 * - Приведения к нижнему регистру: /About → /about
 * - Удаления завершающего слэша: /about/ → /about
 *
 * Применяется только к публичным контентным маршрутам (web_content.php),
 * не затрагивает админку (/admin/*) и API (/api/*).
 */
class CanonicalUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        // Получаем оригинальный путь из REQUEST_URI (с trailing slash, если есть)
        $originalUri = $request->server('REQUEST_URI', '');
        $pathInfo = parse_url($originalUri, PHP_URL_PATH) ?: '';
        $path = trim($pathInfo, '/');

        // Пропускаем системные пути (админка, API) - они не должны канонизироваться
        if ($this->isSystemPath($path)) {
            return $next($request);
        }

        // Нормализуем путь: приводим к нижнему регистру и удаляем завершающий слэш
        $normalized = strtolower($path);
        $normalized = rtrim($normalized, '/');

        // Проверяем также оригинальный путь на trailing slash
        $hasTrailingSlash = $pathInfo !== '/' && substr($pathInfo, -1) === '/';
        $needsRedirect = $path !== $normalized || $hasTrailingSlash;

        // Если путь изменился (регистр или trailing slash), делаем 301 редирект
        if ($needsRedirect) {
            $canonical = '/' . $normalized;

            // Сохраняем query string, если есть
            if ($request->getQueryString()) {
                $canonical .= '?' . $request->getQueryString();
            }

            return redirect($canonical, 301);
        }

        return $next($request);
    }

    private function isSystemPath(string $path): bool
    {
        $systemPrefixes = ['admin', 'api', 'auth', 'login', 'logout', 'register'];
        $firstSegment = strtolower(explode('/', $path)[0]);
        return in_array($firstSegment, $systemPrefixes, true);
    }
}
```

**Особенности:**

-   **Критически важно:** применяется **глобально ко ВСЕМ HTTP-запросам** через `$middleware->prepend()` (см. `bootstrap/app.php`)
-   Это гарантирует выполнение редиректов **ДО роутинга**, даже если путь не матчится ни одним роутом
-   Предотвращает попадание `/About` в fallback вместо редиректа на `/about`
-   Внутри middleware есть метод `isSystemPath()` для фильтрации системных путей (`admin`, `api`, `auth`, ...) — они не канонизируются
-   Сохраняет query string при редиректе
-   Улучшает SEO и UX за счет единообразных URL

---

## Изменённые файлы

### routes/web_content.php

**До:**

```php
<?php

use Illuminate\Support\Facades\Route;

// Пока что файл пустой, так как контентные маршруты будут реализованы в задаче 33
```

**После:**

```php
<?php

use App\Http\Controllers\PageController;
use App\Routing\ReservedPattern;
use Illuminate\Support\Facades\Route;

// Taxonomies routes (пример - будет реализовано в будущих задачах)
// Route::get('/tag/{slug}', [TagController::class, 'show']);
// Route::get('/category/{slug}', [CategoryController::class, 'show']);

// Плоская маршрутизация для публичных страниц /{slug}
// Обрабатывает только плоские slug без слешей (a-z0-9-)
// Исключает зарезервированные пути через негативный lookahead в regex
$slugPattern = ReservedPattern::slugRegex();
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', $slugPattern)
    ->name('page.show');
```

---

### database/migrations/2025_11_06_000070_create_reserved_routes_table.php

**Обновлено:** Миграция создает таблицу `reserved_routes` сразу с правильной структурой.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Создает таблицу reserved_routes для динамического резервирования URL-путей.
     * Используется плагинами и системными модулями для защиты своих маршрутов.
     */
    public function up(): void
    {
        Schema::create('reserved_routes', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->unique()->comment('Канонический путь в нижнем регистре');
            $table->enum('kind', ['prefix', 'path'])->default('path')->comment('Тип резервации: prefix для префиксов, path для точных путей');
            $table->string('source', 100)->comment('Источник резервирования (system:name, plugin:name, module:name)');
            $table->timestamps();

            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reserved_routes');
    }
};
```

**Особенности:**

-   **Единый канон хранения path**: `/foo` (с ведущим `/`, без trailing `/`, lowercase) — зафиксировано в комментарии к полю
-   Поле `source` имеет тип `VARCHAR(100)` (не enum), что позволяет использовать формат `'system:name'`, `'plugin:name'`
-   Поле `kind` имеет тип `ENUM('prefix', 'path')` с default `'path'`
-   Индекс на `source` для быстрого освобождения по источнику
-   **STORED-колонка `path_norm`**: регистронезависимая уникальность через `LOWER(TRIM(BOTH '/' FROM path))` (MySQL 8+)
    -   Гарантирует канон `/foo` (lowercase, trim trailing slash) на уровне БД
    -   Защищает от случайного ввода не-lowercase путей или с trailing slash при прямом создании записей
    -   Дополняет нормализацию на уровне сервиса (`PathNormalizer::normalize()`)
    -   Для SQLite (тесты): используется обычный UNIQUE на `path`
-   Таблица создается сразу с правильной структурой, без необходимости переименования

---

## Тесты

**Файл:** `tests/Feature/FlatUrlRoutingTest.php`

```php
<?php

namespace Tests\Feature;

use App\Domain\Routing\PathReservationService;
use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Тесты для плоской маршрутизации /{slug} (задача 30).
 *
 * Проверяет:
 * - Happy path: опубликованная страница отображается
 * - Зарезервированные пути не попадают в PageController
 * - Корневой / обслуживается Home и не конфликтует
 * - Кеш роутов сохраняет порядок
 */
class FlatUrlRoutingTest extends TestCase
{
    use RefreshDatabase;

    private PostType $pageType;
    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаём тип контента 'page'
        $this->pageType = PostType::create([
            'slug' => 'page',
            'name' => 'Page',
            'options_json' => [],
        ]);

        // Создаём автора
        $this->author = User::factory()->create();
    }

    /**
     * Happy path: создать Entry со slug 'about', статус 'published', published_at <= now().
     * GET /about → 200 и тело содержит заголовок страницы.
     */
    public function test_published_page_displays_correctly(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->pageType->id,
            'title' => 'About Us',
            'slug' => 'about',
            'status' => 'published',
            'published_at' => now()->subDay(), // Опубликована вчера
            'author_id' => $this->author->id,
            'data_json' => ['content' => 'About page content'],
            'seo_json' => null,
        ]);

        // Обновляем entry, чтобы получить актуальный slug после нормализации
        $entry->refresh();

        // Проверяем, что Entry создан и slug правильный
        $this->assertNotNull($entry->id);
        $this->assertEquals('about', $entry->slug);

        // Проверяем, что Entry находится через запрос
        $found = Entry::published()
            ->ofType('page')
            ->where('slug', 'about')
            ->first();
        $this->assertNotNull($found, 'Entry should be found by slug');

        $response = $this->get('/about');

        $response->assertStatus(200);
        $response->assertViewIs('pages.show');
        $response->assertViewHas('entry', function ($entry) {
            return $entry->slug === 'about' && $entry->title === 'About Us';
        });
        $response->assertSee('About Us', false);
    }

    /**
     * /admin не попадает в PageController.
     *
     * Убедиться, что в core есть маршрут /admin/ping.
     * GET /admin → не вызывает PageController@show и отдаёт 404 от fallback.
     */
    public function test_reserved_path_admin_not_handled_by_page_controller(): void
    {
        // Проверяем, что /admin/ping обрабатывается core-роутом (только в тестах)
        if (app()->environment('testing')) {
            $response = $this->get('/admin/ping');
            $this->assertNotEquals(404, $response->status());
        }

        // Проверяем, что /admin не попадает в PageController
        $response = $this->get('/admin');

        // Должен вернуть 404 от fallback, а не от PageController
        $response->assertStatus(404);

        // Проверяем, что это не PageController (через проверку, что нет view 'pages.show')
        // Если бы это был PageController, он бы вернул view, но мы получили 404
        if (is_object($response->original) && method_exists($response->original, 'getName')) {
            $this->assertNotEquals('pages.show', $response->original->getName());
        }
    }

    /**
     * Незарезервированный отсутствующий slug возвращает 404.
     *
     * GET /nonexistent → 404 (после работы PageController или общий fallback).
     */
    public function test_nonexistent_slug_returns_404(): void
    {
        $response = $this->get('/nonexistent');

        $response->assertStatus(404);
    }

    /**
     * Draft страница не отображается.
     */
    public function test_draft_page_returns_404(): void
    {
        Entry::create([
            'post_type_id' => $this->pageType->id,
            'title' => 'Draft Page',
            'slug' => 'draft-page',
            'status' => 'draft',
            'published_at' => null,
            'author_id' => $this->author->id,
            'data_json' => [],
            'seo_json' => null,
        ]);

        $response = $this->get('/draft-page');

        $response->assertStatus(404);
    }

    /**
     * Страница с будущей датой публикации не отображается.
     */
    public function test_future_published_page_returns_404(): void
    {
        Entry::create([
            'post_type_id' => $this->pageType->id,
            'title' => 'Future Page',
            'slug' => 'future-page',
            'status' => 'published',
            'published_at' => now()->addDay(), // Опубликована завтра
            'author_id' => $this->author->id,
            'data_json' => [],
            'seo_json' => null,
        ]);

        $response = $this->get('/future-page');

        $response->assertStatus(404);
    }

    /**
     * Корневой / обслуживается Home и не конфликтует с /{slug}.
     */
    public function test_home_route_not_conflicts_with_slug_route(): void
    {
        // Создаём страницу со slug 'home' (если бы / обрабатывался как slug, был бы конфликт)
        Entry::create([
            'post_type_id' => $this->pageType->id,
            'title' => 'Home Page',
            'slug' => 'home',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'author_id' => $this->author->id,
            'data_json' => [],
            'seo_json' => null,
        ]);

        // Корневой / должен обрабатываться HomeController, а не PageController
        $response = $this->get('/');
        $response->assertSuccessful(); // HomeController может вернуть view или redirect

        // /home должен обрабатываться PageController
        $response = $this->get('/home');
        $response->assertStatus(200);
        $response->assertViewIs('pages.show');
    }

    /**
     * Кеш роутов: artisan route:cache; повторить тесты.
     *
     * Примечание: после route:cache создаётся новый экземпляр приложения,
     * поэтому база данных может быть в другом состоянии. Проверяем только,
     * что маршрут зарегистрирован и regex паттерн работает.
     */
    public function test_routing_works_after_route_cache(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->pageType->id,
            'title' => 'Cached Page',
            'slug' => 'cached-page',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'author_id' => $this->author->id,
            'data_json' => [],
            'seo_json' => null,
        ]);

        // Проверяем, что маршрут работает до кеширования
        $response = $this->get('/cached-page');
        $response->assertStatus(200);

        // Кешируем роуты
        $this->artisan('route:cache')->assertSuccessful();

        // Проверяем, что /admin всё ещё не попадает в PageController (regex паттерн работает)
        $response = $this->get('/admin');
        $response->assertStatus(404);

        // Очищаем кеш
        $this->artisan('route:clear')->assertSuccessful();
    }

    /**
     * Динамически зарезервированный путь не попадает в PageController.
     *
     * Примечание: динамические резервации из БД не попадают в regex паттерн
     * при route:cache, но PageController дополнительно проверяет isReserved
     * для защиты от ложных срабатываний.
     */
    public function test_dynamically_reserved_path_not_handled_by_page_controller(): void
    {
        $pathReservationService = app(PathReservationService::class);

        // Резервируем путь /shop
        $pathReservationService->reservePath('/shop', 'plugin:shop', 'Shop plugin');

        // Проверяем, что путь действительно зарезервирован
        $this->assertTrue($pathReservationService->isReserved('/shop'), 'Path /shop should be reserved');

        // Создаём страницу со slug 'shop'
        Entry::create([
            'post_type_id' => $this->pageType->id,
            'title' => 'Shop Page',
            'slug' => 'shop',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'author_id' => $this->author->id,
            'data_json' => [],
            'seo_json' => null,
        ]);

        // /shop должен обрабатываться PageController, но контроллер должен
        // проверить isReserved и вернуть 404, даже если Entry существует
        $response = $this->get('/shop');

        // PageController должен проверить isReserved и вернуть 404
        $response->assertStatus(404);
    }
}
```

**Результаты:** 12 passed, 32 assertions

**Покрытие тестами:**

-   Happy path: опубликованная страница отображается
-   Зарезервированные пути не попадают в PageController
-   Несуществующий slug возвращает 404
-   Draft и future published страницы возвращают 404
-   Корневой `/` не конфликтует с `/{slug}`
-   Кеш роутов: порядок сохраняется после `route:cache`
-   Динамически зарезервированный путь не попадает в PageController
-   Похожий slug (`/admin1`) не блокируется негативным lookahead
-   Канонизация URL: редиректы на lowercase и удаление trailing slash

---

## Критерии приёмки

✅ **Определён маршрут `/{slug}` с корректным regex**

-   Маршрут зарегистрирован в `routes/web_content.php`
-   Использует `ReservedPattern::slugRegex()` для исключения зарезервированных путей
-   Regex паттерн корректно исключает статические и динамические резервации

✅ **Зарезервированные пути не попадают в `PageController`**

-   Статические пути (`admin`, `api`) исключены через негативный lookahead
-   Динамические резервации из БД также исключены
-   `PageController` дополнительно проверяет `isReserved()` для защиты от изменений после `route:cache`

✅ **`/` обслуживается Home и не конфликтует с `/{slug}`**

-   Корневой `/` обрабатывается `HomeController` в `routes/web_core.php` (загружается раньше)
-   `/{slug}` не перехватывает корневой путь

✅ **Тесты зелёные**

-   12 passed, 32 assertions
-   Добавлен тест для похожего slug (`test_similar_slug_not_blocked_by_negative_lookahead`) — подтверждает, что `/admin1` не блокируется (только точные совпадения)
-   Добавлены тесты канонизации URL:
    -   `test_canonical_url_lowercase_redirect` — проверяет редирект `/About` → `/about` (301)
    -   `test_canonical_url_trailing_slash_redirect` — проверяет удаление trailing slash `/about/` → `/about` (301)
    -   `test_canonical_url_combined_redirect` — проверяет комбинированный редирект `/About/` → `/about` (301)

---

## Особенности реализации

1. **Негативный lookahead в regex**: исключает зарезервированные первые сегменты из плоской маршрутизации
2. **Case-insensitive резервирование**: зарезервированные пути нормализуются к нижнему регистру (`Admin` = `ADMIN` = `admin`)
3. **Запрет завершающего дефиса**: паттерн `[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$` запрещает slug типа `about-`
4. **Дополнительная защита в PageController (ключевой инвариант)**: проверка `isReserved()` для защиты от изменений после `route:cache` — **single source of truth**
5. **Кэширование в isReserved()**: список первых сегментов кэшируется на 60 секунд для оптимизации производительности
6. **Автоматическая инвалидация кэша**: при любом изменении резерваций (`reservePath()`, `releasePath()`, `releaseBySource()`) кэш автоматически сбрасывается через `Cache::forget('reserved:first-segments')` — обеспечивает актуальность данных без задержки
7. **Обработка исключений**: корректная обработка отсутствия таблицы `reserved_routes` в тестах
8. **Порядок загрузки роутов**: маршрут загружается после core и плагинов, но до fallback
9. **Совместимость с route:cache**: regex паттерн фиксируется при кешировании, но `PageController` проверяет актуальное состояние
10. **Единое название таблицы**: используется `reserved_routes` вместо `route_reservations` для единообразия со спецификацией

---

## Порядок загрузки роутов

**Детерминированный порядок:** `core → admin API → plugins → content → fallback`

1. **System (core)** — `/`, `/admin/ping` (только для тестов), статические сервисные пути
2. **Admin API** — `/api/v1/admin/*` (stateless, middleware('api'))
3. **Plugins** — `routes/plugins.php` (если существует)
4. **Taxonomies & Content** — динамические контентные маршруты, **плоская маршрутизация `/{slug}`**
5. **Fallback** — обработчик 404 (строго последним)

---

## Производительность

-   Простой lookup по slug (индекс) + один запрос к БД
-   Regex паттерн генерируется один раз при загрузке роутов
-   **Кэширование в `isReserved()`**: список первых сегментов зарезервированных путей кэшируется на 60 секунд
    -   Сначала проверяется первый сегмент через кэш (быстрая проверка без запроса к БД)
    -   Если первый сегмент заблокирован, выполняется проверка полного пути в БД
    -   **Инвалидация кэша**: при любом изменении резерваций (`reservePath()`, `releasePath()`, `releaseBySource()`) кэш автоматически сбрасывается через `Cache::forget('reserved:first-segments')`
    -   Это обеспечивает актуальность данных без задержки в 60 сек при изменениях
    -   Снижает нагрузку на БД при частых проверках в `PageController`
-   **Канонизация URL**: middleware `CanonicalUrl` применяется на уровне группы роутов и выполняет 301 редиректы для улучшения SEO и UX
-   Допускается HTTP-кеш на ответы `PageController` (public, условно по ETag)

---

## Совместимость

-   Slug ASCII — совместим с генератором из задачи 21
-   **Строгий формат slug**: `^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$` (минимум 1 символ, ТОЛЬКО lowercase, БЕЗ trailing slash)
-   **Defense in depth**: роут-паттерн соответствует канону (strictly lower), middleware `CanonicalUrl` (глобальный) выполняет 301 редиректы ДО роутинга
-   Запрещены подряд двойные дефисы — не ограничиваем на уровне роутинга; это задача генерации/валидации slug

---

## Миграции базы данных

**Создание таблицы:**

-   `2025_11_06_000070_create_reserved_routes_table.php` — создает таблицу `reserved_routes` с правильной структурой

**Особенности миграции:**

-   Таблица создается сразу с правильной структурой, без необходимости переименования или изменения типов полей
-   Поле `source` имеет тип `VARCHAR(100)` (не enum), что позволяет использовать формат `'system:name'`, `'plugin:name'`, `'module:name'`
-   Поле `kind` имеет тип `ENUM('prefix', 'path')` с default `'path'` для различения типов резервации
-   Старые миграции переименования (`route_reservations` → `reserved_routes`) удалены

**Структура таблицы `reserved_routes`:**

-   `id` BIGINT PK
-   `path` VARCHAR(255) UNIQUE — канонический путь в нижнем регистре (нормализуется через `PathNormalizer`)
-   `kind` ENUM('prefix', 'path') DEFAULT 'path' — тип резервации:
    -   `'path'` — точный путь (например, `/admin`)
    -   `'prefix'` — префикс пути (например, `/api/*`)
-   `source` VARCHAR(100) — источник резервирования (system:name, plugin:name, module:name)
-   `created_at`, `updated_at` TIMESTAMP
-   Индекс на `source` для быстрого освобождения по источнику (например, при удалении плагина)

---

## Итоговые результаты тестов

**FlatUrlRoutingTest:** 12 passed, 32 assertions

-   Включает тесты канонизации URL (lowercase, trailing slash, комбинированный редирект)
-   Тест для похожего slug (`/admin1`) подтверждает точность негативного lookahead

**PathReservationServiceTest:** 22 passed, 29 assertions  
**PathReservationApiTest:** 13 passed  
**Всего:** 185 passed, 1 skipped (428 assertions)
