# Краткое описание функционала

Middleware `admin.auth` защищает административные эндпоинты `/api/v1/admin/*`. Оно извлекает access JWT из cookie `cms_at`, верифицирует подпись, срок действия, `iss` и тип `typ=access`, а также проверяет, что токен выпущен для аудитории `admin` и содержит scope `admin`. После проверки middleware находит пользователя, убеждается в наличии флага `is_admin`, помещает пользователя в `Auth` и при ошибках возвращает ответы в формате RFC 7807 с корректными статусами 401/403.

# Структура файлов

- `app/Http/Middleware/AdminAuth.php` — основная логика проверки JWT, прав администратора, выбор guard `admin`, логирование причин отказа и формирование RFC 7807-ответов.
- `bootstrap/app.php` — регистрация алиаса middleware `admin.auth` в конфигурации HTTP-стека.
- `routes/api_admin.php` — административная группа маршрутов, которая применяет middleware `admin.auth`.
- `app/Support/ProblemDetails.php` — централизованные константы/фабрики для RFC 7807 ответов (Unauthorized/Forbidden).
- `tests/Feature/AdminAuthTest.php` — feature-тесты, покрывающие 401/403 сценарии и успешный доступ администратора.
- `tests/Feature/Rfc7807ErrorTest.php` — проверка глобального формата ошибок, включая заголовок `WWW-Authenticate` для 401.

# Связанные задачи

- Задача 27 — Политики доступа.
- Задача 36 — Cookie-based JWT токены.
- Задача 37 — Auth login.
- Задача 38 — Token refresh.
- Задача 39 — Auth logout.
- Задача 40 — CSRF защита.
- Задача 43 — Глобальный обработчик RFC 7807.

