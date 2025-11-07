# Задача 41. Admin Auth Middleware

## Краткое описание функционала

Реализован middleware `admin.auth` для защиты администраторских API endpoints. Middleware проверяет:
- **JWT access token** из cookie
- **Audience (aud)** должен быть `admin`
- **Scope (scp)** должен включать `admin`
- **Роль пользователя** в БД должна быть `is_admin = true`

**Основные характеристики:**

- **Middleware**: `admin.auth`
- **Безопасность**: проверка токена, audience, scope и роли
- **Формат ошибок**: RFC 7807 (problem+json)
- **Аутентификация**: устанавливает пользователя через `Auth::setUser()`

**Ответы:**

- **200 OK**: успешная аутентификация, доступ разрешён
- **401 Unauthorized**: отсутствие токена или невалидный токен
- **403 Forbidden**: недостаточные права (неправильный aud/scp или не админ)

## Структура файлов

### Middleware

```
app/Http/Middleware/AdminAuth.php - Middleware для проверки админских прав
```

### Регистрация

```
bootstrap/app.php - Регистрация middleware alias 'admin.auth'
routes/api_admin.php - Использование middleware на админских роутах
```

## Использование

### Защита админских роутов

```php
// routes/api_admin.php
Route::middleware(['admin.auth', 'throttle:api'])->group(function () {
    Route::get('/dashboard', Admin\DashboardController::class);
    Route::get('/users', Admin\UsersController::class);
});
```

### Требования к токену

Для доступа к админским endpoints требуется access token с:
- `aud = 'admin'` (audience)
- `scp = ['admin']` (scope включает 'admin')
- Пользователь должен иметь `is_admin = true` в БД

**Пример выпуска админского токена:**

```php
$jwtService = app(\App\Domain\Auth\JwtService::class);
$adminToken = $jwtService->issueAccessToken($userId, [
    'aud' => 'admin',
    'scp' => ['admin'],
]);
```

## Безопасность

### Многоуровневая проверка

Middleware выполняет проверки на нескольких уровнях:

1. **Наличие токена**: cookie `cms_at` должен присутствовать
2. **Валидность токена**: токен должен быть валидным (подпись, expiration)
3. **Audience**: `aud` должен быть `'admin'` (не `'api'`)
4. **Scope**: `scp` должен включать `'admin'`
5. **Роль в БД**: пользователь должен иметь `is_admin = true`

Это обеспечивает защиту от:
- Использования обычных токенов для доступа к админке
- Компрометации токена (даже если токен валиден, нужна роль в БД)
- Изменения роли после выпуска токена

### Формат ошибок

Все ошибки возвращаются в формате RFC 7807 (problem+json):

**401 Unauthorized:**
```json
{
    "type": "about:blank",
    "title": "Unauthorized",
    "status": 401,
    "detail": "Missing access token."
}
```

**403 Forbidden:**
```json
{
    "type": "about:blank",
    "title": "Forbidden",
    "status": 403,
    "detail": "Insufficient scope."
}
```

или

```json
{
    "type": "about:blank",
    "title": "Forbidden",
    "status": 403,
    "detail": "Admin role required."
}
```

## API Reference

### AdminAuth Middleware

#### `handle(Request $request, Closure $next)`

Проверить аутентификацию и права доступа для админских endpoints.

**Параметры:**
- `$request` — HTTP запрос
- `$next` — следующий middleware/контроллер

**Возвращает:**
- **Продолжение запроса**: если все проверки пройдены
- **401 Unauthorized**: отсутствие токена или невалидный токен
- **403 Forbidden**: недостаточные права (неправильный aud/scp или не админ)

**Алгоритм:**
1. Извлечь access token из cookie `cms_at`
2. Если токен отсутствует → вернуть 401
3. Верифицировать JWT токен
4. Если токен невалиден → вернуть 401
5. Проверить `aud = 'admin'` и `scp` включает `'admin'`
6. Если проверка не прошла → вернуть 403 "Insufficient scope"
7. Найти пользователя в БД по `sub`
8. Проверить `is_admin = true`
9. Если проверка не прошла → вернуть 403 "Admin role required"
10. Установить пользователя через `Auth::setUser($user)`
11. Продолжить выполнение запроса

## Тесты

**Файл:** `tests/Feature/AdminAuthTest.php`

Покрытие (5 тестов):

1. **Без токена**: проверка 401 при отсутствии access token
2. **Невалидный токен**: проверка 401 при невалидном JWT
3. **Обычный токен**: проверка 403 при использовании обычного токена (aud=api, scp=['api'])
4. **Админский токен, но не админ**: проверка 403 при админском токене, но пользователь не админ
5. **Валидный админский токен**: проверка успешного доступа с валидным админским токеном и админским пользователем

## Список связанных задач

- **Задача 36**: Cookie-based JWT модель токенов - базовая инфраструктура JWT
- **Задача 37**: Login endpoint - выпуск токенов
- **Задача 38**: Token Refresh - обновление токенов
- **Задача 39**: Logout - выход из системы

## Критерии приёмки (Definition of Done)

- [x] AdminAuth middleware реализован
- [x] Проверка access token из cookie
- [x] Проверка `aud = 'admin'`
- [x] Проверка `scp` включает `'admin'`
- [x] Проверка роли `is_admin` в БД
- [x] Возврат 401/403 в формате RFC 7807
- [x] Middleware зарегистрирован как `admin.auth`
- [x] Использован в `routes/api_admin.php`
- [x] Тесты покрывают все сценарии
- [x] Документация создана

