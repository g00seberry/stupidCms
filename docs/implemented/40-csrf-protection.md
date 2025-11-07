# Задача 40. CSRF-защита для API: cookie + заголовок

## Краткое описание функционала

Реализована CSRF-защита для state-changing API запросов (POST, PUT, PATCH, DELETE). Защита работает по принципу двойной отправки токена (double-submit cookie): сервер выдаёт CSRF токен в cookie (настраивается через `config/security.php`, НЕ HttpOnly для доступа через JavaScript), а клиент отправляет тот же токен в заголовке `X-CSRF-Token` или `X-XSRF-TOKEN`. Middleware сверяет значение заголовка со значением cookie на всех state-changing запросах.

**Исключения из проверки:**

-   Роуты `api.auth.login` и `api.auth.refresh` (по именам роутов через `routeIs()`)
-   Идемпотентные методы: GET, HEAD, OPTIONS
-   Preflight запросы (OPTIONS с заголовком `Access-Control-Request-Method`)

**При ошибке 419:**

-   Возвращается ответ в формате RFC 7807 (problem+json)
-   Автоматически выдаётся новый CSRF токен в cookie для восстановления клиента

**Основные характеристики:**

-   **Эндпоинт**: `GET /api/v1/auth/csrf` — выдача CSRF токена
-   **Cookie**: настраивается через `config/security.csrf.cookie_name` (по умолчанию `cms_csrf`, НЕ HttpOnly, доступен через JavaScript)
-   **Заголовки**: `X-CSRF-Token` или `X-XSRF-TOKEN` — токен для проверки (поддерживаются оба)
-   **Исключения**: роуты `api.auth.login` и `api.auth.refresh` (по именам через `routeIs()`)
-   **Методы**: только POST, PUT, PATCH, DELETE (GET, HEAD, OPTIONS не проверяются)
-   **Конфигурация**: все параметры CSRF cookie управляются через `config/security.php`

**Ответы:**

-   **200 OK**: успешная проверка CSRF токена
-   **419 CSRF Token Mismatch**: отсутствие токена, несовпадение токенов (формат RFC 7807)

## Структура файлов

### Конфигурация

```
config/security.php - Конфигурация CSRF (имя cookie, TTL, SameSite, Secure, Domain, Path)
```

### Контроллеры

```
app/Http/Controllers/Auth/CsrfController.php - Контроллер для выдачи CSRF токена
```

### Middleware

```
app/Http/Middleware/VerifyApiCsrf.php - Middleware для проверки CSRF токена
```

### Роутинг

```
routes/api.php - Добавлены имена роутов для login и refresh, маршрут GET /api/v1/auth/csrf
bootstrap/app.php - Зарегистрирован VerifyApiCsrf middleware в группе api (после CORS, перед auth)
```

### Хелперы

```
app/Support/JwtCookies.php - Метод csrf() использует config/security.php
```

### Тесты

```
tests/Feature/AuthCsrfTest.php - Feature-тесты для CSRF функционала (14 тестов)
```

## Использование

### Получение CSRF токена

```bash
GET /api/v1/auth/csrf
```

**Ответ (200 OK):**

```json
{
    "csrf": "random40charactertokenstring..."
}
```

**Cookies:**

-   `Set-Cookie: cms_csrf=...; Secure; SameSite=Strict` (НЕ HttpOnly, доступен через JavaScript)

### Успешный POST запрос с CSRF токеном

```bash
POST /api/v1/auth/logout
Cookie: cms_csrf=random40charactertokenstring...
X-CSRF-Token: random40charactertokenstring...
```

**Ответ (204 No Content):** без тела (CSRФ cookie и заголовки остаются в ответе)
**Ответ (204 No Content):** без тела

### POST запрос без CSRF токена

```bash
POST /api/v1/auth/logout
# Без cookie cms_csrf и заголовка X-CSRF-Token
```

**Ответ (419 CSRF Token Mismatch):**

```json
{
    "type": "about:blank",
    "title": "CSRF Token Mismatch",
    "status": 419,
    "detail": "CSRF token mismatch."
}
```

### POST запрос с неверным CSRF токеном

```bash
POST /api/v1/auth/logout
Cookie: cms_csrf=correcttoken...
X-CSRF-Token: wrongtoken...
```

**Ответ (419 CSRF Token Mismatch):**

```json
{
    "type": "about:blank",
    "title": "CSRF Token Mismatch",
    "status": 419,
    "detail": "CSRF token mismatch."
}
```

### Исключения: login и refresh

Эндпоинты `/api/v1/auth/login` и `/api/v1/auth/refresh` **не требуют** CSRF токена, так как они используются для аутентификации и обновления токенов.

```bash
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "secretPass123"
}
```

**Ответ (200 OK):** без требования CSRF токена

## Безопасность

### Double-Submit Cookie Pattern

CSRF-защита использует паттерн **double-submit cookie**:

1. Сервер выдаёт CSRF токен в cookie `cms_csrf` (НЕ HttpOnly)
2. Клиент (JavaScript) читает токен из cookie и отправляет его в заголовке `X-CSRF-Token`
3. Middleware сравнивает значение заголовка со значением cookie через `hash_equals()` (timing-safe comparison)

**Преимущества:**

-   Не требует состояния на сервере (stateless)
-   Работает с кросс-origin запросами (с правильной настройкой CORS и SameSite)
-   Защита от CSRF атак через сравнение токенов

### Исключения

Эндпоинты исключены из CSRF проверки по именам роутов через `routeIs()`:

-   `api.auth.login` — первичная аутентификация
-   `api.auth.refresh` — обновление токенов

Также исключены идемпотентные методы:

-   GET, HEAD, OPTIONS — не требуют CSRF защиты
-   OPTIONS с заголовком `Access-Control-Request-Method` — preflight запросы обрабатываются CORS middleware

### Timing-Safe Comparison

Middleware использует `hash_equals()` для сравнения токенов, что защищает от timing-атак:

```php
if (! hash_equals($cookie, $header)) {
    return response()->json([...], 419);
}
```

### Конфигурация CSRF Cookie

Все параметры CSRF cookie управляются через `config/security.php`:

```php
'csrf' => [
    'cookie_name' => env('CSRF_COOKIE_NAME', 'cms_csrf'),
    'ttl_hours' => env('CSRF_TTL_HOURS', 12),
    'samesite' => env('CSRF_SAMESITE', env('JWT_SAMESITE', 'Strict')),
    'secure' => env('CSRF_SECURE', env('APP_ENV') !== 'local'),
    'domain' => env('CSRF_DOMAIN', env('SESSION_DOMAIN')),
    'path' => env('CSRF_PATH', '/'),
]
```

**Атрибуты cookie:**

-   **HttpOnly**: `false` (для доступа через JavaScript)
-   **Secure**: настраивается через `CSRF_SECURE` (автоматически `true` при `SameSite=None`)
-   **SameSite**: настраивается через `CSRF_SAMESITE` (Strict, Lax, или None)
-   **Domain**: настраивается через `CSRF_DOMAIN`
-   **Path**: настраивается через `CSRF_PATH` (по умолчанию `/`)
-   **Expires**: настраивается через `CSRF_TTL_HOURS` (по умолчанию 12 часов)

### Кросс-Origin SPA (Single Page Application)

Для кросс-origin SPA (фронтенд на другом домене):

1. **CORS**: настроить CORS для разрешения запросов с credentials (`config/cors.php`)
2. **SameSite=None**: установить `CSRF_SAMESITE=None` в `.env` (требует HTTPS)
3. **Secure**: автоматически устанавливается в `true` при `SameSite=None`
4. **Заголовки**: поддерживаются оба заголовка `X-CSRF-Token` и `X-XSRF-TOKEN`

**Пример конфигурации для cross-origin:**

```env
CSRF_SAMESITE=None
CSRF_SECURE=true
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
```

## API Reference

### CsrfController

#### `issue(): JsonResponse`

Выдать CSRF токен в cookie и JSON ответе.

**Параметры:** нет

**Возвращает:**

-   **200 OK**: JSON с токеном и cookie (имя из `config/security.csrf.cookie_name`)

**Алгоритм:**

1. Генерирует случайный 40-символьный токен через `Str::random(40)`
2. Создаёт cookie с токеном через `JwtCookies::csrf()` (использует `config/security.csrf`)
3. Возвращает JSON с токеном и cookie

### VerifyApiCsrf Middleware

#### `handle(Request $request, Closure $next): Response`

Проверить CSRF токен для state-changing запросов.

**Параметры:**

-   `$request` — HTTP запрос
-   `$next` — следующий middleware/контроллер

**Возвращает:**

-   **Продолжает запрос**: если метод не state-changing или endpoint исключён
-   **419 CSRF Token Mismatch**: если токен отсутствует или не совпадает

**Алгоритм:**

1. Пропускает идемпотентные методы (GET, HEAD, OPTIONS)
2. Пропускает preflight запросы (OPTIONS с `Access-Control-Request-Method`)
3. Исключает роуты `api.auth.login` и `api.auth.refresh` через `routeIs()`
4. Извлекает токен из заголовка `X-CSRF-Token` или `X-XSRF-TOKEN` (поддерживаются оба)
5. Извлекает токен из cookie (имя из `config/security.csrf.cookie_name`)
6. Сравнивает токены через `hash_equals()` (timing-safe)
7. При ошибке 419: возвращает RFC 7807 ответ и выдаёт новый CSRF cookie для восстановления

## Тесты

**Файл:** `tests/Feature/AuthCsrfTest.php`

Покрытие (14 тестов):

1. **csrf endpoint returns token and cookie**: проверка выдачи токена и cookie, проверка что cookie НЕ HttpOnly
2. **csrf cookie attributes are correct**: проверка атрибутов cookie (HttpOnly отсутствует, Secure/SameSite, Path/Domain из config)
3. **post without csrf token returns 419 with new cookie**: проверка возврата 419 при отсутствии CSRF токена и перевыдачи нового cookie
4. **post with valid csrf token succeeds**: проверка успешного POST запроса с валидным CSRF токеном
5. **post with x xsrf token header succeeds**: проверка поддержки заголовка X-XSRF-TOKEN
6. **post with mismatched csrf token returns 419**: проверка возврата 419 при несовпадении токенов
7. **post without csrf cookie returns 419**: проверка возврата 419 при отсутствии cookie
8. **login endpoint excluded from csrf check**: проверка, что login не требует CSRF (по имени роута)
9. **refresh endpoint excluded from csrf check**: проверка, что refresh не требует CSRF (по имени роута)
10. **get request bypasses csrf check**: проверка, что GET запросы не проверяются
11. **head request bypasses csrf check**: проверка, что HEAD запросы не проверяются
12. **options preflight request bypasses csrf check**: проверка, что OPTIONS preflight запросы не проверяются
13. **cross origin request with credentials and valid csrf succeeds**: e2e тест с withCredentials для cross-origin
14. **csrf cookie uses config values**: проверка использования значений из config/security.php

## Интеграция с существующим кодом

### Регистрация Middleware

Middleware зарегистрирован в `bootstrap/app.php` в группе `api` с правильным порядком (CORS → CSRF → Vary → Auth):

```php
// Middleware order for API group: CORS → CSRF → Vary → Auth
// CORS must be first to handle preflight and set headers
// CSRF must be after CORS but before auth (headers/cookies must be available)
$middleware->appendToGroup('api', \App\Http\Middleware\VerifyApiCsrf::class);
```

**Порядок middleware:**

1. **CORS** (HandleCors) — автоматически регистрируется Laravel для API роутов через `config/cors.php`
2. **CSRF** (VerifyApiCsrf) — проверка CSRF токена
3. **Vary** (AddCacheVary) — добавление Vary заголовков
4. **Auth** (admin.auth и другие) — проверка аутентификации

Это означает, что все API запросы (кроме исключённых) проходят через CSRF проверку после обработки CORS, но до проверки аутентификации.

### Исключение Cookie из Шифрования

CSRF cookie исключён из автоматического шифрования в `bootstrap/app.php` (имя из config):

```php
$csrfCookieName = config('security.csrf.cookie_name', 'cms_csrf');
$middleware->encryptCookies(except: [
    'cms_at',
    'cms_rt',
    $csrfCookieName, // CSRF token cookie (non-HttpOnly, needs JS access)
]);
```

### Конфигурация

Все параметры CSRF управляются через `config/security.php`:

-   Имя cookie: `security.csrf.cookie_name`
-   TTL: `security.csrf.ttl_hours`
-   SameSite: `security.csrf.samesite`
-   Secure: `security.csrf.secure`
-   Domain: `security.csrf.domain`
-   Path: `security.csrf.path`

### Интеграция с JwtCookies

Метод `JwtCookies::csrf()` использует настройки из `config/security.csrf`, обеспечивая централизованное управление конфигурацией CSRF cookie.

## Список связанных задач

-   **Задача 37**: Login endpoint - исключён из CSRF проверки
-   **Задача 38**: Token Refresh - исключён из CSRF проверки
-   **Задача 39**: Logout - требует CSRF токен для безопасности
-   **Задача 42**: CORS Configuration - необходима для кросс-origin SPA с CSRF

## Критерии приёмки (Definition of Done)

-   [x] `routeIs()` исключает `api.auth.login` и `api.auth.refresh` из CSRF-проверки
-   [x] Оба заголовка (`X-CSRF-Token`, `X-XSRF-TOKEN`) валидны
-   [x] `config/security.php` управляет CSRF-cookie (имя/TTL/SameSite/Secure/Domain/Path)
-   [x] На проде CSRF-cookie имеет `SameSite=None; Secure`, не HttpOnly
-   [x] 419 возвращается как problem+json и одновременно выставляется новый CSRF-cookie
-   [x] GET/HEAD/OPTIONS не триггерят 419; preflight OPTIONS успешен
-   [x] Порядок middleware: CORS → CSRF → auth
-   [x] Все упоминания имени CSRF-cookie берутся из конфига (код и тесты)
-   [x] Тесты проверяют Set-Cookie атрибуты и кросс-ориджин сценарий с withCredentials

## Примечания

### Влияние на существующие тесты

После добавления CSRF защиты некоторые существующие тесты могут падать, так как они не устанавливают CSRF токен для POST запросов. Это ожидаемо — тесты нужно обновить, чтобы они получали CSRF токен перед отправкой POST запросов (кроме login и refresh endpoints).

### Рекомендации для клиентов

1. При первой загрузке приложения получить CSRF токен через `GET /api/v1/auth/csrf`
2. Сохранить токен из ответа (он также установится в cookie автоматически)
3. Для всех POST/PUT/PATCH/DELETE запросов (кроме login/refresh) отправлять токен в заголовке `X-CSRF-Token` или `X-XSRF-TOKEN`
4. Обновлять токен периодически (например, каждые 12 часов) или при получении 419 ошибки (новый токен автоматически выдается в cookie)

### Пример использования на клиенте

```javascript
// Получить CSRF токен
const csrfResponse = await fetch("/api/v1/auth/csrf", {
    credentials: "include", // важно для cross-origin запросов
});
const { csrf } = await csrfResponse.json();

// Отправить POST запрос с CSRF токеном
// Поддерживаются оба заголовка: X-CSRF-Token и X-XSRF-TOKEN
const response = await fetch("/api/v1/auth/logout", {
    method: "POST",
    credentials: "include", // важно для отправки cookies
    headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrf, // или 'X-XSRF-TOKEN': csrf
    },
});

// При ошибке 419 новый токен автоматически выдается в cookie
// Клиент может прочитать его из cookie или запросить новый токен
```
