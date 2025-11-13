---
title: Admin API — Utils
owner: "@backend-team"
system_of_record: "narrative"
review_cycle_days: 60
last_reviewed: 2025-11-12
related_code:
    - "app/Http/Controllers/Admin/V1/UtilsController.php"
    - "routes/api_admin.php"
---

# Admin API — Utils

> Утилиты для админки: генерация slug, получение списка шаблонов.

## Общие требования

-   **Аутентификация:** admin JWT (`cms_at` cookie) + `X-CSRF-Token`.
-   **Заголовки:** `Cache-Control: private, no-store`, `Vary: Cookie`.
-   **Формат ошибок:** RFC7807 (`application/problem+json`) с расширениями `code`, `meta`, `trace_id`.

## 1. Генерация slug (`/api/v1/admin/utils/slugify`)

### GET `/api/v1/admin/utils/slugify`

Генерирует slug из заголовка с проверкой уникальности.

**Query параметры**:

-   `title` (required, string, max:500) — заголовок для генерации slug
-   `postType` (optional, string) — slug типа поста для проверки уникальности в рамках типа

**Response**: `200 OK`

```json
{
    "base": "new-landing-page",
    "unique": "new-landing-page-2"
}
```

**Описание**:

-   `base` — базовый slug из заголовка
-   `unique` — уникальный slug с суффиксом (если базовый занят)

**Пример**:

```bash
curl -i -X GET \
  --cookie "cms_at=..." \
  -H "X-CSRF-Token: ..." \
  "https://api.example.com/api/v1/admin/utils/slugify?title=Новая%20страница&postType=page"
```

**Ответ**:

```json
{
    "base": "novaya-stranica",
    "unique": "novaya-stranica-2"
}
```

---

## 2. Получение списка шаблонов (`/api/v1/admin/utils/templates`)

### GET `/api/v1/admin/utils/templates`

Возвращает список всех доступных Blade-шаблонов из `resources/views` для назначения PostType или Entry.

**Response**: `200 OK`

```json
{
    "data": [
        "pages.show",
        "home.default",
        "welcome",
        "pages.types.article",
        "pages.types.product"
    ]
}
```

**Описание**:

-   Сканирует директорию `resources/views` рекурсивно
-   Исключает системные директории: `admin`, `errors`, `layouts`, `partials`, `vendor`
-   Конвертирует пути в dot notation (например, `pages/show.blade.php` → `pages.show`)
-   Результаты отсортированы по алфавиту
-   Включает шаблоны из вложенных директорий (например, `pages/types/article.blade.php` → `pages.types.article`)

**Использование**:

-   Шаблоны из этого списка можно назначить в поле `Entry.template_override` при создании/обновлении Entry
-   Формат шаблонов: dot notation (например, `pages.show` соответствует `resources/views/pages/show.blade.php`)

**Пример**:

```bash
curl -i -X GET \
  --cookie "cms_at=..." \
  -H "X-CSRF-Token: ..." \
  "https://api.example.com/api/v1/admin/utils/templates"
```

**Ответ**:

```json
{
    "data": ["home.default", "pages.show", "pages.types.article", "welcome"]
}
```

### Приоритет выбора шаблона

При рендеринге Entry шаблон выбирается по следующему приоритету:

1. **`Entry.template_override`** — переопределение для конкретной записи (если задан и существует)
2. **`entry--{postType}--{slug}`** — специфичный шаблон для конкретной записи (если существует)
3. **`entry--{postType}`** — шаблон для всех записей типа (если существует)
4. **`entry`** — глобальный шаблон по умолчанию

См. `BladeTemplateResolver` и [Post Types → Шаблоны](../../10-concepts/post-types.md#шаблоны-templates) для деталей реализации.

### Структура шаблонов

Шаблоны хранятся в `resources/views` и могут быть организованы в поддиректориях:

```
resources/views/
├── pages/
│   ├── show.blade.php           → pages.show
│   └── types/
│       └── article.blade.php    → pages.types.article
├── home/
│   └── default.blade.php        → home.default
└── welcome.blade.php            → welcome
```

**Исключаются**:

-   `admin/` — административные шаблоны
-   `errors/` — шаблоны ошибок
-   `layouts/` — layout шаблоны
-   `partials/` — частичные шаблоны
-   `vendor/` — шаблоны из пакетов

---

## Обработка ошибок

### 401 Unauthorized

```json
{
    "type": "https://stupidcms.dev/problems/unauthorized",
    "title": "Unauthorized",
    "status": 401,
    "code": "UNAUTHORIZED",
    "detail": "Authentication is required to access this resource.",
    "meta": {
        "request_id": "...",
        "reason": "missing_token"
    },
    "trace_id": "..."
}
```

### 422 Validation Error (slugify)

```json
{
    "type": "https://stupidcms.dev/problems/validation-error",
    "title": "Validation Error",
    "status": 422,
    "code": "VALIDATION_ERROR",
    "detail": "The title field is required.",
    "meta": {
        "request_id": "...",
        "errors": {
            "title": ["The title field is required."]
        }
    },
    "trace_id": "..."
}
```

---

## Связанные страницы

-   [Post Types](../10-concepts/post-types.md) — описание шаблонов для типов постов
-   [Entries](../10-concepts/entries.md) — описание `template_override` для записей
-   Scribe API Reference (`../../_generated/api-docs/index.html`) — полная API документация
