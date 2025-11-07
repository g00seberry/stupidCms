# Задача 37. Аутентификация: `POST /api/v1/auth/login`

## Краткое описание функционала

Реализован endpoint входа по email/паролю. При успешной аутентификации выдаются **два HttpOnly Secure cookie**: `cms_at` (access JWT) и `cms_rt` (refresh JWT). При ошибке возвращается `401 Unauthorized` в формате RFC 7807 (problem+json) без уточнений. Все попытки входа (успешные и неуспешные) записываются в таблицу `audits`.

**Основные характеристики:**

-   **Маршрут**: `POST /api/v1/auth/login`
-   **Валидация**: email (required, strict, lowercase, max:254) и password (required, string, min:8, max:200)
-   **Rate limiting**: 5 попыток в минуту на связку email+IP
-   **Безопасность**: слепая аутентификация (не раскрывает существование email)
-   **Cookies**: HttpOnly, Secure, SameSite=Strict (настраивается через `JWT_SAMESITE`)

**Ответы:**

-   **200 OK**: успешный вход с установленными cookies `cms_at` и `cms_rt`, возвращает данные пользователя
-   **401 Unauthorized**: неверные учётные данные без cookies (формат RFC 7807)
-   **422 Unprocessable Entity**: ошибки валидации (формат RFC 7807)

## Структура файлов

### Роутинг

```
routes/api.php                              - Публичные API маршруты
app/Providers/RouteServiceProvider.php     - Регистрация роутов и rate limiters
```

### Валидация

```
app/Http/Requests/Auth/LoginRequest.php     - FormRequest для валидации входных данных
```

### Контроллеры

```
app/Http/Controllers/Auth/LoginController.php - Контроллер обработки входа
```

### Тесты

```
tests/Feature/AuthLoginTest.php            - Feature-тесты для login endpoint
```

## Использование

### Успешный вход

```bash
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "secretPass123"
}
```

**Ответ (200 OK):**

```json
{
    "user": {
        "id": 1,
        "email": "user@example.com",
        "name": "User Name"
    }
}
```

**Cookies:**

-   `Set-Cookie: cms_at=...; HttpOnly; Secure; SameSite=Strict`
-   `Set-Cookie: cms_rt=...; HttpOnly; Secure; SameSite=Strict`

### Неверные учётные данные

```bash
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "wrong@example.com",
  "password": "wrongpassword"
}
```

**Ответ (401 Unauthorized):**

```http
HTTP/1.1 401 Unauthorized
Content-Type: application/problem+json

{
    "type": "about:blank",
    "title": "Unauthorized",
    "status": 401,
    "detail": "Invalid credentials."
}
```

Cookies не устанавливаются. Формат ошибки соответствует RFC 7807 (problem+json).

### Ошибки валидации

```bash
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "not-an-email",
  "password": "short"
}
```

**Ответ (422 Unprocessable Entity):**

```json
{
    "type": "https://stupidcms.dev/problems/validation-error",
    "title": "Validation Failed",
    "status": 422,
    "detail": "The given data was invalid.",
    "errors": {
        "email": ["The email field must be a valid email address."],
        "password": ["The password field must be at least 8 characters."]
    }
}
```

## Безопасность

### Rate Limiting

Настроен rate limiter `login` в `RouteServiceProvider`:

-   **Лимит**: 5 попыток в минуту
-   **Ключ**: `login:{email}|{ip}` (email приводится к lowercase)
-   **Цель**: защита от брутфорса

### Слепая аутентификация

Контроллер не раскрывает, существует ли указанный email в системе:

-   Неверный email → 401
-   Неверный пароль → 401
-   Одинаковый ответ для обоих случаев

### Cookies

Cookies настраиваются через `JwtCookies` хелпер:

-   **HttpOnly**: защита от XSS (JavaScript не может получить доступ)
-   **Secure**: только по HTTPS (отключено в local окружении, автоматически `true` при `SameSite=None`)
-   **SameSite**: защита от CSRF (настраивается через `JWT_SAMESITE`)
    -   `Strict` (по умолчанию) — максимальная защита
    -   `Lax` — компромисс между безопасностью и удобством
    -   `None` — для cross-origin SPA (требует HTTPS)

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
   fetch('https://api.example.com/api/v1/auth/login', {
     method: 'POST',
     credentials: 'include', // Важно!
     headers: { 'Content-Type': 'application/json' },
     body: JSON.stringify({ email, password })
   });
   ```

**Важно**: Без этих настроек cookies не будут отправляться браузером в cross-origin запросах.

### Валидация

-   **Email**: строгая валидация (`email:strict`), автоматическое приведение к lowercase, максимум 254 символа
-   **Password**: минимум 8 символов, максимум 200 символов

### Case-insensitive поиск по email

Контроллер использует case-insensitive поиск пользователя по email через `whereRaw('LOWER(email) = ?', [$email])`. Это позволяет пользователям входить независимо от регистра символов в email (например, `Test@Example.com` и `test@example.com` считаются одинаковыми).

## Rate Limiting

Rate limiter настроен в `RouteServiceProvider`:

```php
RateLimiter::for('login', function (Request $request) {
    $key = 'login:'.Str::lower($request->input('email')).'|'.$request->ip();
    return Limit::perMinute(5)->by($key);
});
```

При превышении лимита возвращается `429 Too Many Requests`.

## Аудит логинов

Все попытки входа (успешные и неуспешные) записываются в таблицу `audits`:

-   **Успешный вход**: `action='login'`, `user_id` = ID пользователя, `subject_id` = ID пользователя
-   **Неуспешный вход**: `action='login_failed'`, `user_id=null`, `subject_id=0`

В обоих случаях записываются:
-   `ip` — IP адрес клиента
-   `ua` — User-Agent
-   `subject_type` — `App\Models\User`

Аудит позволяет отслеживать подозрительную активность и расследовать инциденты безопасности.

## Формат ошибок (RFC 7807)

Все ошибки API возвращаются в формате RFC 7807 (problem+json):

**401 Unauthorized:**
```json
{
    "type": "about:blank",
    "title": "Unauthorized",
    "status": 401,
    "detail": "Invalid credentials."
}
```

**422 Unprocessable Entity:**
```json
{
    "type": "https://stupidcms.dev/problems/validation-error",
    "title": "Validation Failed",
    "status": 422,
    "detail": "The given data was invalid.",
    "errors": {
        "email": ["The email field must be a valid email address."],
        "password": ["The password field must be at least 8 characters."]
    }
}
```

Заголовок `Content-Type: application/problem+json` устанавливается автоматически.

## Тесты

**Файл:** `tests/Feature/AuthLoginTest.php`

Покрытие:

1. **Успешный вход**: проверка установки cookies, атрибутов cookies (HttpOnly, Secure, SameSite) и возврата данных пользователя
2. **Неверные учётные данные**: проверка 401 в формате RFC 7807 без cookies
3. **Неверный пароль**: проверка 401 для существующего пользователя с неверным паролем
4. **Валидация email**: проверка требований к email
5. **Валидация password**: проверка требований к паролю
6. **Case-insensitive email**: проверка, что email приводится к lowercase и поиск работает независимо от регистра
7. **Аудит логинов**: проверка записи успешных и неуспешных попыток входа в таблицу `audits`
8. **Атрибуты cookies**: проверка HttpOnly, Secure (в зависимости от окружения), SameSite (из конфига)

**Примечание по тестированию:**

Тесты автоматически генерируют JWT ключи в `setUp()` методе. Если генерация ключей не удаётся (например, на Windows с проблемами OpenSSL), тесты пропускаются с понятным сообщением. Для успешного запуска тестов необходимо наличие JWT ключей в `storage/keys/jwt-v1-private.pem` и `storage/keys/jwt-v1-public.pem`.

## Список связанных задач

-   **Задача 36**: Cookie-based JWT модель токенов - используется для выпуска access и refresh токенов
-   **Задача 38**: Refresh endpoint - обновление access токена по refresh токену
-   **Задача 39**: Logout/Rotate - выход и ротация токенов
-   **Задача 27**: Policies/Abilities - проверка доступа к API через JWT токены
-   **Задача 80**: Audit - аудит входов и использования токенов

## Критерии приёмки (Definition of Done)

-   [x] Маршрут и контроллер реализованы
-   [x] Cookies `cms_at/cms_rt` с корректными флагами устанавливаются при успехе
-   [x] Неверные креды → 401 без cookies в формате RFC 7807
-   [x] Rate limiting настроен (5 попыток в минуту на связку email+IP)
-   [x] Валидация входных данных реализована
-   [x] Аудит логинов (успешных и неуспешных) реализован
-   [x] Тесты проверяют атрибуты cookies (HttpOnly, Secure, SameSite)
-   [x] Тесты проверяют формат ошибок RFC 7807
-   [x] Тесты проверяют аудит логинов
-   [x] Документация создана с описанием CORS+SameSite для кросс-origin SPA
