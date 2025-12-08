# Система роутинга приложения

Исчерпывающее описание системы маршрутизации в headless CMS.

## Содержание

1. [Архитектура и порядок загрузки](#архитектура-и-порядок-загрузки)
2. [Файлы роутов](#файлы-роутов)
3. [Middleware](#middleware)
4. [Система резервирования путей](#система-резервирования-путей)
5. [Плагины и их роутинг](#плагины-и-их-роутинг)
6. [Fallback механизм](#fallback-механизм)
7. [Rate Limiting](#rate-limiting)
8. [Безопасность](#безопасность)
9. [Канонизация URL](#канонизация-url)
10. [Примеры использования](#примеры-использования)

---

## Архитектура и порядок загрузки

### Общая схема

Роуты загружаются через `RouteServiceProvider` в **детерминированном порядке** для обеспечения корректной работы catch-all маршрутов и предотвращения конфликтов.

**Порядок загрузки:**

```
1) Core routes (web_core.php)
   ↓
2) Public API routes (api.php)
   ↓
3) Admin API routes (api_admin.php)
   ↓
4) Plugin routes (plugins/*/routes/*.php)
   ↓
5) Content routes (web_content.php) — **удалены (плоский роутинг)**
   ↓
6) Fallback (404 handler)
```

### RouteServiceProvider

**Файл:** `app/Providers/RouteServiceProvider.php`

**Основные функции:**

- Настройка rate limiters для различных типов запросов
- Загрузка роутов в строгом порядке
- Регистрация fallback-обработчика

**Ключевые методы:**

```php
public function boot(): void
{
    // Настройка rate limiters
    RateLimiter::for('api', ...);
    RateLimiter::for('login', ...);
    RateLimiter::for('refresh', ...);
    RateLimiter::for('search-public', ...);
    RateLimiter::for('search-reindex', ...);
    
    // Загрузка роутов
    $this->routes(function () {
        // Порядок загрузки...
    });
}
```

**Почему важен порядок:**

1. **Core routes** загружаются первыми, чтобы системные пути (`/`, `/up`) не перехватывались catch-all
2. **Public API** загружается до Admin API для корректной работы публичных эндпоинтов
3. **Admin API** загружается до плагинов, чтобы `/api/v1/admin/*` не перехватывались плагинами
4. **Plugin routes** — удалены (система плагинов удалена)
5. **Content routes** — удалены (плоский роутинг на основе `slug` удалён, готовится иерархическая система)
6. **Fallback** загружается строго последним для обработки всех несовпавших запросов

---

## Файлы роутов

### 1. routes/web_core.php

**Назначение:** Системные и служебные маршруты

**Middleware:** `web` (CSRF защита включена)

**Содержимое:**

- `GET /` — главная страница (`HomeController`)
- Тестовые маршруты (только в `testing` окружении)
- Зарезервировано для статических сервисных путей (`/health`, `/feed.xml`, `/sitemap.xml`)

**Особенности:**

- Загружается первым, чтобы не перехватываться контентным catch-all
- Использует `web` middleware для веб-запросов с CSRF

### 2. routes/api.php

**Назначение:** Публичные API эндпоинты

**Middleware:** `api` (stateless, без CSRF для auth endpoints)

**Префикс:** `/api`

**Содержимое:**

```
POST   /api/v1/auth/login      - Аутентификация
POST   /api/v1/auth/refresh     - Обновление токена
POST   /api/v1/auth/logout     - Выход (требует JWT)
GET    /api/v1/search          - Публичный поиск
GET    /api/v1/media/{id}      - Публичный доступ к медиа
```

**Безопасность:**

- Rate limiting: `throttle:login`, `throttle:refresh`, `throttle:search-public`
- CSRF исключён для `login` и `refresh` (credentials-based auth)
- `logout` требует JWT аутентификации
- `media` поддерживает опциональную JWT для доступа к удалённым файлам

### 3. routes/api_admin.php

**Назначение:** Администраторские API эндпоинты

**Middleware:** `api`, `jwt.auth`, `throttle:api`

**Префикс:** `/api/v1/admin`

**Основные группы:**

- **Auth:** `GET /auth/current` — текущий пользователь
- **Utils:** `GET /utils/slugify` — генерация slug (для будущей иерархической системы)
- **Templates:** CRUD для шаблонов
- **Plugins:** управление плагинами (`index`, `sync`, `enable`, `disable`)
- **Reservations:** управление резервированием путей
- **Post Types:** CRUD для типов записей
- **Form Configs:** конфигурация форм компонентов
- **Entries:** CRUD + soft-delete/restore
- **Taxonomies:** CRUD для таксономий
- **Terms:** управление терминами таксономий
- **Media:** CRUD + bulk операции
- **Options:** управление опциями системы
- **Search:** `POST /search/reindex` — переиндексация
- **Blueprints:** CRUD + зависимости/embeddable
- **Paths:** глобальные операции с путями

**Безопасность:**

- Все эндпоинты требуют JWT аутентификации (`jwt.auth`)
- Rate limiting: `throttle:api` (120 запросов/минуту)
- Дополнительные rate limiters для специфичных операций
- Авторизация через Policies и `can:` middleware

### 4. routes/web_content.php

**Назначение:** Динамические контентные маршруты

**Статус:** **Плоская маршрутизация удалена**

**Примечание:** Плоский роутинг `GET /{slug}` на основе поля `slug` модели `Entry` был удалён в рамках подготовки к переходу на иерархическую систему роутинга через `route_nodes`. Контентные маршруты будут реализованы для новой иерархической системы.

### 5. routes/console.php

**Назначение:** Консольные команды и расписание задач

**Содержимое:**

- `php artisan inspire` — пример команды
- Расписание: ежедневная очистка истёкших refresh токенов в 02:00

### 6. routes/web.php

**Статус:** **Не используется** (оставлен для обратной совместимости)

**Примечание:** Все роуты перенесены в `web_core.php`. Файл содержит только комментарий с инструкциями.

---

## Middleware

### Глобальные middleware

Настраиваются в `bootstrap/app.php`:

#### CanonicalUrl

**Файл:** `app/Http/Middleware/CanonicalUrl.php`

**Назначение:** Канонизация URL публичных страниц

**Применение:** Глобально (prepend) ко всем HTTP-запросам

**Функции:**

- Приведение к нижнему регистру: `/About` → `/about`
- Удаление завершающего слэша: `/about/` → `/about`
- Сохранение query string

**Исключения:**

- Системные пути: `admin`, `api`, `auth`, `login`, `logout`, `register`
- Выполняется ДО роутинга, поэтому работает даже для несуществующих путей

**Пример:**

```php
// Запрос: GET /About?page=1
// Редирект: 301 → /about?page=1
```

#### EncryptCookies

**Исключения:**

- `cms_at` — JWT access token cookie
- `cms_rt` — JWT refresh token cookie
- `cms_csrf` — CSRF token cookie

Эти cookies не шифруются, так как они уже подписаны/защищены другими механизмами.

### Группы middleware

#### web

**Применение:** `web_core.php` (ранее также `web_content.php`)

**Включает:**

- EncryptCookies
- StartSession
- ShareErrorsFromSession
- VerifyCsrfToken (для state-changing запросов)
- SubstituteBindings

**Особенности:**

- CSRF защита для POST/PUT/PATCH/DELETE
- Сессии для веб-запросов
- Поддержка Blade views

#### api

**Применение:** `api.php`, `api_admin.php`

**Включает:**

- EncryptCookies (с исключениями)
- ThrottleApi (120 запросов/минуту)
- CORS (если настроен)
- VerifyApiCsrf (дополнительно)
- AddCacheVary (дополнительно)

**Особенности:**

- Stateless (без сессий)
- CSRF проверка для state-changing запросов (кроме auth endpoints)
- Rate limiting на уровне группы

### Специализированные middleware

#### JwtAuth

**Файл:** `app/Http/Middleware/JwtAuth.php`

**Назначение:** JWT аутентификация

**Применение:** Все админские API эндпоинты

**Функции:**

- Извлечение access token из cookie `cms_at`
- Валидация токена (подпись, срок действия)
- Проверка существования пользователя в БД
- Установка аутентифицированного пользователя

**Ошибки:**

- `missing_token` — токен отсутствует
- `invalid_token` — токен невалиден
- `invalid_subject` — невалидный subject claim
- `user_not_found` — пользователь не найден

**Ответ:** 401 Unauthorized с заголовками `WWW-Authenticate: Bearer`, `Pragma: no-cache`

#### OptionalJwtAuth

**Файл:** `app/Http/Middleware/OptionalJwtAuth.php`

**Назначение:** Опциональная JWT аутентификация

**Применение:** Публичные эндпоинты, где админы имеют расширенные права

**Функции:**

- Аналогично `JwtAuth`, но не выбрасывает исключение при отсутствии токена
- Устанавливает пользователя, если токен валиден
- Позволяет анонимный доступ

#### VerifyApiCsrf

**Файл:** `app/Http/Middleware/VerifyApiCsrf.php`

**Назначение:** Проверка CSRF токена для state-changing API запросов

**Применение:** Группа `api` (append)

**Функции:**

- Проверка CSRF токена из заголовка `X-CSRF-Token` или `X-XSRF-TOKEN`
- Сравнение с cookie `cms_csrf` (timing-safe через `hash_equals`)
- Применяется только к POST/PUT/PATCH/DELETE
- Исключает `api.auth.login`, `api.auth.refresh`, `api.auth.logout`

**Ошибки:**

- 419 CSRF Token Mismatch
- При ошибке выдаёт новый CSRF токен в cookie для восстановления клиента

#### RejectReservedIfMatched

**Статус:** **Удалён** (легаси-код)

**Примечание:** Middleware был частью плоской маршрутизации `GET /{slug}`, которая была удалена. Будет пересоздан для иерархической системы роутинга при необходимости.

#### AddCacheVary

**Файл:** `app/Http/Middleware/AddCacheVary.php`

**Назначение:** Добавление заголовков Vary для кэширования

**Применение:** Группа `api` (append)

**Функции:**

- Добавляет `Vary: Origin, Cookie` для правильного кэширования API ответов с cookies
- Применяется после CORS и CSRF middleware

#### NoCacheAuth

**Назначение:** Предотвращение кэширования ответов аутентификации

**Применение:** Auth endpoints (`/auth/login`, `/auth/refresh`, `/auth/current`)

**Функции:**

- Устанавливает `Cache-Control: no-store, no-cache, must-revalidate`
- Предотвращает кэширование токенов и данных пользователя

---

## Система резервирования путей

### Обзор

Система резервирования путей предотвращает конфликты между системными/плагинными маршрутами и контентными страницами.

### Компоненты

#### 1. ReservedPattern

**Файл:** `app/Domain/Routing/ReservedPattern.php`

**Назначение:** Генерация regex паттерна для исключения зарезервированных путей

**Метод:** `ReservedPattern::slugRegex()`

**Источники зарезервированных путей:**

1. **Статические пути** из `config('stupidcms.reserved_routes.paths')`
2. **Префиксы** из `config('stupidcms.reserved_routes.prefixes')`
3. **Динамические резервации** из БД (`reserved_routes` таблица)

**Алгоритм:**

1. Собирает все зарезервированные первые сегменты
2. Нормализует к lowercase
3. Строит негативный lookahead: `(?!^(?:admin|api|...)$)`
4. Объединяет с базовым паттерном slug

**Пример результата:**

```regex
^(?!^(?:admin|api|auth)$)[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$
```

#### 2. PathReservationService

**Интерфейс:** `app/Domain/Routing/PathReservationService.php`

**Реализация:** `app/Domain/Routing/PathReservationServiceImpl.php`

**Назначение:** Управление динамическими резервациями путей

**Методы:**

- `reservePath(string $path, string $source, ?string $reason): void` — зарезервировать путь
- `releasePath(string $path, string $source): void` — освободить путь
- `releaseBySource(string $source): int` — освободить все пути источника
- `isReserved(string $path): bool` — проверить резервацию
- `ownerOf(string $path): ?string` — получить владельца пути

**Особенности:**

- Работает только с путями из конфига (kind='path')
- Таблица `reserved_routes` используется для fallback-роутера и валидации slug'ов
- Поддерживает статические пути из конфига

#### 3. ReservedRoute (модель)

**Файл:** `app/Models/ReservedRoute.php`

**Таблица:** `reserved_routes`

**Поля:**

- `id` — первичный ключ
- `path` — нормализованный путь (lowercase, без trailing slash)
- `kind` — тип: `'path'` (точное совпадение) или `'prefix'` (префикс)
- `source` — источник резервации (например, `'system:admin'`, `'plugin:example'`)
- `created_at`, `updated_at` — временные метки

**Особенности:**

- Автоматическая нормализация пути при установке
- STORED-колонка `path_norm` для регистронезависимой уникальности (MySQL/PostgreSQL)
- Уникальный индекс на `path_norm`

#### 4. ReservedRouteRegistry

**Назначение:** Объединение зарезервированных путей из конфига и БД

**Использование:** Будет использоваться для валидации путей в иерархической системе роутинга

#### 5. ReservedSlug (правило валидации)

**Файл:** `app/Rules/ReservedSlug.php`

**Назначение:** Валидация путей на конфликты с зарезервированными путями (будет использоваться для иерархической системы)

**Проверки:**

- Для `kind='path'`: точное совпадение
- Для `kind='prefix'`: совпадение или начало с префикса

### Конфигурация

**Файл:** `config/stupidcms.php`

```php
'reserved_routes' => [
    'paths' => [
        'admin', // строгое совпадение для "/admin"
    ],
    'prefixes' => [
        'admin', // префикс для "/admin/*"
        'api',   // префикс для "/api/*"
    ],
],
```

### Использование

#### Резервирование пути плагином

```php
$pathReservationService = app(PathReservationService::class);
$pathReservationService->reservePath('/my-plugin', 'plugin:my-plugin', 'Plugin routes');
```

#### Освобождение пути

```php
$pathReservationService->releasePath('/my-plugin', 'plugin:my-plugin');
```

#### Проверка резервации

```php
if ($pathReservationService->isReserved('/my-slug')) {
    // Путь зарезервирован
}
```

#### Валидация путей (для будущей иерархической системы)

```php
use App\Rules\ReservedSlug;

// Пример для будущей системы
$request->validate([
    'path' => ['required', 'string', new ReservedSlug()],
]);
```

---

## Плагины и их роутинг

### Архитектура

**Статус:** Система плагинов удалена.

**Порядок загрузки:**

1. Получает список включённых плагинов из `PluginRegistry`
2. Регистрирует классы плагинов через `PluginAutoloader`
3. Регистрирует Service Providers плагинов

**Особенности:**

- Проверяет существование класса провайдера перед регистрацией
- Плагины загружаются после Admin API, но до Content routes

### Структура плагина

**Пример:** `plugins/example/`

```
plugins/example/
├── plugin.json          # Метаданные плагина
├── routes/
│   └── plugin.php       # Роуты плагина
└── src/
    └── ExamplePluginServiceProvider.php
```

**plugin.json:**

```json
{
    "slug": "example",
    "name": "Example Plugin",
    "version": "1.0.0",
    "provider": "Plugins\\Example\\ExamplePluginServiceProvider",
    "routes": [
        "routes/plugin.php"
    ]
}
```

### Регистрация роутов плагина

**Пример:** `plugins/example/src/ExamplePluginServiceProvider.php`

```php
public function boot(): void
{
    Route::middleware('web')
        ->prefix('example')
        ->group(__DIR__ . '/../../routes/plugin.php');
}
```

### Перезагрузка роутов плагинов

**Сервис:** `app/Domain/Plugins/Services/PluginsRouteReloader.php`

**Процесс:**

1. Очищает кэш роутов (`route:clear`)
2. Регистрирует автозагрузку для включённых плагинов
3. Регистрирует Service Providers плагинов
4. Кэширует роуты (если включено)
5. Отправляет событие `PluginsRoutesReloaded`

**Использование:**

- При включении/отключении плагина
- При синхронизации плагинов (`php artisan plugins:sync`)

### Резервирование путей плагинами

Плагины могут резервировать пути через `PathReservationService`:

```php
// В Service Provider плагина
public function boot(): void
{
    $pathReservationService = app(PathReservationService::class);
    $pathReservationService->reservePath('/my-plugin', 'plugin:my-plugin', 'Plugin routes');
    
    // Регистрация роутов...
}

public function register(): void
{
    // Освобождение при отключении
    $this->app->terminating(function () {
        $pathReservationService = app(PathReservationService::class);
        $pathReservationService->releaseBySource('plugin:my-plugin');
    });
}
```

---

## Fallback механизм

### FallbackController

**Файл:** `app/Http/Controllers/FallbackController.php`

**Назначение:** Обработка всех несовпавших запросов (404)

**Регистрация:**

```php
// В RouteServiceProvider
Route::fallback(FallbackController::class); // GET, HEAD
Route::match(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '{any?}', FallbackController::class)
    ->where('any', '.*')
    ->fallback();
```

**Особенности:**

- **НЕ** использует `web` middleware (иначе POST на несуществующий путь получит 419 CSRF вместо 404)
- Регистрируется для всех HTTP методов отдельно
- Определяет формат ответа по типу запроса (JSON/HTML)

**Логика:**

1. Логирует информацию о запросе (path, method, referer, accept, user_agent, ip)
2. Если запрос ожидает JSON или является API (`expectsJson() || is('api/*') || wantsJson()`):
   - Возвращает JSON с ошибкой `NOT_FOUND`
   - Использует `ErrorResponseFactory` для форматирования
3. Иначе:
   - Возвращает HTML view `errors.404`
   - Статус: 404 Not Found

**Пример ответа JSON:**

```json
{
    "error": {
        "code": "NOT_FOUND",
        "detail": "The requested resource was not found.",
        "meta": {
            "path": "/non-existent"
        }
    }
}
```

### Порядок обработки

1. Запрос проходит через все зарегистрированные роуты
2. Если ни один роут не совпал, запрос попадает в `FallbackController`
3. Контроллер определяет формат ответа и возвращает соответствующий 404

---

## Rate Limiting

### Настройка

Все rate limiters настраиваются в `RouteServiceProvider::boot()`.

### Доступные limiters

#### api

**Лимит:** 120 запросов в минуту

**Идентификатор:** `user_id` (если аутентифицирован) или `ip`

**Применение:** Все API эндпоинты по умолчанию

```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
});
```

#### login

**Лимит:** 10 попыток в минуту

**Идентификатор:** `email|ip` (комбинация email и IP)

**Применение:** `POST /api/v1/auth/login`

```php
RateLimiter::for('login', function (Request $request) {
    $key = 'login:'.Str::lower($request->input('email')).'|'.$request->ip();
    return Limit::perMinute(10)->by($key);
});
```

#### refresh

**Лимит:** 20 попыток в минуту

**Идентификатор:** `hash(refresh_token_cookie|ip)` (xxh128 или sha256)

**Применение:** `POST /api/v1/auth/refresh`

```php
RateLimiter::for('refresh', function (Request $request) {
    $refreshToken = (string) $request->cookie(config('jwt.cookies.refresh'), '');
    $algo = in_array('xxh128', hash_algos(), true) ? 'xxh128' : 'sha256';
    $key = hash($algo, $refreshToken . '|' . $request->ip());
    return Limit::perMinute(20)->by($key);
});
```

#### search-public

**Лимит:** 240 запросов в минуту

**Идентификатор:** `ip`

**Применение:** `GET /api/v1/search`

```php
RateLimiter::for('search-public', function (Request $request) {
    return Limit::perMinute(240)->by($request->ip());
});
```

#### search-reindex

**Лимит:** 10 запросов в минуту

**Идентификатор:** `user_id` (если аутентифицирован) или `ip`

**Применение:** `POST /api/v1/admin/search/reindex`

```php
RateLimiter::for('search-reindex', function (Request $request) {
    $identifier = $request->user()?->getAuthIdentifier();
    return Limit::perMinute(10)->by($identifier ?: $request->ip());
});
```

### Использование в роутах

```php
// Применение к конкретному роуту
Route::post('/auth/login', [LoginController::class, 'login'])
    ->middleware('throttle:login');

// Применение к группе
Route::middleware(['jwt.auth', 'throttle:api'])->group(function () {
    // ...
});

// Кастомный лимит
Route::get('/media/config', [MediaController::class, 'config'])
    ->middleware('throttle:60,1'); // 60 запросов в минуту
```

### Ответ при превышении лимита

**Статус:** 429 Too Many Requests

**Заголовки:**

- `X-RateLimit-Limit` — максимальное количество запросов
- `X-RateLimit-Remaining` — оставшееся количество запросов
- `Retry-After` — секунды до сброса лимита

---

## Безопасность

### CSRF защита

#### Веб-запросы (web middleware)

- Автоматическая защита для всех state-changing запросов (POST, PUT, PATCH, DELETE)
- Токен передаётся через:
  - Meta tag: `<meta name="csrf-token" content="{{ csrf_token() }}">`
  - Header: `X-CSRF-TOKEN` или `X-XSRF-TOKEN`
- Проверка через `VerifyCsrfToken` middleware

#### API запросы

- CSRF проверка через `VerifyApiCsrf` middleware
- Исключения:
  - `api.auth.login` — credentials-based auth
  - `api.auth.refresh` — credentials-based auth
  - `api.auth.logout` — использует JWT auth
- Токен передаётся через заголовок `X-CSRF-Token` или `X-XSRF-TOKEN`
- Cookie `cms_csrf` устанавливается при первом запросе

**Порядок middleware для API:**

```
CORS → CSRF → Vary → Auth
```

### JWT аутентификация

#### Access Token

- Хранится в cookie `cms_at`
- Не шифруется (уже подписан)
- Используется для аутентификации в админских эндпоинтах
- Проверяется через `JwtAuth` middleware

#### Refresh Token

- Хранится в cookie `cms_rt`
- Не шифруется (уже подписан)
- Используется для обновления access token
- Проверяется через `RefreshController`

#### Опциональная аутентификация

- `OptionalJwtAuth` middleware позволяет анонимный доступ
- Если токен валиден, устанавливает пользователя
- Используется для публичных эндпоинтов с расширенными правами для админов

### Авторизация

#### Policies

Используются для проверки прав доступа к ресурсам:

```php
Route::get('/entries', [EntryController::class, 'index'])
    ->middleware('can:viewAny,' . Entry::class);
```

#### Custom Abilities

Кастомные способности для специфичных операций:

```php
Route::post('/plugins/sync', [PluginsController::class, 'sync'])
    ->middleware('can:plugins.sync');
```

#### Middleware для управления

```php
Route::middleware('can:manage.taxonomies')->group(function () {
    // Роуты для управления таксономиями
});
```

### CORS

Настраивается в `config/cors.php` (если используется).

**Требования для кросс-сайтовых запросов:**

- `SameSite=None; Secure` для cookies
- `credentials: true` в CORS конфигурации
- Правильные заголовки `Access-Control-Allow-Origin`, `Access-Control-Allow-Credentials`

---

## Канонизация URL

### CanonicalUrl Middleware

**Файл:** `app/Http/Middleware/CanonicalUrl.php`

**Применение:** Глобально (prepend) ко всем HTTP-запросам

**Функции:**

1. **Приведение к нижнему регистру:** `/About` → `/about`
2. **Удаление trailing slash:** `/about/` → `/about`
3. **Сохранение query string:** `/About?page=1` → `/about?page=1`

**Исключения:**

- Системные пути: `admin`, `api`, `auth`, `login`, `logout`, `register`
- Определяется по первому сегменту пути

**Алгоритм:**

1. Получает оригинальный путь из `REQUEST_URI`
2. Проверяет, является ли путь системным
3. Нормализует путь (lowercase, trim trailing slash)
4. Если путь изменился, выполняет 301 редирект

**Примеры:**

```
GET /About        → 301 → /about
GET /about/       → 301 → /about
GET /About?p=1    → 301 → /about?p=1
GET /admin        → (без изменений)
GET /api/v1/auth  → (без изменений)
```

**Важно:**

- Выполняется **ДО роутинга**, поэтому работает даже для несуществующих путей
- Это гарантирует, что все запросы к публичным страницам приходят в каноническом виде

---

## Примеры использования

### Создание нового публичного API эндпоинта

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::get('/public-data', [PublicDataController::class, 'index'])
        ->middleware('throttle:search-public')
        ->name('api.v1.public-data');
});
```

### Создание нового админского эндпоинта

```php
// routes/api_admin.php
Route::middleware(['jwt.auth', 'throttle:api'])->group(function () {
    Route::get('/custom-resource', [CustomResourceController::class, 'index'])
        ->middleware('can:viewAny,' . CustomResource::class)
        ->name('admin.v1.custom-resource.index');
    
    Route::post('/custom-resource', [CustomResourceController::class, 'store'])
        ->middleware('can:create,' . CustomResource::class)
        ->name('admin.v1.custom-resource.store');
});
```

### Создание контентного маршрута

```php
// routes/web_content.php
// Плоская маршрутизация удалена
// Будет реализована иерархическая система через route_nodes
```

### Резервирование пути для плагина

```php
// plugins/my-plugin/src/MyPluginServiceProvider.php
public function boot(): void
{
    $pathReservationService = app(PathReservationService::class);
    
    // Резервируем путь
    try {
        $pathReservationService->reservePath('/my-plugin', 'plugin:my-plugin', 'Plugin routes');
    } catch (PathAlreadyReservedException $e) {
        // Путь уже зарезервирован
    }
    
    // Регистрируем роуты
    Route::middleware('web')
        ->prefix('my-plugin')
        ->group(__DIR__ . '/../../routes/plugin.php');
}

public function register(): void
{
    // Освобождаем путь при отключении плагина
    $this->app->terminating(function () {
        $pathReservationService = app(PathReservationService::class);
        $pathReservationService->releaseBySource('plugin:my-plugin');
    });
}
```

### Добавление зарезервированного пути в конфиг

```php
// config/stupidcms.php
return [
    'reserved_routes' => [
        'paths' => [
            'admin',
            'my-reserved-path', // Новый зарезервированный путь
        ],
        'prefixes' => [
            'admin',
            'api',
            'my-prefix', // Новый зарезервированный префикс
        ],
    ],
];
```

**Важно:** После изменения конфига необходимо очистить кэш роутов:

```bash
php artisan route:clear
php artisan route:cache
```

---

## Заключение

Система роутинга в приложении обеспечивает:

1. **Детерминированный порядок загрузки** — предотвращает конфликты между роутами
2. **Гибкую систему резервирования путей** — защищает системные маршруты от конфликтов
3. **Многоуровневую безопасность** — CSRF, JWT, rate limiting, авторизация
4. **Поддержку плагинов** — динамическая загрузка роутов плагинов
5. **Канонизацию URL** — единообразие публичных URL
6. **Корректную обработку ошибок** — fallback для 404 с правильным форматированием

Все компоненты работают вместе для обеспечения надёжной и безопасной маршрутизации в headless CMS.

---

**Дата создания:** 2025-12-05  
**Версия:** 1.0  
**Автор:** Автоматически сгенерировано на основе анализа кодовой базы

