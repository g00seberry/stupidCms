# Детерминированный порядок роутинга и HTTP middleware

## Резюме

Реализован `RouteServiceProvider` с детерминированным порядком загрузки роутов, обеспечивающий правильную обработку системных маршрутов до catch-all и fallback. Система гарантирует, что узкие статические пути обрабатываются раньше динамических контентных маршрутов.

**Дата реализации:** 2025-11-07

---

## Проблема

В Laravel порядок регистрации роутов критичен: если catch-all маршрут `/{slug}` зарегистрирован раньше системных маршрутов типа `/admin`, то все запросы к `/admin` будут перехватываться контентным резолвером, что приводит к неправильной обработке запросов.

## Решение

Создан `RouteServiceProvider`, который загружает роуты в строго определённом порядке:

1. **System (core)** — системные маршруты (`/admin`, `/auth/*`, `/api/*`, статические сервисные)
2. **Plugins** — маршруты плагинов (детерминированный порядок)
3. **Taxonomies & Content** — динамические контентные маршруты и таксономии
4. **Fallback** — обработчик 404 (строго последним)

---

## Структура системы

### 1. RouteServiceProvider

**Файл:** `app/Providers/RouteServiceProvider.php`

Основной провайдер, который управляет порядком загрузки роутов:

```php
public function boot(): void
{
    $this->routes(function () {
        // 1) System/Core routes
        Route::middleware('web')
            ->group(base_path('routes/web_core.php'));

        // 1.5) Admin API routes - загружаются после core, но до плагинов
        // Используют middleware('api') для stateless API без CSRF
        Route::middleware('api')
            ->prefix('api/v1/admin')
            ->group(base_path('routes/api_admin.php'));

        // 2) Plugin routes
        $this->mapPluginRoutes();

        // 3) Taxonomies & Content routes
        Route::middleware('web')
            ->group(base_path('routes/web_content.php'));

        // 4) Fallback
        Route::fallback(\App\Http\Controllers\FallbackController::class)
            ->middleware('web');
    });
}
```

**Особенности:**

-   Детерминированный порядок загрузки
-   Админский API вынесен в отдельный файл с middleware('api') для stateless работы
-   Поддержка плагинов (заглушка для будущего расширения)
-   Fallback регистрируется строго последним

### 2. Разделение файлов роутов

#### `routes/web_core.php`

Содержит все системные и узкие статические пути:

-   `/` — главная страница (HomeController)
-   `/admin/ping` — тестовый маршрут для проверки порядка (только для тестов)
-   Статические сервисные пути (закомментированы для будущего расширения)

**Важно:** Главная страница должна быть в `web_core.php`, чтобы не перехватывалась контентным catch-all.

#### `routes/api_admin.php`

Содержит админские API маршруты:

-   `/api/v1/admin/utils/slugify` — утилита для генерации slug
-   `/api/v1/admin/reservations` — управление резервированием путей

**Особенности:**

-   Использует middleware('api') для stateless API без CSRF
-   Throttle `api` для защиты от злоупотреблений (60 запросов в минуту, настроен в `RouteServiceProvider::boot()`)
-   Авторизация через `auth:admin` middleware (явный guard для администраторских запросов)
-   Политики для проверки прав доступа

**Безопасность:**

-   Guard 'admin' использует тот же provider 'users', что и 'web', но явно идентифицирует администраторские запросы
-   Для кросс-сайтовых запросов (SPA на другом origin) требуется настройка:
    -   Cookies: `SameSite=None; Secure`
    -   CORS: `credentials: true`
    -   CSRF токены для state-changing операций (если используется cookie-based auth)

#### `routes/web_content.php`

Содержит динамические контентные маршруты:

-   Таксономии (примеры закомментированы)
-   Контентный резолвер `/{slug}` (будет реализован в задаче 33)

**Важно:**

-   Все catch-all маршруты должны быть здесь, а не в `web_core.php`.
-   Catch-all должен игнорировать зарезервированные префиксы через негативный lookahead:
    ```php
    Route::get('{slug}', ContentController::class)
        ->where('slug', '^(?!(admin|api|auth|shop)(/|$))[A-Za-z0-9][A-Za-z0-9\-\/]*$');
    ```
    Это дополнительно к проверке в `ReservedRouteRegistry` и `PathReservationService`.

#### `routes/web.php`

Оставлен для обратной совместимости, но больше не используется. Роуты загружаются через `RouteServiceProvider`.

### 3. FallbackController

**Файл:** `app/Http/Controllers/FallbackController.php`

Обрабатывает все несовпавшие запросы (404):

-   Для API запросов возвращает JSON в формате RFC 7807
-   Для веб-запросов возвращает view `errors.404`

**Особенности:**

-   Регистрируется строго последним в `RouteServiceProvider`
-   Поддерживает как JSON, так и HTML ответы
-   Включает путь запроса в ответ для отладки
-   Детекция JSON через `expectsJson()`, `is('api/*')` и `wantsJson()`
-   Типизированный возвращаемый тип: `JsonResponse|Response|View`

### 4. AdminPingController

**Файл:** `app/Http/Controllers/AdminPingController.php`

Тестовый контроллер для проверки порядка роутинга:

-   Маршрут `/admin/ping` должен обрабатываться до fallback
-   Используется в тестах для проверки критерия приёмки

### 5. View для ошибки 404

**Файл:** `resources/views/errors/404.blade.php`

Простой HTML шаблон для отображения ошибки 404:

-   Показывает код ошибки и путь запроса
-   Ссылка на главную страницу
-   Минималистичный дизайн

---

## Интеграция с Laravel 11

В Laravel 11 используется новый подход к конфигурации приложения через `bootstrap/app.php`.

**Изменения:**

-   Убран параметр `web: __DIR__.'/../routes/web.php'` из `withRouting()`
-   Роуты теперь загружаются через `RouteServiceProvider`
-   `RouteServiceProvider` зарегистрирован в `bootstrap/providers.php`

---

## Порядок загрузки роутов

**Детерминированный порядок:** `core → admin API → plugins → content → fallback`

### 1. System (core) routes

Загружаются из `routes/web_core.php`:

-   `/` — HomeController
-   `/admin/ping` — AdminPingController (только для тестов)
-   Статические сервисные пути
-   Используют middleware('web') для веб-запросов с CSRF

### 2. Admin API routes

**КРИТИЧНО:** Загружаются ДО плагинов, чтобы `/api/v1/admin/*` не перехватывались catch-all

Загружаются из `routes/api_admin.php`:

-   `/api/v1/admin/*` — API маршруты админки
-   Используют middleware('api') для stateless работы без CSRF
-   Throttle `api` для защиты от злоупотреблений (60 запросов в минуту)
-   Guard `auth:admin` для явной идентификации администраторских запросов

### 3. Plugin routes

Загружаются через `mapPluginRoutes()`:

-   Проверяется наличие `routes/plugins.php`
-   В будущем будет интеграция с `PluginRegistry` с сортировкой по приоритету
-   **КРИТИЧНО:** Плагин сам должен объявлять нужные middleware группы (web/api)
    -   НЕ навешиваем `middleware('web')` сверху, иначе получится микс web+api
    -   Это позволяет плагину создавать как веб-страницы, так и stateless API
    -   Пример для плагина:
        ```php
        Route::middleware('web')->group(function() {
            Route::get('/plugin/page', PluginController::class);
        });
        Route::middleware('api')->prefix('api/plugin')->group(function() {
            Route::get('/data', PluginApiController::class);
        });
        ```
-   **Важно:** После `route:cache` порядок и наличие плагин-роутов "застынет". При включении/выключении плагина обязателен перезапуск `route:cache`.

### 4. Taxonomies & Content routes

Загружаются из `routes/web_content.php`:

-   Таксономии (если есть)
-   Контентный резолвер `/{slug}` (catch-all для контента)
-   **Важно:** Catch-all должен игнорировать зарезервированные префиксы через негативный lookahead

### 5. Fallback

Регистрируется последним для обработки всех несовпавших запросов:

-   Обрабатывает ВСЕ HTTP методы (GET, POST, PUT, DELETE, HEAD, OPTIONS, и т.д.)
-   **КРИТИЧНО:** НЕ использует `middleware('web')` - иначе POST без CSRF вернёт 419 вместо 404
-   Контроллер сам определяет формат ответа (HTML/JSON) по типу запроса
-   Возвращает RFC 7807 (application/problem+json) для API запросов
-   **Телеметрия:** Логирует структурированные данные о 404 (path, method, referer, accept, user_agent, ip) для поиска битых ссылок

---

## Тесты

**Файл:** `tests/Feature/RoutingOrderTest.php`

Покрытие:

1. ✅ `/admin/ping` обрабатывается до fallback (критерий приёмки)
2. ✅ Неизвестный путь обрабатывается fallback (404)
3. ✅ Fallback возвращает JSON для API запросов (RFC 7807, Content-Type: application/problem+json)
4. ✅ Главная страница обрабатывается до fallback (assertSuccessful для 2xx)
5. ✅ API роуты обрабатываются до fallback (401/403, не 404)
6. ✅ API роуты обрабатываются для HEAD запросов (не 404)
7. ✅ Порядок роутов сохраняется после `route:cache`
8. ✅ POST на несуществующий путь возвращает 404, а не 419 CSRF
9. ✅ Неизвестный путь под /api/\* использует problem+json
10. ✅ OPTIONS preflight на неизвестный API путь обрабатывается fallback

**Результаты:** 10 passed, 35 assertions

---

## Критерии приёмки

✅ `RouteServiceProvider` загружает маршруты в порядке: **core → admin API → plugins → content → fallback**  
✅ `/admin` и другие системные адреса **никогда** не перехватываются контентным catch-all  
✅ Автотесты зелёные (10 passed, 35 assertions)

---

## Middleware группы

Используются стандартные группы Laravel:

-   `web` — для всех веб-роутов (включает сессии, CSRF, cookies)
-   `auth` — для защищённых API маршрутов
-   `can:...` — для проверки прав доступа

Все роуты используют middleware группу `web`, что обеспечивает:

-   Обработку сессий
-   CSRF защиту
-   Cookie шифрование
-   Локализацию (если настроена)

---

## Расширение

### Плагины

Для будущей интеграции с системой плагинов:

1. Создать `PluginRegistry` (задача из roadmap)
2. Обновить `mapPluginRoutes()` для загрузки роутов из плагинов
3. Обеспечить сортировку по приоритету для детерминированности

### Контентные маршруты

В задаче 33 будет реализован контентный резолвер:

-   Маршрут `/{slug}` в `routes/web_content.php`
-   Проверка зарезервированных путей через `ReservedRouteRegistry`
-   Рендеринг Entry через соответствующий контроллер

---

## Файлы изменений

### Новые файлы:

-   `app/Providers/RouteServiceProvider.php` — провайдер для управления порядком роутов
-   `routes/web_core.php` — системные маршруты
-   `routes/api_admin.php` — админские API маршруты (stateless)
-   `routes/web_content.php` — контентные маршруты
-   `app/Http/Controllers/FallbackController.php` — обработчик 404
-   `app/Http/Controllers/AdminPingController.php` — тестовый контроллер
-   `resources/views/errors/404.blade.php` — шаблон ошибки 404
-   `resources/css/errors.css` — стили для страниц ошибок (вынесены из inline для CSP)
-   `tests/Feature/RoutingOrderTest.php` — тесты порядка роутинга
-   `docs/implemented/routing_order_middleware.md` — документация

### Изменённые файлы:

-   `bootstrap/app.php` — убран параметр `web` из `withRouting()`
-   `bootstrap/providers.php` — добавлен `RouteServiceProvider`

### Устаревшие файлы:

-   `routes/web.php` — больше не используется, содержит только комментарий с объяснением. Все роуты перенесены в `routes/web_core.php`

---

## Примечания

-   Порядок загрузки роутов критичен для правильной работы системы
-   Все catch-all маршруты должны быть после узких статических путей
-   Fallback всегда регистрируется последним
-   После `route:cache` порядок сохраняется благодаря детерминированной загрузке
-   **Важно:** После `route:cache` при включении/выключении плагинов требуется перезапуск `route:cache`

**Чек-лист деплоя:**

-   После включения/выключения плагинов выполнить `php artisan route:clear && php artisan route:cache`
-   После изменения порядка роутов в `RouteServiceProvider` выполнить `php artisan route:clear`
-   Админский API использует middleware('api') для stateless работы без CSRF
-   Тестовый маршрут `/admin/ping` доступен только в тестовой среде
-   Catch-all контентных маршрутов должен игнорировать зарезервированные префиксы
-   Система готова к интеграции с плагинами через `PluginRegistry`
