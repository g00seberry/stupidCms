# Задача 42. CORS Configuration

## Краткое описание функционала

Настроена конфигурация Cross-Origin Resource Sharing (CORS) для поддержки кросс-сайтовых запросов от SPA и админки на других origin'ах. Реализовано:
- **Whitelist origins** через конфигурацию
- **Credentials support** для передачи cookies
- **Vary headers** для правильного кэширования

**Основные характеристики:**

- **Конфигурация**: `config/cors.php`
- **Credentials**: `supports_credentials = true`
- **Whitelist**: настраивается через `CORS_ALLOWED_ORIGINS`
- **Vary headers**: автоматически добавляются для ответов с cookies

## Структура файлов

### Конфигурация

```
config/cors.php - Конфигурация CORS
```

### Middleware

```
app/Http/Middleware/AddCacheVary.php - Middleware для добавления Vary headers
```

### Регистрация

```
bootstrap/app.php - Регистрация AddCacheVary в api группе
```

## Конфигурация

### config/cors.php

```php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'https://app.example.com')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => true,
];
```

### .env

```env
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
JWT_COOKIE_SAMESITE=None
JWT_COOKIE_SECURE=true
```

## Использование

### Preflight запрос (OPTIONS)

```bash
OPTIONS /api/v1/auth/login
Origin: https://app.example.com
Access-Control-Request-Method: POST
Access-Control-Request-Headers: Content-Type
```

**Ответ (204 No Content):**

```
Access-Control-Allow-Origin: https://app.example.com
Access-Control-Allow-Credentials: true
Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE, PATCH
Access-Control-Allow-Headers: Content-Type, Accept, X-CSRF-Token, X-Requested-With, Origin, Authorization
Access-Control-Max-Age: 600
```

### Реальный запрос с credentials

```bash
POST /api/v1/auth/login
Origin: https://app.example.com
Content-Type: application/json
Cookie: cms_rt=...

{
    "email": "user@example.com",
    "password": "password123"
}
```

**Ответ (200 OK):**

```
Access-Control-Allow-Origin: https://app.example.com
Access-Control-Allow-Credentials: true
Vary: Origin, Cookie
Set-Cookie: cms_at=...; HttpOnly; Secure; SameSite=None
Set-Cookie: cms_rt=...; HttpOnly; Secure; SameSite=None
```

## Безопасность

### Whitelist Origins

Только origins из `CORS_ALLOWED_ORIGINS` разрешены для кросс-сайтовых запросов. Это предотвращает:
- **CSRF атаки** с других доменов
- **Утечку данных** через CORS
- **Несанкционированный доступ** к API

### Credentials Support

`supports_credentials = true` позволяет передавать cookies в кросс-сайтовых запросах. Это необходимо для:
- Cookie-based JWT аутентификации
- Работы SPA на другом origin
- Сохранения сессий между доменами

**Важно:** При `supports_credentials = true` нельзя использовать `allowed_origins = ['*']`. Нужен явный whitelist.

### SameSite и Secure

Для кросс-сайтовых запросов требуется:
- `SameSite=None` (для отправки cookies в кросс-сайтовых запросах)
- `Secure=true` (обязательно при `SameSite=None`)

## Vary Headers

### AddCacheVary Middleware

Middleware автоматически добавляет `Vary: Origin, Cookie` для ответов, которые устанавливают cookies. Это необходимо для:
- **Правильного кэширования** в CDN и прокси
- **Предотвращения утечки данных** между пользователями
- **Соответствия HTTP спецификации**

**Регистрация:**

```php
// bootstrap/app.php
$middleware->appendToGroup('api', \App\Http\Middleware\AddCacheVary::class);
```

## Тесты

**Файл:** `tests/Feature/CorsTest.php`

Покрытие (4 теста):

1. **Preflight запрос**: проверка 204 с `Access-Control-Allow-Credentials: true`
2. **Preflight с невалидным origin**: проверка 403 при origin не из whitelist
3. **Реальный запрос с allowed origin**: проверка установки cookies и CORS заголовков
4. **Vary headers**: проверка наличия `Vary: Origin, Cookie` в ответах с cookies

## Список связанных задач

- **Задача 36**: Cookie-based JWT модель токенов
- **Задача 37**: Login endpoint
- **Задача 38**: Token Refresh
- **Задача 39**: Logout
- **Задача 40**: CSRF-cookie + заголовок

## Критерии приёмки (Definition of Done)

- [x] CORS конфигурация создана (`config/cors.php`)
- [x] Whitelist origins настраивается через `.env`
- [x] `supports_credentials = true`
- [x] AddCacheVary middleware реализован
- [x] Vary headers добавляются автоматически
- [x] Middleware зарегистрирован в api группе
- [x] Тесты покрывают все сценарии
- [x] Документация создана

