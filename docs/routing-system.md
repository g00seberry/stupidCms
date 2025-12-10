# Система роутинга приложения

Исчерпывающее описание архитектуры и работы системы роутинга в headless CMS.

**Дата создания:** 2025-12-09  
**Версия Laravel:** 12  
**Версия PHP:** 8.3+

---

## Содержание

1. [Архитектура роутинга](#архитектура-роутинга)
2. [Порядок загрузки роутов](#порядок-загрузки-роутов)
3. [Файлы роутов](#файлы-роутов)
4. [Middleware](#middleware)
5. [Rate Limiting](#rate-limiting)
6. [Авторизация и политики](#авторизация-и-политики)
7. [Обработка ошибок](#обработка-ошибок)
8. [Особенности реализации](#особенности-реализации)

---

## Архитектура роутинга

Система роутинга построена на Laravel 12 с использованием `RouteServiceProvider` для детерминированного порядка загрузки маршрутов.

### Основные принципы

1. **Детерминированный порядок загрузки** — роуты загружаются в строго определённой последовательности для предотвращения конфликтов
2. **Разделение по назначению** — роуты разделены на группы: core, public API, admin API, content, fallback
3. **Middleware-first** — безопасность и обработка запросов на уровне middleware
4. **API-first подход** — приоритет отдаётся API endpoints, веб-роуты используются только для служебных задач

### Компоненты системы

```
bootstrap/app.php
  └── RouteServiceProvider (app/Providers/RouteServiceProvider.php)
      ├── routes/web_core.php          (системные маршруты)
      ├── routes/api.php                (публичные API)
      ├── routes/api_admin.php          (админские API)
      ├── routes/web_content.php       (контентные маршруты)
      └── FallbackController           (обработка 404)
```

---

## Порядок загрузки роутов

Роуты загружаются в строго определённом порядке в методе `RouteServiceProvider::boot()`:

### 1. Core Routes (`routes/web_core.php`)

**Порядок:** Первый  
**Middleware:** `web`  
**Префикс:** нет

**Назначение:**
- Системные маршруты, которые должны обрабатываться до всех остальных
- Главная страница `/`
- Статические сервисные пути (health, feed, sitemap — закомментированы)
- Тестовые маршруты (только в `testing` окружении)

**Примеры:**
- `GET /` → `HomeController`
- `GET /admin/ping` → `AdminPingController::ping` (только testing)

**Критичность:** Должны быть первыми, чтобы не перехватывались catch-all маршрутами.

### 2. Public API Routes (`routes/api.php`)

**Порядок:** Второй  
**Middleware:** `api`  
**Префикс:** `/api`

**Назначение:**
- Публичные API endpoints, доступные без аутентификации
- Аутентификация (login, refresh, logout)
- Публичный доступ к медиа-файлам

**Структура:**
```
/api/v1/
  ├── POST /auth/login          (аутентификация)
  ├── POST /auth/refresh        (обновление токена)
  ├── POST /auth/logout         (выход, требует JWT)
  └── GET  /media/{id}          (публичный доступ к медиа)
```

**Безопасность:**
- Rate limiting для каждого endpoint отдельно
- CSRF исключён для `login` и `refresh` (credentials-based)
- JWT auth для `logout` и опционально для `media`

### 3. Admin API Routes (`routes/api_admin.php`)

**Порядок:** Третий  
**Middleware:** `api` + `jwt.auth` + `throttle:api`  
**Префикс:** `/api/v1/admin`

**Назначение:**
- Административные API endpoints
- Полный CRUD для всех сущностей CMS
- Управление контентом, медиа, blueprints, таксономиями

**Критичность:** Должны быть загружены ДО контентных маршрутов, чтобы `/api/v1/admin/*` не перехватывались catch-all.

**Структура:**
```
/api/v1/admin/
  ├── GET    /auth/current                    (текущий пользователь)
  ├── GET    /utils/slugify                   (утилиты)
  │
  ├── Templates (CRUD)
  ├── POST   /templates
  ├── GET    /templates
  ├── GET    /templates/{name}
  ├── PUT    /templates/{name}
  │
  ├── Post Types (CRUD + Form Configs)
  ├── POST   /post-types
  ├── GET    /post-types
  ├── GET    /post-types/{id}
  ├── PUT    /post-types/{id}
  ├── DELETE /post-types/{id}
  ├── GET    /post-types/{id}/form-config/{blueprint}
  ├── PUT    /post-types/{id}/form-config/{blueprint}
  ├── DELETE /post-types/{id}/form-config/{blueprint}
  ├── GET    /post-types/{id}/form-configs
  │
  ├── Entries (CRUD + soft-delete)
  ├── GET    /entries/statuses
  ├── GET    /entries
  ├── POST   /entries
  ├── GET    /entries/{id}
  ├── PUT    /entries/{id}
  ├── DELETE /entries/{id}
  ├── POST   /entries/{id}/restore
  │
  ├── Taxonomies (CRUD)
  ├── GET    /taxonomies
  ├── POST   /taxonomies
  ├── GET    /taxonomies/{id}
  ├── PUT    /taxonomies/{id}
  ├── DELETE /taxonomies/{id}
  │
  ├── Terms (CRUD + tree)
  ├── GET    /taxonomies/{taxonomy}/terms/tree
  ├── GET    /taxonomies/{taxonomy}/terms
  ├── POST   /taxonomies/{taxonomy}/terms
  ├── GET    /terms/{term}
  ├── PUT    /terms/{term}
  ├── DELETE /terms/{term}
  │
  ├── Entry Terms (связи)
  ├── GET    /entries/{entry}/terms
  ├── PUT    /entries/{entry}/terms/sync
  │
  ├── Media (CRUD + bulk operations)
  ├── GET    /media/config
  ├── GET    /media
  ├── GET    /media/{media}
  ├── POST   /media
  ├── POST   /media/bulk
  ├── PUT    /media/{media}
  ├── DELETE /media/bulk
  ├── POST   /media/bulk/restore
  ├── DELETE /media/bulk/force
  │
  ├── Blueprints (CRUD + dependencies)
  ├── GET    /blueprints
  ├── POST   /blueprints
  ├── GET    /blueprints/{blueprint}
  ├── PUT    /blueprints/{blueprint}
  ├── DELETE /blueprints/{blueprint}
  ├── GET    /blueprints/{blueprint}/can-delete
  ├── GET    /blueprints/{blueprint}/dependencies
  ├── GET    /blueprints/{blueprint}/embeddable
  ├── GET    /blueprints/{blueprint}/schema
  │
  ├── Paths (CRUD)
  ├── GET    /blueprints/{blueprint}/paths
  ├── POST   /blueprints/{blueprint}/paths
  ├── GET    /paths/{path}
  ├── PUT    /paths/{path}
  ├── DELETE /paths/{path}
  │
  └── Embeds (CRUD)
      ├── GET    /blueprints/{blueprint}/embeds
      ├── POST   /blueprints/{blueprint}/embeds
      ├── GET    /embeds/{embed}
      └── DELETE /embeds/{embed}
```

**Авторизация:**
- Все маршруты требуют JWT аутентификации
- Используются политики доступа (Policies) для проверки прав
- Gate abilities для административных разрешений

### 4. Content Routes (`routes/web_content.php`)

**Порядок:** Четвёртый  
**Middleware:** `web`  
**Префикс:** нет

**Назначение:**
- Динамические контентные маршруты
- Иерархическая маршрутизация через `route_nodes` (планируется)
- Catch-all маршруты для публичного контента

**Текущее состояние:**
- Файл пуст (контентная маршрутизация в разработке)
- Плоская маршрутизация `/{slug}` удалена

**Особенности:**
- Middleware `CanonicalUrl` применяется глобально и выполняет 301 редиректы ДО роутинга
- Нормализация URL: `/About` → `/about`, `/about/` → `/about`

### 5. Fallback Routes

**Порядок:** Последний (строго)  
**Middleware:** нет (важно!)  
**Префикс:** нет

**Назначение:**
- Обработка всех несовпавших запросов (404)
- Поддержка всех HTTP методов (GET, HEAD, POST, PUT, PATCH, DELETE, OPTIONS)

**Реализация:**
```php
Route::fallback(FallbackController::class); // GET, HEAD
Route::match(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '{any?}', FallbackController::class)
    ->where('any', '.*')
    ->fallback();
```

**Критичность:**
- Fallback НЕ должен быть под `web` middleware, иначе POST на несуществующий путь получит 419 CSRF вместо 404
- Контроллер сам определяет формат ответа (HTML/JSON) по типу запроса

**Логирование:**
- Все 404 запросы логируются с деталями (path, method, referer, accept, user_agent, ip)

---

## Файлы роутов

### `routes/web_core.php`

Системные маршруты, загружаемые первыми.

**Middleware:** `web` (CSRF защита, сессии)

**Маршруты:**
- `GET /` → `HomeController::__invoke()` (name: `home`)
- `GET /admin/ping` → `AdminPingController::ping()` (только testing)
- `GET /test/admin/entries` → closure (только testing, проверка авторизации)

**Закомментированные (примеры):**
- `/health` — health check
- `/feed.xml` — RSS feed
- `/sitemap.xml` — sitemap

### `routes/api.php`

Публичные API endpoints.

**Middleware:** `api` (stateless, без CSRF по умолчанию)

**Структура:**
```php
Route::prefix('v1')->group(function () {
    // Auth endpoints
    Route::post('/auth/login', [LoginController::class, 'login'])
        ->name('api.auth.login')
        ->middleware(['throttle:login', 'no-cache-auth']);
    
    Route::post('/auth/refresh', [RefreshController::class, 'refresh'])
        ->name('api.auth.refresh')
        ->middleware(['throttle:refresh', 'no-cache-auth']);

    Route::post('/auth/logout', [LogoutController::class, 'logout'])
        ->name('api.auth.logout')
        ->middleware(['jwt.auth', 'throttle:login', 'no-cache-auth']);

    // Public media access
    Route::get('/media/{id}', [MediaPreviewController::class, 'show'])
        ->middleware(['jwt.auth.optional', 'throttle:api'])
        ->name('api.v1.media.show');
});
```

**Особенности:**
- CSRF проверка для state-changing операций через `VerifyApiCsrf` middleware
- Исключения: `login`, `refresh` (credentials-based, не требуют CSRF)
- `logout` использует JWT auth, CSRF избыточен

### `routes/api_admin.php`

Административные API endpoints.

**Middleware:** `api` + `jwt.auth` + `throttle:api` (глобально)

**Структура:**
- Все маршруты требуют JWT аутентификации
- Используются политики доступа через `can:` middleware
- Специфичные rate limiters для медиа (60/1, 20/1)

**Группировка:**
- Templates — полный CRUD
- Post Types — CRUD + Form Configs (требует `manage.posttypes`)
- Entries — CRUD + soft-delete/restore (требует `manage.entries`)
- Taxonomies — CRUD (требует `manage.taxonomies`)
- Terms — CRUD + tree (требует `manage.terms`)
- Entry Terms — связи (требует `manage.terms`)
- Media — CRUD + bulk operations (требует `media.*` permissions)
- Blueprints — CRUD + dependencies/embeddable/schema
- Paths — CRUD (вложенные и глобальные)
- Embeds — CRUD (вложенные и глобальные)

### `routes/web_content.php`

Контентные маршруты (в разработке).

**Middleware:** `web` (CSRF защита, сессии)

**Текущее состояние:** Пуст (контентная маршрутизация планируется через `route_nodes`)

### `routes/web.php`

**Статус:** Не используется (оставлен для обратной совместимости)

**Примечание:** Все роуты перенесены в `routes/web_core.php`.

### `routes/console.php`

Консольные команды и scheduled tasks.

**Содержимое:**
- `inspire` — пример команды
- `auth:cleanup-tokens` — ежедневная очистка истёкших refresh токенов (02:00)

---

## Middleware

### Глобальные Middleware

Настраиваются в `bootstrap/app.php`:

#### 1. `CanonicalUrl` (prepend)

**Порядок:** Первый (prepend)  
**Применение:** Глобально ко всем HTTP-запросам

**Функции:**
- Нормализация URL: `/About` → `/about` (нижний регистр)
- Удаление trailing slash: `/about/` → `/about`
- 301 редирект при необходимости
- Сохранение query string

**Исключения:**
- Системные пути: `admin`, `api`, `auth`, `login`, `logout`, `register`

**Реализация:** `app/Http/Middleware/CanonicalUrl.php`

#### 2. Cookie Encryption

**Исключения:**
- `cms_at` — JWT access token cookie
- `cms_rt` — JWT refresh token cookie
- `cms_csrf` — CSRF token cookie

**Причина:** Эти cookies должны быть доступны JavaScript для отправки в заголовках.

### Middleware Groups

#### `web` Group

**Применение:** `routes/web_core.php`, `routes/web_content.php`

**Стандартные middleware:**
- Encrypt cookies
- Start session
- Share errors
- CSRF protection (Laravel default)

**Особенности:**
- Stateful (сессии)
- CSRF защита для всех state-changing операций

#### `api` Group

**Применение:** `routes/api.php`, `routes/api_admin.php`

**Стандартные middleware:**
- Throttle API (120 запросов/минуту)
- Encrypt cookies (с исключениями)

**Дополнительные middleware (порядок):**
1. `VerifyApiCsrf` — проверка CSRF для state-changing операций
2. `AddCacheVary` — добавление Vary заголовков для ответов с cookies

**Особенности:**
- Stateless (без сессий)
- CSRF проверка только для POST/PUT/PATCH/DELETE
- Исключения: `api.auth.login`, `api.auth.refresh`, `api.auth.logout`

### Кастомные Middleware

#### `jwt.auth`

**Класс:** `App\Http\Middleware\JwtAuth`  
**Алиас:** `jwt.auth`

**Функции:**
- Извлечение JWT access token из cookie `cms_at`
- Валидация токена (подпись, срок действия)
- Проверка существования пользователя в БД
- Установка аутентифицированного пользователя в guard

**Ошибки:**
- `missing_token` — токен отсутствует
- `invalid_token` — токен невалиден
- `invalid_subject` — невалидный subject claim
- `user_not_found` — пользователь не найден

**Ответ:** 401 Unauthorized с заголовками:
- `WWW-Authenticate: Bearer`
- `Pragma: no-cache`

**Использование:**
- Все маршруты в `routes/api_admin.php`
- `POST /api/v1/auth/logout`

#### `jwt.auth.optional`

**Класс:** `App\Http\Middleware\OptionalJwtAuth`  
**Алиас:** `jwt.auth.optional`

**Функции:**
- Аналогично `jwt.auth`, но не выбрасывает ошибку при отсутствии/невалидности токена
- Пропускает запрос дальше без установки пользователя

**Использование:**
- `GET /api/v1/media/{id}` — публичный доступ с опциональной аутентификацией (для доступа к удалённым файлам)

#### `no-cache-auth`

**Класс:** `App\Http\Middleware\NoCacheAuth`  
**Алиас:** `no-cache-auth`

**Функции:**
- Добавляет заголовок `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`
- Предотвращает кэширование ответов аутентификации

**Использование:**
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/refresh`
- `POST /api/v1/auth/logout`
- `GET /api/v1/admin/auth/current`

#### `VerifyApiCsrf`

**Класс:** `App\Http\Middleware\VerifyApiCsrf`

**Функции:**
- Проверка CSRF токена для state-changing API запросов (POST, PUT, PATCH, DELETE)
- Сравнение заголовка `X-CSRF-Token` или `X-XSRF-TOKEN` со значением CSRF cookie
- Timing-safe сравнение (`hash_equals`)

**Исключения:**
- Idempotent методы (GET, HEAD, OPTIONS)
- Preflight requests (OPTIONS с `Access-Control-Request-Method`)
- Маршруты: `api.auth.login`, `api.auth.refresh`, `api.auth.logout`

**Ошибка:** 419 CSRF Token Mismatch с новым CSRF токеном в cookie

**Порядок:** После CORS, перед auth

#### `AddCacheVary`

**Класс:** `App\Http\Middleware\AddCacheVary`

**Функции:**
- Добавляет заголовки `Vary: Origin, Cookie` к ответам, которые устанавливают cookies
- Обеспечивает корректное поведение кэша при наличии cookies

**Порядок:** После CORS и CSRF

#### `EnsureCanManagePostTypes`

**Класс:** `App\Http\Middleware\EnsureCanManagePostTypes`

**Функции:**
- Проверка права `manage.posttypes` через Gate
- Выбрасывает `AuthorizationException` при отсутствии права

**Использование:**
- Все маршруты Post Types и Form Configs

---

## Rate Limiting

Rate limiting настраивается в `RouteServiceProvider::boot()` через `RateLimiter::for()`.

### Rate Limiters

#### `api`

**Лимит:** 120 запросов в минуту  
**Ключ:** `user_id` (если аутентифицирован) или `ip` (если нет)

**Использование:**
- Все маршруты в `api` middleware group
- `GET /api/v1/media/{id}`

**Настройка:** `bootstrap/app.php` → `$middleware->throttleApi()`

#### `login`

**Лимит:** 10 попыток в минуту  
**Ключ:** `login:{email}|{ip}` (lowercase email + IP)

**Использование:**
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`

**Цель:** Защита от brute-force атак на аутентификацию

#### `refresh`

**Лимит:** 20 попыток в минуту  
**Ключ:** `hash({refresh_token}|{ip})` (xxh128 или sha256)

**Использование:**
- `POST /api/v1/auth/refresh`

**Особенности:**
- Использует хэш refresh token + IP для более точной идентификации
- Помогает избежать ложных блокировок за NAT
- Ловит автоматизированные атаки

### Специфичные Rate Limiters

#### Media Endpoints

**Чтение (GET):**
- `throttle:60,1` — 60 запросов в минуту
- Используется для: `GET /api/v1/admin/media/config`, `index`, `show`

**Запись (POST/PUT/DELETE):**
- `throttle:20,1` — 20 запросов в минуту
- Используется для: `POST /api/v1/admin/media`, `bulkStore`, `update`, `bulkDestroy`, `bulkRestore`, `bulkForceDestroy`

**Цель:** Защита от злоупотреблений при работе с медиа-файлами

---

## Авторизация и политики

### Политики доступа (Policies)

Регистрируются в `AuthServiceProvider`:

#### `EntryPolicy`

**Модель:** `App\Models\Entry`

**Методы:**
- `viewAny()` — требует `manage.entries`
- `view()` — требует `manage.entries`
- `create()` — требует `manage.entries`
- `update()` — требует `manage.entries`
- `delete()` — требует `manage.entries`

**Использование:**
- `GET /api/v1/admin/entries` → `can:viewAny,Entry`
- `POST /api/v1/admin/entries` → `can:create,Entry`
- `GET /api/v1/admin/entries/{id}` → проверка в контроллере
- `PUT /api/v1/admin/entries/{id}` → проверка в контроллере
- `DELETE /api/v1/admin/entries/{id}` → проверка в контроллере

#### `MediaPolicy`

**Модель:** `App\Models\Media`

**Методы:**
- `viewAny()` — требует `media.read`
- `view()` — требует `media.read`
- `create()` — требует `media.create`
- `update()` — требует `media.update`
- `delete()` — требует `media.delete`
- `restore()` — требует `media.delete`

**Использование:**
- `GET /api/v1/admin/media` → `can:viewAny,Media`
- `POST /api/v1/admin/media` → `can:create,Media`
- `PUT /api/v1/admin/media/{media}` → проверка в контроллере
- Bulk операции → проверка в контроллере

#### `TermPolicy`

**Модель:** `App\Models\Term`

**Статус:** Все методы возвращают `false` (термы управляются через `EntryPolicy`)

**Причина:** Политика оставлена для совместимости с Laravel Gate, но реальная авторизация происходит через `manage.terms` Gate ability.

### Gate Abilities

Определяются в `AuthServiceProvider::boot()`:

#### `manage.posttypes`

**Проверка:** `$user->hasAdminPermission('manage.posttypes')`

**Использование:**
- Все маршруты Post Types и Form Configs
- Middleware: `EnsureCanManagePostTypes`

#### `manage.entries`

**Проверка:** `$user->hasAdminPermission('manage.entries')`

**Использование:**
- `EntryPolicy` методы
- `GET /api/v1/admin/entries/statuses`
- `GET /api/v1/admin/entries`
- `POST /api/v1/admin/entries`

#### `manage.taxonomies`

**Проверка:** `$user->hasAdminPermission('manage.taxonomies')`

**Использование:**
- Все маршруты Taxonomies (CRUD)

#### `manage.terms`

**Проверка:** `$user->hasAdminPermission('manage.terms')`

**Использование:**
- Все маршруты Terms и Entry Terms

#### `media.*`

**Abilities:**
- `media.read` — чтение медиа
- `media.create` — создание медиа
- `media.update` — обновление медиа
- `media.delete` — удаление/восстановление медиа

**Проверка:** `$user->hasAdminPermission('media.{action}')`

**Использование:**
- `MediaPolicy` методы

### Глобальный доступ администратора

**Реализация:** `Gate::before()` в `AuthServiceProvider`

**Логика:**
```php
Gate::before(function (User $user, string $ability) {
    return $user->is_admin ? true : null; // null => продолжить обычные проверки
});
```

**Эффект:** Пользователь с `is_admin=true` получает доступ ко всем операциям, минуя проверки политик и Gate abilities.

---

## Обработка ошибок

### Fallback Controller

**Класс:** `App\Http\Controllers\FallbackController`

**Назначение:** Обработка всех несовпавших запросов (404)

**Логика:**
1. Логирование деталей запроса (path, method, referer, accept, user_agent, ip)
2. Определение формата ответа:
   - JSON для API запросов (`expectsJson()`, `is('api/*')`, `wantsJson()`)
   - HTML для веб-запросов
3. Возврат соответствующего ответа:
   - JSON: RFC7807 problem+json с кодом `NOT_FOUND`
   - HTML: Blade view `errors.404`

**HTTP методы:** Поддерживает все методы (GET, HEAD, POST, PUT, PATCH, DELETE, OPTIONS)

**Важно:** Fallback НЕ должен быть под `web` middleware, иначе POST на несуществующий путь получит 419 CSRF вместо 404.

### Глобальная обработка исключений

**Настройка:** `bootstrap/app.php` → `withExceptions()`

**Логика:**
- Для API запросов (`expectsJson()` или `is('api/*')`):
  - `HttpErrorException` → RFC7807 problem+json
  - Другие исключения → стандартная обработка Laravel
- Для веб-запросов:
  - Стандартная обработка Laravel (HTML страницы ошибок)

---

## Особенности реализации

### 1. Детерминированный порядок загрузки

**Проблема:** Laravel проверяет роуты в порядке их регистрации. Если catch-all маршрут зарегистрирован раньше специфичных, он перехватит все запросы.

**Решение:** Строгий порядок загрузки в `RouteServiceProvider`:
1. Core (системные)
2. Public API
3. Admin API
4. Content (catch-all здесь)
5. Fallback (строго последний)

### 2. CSRF для API

**Проблема:** Традиционно API не требует CSRF (stateless), но при использовании cookies для JWT токенов нужна защита от CSRF атак.

**Решение:**
- `VerifyApiCsrf` middleware проверяет CSRF только для state-changing операций
- Исключения: `login`, `refresh` (credentials-based, не требуют CSRF)
- `logout` использует JWT auth, CSRF избыточен

### 3. Канонизация URL

**Проблема:** Разные варианты URL (`/About`, `/about`, `/about/`) должны приводиться к каноническому виду.

**Решение:**
- `CanonicalUrl` middleware применяется глобально (prepend)
- Выполняет 301 редирект ДО роутинга
- Исключает системные пути (`admin`, `api`, `auth`, etc.)

### 4. Fallback для всех HTTP методов

**Проблема:** `Route::fallback()` по умолчанию обрабатывает только GET/HEAD.

**Решение:**
```php
Route::fallback(FallbackController::class); // GET, HEAD
Route::match(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '{any?}', FallbackController::class)
    ->where('any', '.*')
    ->fallback();
```

### 5. Rate Limiting для refresh токенов

**Проблема:** Нужно ограничить частоту обновления токенов, но идентификация по IP может блокировать легитимных пользователей за NAT.

**Решение:**
- Ключ: `hash({refresh_token}|{ip})`
- Использует xxh128 (fallback sha256)
- Более точная идентификация клиента

### 6. Опциональная JWT аутентификация

**Проблема:** Некоторые публичные endpoints должны работать как для аутентифицированных, так и для неаутентифицированных пользователей.

**Решение:**
- `OptionalJwtAuth` middleware не выбрасывает ошибку при отсутствии/невалидности токена
- Устанавливает пользователя в guard, если токен валиден
- Используется для `GET /api/v1/media/{id}` (админы могут видеть удалённые файлы)

### 7. Cookie Encryption Exceptions

**Проблема:** JWT токены и CSRF токены должны быть доступны JavaScript для отправки в заголовках.

**Решение:**
- Исключения в `encryptCookies()`:
  - `cms_at` (JWT access token)
  - `cms_rt` (JWT refresh token)
  - `cms_csrf` (CSRF token)

### 8. Vary Headers для Cache

**Проблема:** Ответы с cookies могут различаться в зависимости от Origin и Cookie заголовков, что требует правильной настройки кэша.

**Решение:**
- `AddCacheVary` middleware добавляет `Vary: Origin, Cookie` к ответам, которые устанавливают cookies
- Обеспечивает корректное поведение кэша

---

## Схема обработки запроса

```
1. HTTP Request
   │
   ├─→ CanonicalUrl (prepend, глобально)
   │   └─→ 301 редирект при необходимости
   │
   ├─→ Cookie Encryption (исключения: cms_at, cms_rt, cms_csrf)
   │
   ├─→ Route Matching (порядок):
   │   ├─→ web_core.php (web middleware)
   │   ├─→ api.php (api middleware)
   │   ├─→ api_admin.php (api middleware)
   │   ├─→ web_content.php (web middleware)
   │   └─→ FallbackController (без middleware)
   │
   ├─→ Middleware Stack (для matched route):
   │   ├─→ CORS (если API)
   │   ├─→ VerifyApiCsrf (если API, state-changing)
   │   ├─→ AddCacheVary (если API)
   │   ├─→ JwtAuth / OptionalJwtAuth (если требуется)
   │   ├─→ Throttle (rate limiting)
   │   ├─→ NoCacheAuth (если auth endpoint)
   │   └─→ Authorization (Policies / Gates)
   │
   └─→ Controller Action
       └─→ Response
```

---

## Резюме

Система роутинга построена на принципах:

1. **Детерминированность** — строгий порядок загрузки предотвращает конфликты
2. **Безопасность** — многоуровневая защита (CSRF, JWT, rate limiting, авторизация)
3. **Гибкость** — разделение на группы по назначению упрощает поддержку
4. **Производительность** — оптимизированный порядок проверки маршрутов
5. **Масштабируемость** — легко добавлять новые группы маршрутов

Все компоненты документированы, протестированы и следуют принципам Laravel 12 и PSR-12.

---

**Последнее обновление:** 2025-12-09

