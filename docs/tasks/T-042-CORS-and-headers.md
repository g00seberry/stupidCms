# T-042 — Вариации CORS/headers (CORS + Vary: Cookie)

```yaml
id: T-042
title: Настроить CORS и Vary-заголовки для фронта; отключить кеширование тулбара
area: [backend, laravel, api, frontend]
priority: P1
size: S
depends_on: []
blocks: []
labels: [stupidCms, mvp, http, cors, cache]
```

## 1) Контекст

Во фронте (админка/виджеты) есть запросы с другого origin. Используем cookie-based JWT (`cms_at/cms_rt`) и отдельный CSRF cookie — значит, CORS должен:
- корректно пускать к API «разрешённые» origins;
- разрешать **credentials** (cookies) и preflight (OPTIONS);
- проставлять консистентный `Vary` (в т.ч. `Vary: Cookie`), чтобы персонализированные ответы не кешировались публичными прокси как общие;
- исключить из кеширования «toolbar» (панелька для авторизованных).

## 2) Требуемый результат (Deliverables)

- **Код/конфиг:**
  - `config/cors.php` — актуализирован под проект (разрешённые origins из `.env`, credentials включены, max_age настроен).
  - `app/Http/Middleware/AddVaryHeaders.php` — добавляет/объединяет `Vary: Origin, Accept, Accept-Encoding, Accept-Language, Cookie`.
  - `app/Http/Middleware/PreventToolbarCache.php` — помечает ответы с тулбаром как `Cache-Control: private, no-store`.
  - Обновлён `app/Http/Kernel.php` — `HandleCors` в глобальном стеке, выше роутов; новые middleware подключены в группы `web` и `api` (после аутентификации).
  - `.env.example` — `CORS_ALLOWED_ORIGINS=` (через запятую).
- **Тесты:**
  - `tests/Feature/Cors/PreflightTest.php` — успешный preflight (204 + все CORS-заголовки).
  - `tests/Feature/Cors/SimpleRequestTest.php` — обычный GET с `Origin` + credentials.
  - `tests/Feature/Cache/ToolbarNoCacheTest.php` — тулбар/авторизованный ответ не кешируется и содержит `Vary: Cookie`.
- **Документация:**
  - `docs/http-cors-and-vary.md` — правила настройки, примеры `curl`, проблемы и отладка.
- **Команды проверки:**
  - `phpunit --testsuite Feature --filter '(Cors|Cache)'`
  - `curl` сценарии (ниже).

## 3) Функциональные требования

- CORS применён к путям `api/*`, `auth/*`, `admin/*` (тонкая настройка в `config/cors.php:paths`).
- Разрешены origins из `.env:CORS_ALLOWED_ORIGINS` (список через запятую). Никаких `*`, т.к. нужны credentials.
- `supports_credentials: true`. В ответах присутствует `Access-Control-Allow-Credentials: true` для разрешённых origins.
- `allowed_methods: ['GET','POST','PUT','PATCH','DELETE','OPTIONS']`.
- `allowed_headers`: как минимум `Content-Type, X-Requested-With, X-CSRF-TOKEN, Authorization` (+ любые из запроса).
- `exposed_headers`: `ETag, X-Request-Id` (по проектной необходимости).
- `max_age: 600` секунд для preflight.
- **Preflight:** OPTIONS-запрос возвращает `204 No Content` + корректные CORS-заголовки.
- **Vary:** на все публичные эндпоинты добавляется заголовок `Vary` со значениями `Origin, Accept, Accept-Encoding, Accept-Language, Cookie` (объединять с существующим).
- **Toolbar/no-cache:** если запрос с авторизационным cookie `cms_at` **или** активен флаг тулбара (например, хедер `X-Toolbar: 1`/presence), то ответ помечается `Cache-Control: private, no-store, max-age=0` + `Pragma: no-cache`, и **всегда** содержит `Vary: Cookie`.
- **Безопасность:** если `Origin` не в белом списке — CORS-заголовки **не** выставляются, запрос обрабатывается как обычный same-origin.

## 4) Нефункциональные требования

- Совместимость: Laravel 12.x, PHP 8.2+; без сторонних пакетов CORS (используем `\Illuminate\Http\Middleware\HandleCors`).
- Производительность: preflight кешируется браузером по `Access-Control-Max-Age` (600s).
- Приватность: персонализированные ответы — `private` + `Vary: Cookie`.
- Наблюдаемость: логировать `Origin` и отказ по нему на уровне debug (по желанию).

## 5) Конфигурация/Код

### 5.1 `config/cors.php`

```php
<?php

return [
    'paths' => ['api/*', 'auth/*', 'admin/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_filter(
        array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '')))
    ),
    // Поддерживаем поддомены через allowed_origins_patterns при необходимости:
    'allowed_origins_patterns' => [], // ['~^https://(.+\\.)?example\\.com$~']
    'allowed_headers' => ['*'], // или перечислить явно
    'exposed_headers' => ['ETag', 'X-Request-Id'],
    'max_age' => 600,
    'supports_credentials' => true,
];
```

`.env.example`:
```dotenv
# Через запятую, без пробелов
CORS_ALLOWED_ORIGINS=https://admin.example.com,https://www.example.com
```

### 5.2 Middleware `AddVaryHeaders`

```php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddVaryHeaders
{
    /** @var string[] */
    private array $vary = ['Origin', 'Accept', 'Accept-Encoding', 'Accept-Language', 'Cookie'];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $existing = array_filter(array_map('trim', explode(',', (string) $response->headers->get('Vary'))));
        $merged = array_values(array_unique(array_merge($existing, $this->vary)));
        if ($merged) {
            $response->headers->set('Vary', implode(', ', $merged), true);
        }

        return $response;
    }
}
```

### 5.3 Middleware `PreventToolbarCache`

```php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventToolbarCache
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $hasAuthCookie = $request->cookies->has('cms_at');
        $toolbarFlag   = $request->headers->get('X-Toolbar') === '1';

        if ($hasAuthCookie || $toolbarFlag) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0', true);
            $response->headers->set('Pragma', 'no-cache', true);

            // Гарантируем Vary: Cookie
            $existing = array_filter(array_map('trim', explode(',', (string) $response->headers->get('Vary'))));
            if (!in_array('Cookie', $existing, true)) {
                $existing[] = 'Cookie';
            }
            $response->headers->set('Vary', implode(', ', array_unique($existing)), true);
        }

        return $response;
    }
}
```

### 5.4 Kernel подключение

```php
// app/Http/Kernel.php

protected $middleware = [
    // ...
    \Illuminate\Http\Middleware\HandleCors::class, // Глобально, чтобы обработать OPTIONS preflight
    // ...
];

protected $middlewareGroups = [
    'web' => [
        // ...
        \App\Http\Middleware\AddVaryHeaders::class,
        \App\Http\Middleware\PreventToolbarCache::class,
    ],
    'api' => [
        // ...
        \App\Http\Middleware\AddVaryHeaders::class,
        \App\Http\Middleware\PreventToolbarCache::class,
    ],
];
```

## 6) План реализации

1. Обновить `config/cors.php` и `.env.example` (whitelist origins, credentials=true, max_age=600).
2. Убедиться, что `HandleCors` стоит в глобальном стеке middleware.
3. Добавить `AddVaryHeaders` и `PreventToolbarCache`; подключить их в группы `web` и `api` **после** аутентификации.
4. Пересобрать конфиг: `php artisan config:clear`.
5. Написать и запустить Feature-тесты preflight/GET/toolbar.
6. Обновить `docs/http-cors-and-vary.md` с инструкциями и троблшутингом.

## 7) Acceptance Criteria

- [ ] Preflight (`OPTIONS`) к `api/*` от разрешённого origin возвращает `204` и включает `Access-Control-Allow-Origin` (равный origin), `Access-Control-Allow-Credentials: true`, `Access-Control-Allow-Methods`, `Access-Control-Allow-Headers`, `Access-Control-Max-Age: 600`.
- [ ] Обычный `GET` с `Origin` от разрешённого origin включает `Access-Control-Allow-Origin` (равный origin) и `Access-Control-Allow-Credentials: true`.
- [ ] Во всех ответах присутствует корректный объединённый `Vary` (минимум `Origin, Accept, Accept-Encoding, Accept-Language, Cookie`).
- [ ] При наличии cookie `cms_at` или хедера `X-Toolbar: 1` ответ содержит `Cache-Control: private, no-store` и **не кешируется**; тулбар не попадает в публичный кеш.
- [ ] Origin вне белого списка не получает CORS-заголовков.
- [ ] Все тесты зелёные.

## 8) Роллаут / Бэкаут

**Роллаут:** деплой → `php artisan config:cache` → проверить preflight `curl` ниже.  
**Бэкаут:** вернуть предыдущий `config/cors.php` или временно очистить `allowed_origins`; удалить middleware из `Kernel` при необходимости.

## 9) Примеры `curl`

Preflight:
```bash
curl -i -X OPTIONS \
  -H 'Origin: https://admin.example.com' \
  -H 'Access-Control-Request-Method: GET' \
  -H 'Access-Control-Request-Headers: content-type,x-csrf-token' \
  https://api.example.com/api/pages

# Ожидаем: 204 + ACAO: https://admin.example.com, ACAC: true, ACAM: GET,POST,PUT,PATCH,DELETE,OPTIONS, ACRH: ..., ACMA: 600
```

Запрос с credentials:
```bash
curl -i \
  -H 'Origin: https://admin.example.com' \
  --cookie 'cms_at=token' \
  https://api.example.com/api/pages
# Ожидаем: ACAO: https://admin.example.com, ACAC: true, Vary: Origin, Accept, Accept-Encoding, Accept-Language, Cookie
```

Тулбар не кешируется:
```bash
curl -i -H 'X-Toolbar: 1' https://example.com/
# Ожидаем: Cache-Control: private, no-store, max-age=0; Vary содержит Cookie
```

## 10) Формат ответа от нейросети (для реализации)

- **Plan** / **Files** / **Patchset** / **Tests** / **Checks** / **Notes** — как в общем шаблоне проекта.
