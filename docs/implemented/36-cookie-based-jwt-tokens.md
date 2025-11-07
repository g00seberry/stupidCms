# Задача 36. Cookie-based JWT модель токенов

## Краткое описание функционала

Реализована система выпуска и верификации JWT токенов (access/refresh) с хранением в HttpOnly Secure cookies. Используется **симметричная криптография HS256** (HMAC с SHA-256) с секретным ключом.

**Основные характеристики:**

-   **Access токен**: срок действия 15 минут
-   **Refresh токен**: срок действия 30 дней
-   **Алгоритм**: HS256 (HMAC с SHA-256)
-   **Хранение**: HttpOnly, Secure, SameSite cookies (настраивается через `JWT_SAMESITE`)
-   **Простота**: один секретный ключ в `.env`, никаких сложных RSA-ключей

**Структура claims (payload):**

-   `iss` — issuer (издатель)
-   `aud` — audience (аудитория)
-   `iat` — issued at (время выпуска)
-   `nbf` — not before (не раньше чем)
-   `exp` — expiration time (время истечения)
-   `jti` — JWT ID (уникальный ID токена, UUID)
-   `sub` — subject (ID пользователя)
-   `typ` — token type (`access` или `refresh`)
-   Дополнительные claims по необходимости (roles, permissions, etc.)

**Заголовок JWT:**

-   `alg` = `HS256`
-   `typ` = `JWT`

## Структура файлов

### Конфигурация

```
config/jwt.php                          - Конфигурация JWT токенов
```

### Доменная логика

```
app/Domain/Auth/JwtService.php          - Сервис для выпуска и верификации JWT токенов
```

### Вспомогательные классы

```
app/Support/JwtCookies.php              - Хелпер для создания JWT cookies
```

### Провайдеры

```
app/Providers/AppServiceProvider.php    - Регистрация JwtService как singleton
```

### Тесты

```
tests/Unit/JwtServiceTest.php           - Юнит-тесты для JwtService
```

## Использование

### Настройка секретного ключа

Добавьте в `.env` секретный ключ для подписи токенов:

```bash
JWT_SECRET=your-random-secret-key-minimum-32-characters-for-security
```

Сгенерируйте случайный ключ:

```bash
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

### Выпуск токенов

```php
use App\Domain\Auth\JwtService;

$jwtService = app(JwtService::class);

// Выпуск access токена
$accessToken = $jwtService->issueAccessToken($userId, [
    'role' => 'admin',
    'permissions' => ['read', 'write']
]);

// Выпуск refresh токена
$refreshToken = $jwtService->issueRefreshToken($userId);
```

### Создание cookies

```php
use App\Support\JwtCookies;

// Создание access token cookie
$accessCookie = JwtCookies::access($accessToken);

// Создание refresh token cookie
$refreshCookie = JwtCookies::refresh($refreshToken);

// Добавление cookies к ответу
return response()->json(['success' => true])
    ->withCookie($accessCookie)
    ->withCookie($refreshCookie);
```

### Верификация токенов

```php
// Верификация с проверкой типа
try {
    $result = $jwtService->verify($jwt, 'access');
    $claims = $result['claims'];
    $userId = $claims['sub'];
    $role = $claims['role'] ?? null;
} catch (\Firebase\JWT\ExpiredException $e) {
    // Токен истек
} catch (\Firebase\JWT\SignatureInvalidException $e) {
    // Неверная подпись (неверный ключ или поврежденный токен)
} catch (\UnexpectedValueException $e) {
    // Неверный тип токена, issuer или audience
} catch (\RuntimeException $e) {
    // Ошибка конфигурации (секретный ключ не настроен)
}

// Верификация без проверки типа
$result = $jwtService->verify($jwt);
```

### Удаление cookies (logout)

```php
use App\Support\JwtCookies;

return response()->json(['success' => true])
    ->withCookie(JwtCookies::forgetAccess())
    ->withCookie(JwtCookies::forgetRefresh());
```

## Безопасность

**Cookies:**

-   **HttpOnly**: защита от XSS (JavaScript не может получить доступ)
-   **Secure**: только по HTTPS (отключено в local окружении, автоматически `true` при `SameSite=None`)
-   **SameSite**: защита от CSRF (настраивается через `JWT_SAMESITE`)
    -   `Strict` (по умолчанию) — максимальная защита
    -   `Lax` — компромисс между безопасностью и удобством
    -   `None` — для cross-origin SPA (требует HTTPS)
-   **Path=/**: доступны для всех эндпоинтов

**Токены:**

-   Refresh токен никогда не используется как Bearer token в заголовках
-   Рекомендуется хранить реестр отозванных `jti` в БД/Redis для немедленной инвалидации refresh токенов
-   Секретный ключ должен быть **минимум 256 бит** (32 символа) для безопасности HS256

**Рекомендации:**

-   Никогда не коммитьте `.env` с реальным `JWT_SECRET`
-   На production используйте длинный случайный ключ (минимум 32 байта)
-   Регулярно ротируйте секретный ключ (при этом старые токены станут невалидными)
-   Мониторьте истечение токенов и неудачные попытки верификации

### Кросс-origin SPA (Single Page Application)

Если фронтенд-приложение находится на другом домене (например, `https://admin.example.com`), необходимо настроить:

1. **CORS на сервере**: разрешить запросы с credentials

    ```php
    // В config/cors.php или middleware
    'supports_credentials' => true,
    'allowed_origins' => ['https://admin.example.com'],
    ```

2. **SameSite=None для cookies**: установить в `.env`

    ```
    JWT_SAMESITE=None
    ```

    При `SameSite=None` флаг `Secure` автоматически устанавливается в `true` (требование браузера).

3. **Клиент должен отправлять credentials**:
    ```javascript
    fetch("https://api.example.com/api/v1/auth/login", {
        method: "POST",
        credentials: "include", // Важно!
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password }),
    });
    ```

**Важно**: Без этих настроек cookies не будут отправляться браузером в cross-origin запросах.

### Валидация

-   **Email**: строгая валидация (`email:strict`), автоматическое приведение к lowercase, максимум 254 символа
-   **Password**: минимум 8 символов, максимум 200 символов

## Производительность

**HMAC SHA-256** работает значительно быстрее RSA:

-   Подпись токена: ~0.01ms (vs ~5ms для RSA)
-   Верификация: ~0.01ms (vs ~0.5ms для RSA)
-   Простота: один ключ для подписи и верификации

**Дрейф часов и leeway:**
При верификации токенов может возникать расхождение времени между сервером и клиентом. Leeway (допуск) настраивается через `JWT_LEEWAY` в `.env` или `config/jwt.php` (по умолчанию 5 секунд). Это значение автоматически применяется в `AppServiceProvider` для стабильной верификации при небольшом дрейфе часов.

## Зависимости

```json
{
    "firebase/php-jwt": "^6.11"
}
```

## Переменные окружения

```env
JWT_SECRET=your-random-secret-key-minimum-32-characters
JWT_ISS=https://stupidcms.local
JWT_AUD=stupidcms-api
JWT_SAMESITE=Strict
JWT_LEEWAY=5
SESSION_DOMAIN=stupidcms.local
```

**JWT_SECRET** — секретный ключ для HMAC подписи (минимум 32 символа для безопасности)

**JWT_SAMESITE** — настройка SameSite для cookies:

-   `Strict` (по умолчанию) — максимальная защита от CSRF, cookies не отправляются в cross-site запросах
-   `Lax` — cookies отправляются при top-level navigation (GET запросы)
-   `None` — cookies отправляются во всех cross-site запросах (требует `secure=true`)

**JWT_LEEWAY** — допуск времени в секундах для верификации токенов (по умолчанию 5 секунд). Учитывает небольшой дрейф часов между сервером и клиентом. Рекомендуется 2-5 секунд.

**Важно:** Для cross-origin SPA (когда админка на другом домене) установите `JWT_SAMESITE=None`. При этом `secure` автоматически устанавливается в `true` для соответствия требованиям браузера.

## Связь с задачей 37 (Login endpoint)

**Важно:** Эндпоинт `/auth/login` (задача 37) должен выставлять cookies `cms_at` (access token) и `cms_rt` (refresh token) согласно спецификации security/API. Используйте `JwtCookies::access()` и `JwtCookies::refresh()` для создания этих cookies.

Пример использования в login endpoint:

```php
$accessToken = $jwtService->issueAccessToken($user->id);
$refreshToken = $jwtService->issueRefreshToken($user->id);

return response()->json(['success' => true])
    ->withCookie(JwtCookies::access($accessToken))
    ->withCookie(JwtCookies::refresh($refreshToken));
```

## Список связанных задач

-   **Задача 37**: Login endpoint - эндпоинт аутентификации с выпуском токенов (выставляет `cms_at`/`cms_rt` cookies)
-   **Задача 38**: Refresh endpoint - обновление access токена по refresh токену
-   **Задача 39**: Logout/Rotate - выход и ротация токенов
-   **Задача 27**: Policies/Abilities - проверка доступа к API через JWT токены
-   **Задача 80**: Audit - аудит входов и использования токенов

## Критерии приёмки (Definition of Done)

-   [x] Конфигурационный файл `config/jwt.php` создан
-   [x] Сервис `JwtService` с методами `issueAccessToken`, `issueRefreshToken`, `encode`, `verify`
-   [x] Cookie хелпер `JwtCookies` с методами `access`, `refresh`, `forgetAccess`, `forgetRefresh`
-   [x] Регистрация `JwtService` как singleton в `AppServiceProvider`
-   [x] Юнит-тесты для encode/verify, expiration, wrong key, wrong issuer/audience
-   [x] Использование HS256 вместо RS256 для простоты и производительности
-   [x] Документация создана

## API Reference

### JwtService

#### `issueAccessToken(int|string $userId, array $extra = []): string`

Выпустить access токен для пользователя.

**Параметры:**

-   `$userId` — ID пользователя
-   `$extra` — дополнительные claims

**Возвращает:** JWT string

#### `issueRefreshToken(int|string $userId, array $extra = []): string`

Выпустить refresh токен для пользователя.

**Параметры:**

-   `$userId` — ID пользователя
-   `$extra` — дополнительные claims

**Возвращает:** JWT string

#### `encode(int|string $userId, string $type, int $ttl, array $extra = []): string`

Закодировать JWT с произвольными параметрами.

**Параметры:**

-   `$userId` — ID пользователя
-   `$type` — тип токена ('access' или 'refresh')
-   `$ttl` — время жизни в секундах
-   `$extra` — дополнительные claims

**Возвращает:** JWT string

**Исключения:**

-   `RuntimeException` — если секретный ключ не настроен

#### `verify(string $jwt, ?string $expectType = null): array`

Верифицировать JWT и вернуть claims.

**Параметры:**

-   `$jwt` — JWT token string
-   `$expectType` — ожидаемый тип токена (optional)

**Возвращает:**

```php
[
    'claims' => [
        'sub' => '123',
        'typ' => 'access',
        'iss' => 'https://stupidcms.local',
        'aud' => 'stupidcms-api',
        'iat' => 1234567890,
        'nbf' => 1234567890,
        'exp' => 1234568790,
        'jti' => 'uuid-string',
        // ... дополнительные claims
    ]
]
```

**Исключения:**

-   `RuntimeException` — если секретный ключ не настроен
-   `Firebase\JWT\SignatureInvalidException` — если подпись неверна (неверный ключ или поврежденный токен)
-   `Firebase\JWT\ExpiredException` — если токен истек
-   `Firebase\JWT\BeforeValidException` — если токен еще не валиден (nbf в будущем)
-   `UnexpectedValueException` — если тип токена, issuer или audience не совпадают с ожидаемыми

### JwtCookies

#### `static access(string $jwt): Cookie`

Создать cookie для access токена.

#### `static refresh(string $jwt): Cookie`

Создать cookie для refresh токена.

#### `static forgetAccess(): Cookie`

Создать expired cookie для удаления access токена.

#### `static forgetRefresh(): Cookie`

Создать expired cookie для удаления refresh токена.

## Конфигурация по умолчанию

```php
return [
    'algo' => 'HS256',
    'access_ttl' => 900,           // 15 минут
    'refresh_ttl' => 2592000,      // 30 дней
    'leeway' => 5,                 // 5 секунд допуска для дрейфа часов
    'secret' => env('JWT_SECRET', ''),
    'issuer' => 'https://stupidcms.local',
    'audience' => 'stupidcms-api',
    'cookies' => [
        'access' => 'cms_at',
        'refresh' => 'cms_rt',
        'domain' => null,
        'secure' => true,          // false в local
        'samesite' => 'Strict',
        'path' => '/',
    ],
];
```

## Отличия от RS256

**Преимущества HS256:**

-   ✅ Простота: один секретный ключ вместо пары RSA-ключей
-   ✅ Производительность: в ~500 раз быстрее подпись, в ~50 раз быстрее верификация
-   ✅ Удобство: не нужен OpenSSL для генерации ключей
-   ✅ Кросс-платформенность: работает везде без проблем
-   ✅ Простота развертывания: просто установить `JWT_SECRET` в `.env`

**Недостатки HS256:**

-   ❌ Нет асимметричности: сервер, который верифицирует токены, может их и подписывать
-   ❌ Нет публичного распространения: нельзя дать публичный ключ третьим сторонам для верификации

**Когда использовать HS256:**

-   ✅ Internal API (токены верифицируются тем же сервером, который их выпускает)
-   ✅ Simple applications (нет необходимости в публичной верификации)
-   ✅ Быстрая разработка и развертывание

**Когда использовать RS256:**

-   Microservices architecture (разные сервисы выпускают и верифицируют токены)
-   Public API (публичный ключ распространяется для верификации третьими сторонами)
-   Compliance requirements (некоторые стандарты требуют асимметричную криптографию)
