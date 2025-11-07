# Задача 43. Глобальный RFC 7807 Handler

## Краткое описание функционала

Реализован глобальный обработчик ошибок в формате RFC 7807 (Problem Details for HTTP APIs) для всех API endpoints. Все ошибки возвращаются в едином формате problem+json.

**Основные характеристики:**

- **Формат**: RFC 7807 (Problem Details for HTTP APIs)
- **Content-Type**: `application/problem+json`
- **Покрытие**: 401, 403, 404, 422, 429, 500
- **Унификация**: единый формат для всех ошибок API

**Обрабатываемые ошибки:**

- **422 Unprocessable Entity**: ошибки валидации
- **401 Unauthorized**: ошибки аутентификации
- **403 Forbidden**: ошибки авторизации
- **404 Not Found**: маршрут не найден
- **429 Too Many Requests**: превышен rate limit
- **500 Internal Server Error**: внутренние ошибки (через trait Problems)

## Структура файлов

### Exception Handler

```
bootstrap/app.php - Глобальный обработчик исключений в withExceptions()
```

### Trait

```
app/Http/Controllers/Traits/Problems.php - Trait для problem+json ответов (используется в контроллерах)
```

## Формат ответов

### 422 Unprocessable Entity (Validation Error)

```json
{
    "type": "about:blank",
    "title": "Unprocessable Entity",
    "status": 422,
    "detail": "Validation failed.",
    "errors": {
        "email": ["The email field is required."],
        "password": ["The password must be at least 8 characters."]
    }
}
```

### 401 Unauthorized (Authentication Error)

```json
{
    "type": "about:blank",
    "title": "Unauthorized",
    "status": 401,
    "detail": "Authentication required."
}
```

### 403 Forbidden (Authorization Error)

```json
{
    "type": "about:blank",
    "title": "Forbidden",
    "status": 403,
    "detail": "Forbidden."
}
```

### 404 Not Found (Route Not Found)

```json
{
    "type": "about:blank",
    "title": "Not Found",
    "status": 404,
    "detail": "Route not found."
}
```

### 429 Too Many Requests (Rate Limit)

```json
{
    "type": "about:blank",
    "title": "Too Many Requests",
    "status": 429,
    "detail": "Rate limit exceeded."
}
```

### 500 Internal Server Error

```json
{
    "type": "about:blank",
    "title": "Internal Server Error",
    "status": 500,
    "detail": "An error occurred while processing your request."
}
```

## Реализация

### Глобальный Handler (bootstrap/app.php)

```php
->withExceptions(function (Exceptions $exceptions): void {
    // 422 Unprocessable Entity - Validation errors
    $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Unprocessable Entity',
                'status' => 422,
                'detail' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422)->header('Content-Type', 'application/problem+json');
        }
    });

    // 401 Unauthorized - Authentication errors
    $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => $e->getMessage() ?: 'Authentication required.',
            ], 401)->header('Content-Type', 'application/problem+json');
        }
    });

    // ... остальные обработчики
})
```

### Trait Problems

Для контроллеров доступен trait `Problems` с методами:

```php
use App\Http\Controllers\Traits\Problems;

class MyController
{
    use Problems;

    public function method()
    {
        return $this->unauthorized('Missing token.');
        return $this->internalError('Database error.');
        return $this->tooManyRequests('Rate limit exceeded.');
        return $this->problem(403, 'Forbidden', 'Access denied.');
    }
}
```

## Применение

### Автоматическая обработка

Все исключения автоматически обрабатываются глобальным handler'ом для:
- Запросов с `Accept: application/json`
- Запросов к путям `api/*`

### Ручная обработка в контроллерах

Контроллеры могут использовать trait `Problems` для явного возврата problem+json:

```php
final class RefreshController
{
    use Problems;

    public function refresh(Request $request): JsonResponse
    {
        if (!$token) {
            return $this->unauthorized('Missing refresh token.');
        }

        try {
            // ...
        } catch (Throwable $e) {
            report($e);
            return $this->internalError('Failed to refresh token.');
        }
    }
}
```

## Тесты

**Файл:** `tests/Feature/Rfc7807ErrorTest.php`

Покрытие (5 тестов):

1. **422 Validation Error**: проверка формата problem+json для ошибок валидации
2. **404 Not Found**: проверка формата problem+json для несуществующих маршрутов
3. **429 Rate Limit**: проверка формата problem+json при превышении rate limit
4. **401 Unauthorized**: проверка формата problem+json для ошибок аутентификации
5. **403 Forbidden**: проверка формата problem+json для ошибок авторизации

## Преимущества

### Унификация

Все ошибки API возвращаются в едином формате, что упрощает:
- Обработку ошибок на клиенте
- Документирование API
- Отладку проблем

### Соответствие стандартам

RFC 7807 — стандарт IETF для описания проблем в HTTP API:
- Широко поддерживается
- Хорошо документирован
- Совместим с существующими инструментами

### Расширяемость

Формат позволяет добавлять дополнительные поля:

```json
{
    "type": "about:blank",
    "title": "Unprocessable Entity",
    "status": 422,
    "detail": "Validation failed.",
    "errors": {...},
    "instance": "/api/v1/users",
    "trace_id": "abc123"
}
```

## Список связанных задач

- **Задача 36**: Cookie-based JWT модель токенов
- **Задача 37**: Login endpoint
- **Задача 38**: Token Refresh
- **Задача 39**: Logout
- **Задача 41**: Admin Auth Middleware

## Критерии приёмки (Definition of Done)

- [x] Глобальный handler реализован в `bootstrap/app.php`
- [x] Обработка 422 (ValidationException)
- [x] Обработка 401 (AuthenticationException)
- [x] Обработка 403 (AuthorizationException)
- [x] Обработка 404 (NotFoundHttpException)
- [x] Обработка 429 (ThrottleRequestsException)
- [x] Trait Problems для контроллеров
- [x] Все ответы в формате problem+json
- [x] Тесты покрывают все типы ошибок
- [x] Документация создана

