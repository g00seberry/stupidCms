---
owner: "@backend-team"
system_of_record: "generated"
review_cycle_days: 14
last_reviewed: 2025-11-08
related_code:
    - "app/Http/Controllers/*.php"
    - "app/Http/Requests/*.php"
---

# API Reference

## Генерация

```bash
# через composer
composer docs:gen
```

## Endpoints Overview

### Public API (`/api/*`)

#### Entries

-   `GET /api/entries` — Список опубликованных entries
-   `GET /api/entries/{slug}` — Entry по slug

#### Post Types

-   `GET /api/post-types` — Список типов контента

#### Taxonomies & Terms

-   `GET /api/taxonomies` — Список таксономий
-   `GET /api/taxonomies/{slug}/terms` — Термины таксономии
-   `GET /api/terms/{id}` — Термин по ID

#### Search

-   `GET /api/search` — Полнотекстовый поиск

#### Options

-   `GET /api/options` — Публичные настройки сайта

---

### Admin API (`/api/admin/*`)

> 🔒 **Требуется аутентификация** (JWT Bearer token)

#### Entries

-   `POST /api/admin/entries` — Создать entry
-   `PUT /api/admin/entries/{id}` — Обновить entry
-   `DELETE /api/admin/entries/{id}` — Удалить entry
-   `GET /api/admin/entries/{id}/slugs` — История slugs

#### Media

-   `POST /api/admin/media` — Загрузить медиафайл
-   `PUT /api/admin/media/{id}` — Обновить метаданные
-   `DELETE /api/admin/media/{id}` — Удалить медиафайл
-   `GET /api/admin/media` — Список медиа

#### Terms

-   `POST /api/admin/terms` — Создать термин
-   `PUT /api/admin/terms/{id}` — Обновить термин
-   `DELETE /api/admin/terms/{id}` — Удалить термин

#### Post Types

-   `POST /api/admin/post-types` — Создать Post Type
-   `PUT /api/admin/post-types/{id}` — Обновить Post Type

#### Options

-   `PUT /api/admin/options/{key}` — Обновить настройку

---

### Auth (`/api/auth/*`)

-   `POST /api/auth/login` — Вход (получить JWT)
-   `POST /api/auth/refresh` — Обновить токен
-   `POST /api/auth/logout` — Выход
-   `GET /api/auth/me` — Информация о текущем пользователе

---

## Authentication

### JWT Bearer Token

```http
Authorization: Bearer <your-jwt-token>
```

### Получение токена

```bash
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password"
}
```

**Response**:

```json
{
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "bearer",
    "expires_in": 3600,
    "refresh_token": "def502..."
}
```

### Обновление токена

```bash
POST /api/auth/refresh
Cookie: refresh_token=def502...
```

Подробнее: [Security](../40-architecture/security.md)

---

## Response Format

### Success (200/201)

```json
{
  "data": {
    "id": 1,
    "title": "Entry Title",
    ...
  }
}
```

Для коллекций:

```json
{
  "data": [
    {"id": 1, ...},
    {"id": 2, ...}
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "total": 100,
    "per_page": 20
  }
}
```

### Error (4xx/5xx)

RFC7807 Problem Details:

```json
{
    "type": "https://api.stupidcms.local/errors/validation",
    "title": "Validation Error",
    "status": 422,
    "detail": "The given data was invalid.",
    "errors": {
        "title": ["The title field is required."]
    }
}
```

Подробнее: [Errors Reference](errors.md)

---

## Rate Limiting

-   **Public API**: 60 запросов/минуту
-   **Admin API**: 120 запросов/минуту (для авторизованных)

При превышении: `429 Too Many Requests` с заголовком `Retry-After`.

---

## Pagination

Все list endpoints поддерживают пагинацию:

```
GET /api/entries?page=2&per_page=20
```

**Query параметры**:

-   `page` — номер страницы (default: 1)
-   `per_page` — результатов на страницу (default: 20, max: 100)

---

## Filtering & Sorting

### Фильтрация

```
GET /api/entries?post_type=article&term_id=5
```

### Сортировка

```
GET /api/entries?sort=-published_at
```

-   Префикс `-` для DESC
-   Без префикса — ASC

---

## CORS

Настройки CORS в `config/cors.php`.

Для локальной разработки:

```env
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:5173
```

Подробнее: [CORS & Cookies](../20-how-to/cors.md)

---

## Testing API

### cURL

```bash
curl -X GET https://api.stupidcms.local/api/entries \
  -H "Accept: application/json"
```

### HTTPie

```bash
http GET https://api.stupidcms.local/api/entries \
  Accept:application/json
```

---

## Linked Pages

-   [Errors Reference](errors.md) — коды ошибок
-   [Permissions](permissions.md) — права доступа
-   [How-to: CORS](../20-how-to/cors.md) — настройка CORS
-   [Security](../40-architecture/security.md) — аутентификация

---

> 💡 **Актуальность**: API документация генерируется из кода. При изменении endpoints обновите через `composer docs:gen`.
