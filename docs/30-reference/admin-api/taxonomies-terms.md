---
title: Admin API — Taxonomies & Terms
owner: "@backend-team"
system_of_record: "narrative"
review_cycle_days: 60
last_reviewed: 2025-11-08
related_code:
    - "app/Http/Controllers/Admin/V1/TaxonomyController.php"
    - "app/Http/Controllers/Admin/V1/TermController.php"
    - "app/Http/Controllers/Admin/V1/EntryTermsController.php"
    - "routes/api_admin.php"
---

# Admin API — Taxonomies & Terms

> Управление справочниками (taxonomies), терминами (terms) и привязками терминов к записям (entry ↔ term).

## Общие требования

-   **Аутентификация:** admin JWT (`cms_at` cookie) + `X-CSRF-Token`.
-   **Заголовки:** `Cache-Control: private, no-store`, `Vary: Cookie`. Все контроллеры выставляют автоматически.
-   **Права доступа:**
    -   `manage.taxonomies` — CRUD таксономий.
    -   `manage.terms` — CRUD терминов + pivot операции.
-   **Формат ошибок:** RFC7807 (`application/problem+json`) c расширениями `code`, `meta`, `trace_id`. См. [Error Payload](../errors.md).

## Модель данных (актуальная)

| Таблица      | Поля (ключевые)                                                                                 |
| ------------ | ----------------------------------------------------------------------------------------------- |
| `taxonomies` | `slug` (unique), `name` (`label` в API), `hierarchical`, `options_json` (json)                  |
| `terms`      | `taxonomy_id`, `name`, `slug` (уникален в пределах taxonomy, если не soft-deleted), `meta_json` |
| `entry_term` | `entry_id`, `term_id`, `created_at`, `updated_at`                                               |

## 1. Таксономии (`/api/v1/admin/taxonomies`)

### Список

-   **GET** `/api/v1/admin/taxonomies`
-   Query:
    -   `q` — фильтр по `slug`/`label`.
    -   `per_page` (10..100), default `15`.
    -   `sort` ∈ `created_at.desc|created_at.asc|slug.asc|slug.desc|label.asc|label.desc`.

```bash
curl -i -X GET \
  --cookie "cms_at=..." \
  -H "X-CSRF-Token: ..." \
  https://api.example.com/api/v1/admin/taxonomies?q=cat&sort=label.asc
```

Ответ (`200`):

```json
{
    "data": [
        {
            "slug": "categories",
            "label": "Categories",
            "hierarchical": true,
            "options_json": {},
            "created_at": "...",
            "updated_at": "..."
        }
    ],
    "links": { "...": "..." },
    "meta": { "current_page": 1, "per_page": 15, "total": 1 }
}
```

### Создание

-   **POST** `/api/v1/admin/taxonomies`
-   Body:
    -   `label` (string, required)
    -   `slug` (optional, `[a-z0-9_-]+`, unique, `ReservedSlug`)
    -   `hierarchical` (bool, default `false`)
    -   `options_json` (object, optional)

```bash
curl -i -X POST \
  --cookie "cms_at=..." \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: ..." \
  -d '{"label":"Категории","hierarchical":true}' \
  https://api.example.com/api/v1/admin/taxonomies
```

Ответ (`201`) содержит сгенерированный slug.

### Просмотр / обновление / удаление

-   **GET** `/api/v1/admin/taxonomies/{slug}`
-   **PUT** `/api/v1/admin/taxonomies/{slug}` — те же поля, все опциональны. `slug = null` → сгенерировать заново из `label`.
-   **DELETE** `/api/v1/admin/taxonomies/{slug}`
    -   Без параметров: если есть активные термины — `409`.
    -   `?force=1` — удаляет таксономию, force-delete всех связанных терминов, очищает pivot.

## 2. Термины (`/api/v1/admin/taxonomies/{taxonomy}/terms`, `/api/v1/admin/terms/{id}`)

### Список терминов таксономии

-   **GET** `/api/v1/admin/taxonomies/{taxonomy}/terms`
-   Query:
    -   `q` — фильтр по `name`/`slug`.
    -   `per_page` (10..100).
    -   `sort` ∈ `created_at.desc|created_at.asc|name.asc|name.desc|slug.asc|slug.desc`.

### Создание термина

-   **POST** `/api/v1/admin/taxonomies/{taxonomy}/terms`
-   Body:
    -   `name` (required)
    -   `slug` (optional, `[a-z0-9][a-z0-9_-]*`)
    -   `meta_json` (optional object)
    -   `parent_id` (optional int) — ID родительского термина (только для иерархических таксономий). Должен принадлежать той же таксономии.
    -   `attach_entry_id` (optional int) — моментальная привязка к записи.

```
POST /api/v1/admin/taxonomies/topics/terms
{
  "name": "Analytics",
  "parent_id": 5,
  "attach_entry_id": 123
}
```

Ответ (`201`):

```json
{
    "data": {
        "id": 11,
        "taxonomy": "topics",
        "name": "Analytics",
        "slug": "analytics",
        "parent_id": 5
    },
    "entry_terms": {
        "entry_id": 123,
        "terms": [
            {
                "id": 11,
                "taxonomy": "topics",
                "name": "Analytics",
                "slug": "analytics"
            }
        ],
        "terms_by_taxonomy": {
            "topics": [{ "id": 11, "name": "Analytics", "slug": "analytics" }]
        }
    }
}
```

### Получение дерева терминов

-   **GET** `/api/v1/admin/taxonomies/{taxonomy}/terms/tree`
-   Возвращает иерархическое дерево терминов (только для иерархических таксономий).
-   Для неиерархических таксономий возвращает плоский список.

Ответ (`200`):

```json
{
    "data": [
        {
            "id": 1,
            "taxonomy": "categories",
            "name": "Технологии",
            "slug": "tech",
            "parent_id": null,
            "children": [
                {
                    "id": 2,
                    "taxonomy": "categories",
                    "name": "Laravel",
                    "slug": "laravel",
                    "parent_id": 1,
                    "children": []
                }
            ]
        }
    ]
}
```

### Просмотр / обновление / удаление

-   **GET** `/api/v1/admin/terms/{id}`
    -   Возвращает термин с полем `parent_id` (для иерархических таксономий).
-   **PUT** `/api/v1/admin/terms/{id}` — `name`, `slug`, `meta_json`, `parent_id` (optional).
    -   При изменении `parent_id` автоматически обновляется `term_tree` (Closure Table).
    -   `parent_id` должен принадлежать той же таксономии.
    -   Нельзя сделать родителем самого себя или потомка.
-   **DELETE** `/api/v1/admin/terms/{id}`
    -   Если термин привязан к записям → `409`.
    -   `?forceDetach=1` — `detach` всех связей + soft-delete.

## 3. Привязка терминов к записям (`/api/v1/admin/entries/{entry}/terms`)

> Требуется `manage.terms`. Проверяется, что taxonomy допустима для `Entry.postType.options_json['taxonomies']`.

### Получить текущие термины записи

-   **GET** `/api/v1/admin/entries/{entry}/terms`
-   Ответ (`200`):

```json
{
    "data": {
        "entry_id": 123,
        "terms": [
            {
                "id": 7,
                "taxonomy": "topics",
                "name": "Analytics",
                "slug": "analytics"
            }
        ],
        "terms_by_taxonomy": {
            "topics": [{ "id": 7, "name": "Analytics", "slug": "analytics" }]
        }
    }
}
```

### Операции

| Метод | Путь                            | Назначение                                              |
| ----- | ------------------------------- | ------------------------------------------------------- |
| POST  | `/entries/{entry}/terms/attach` | Добавить `term_ids[]` (min 1)                           |
| POST  | `/entries/{entry}/terms/detach` | Удалить `term_ids[]`                                    |
| PUT   | `/entries/{entry}/terms/sync`   | Полная замена набора `term_ids[]` (можно пустой массив) |

Все операции возвращают обновлённую структуру `entry_terms` (как в примере выше).

## Тесты

Покрытие (Pest/PHPUnit):

-   `tests/Feature/Admin/Taxonomies/CrudTaxonomiesTest.php`
-   `tests/Feature/Admin/Terms/CrudTermsTest.php`
-   `tests/Feature/Admin/Terms/AttachPivotTest.php`
-   `tests/Feature/Admin/Terms/AttachDetachSyncTest.php`

Команда для выборочного запуска:

```bash
vendor/bin/phpunit --testsuite Feature --filter Taxonomies
vendor/bin/phpunit --testsuite Feature --filter Terms
```

---

**Связанные артефакты:**

-   Контроллеры: `app/Http/Controllers/Admin/V1/*`
-   Форм-реквесты: `app/Http/Requests/Admin/*`
-   Ресурсы: `app/Http/Resources/Admin/*`
-   Правила: `app/Rules/UniqueTermSlug.php`
-   Миграции: `database/migrations/*taxonomies*`, `*terms*`, `*entry_term*`
