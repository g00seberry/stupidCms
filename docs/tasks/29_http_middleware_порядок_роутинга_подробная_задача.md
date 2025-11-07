# Задача 29. HTTP middleware / порядок роутинга

## Резюме
Настроить **детерминированный порядок загрузки роутов** и групп middleware в `RouteServiceProvider`, чтобы маршруты ядра шли первыми, затем — маршруты плагинов и таксономий, и **только после этого** — catch-all и `fallback`.

**Критерии приёмки:** тестовый роут `/admin` **не** перехватывается catch-all/fallback, а обрабатывается своим контроллером.

Связанные задачи: 23 (reserved routes), 26 (home `/`), 27 (политики), 28 (резерв путей), 32 (HomeController), 33 (Entry rendering).

---

## Требуемый порядок
1. **System (core)** — `/admin`, `/auth/*`, `/api/*`, статические сервисные (`/health`, `/feed.xml`, `/sitemap.xml`).
2. **Plugins** — под-секции, предоставленные установленными плагинами (например, `/shop`, `/comments`).
3. **Taxonomies & Content** — маршруты таксономий (если есть собственные URL) и **контентные маршруты** (например, `/{slug}` и nested `/{parent}/{child}`), использующие резолвер Entry.
4. **Fallback** — `Route::fallback(...)`.

> Любые catch-all/динамические маршруты **всегда** после «узких» статических путей.

---

## `RouteServiceProvider` (эскиз)
```php
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        parent::boot();
        $this->routes(function () {
            // 1) System/Core
            Route::middleware(['web'])
                ->group(base_path('routes/web_core.php'));

            // 2) Plugins (детерминированный порядок)
            $this->mapPluginRoutes();

            // 3) Taxonomies & Content
            Route::middleware(['web'])
                ->group(base_path('routes/web_content.php'));

            // 4) Fallback — строго последним
            Route::middleware(['web'])
                ->fallback(\App\Http\Controllers\FallbackController::class);
        });
    }

    protected function mapPluginRoutes(): void
    {
        $plugins = app(\App\Domain\Plugins\PluginRegistry::class)->enabled();
        // Отсортировать по приоритету/имени для стабильности
        $plugins = collect($plugins)->sortBy('priority')->all();

        foreach ($plugins as $plugin) {
            if (file_exists($file = $plugin->routesFile('web'))) {
                Route::middleware(['web'])
                    ->group($file);
            }
        }
    }
}
```

### Содержимое файлов маршрутов
- `routes/web_core.php` — все системные и «узкие» пути: `/admin` (панель), `/auth/*`, `/health`, `/feed.xml`, `/sitemap.xml`, `/` (HomeController).
- `routes/web_content.php` — **динамика** контента и таксономий, пример:
```php
// Taxonomies (пример)
Route::get('/tag/{slug}', [TagController::class, 'show']);

// Content resolver (catch-more, но не полный fallback)
Route::get('{slug}', ContentController::class)
    ->where('slug', '^[A-Za-z0-9][A-Za-z0-9\-\/]*$');
```
- `fallback` — общий обработчик 404.

---

## Middleware-группы и приоритеты
Используем стандартные группы `web`/`api`. Для контента рекомендуется `web + cache.headers` (если ResponseCache), для админки — `web + auth + can:...`.

В `App\Http\Kernel` убедиться, что `\Illuminate\Routing\Middleware\SubstituteBindings::class` и `\App\Http\Middleware\SetLocale::class` находятся в `web` до резолвинга контента.

При необходимости задать **приоритет middleware**:
```php
protected $middlewarePriority = [
    // ...
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
    \App\Http\Middleware\SetLocale::class,
    // ниже кеш/троттлинг и др.
];
```

---

## Поведение `/`
Маршрут главной (см. Задачу 26) должен быть определён **в `web_core.php`**, чтобы не попадать под контентные catch-all.

---

## Тесты (Feature)
1. **`/admin` против fallback**: определить тестовый контроллер `AdminPingController` в `web_core.php` с маршрутом `/admin/ping`. Запрос `GET /admin/ping` → 200 `"OK"`. Убедиться, что `fallback` не срабатывает.
2. **Unknown → fallback**: `GET /non-existent-xyz` → 404 с телом/шаблоном `errors.404`.
3. **Плагины раньше контента**: определить в фиктивном плагине маршрут `/shop` → 200. При этом контентный маршрут `/{slug}` не должен перехватывать `/shop`.
4. **Порядок с кешем роутов**: после `artisan route:cache` поведение не меняется.

---

## Приёмка (Definition of Done)
- [ ] `RouteServiceProvider` загружает маршруты в порядке: core → plugins → content → fallback.
- [ ] `/admin` и другие системные адреса **никогда** не перехватываются контентным catch-all.
- [ ] Автотесты выше зелёные.

---

## Дополнительно
- Для конфликтов с путями — использовать Сервис резервирования путей (Задача 28) и статический конфиг (Задача 23).
- Все плагины обязаны регистрировать свои пути в группе `web` и избегать catch-all внутри себя (иначе ломают общий порядок).

