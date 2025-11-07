# Задача 36. Cookie-based JWT модель токенов

## Краткое описание функционала

Реализована система выпуска и верификации JWT токенов (access/refresh) с хранением в HttpOnly Secure cookies. Используется **асимметричная криптография RS256** (RSA ключи) с поддержкой ротации через `kid` (key ID).

**Основные характеристики:**
- **Access токен**: срок действия 15 минут
- **Refresh токен**: срок действия 30 дней
- **Алгоритм**: RS256 (RSA 2048-bit)
- **Хранение**: HttpOnly, Secure, SameSite cookies (настраивается через `JWT_SAMESITE`)
- **Ротация ключей**: поддержка через `kid` в заголовке JWT с обратной совместимостью
- **Производительность**: кэширование RSA ключей в памяти для избежания I/O на каждом запросе

**Структура claims (payload):**
- `iss` — issuer (издатель)
- `aud` — audience (аудитория)
- `iat` — issued at (время выпуска)
- `nbf` — not before (не раньше чем)
- `exp` — expiration time (время истечения)
- `jti` — JWT ID (уникальный ID токена, UUID)
- `sub` — subject (ID пользователя)
- `typ` — token type (`access` или `refresh`)
- Дополнительные claims по необходимости (roles, permissions, etc.)

**Заголовок JWT:**
- `alg` = `RS256`
- `kid` = текущий key ID из конфигурации
- `typ` = `JWT`

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

### Artisan команды
```
app/Console/Commands/GenerateJwtKeys.php - Генерация RSA ключей
```

### Провайдеры
```
app/Providers/AppServiceProvider.php    - Регистрация JwtService как singleton
```

### Хранилище ключей
```
storage/keys/                           - Директория для RSA ключей
storage/keys/.gitkeep                   - Placeholder для Git
storage/keys/jwt-{kid}-private.pem      - Приватные ключи (добавлены в .gitignore)
storage/keys/jwt-{kid}-public.pem       - Публичные ключи (добавлены в .gitignore)
```

### Тесты
```
tests/Unit/JwtServiceTest.php           - Юнит-тесты для JwtService
```

## Использование

### Генерация ключей

Перед использованием необходимо сгенерировать RSA ключи:

```bash
php artisan cms:jwt:keys v1
```

Опции:
- `--bits=2048` — размер ключа (минимум 2048)
- `--force` — перезаписать существующие ключи

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
use App\Support/JwtCookies;

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
    // Ошибка конфигурации (не найден kid или ключ не читается)
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

## Ротация ключей

Для ротации ключей без инвалидации существующих токенов (backward compatibility):

1. Сгенерировать новую пару ключей:
```bash
php artisan cms:jwt:keys v2
```

2. Добавить в `config/jwt.php` (не удаляя старые ключи):
```php
'keys' => [
    'v1' => [
        'private_path' => storage_path('keys/jwt-v1-private.pem'),
        'public_path' => storage_path('keys/jwt-v1-public.pem'),
    ],
    'v2' => [
        'private_path' => storage_path('keys/jwt-v2-private.pem'),
        'public_path' => storage_path('keys/jwt-v2-public.pem'),
    ],
],
```

3. Обновить `current_kid` в конфиге или `.env`:
```
JWT_CURRENT_KID=v2
```

**Результат:**
- Новые токены будут подписаны ключом `v2` (указывается в `kid` header)
- Старые токены с `kid=v1` остаются валидными до истечения срока
- Верификация автоматически использует правильный публичный ключ на основе `kid` из JWT header
- После истечения всех токенов с `v1` можно удалить старые ключи из конфигурации

**Важно:** Публичные ключи всех версий должны оставаться в конфигурации до полного истечения всех токенов, подписанных этими ключами.

## Безопасность

**Cookies:**
- `HttpOnly` — защита от XSS (JavaScript не может получить доступ)
- `Secure` — только по HTTPS (отключено в local окружении, автоматически `true` при `SameSite=None`)
- `SameSite` — защита от CSRF (настраивается через `JWT_SAMESITE`)
  - `Strict` (по умолчанию) — максимальная защита
  - `Lax` — компромисс между безопасностью и удобством
  - `None` — для cross-origin SPA (требует HTTPS)
- `Path=/` — доступны для всех эндпоинтов

**Токены:**
- Refresh токен никогда не используется как Bearer token в заголовках
- Рекомендуется хранить реестр отозванных `jti` в БД/Redis для немедленной инвалидации refresh токенов
- Приватные ключи имеют права доступа `0600` (только владелец может читать/писать)
- Публичные ключи имеют права `0644` (все могут читать)
- RSA ключи кэшируются в памяти сервиса для производительности

**Рекомендации:**
- Добавить `storage/keys/*.pem` в `.gitignore`
- На production хранить ключи в secure vault (AWS Secrets Manager, HashiCorp Vault)
- Регулярно ротировать ключи (старые остаются валидными до истечения токенов)
- Мониторить истечение токенов и неудачные попытки верификации

## Производительность

**Кэширование ключей:**
RSA ключи загружаются из файловой системы один раз и кэшируются в памяти сервиса. Это устраняет I/O операции при каждом `encode()`/`verify()` запросе.

**Дрейф часов и leeway:**
При верификации токенов может возникать расхождение времени между сервером и клиентом. Leeway (допуск) настраивается через `JWT_LEEWAY` в `.env` или `config/jwt.php` (по умолчанию 5 секунд). Это значение автоматически применяется в `AppServiceProvider` и тестах для стабильной верификации при небольшом дрейфе часов.

## Зависимости

```json
{
  "firebase/php-jwt": "^6.11"
}
```

## Переменные окружения

```env
JWT_CURRENT_KID=v1
JWT_ISS=https://stupidcms.local
JWT_AUD=stupidcms-api
JWT_SAMESITE=Strict
JWT_LEEWAY=5
SESSION_DOMAIN=stupidcms.local
```

**JWT_SAMESITE** — настройка SameSite для cookies:
- `Strict` (по умолчанию) — максимальная защита от CSRF, cookies не отправляются в cross-site запросах
- `Lax` — cookies отправляются при top-level navigation (GET запросы)
- `None` — cookies отправляются во всех cross-site запросах (требует `secure=true`)

**JWT_LEEWAY** — допуск времени в секундах для верификации токенов (по умолчанию 5 секунд). Учитывает небольшой дрейф часов между сервером и клиентом. Рекомендуется 2-5 секунд.

**Важно:** Для cross-origin SPA (когда админка на другом домене) установите `JWT_SAMESITE=None`. При этом `secure` автоматически устанавливается в `true` для соответствия требованиям браузера.

## Примечания по тестированию

**Важно:** Юнит-тесты требуют работающего OpenSSL для генерации тестовых RSA ключей. На некоторых Windows системах с OpenSSL 3.x могут возникать проблемы с конфигурацией.

**Решения:**
1. Запустить тесты на Unix-системе (Linux/macOS)
2. Использовать WSL (Windows Subsystem for Linux)
3. Предварительно сгенерировать тестовые ключи вручную:
   ```bash
   php artisan cms:jwt:keys test-v1
   ```

Тесты автоматически пропустятся (skip) если генерация ключей не удалась.

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

- **Задача 37**: Login endpoint - эндпоинт аутентификации с выпуском токенов (выставляет `cms_at`/`cms_rt` cookies)
- **Задача 38**: Refresh endpoint - обновление access токена по refresh токену
- **Задача 39**: Logout/Rotate - выход и ротация токенов
- **Задача 27**: Policies/Abilities - проверка доступа к API через JWT токены
- **Задача 45**: Cache - кэширование публичных ключей для производительности
- **Задача 80**: Audit - аудит входов и использования токенов

## Критерии приёмки (Definition of Done)

- [x] Конфигурационный файл `config/jwt.php` создан
- [x] Artisan команда `cms:jwt:keys` для генерации RSA ключей
- [x] Сервис `JwtService` с методами `issueAccessToken`, `issueRefreshToken`, `encode`, `verify`
- [x] Cookie хелпер `JwtCookies` с методами `access`, `refresh`, `forgetAccess`, `forgetRefresh`
- [x] Регистрация `JwtService` как singleton в `AppServiceProvider`
- [x] Юнит-тесты для encode/verify, expiration, wrong key, wrong issuer/audience
- [x] Поддержка ротации ключей через `kid`
- [x] Документация создана

## API Reference

### JwtService

#### `issueAccessToken(int|string $userId, array $extra = []): string`
Выпустить access токен для пользователя.

**Параметры:**
- `$userId` — ID пользователя
- `$extra` — дополнительные claims

**Возвращает:** JWT string

#### `issueRefreshToken(int|string $userId, array $extra = []): string`
Выпустить refresh токен для пользователя.

**Параметры:**
- `$userId` — ID пользователя
- `$extra` — дополнительные claims

**Возвращает:** JWT string

#### `encode(int|string $userId, string $type, int $ttl, array $extra = []): string`
Закодировать JWT с произвольными параметрами.

**Параметры:**
- `$userId` — ID пользователя
- `$type` — тип токена ('access' или 'refresh')
- `$ttl` — время жизни в секундах
- `$extra` — дополнительные claims

**Возвращает:** JWT string

**Исключения:**
- `RuntimeException` — если kid не найден в конфигурации или ключ не читается

#### `verify(string $jwt, ?string $expectType = null): array`
Верифицировать JWT и вернуть claims.

**Параметры:**
- `$jwt` — JWT token string
- `$expectType` — ожидаемый тип токена (optional)

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
    ],
    'kid' => 'v1'
]
```

**Исключения:**
- `RuntimeException` — если kid не найден или ключ не читается
- `Firebase\JWT\SignatureInvalidException` — если подпись неверна (неверный ключ или поврежденный токен)
- `Firebase\JWT\ExpiredException` — если токен истек
- `Firebase\JWT\BeforeValidException` — если токен еще не валиден (nbf в будущем)
- `UnexpectedValueException` — если тип токена, issuer или audience не совпадают с ожидаемыми

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
    'algo' => 'RS256',
    'access_ttl' => 900,           // 15 минут
    'refresh_ttl' => 2592000,      // 30 дней
    'leeway' => 5,                 // 5 секунд допуска для дрейфа часов
    'current_kid' => 'v1',
    'keys' => [
        'v1' => [
            'private_path' => storage_path('keys/jwt-v1-private.pem'),
            'public_path' => storage_path('keys/jwt-v1-public.pem'),
        ],
    ],
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

