# API Документация для фронтенда

> **Версия:** 1.0  
> **Базовый URL:** `/api/v1/admin`  
> **Аутентификация:** JWT токен в заголовке `Authorization: Bearer {token}`

---

## Оглавление

1. [Аутентификация](#аутентификация)
2. [Blueprint API](#blueprint-api)
3. [Path API](#path-api)
4. [Blueprint Embed API](#blueprint-embed-api)
5. [Entry API](#entry-api)
6. [PostType API](#posttype-api)
7. [Template API](#template-api)
8. [Media API](#media-api)
9. [Taxonomy & Terms API](#taxonomy--terms-api)
10. [Options API](#options-api)
11. [Ограничения системы](#ограничения-системы)

---

## Аутентификация

Все запросы к админскому API требуют JWT токен в заголовке:

```
Authorization: Bearer {token}
```

**Rate Limiting:**

-   Общий лимит: 120 запросов в минуту
-   Логин/Refresh: 20 запросов в минуту
-   Поиск: 240 запросов в минуту (публичный), 10 запросов в минуту (реиндексация)

---

## Blueprint API

### GET `/blueprints`

Список Blueprint с фильтрацией и пагинацией.

**Query параметры:**

-   `search` (string, optional) - Поиск по name/code
-   `sort_by` (string, optional) - Поле сортировки: `created_at`, `name`, `code`. Default: `created_at`
-   `sort_dir` (string, optional) - Направление: `asc`, `desc`. Default: `desc`
-   `per_page` (int, optional) - Записей на страницу (10-100). Default: 15

**Ответ (200):**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Article",
      "code": "article",
      "description": "Blog article structure",
      "paths_count": 5,
      "embeds_count": 2,
      "created_at": "2025-01-10T12:00:00+00:00",
      "updated_at": "2025-01-10T12:00:00+00:00"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

### POST `/blueprints`

Создать Blueprint.

**Body:**

```json
{
    "name": "Article", // required, string, max:255
    "code": "article", // required, string, max:255, unique, regex: /^[a-z0-9_]+$/
    "description": "..." // optional, string, max:1000
}
```

**Ограничения:**

-   `code`: только строчные буквы, цифры и подчёркивания
-   `code`: должен быть уникальным

**Ответ (201):**

```json
{
    "data": {
        "id": 1,
        "name": "Article",
        "code": "article",
        "description": "...",
        "created_at": "2025-01-10T12:00:00+00:00",
        "updated_at": "2025-01-10T12:00:00+00:00"
    }
}
```

### GET `/blueprints/{id}`

Просмотр Blueprint.

**Ответ (200):**

```json
{
    "data": {
        "id": 1,
        "name": "Article",
        "code": "article",
        "description": "...",
        "paths_count": 5,
        "embeds_count": 2,
        "embedded_in_count": 1,
        "post_types_count": 3,
        "post_types": [{ "id": 1, "slug": "article", "name": "Article" }],
        "created_at": "2025-01-10T12:00:00+00:00",
        "updated_at": "2025-01-10T12:00:00+00:00"
    }
}
```

### PUT `/blueprints/{id}`

Обновить Blueprint.

**Body:** (все поля optional)

```json
{
    "name": "Article Updated", // string, max:255
    "code": "article_updated", // string, max:255, unique, regex: /^[a-z0-9_]+$/
    "description": "..." // string, max:1000
}
```

### DELETE `/blueprints/{id}`

Удалить Blueprint.

**Ответ (200):**

```json
{
    "message": "Blueprint удалён"
}
```

**Ответ (422):** Если нельзя удалить

```json
{
    "message": "Невозможно удалить blueprint",
    "reasons": ["Используется в 3 PostType", "Встроен в 2 других blueprint"]
}
```

### GET `/blueprints/{id}/can-delete`

Проверить возможность удаления.

**Ответ (200):**

```json
{
    "can_delete": false,
    "reasons": ["Используется в 3 PostType"]
}
```

### GET `/blueprints/{id}/dependencies`

Получить граф зависимостей.

**Ответ (200):**

```json
{
    "depends_on": [{ "id": 2, "code": "address", "name": "Address" }],
    "depended_by": [{ "id": 5, "code": "company", "name": "Company" }]
}
```

### GET `/blueprints/{id}/embeddable`

Получить список Blueprint, которые можно встроить (исключая циклические зависимости).

**Ответ (200):**

```json
{
    "data": [{ "id": 2, "code": "address", "name": "Address" }]
}
```

---

## Path API

### GET `/blueprints/{blueprint_id}/paths`

Список Path для Blueprint (дерево).

**Ответ (200):**

```json
{
    "data": [
        {
            "id": 1,
            "blueprint_id": 1,
            "parent_id": null,
            "name": "title",
            "full_path": "title",
            "data_type": "string",
            "cardinality": "one",
            "is_required": true,
            "is_indexed": true,
            "is_readonly": false,
            "sort_order": 0,
            "validation_rules": null,
            "source_blueprint_id": null,
            "source_blueprint": null,
            "blueprint_embed_id": null,
            "children": [],
            "created_at": "2025-01-10T12:00:00+00:00",
            "updated_at": "2025-01-10T12:00:00+00:00"
        }
    ]
}
```

**Дополнительные поля (для скопированных полей):**

-   `validation_rules` (array|null) - Правила валидации поля
-   `source_blueprint_id` (int|null) - ID исходного blueprint (для скопированных полей)
-   `source_blueprint` (object|null) - Исходный blueprint `{id, code, name}` (когда загружен связью)
-   `blueprint_embed_id` (int|null) - ID встраивания (для скопированных полей)

### POST `/blueprints/{blueprint_id}/paths`

Создать Path.

**Body:**

```json
{
    "name": "title", // required, string, max:255, regex: /^[a-z0-9_]+$/
    "parent_id": 5, // optional, integer, exists:paths,id
    "data_type": "string", // required, enum: string,text,int,float,bool,date,datetime,json,ref
    "cardinality": "one", // optional, enum: one,many, default: one
    "is_required": false, // optional, boolean, default: false
    "is_indexed": false, // optional, boolean, default: false
    "sort_order": 0, // optional, integer, min:0, default: 0
    "validation_rules": {} // optional, array
}
```

**Ограничения:**

-   `name`: только строчные буквы, цифры и подчёркивания
-   `data_type`: один из указанных типов

**Ответ (201):**

```json
{
    "data": {
        "id": 1,
        "blueprint_id": 1,
        "parent_id": null,
        "name": "title",
        "full_path": "title",
        "data_type": "string",
        "cardinality": "one",
        "is_required": true,
        "is_indexed": true,
        "is_readonly": false,
        "sort_order": 0,
        "validation_rules": null,
        "source_blueprint_id": null,
        "source_blueprint": null,
        "blueprint_embed_id": null,
        "children": [],
        "created_at": "2025-01-10T12:00:00+00:00",
        "updated_at": "2025-01-10T12:00:00+00:00"
    }
}
```

### GET `/paths/{id}`

Просмотр Path.

**Ответ (200):**

```json
{
    "data": {
        "id": 1,
        "blueprint_id": 1,
        "parent_id": null,
        "name": "title",
        "full_path": "title",
        "data_type": "string",
        "cardinality": "one",
        "is_required": true,
        "is_indexed": true,
        "is_readonly": false,
        "sort_order": 0,
        "validation_rules": { "max": 500 },
        "source_blueprint_id": 2,
        "source_blueprint": {
            "id": 2,
            "code": "address",
            "name": "Address"
        },
        "blueprint_embed_id": 5,
        "children": [],
        "created_at": "2025-01-10T12:00:00+00:00",
        "updated_at": "2025-01-10T12:00:00+00:00"
    }
}
```

**Примечание:** Для скопированных полей (из встроенных blueprint) будут заполнены `source_blueprint_id`, `source_blueprint` (если загружен), `blueprint_embed_id`.

### PUT `/paths/{id}`

Обновить Path.

**Body:** (все поля optional)

```json
{
    "name": "title_updated", // string, max:255, regex: /^[a-z0-9_]+$/
    "parent_id": 5, // integer, exists:paths,id
    "data_type": "string", // enum: string,text,int,float,bool,date,datetime,json,ref
    "cardinality": "one", // enum: one,many
    "is_required": false, // boolean
    "is_indexed": false, // boolean
    "sort_order": 0, // integer, min:0
    "validation_rules": {} // array
}
```

**Ограничения:**

-   Нельзя редактировать скопированные поля (`is_readonly: true`)

**Ответ (422):**

```json
{
    "message": "Невозможно редактировать скопированное поле 'author.contacts.phone'. Измените исходное поле в blueprint 'contact_info'."
}
```

### DELETE `/paths/{id}`

Удалить Path.

**Ограничения:**

-   Нельзя удалить скопированные поля (`is_readonly: true`)

**Ответ (422):**

```json
{
    "message": "Невозможно удалить скопированное поле 'author.contacts.phone'. Удалите встраивание в blueprint 'article'."
}
```

---

## Blueprint Embed API

### GET `/blueprints/{blueprint_id}/embeds`

Список встраиваний Blueprint.

**Ответ (200):**

```json
{
    "data": [
        {
            "id": 1,
            "blueprint_id": 1,
            "embedded_blueprint_id": 2,
            "host_path_id": 5,
            "embedded_blueprint": {
                "id": 2,
                "code": "address",
                "name": "Address"
            },
            "host_path": {
                "id": 5,
                "name": "office",
                "full_path": "office"
            },
            "created_at": "2025-01-10T12:00:00+00:00",
            "updated_at": "2025-01-10T12:00:00+00:00"
        }
    ]
}
```

### POST `/blueprints/{blueprint_id}/embeds`

Создать встраивание.

**Body:**

```json
{
    "embedded_blueprint_id": 2, // required, integer, exists:blueprints,id
    "host_path_id": 5 // optional, integer, exists:paths,id (null = корень)
}
```

**Ограничения:**

-   Нельзя встроить blueprint в самого себя
-   Нельзя создать циклическую зависимость
-   Нельзя создать конфликт путей (одинаковые `full_path`)

**Ответ (201):**

```json
{
  "data": {
    "id": 1,
    "blueprint_id": 1,
    "embedded_blueprint_id": 2,
    "host_path_id": 5,
    "embedded_blueprint": {...},
    "host_path": {...},
    "created_at": "2025-01-10T12:00:00+00:00",
    "updated_at": "2025-01-10T12:00:00+00:00"
  }
}
```

**Ответ (422):**

```json
{
    "message": "Циклическая зависимость: 'address' уже зависит от 'article'"
}
```

```json
{
    "message": "Невозможно встроить blueprint 'address' в 'article': конфликт путей: 'email'"
}
```

### GET `/embeds/{id}`

Просмотр встраивания.

**Ответ (200):** Аналогично созданию

### DELETE `/embeds/{id}`

Удалить встраивание.

**Ответ (200):**

```json
{
    "message": "Встраивание удалено"
}
```

---

## Entry API

### GET `/entries`

Список записей с фильтрами и пагинацией.

**Query параметры:**

-   `post_type` (string, optional) - Фильтр по slug PostType
-   `status` (string, optional) - Фильтр по статусу: `all`, `draft`, `published`, `scheduled`, `trashed`. Default: `all`
-   `q` (string, optional) - Поиск по названию/slug (<=500 символов)
-   `author_id` (int, optional) - ID автора
-   `term[]` (int[], optional) - ID термов (множественный фильтр)
-   `date_field` (string, optional) - Поле даты: `updated`, `published`. Default: `updated`
-   `date_from` (date, optional) - Начальная дата (ISO 8601)
-   `date_to` (date, optional) - Конечная дата (>= date_from)
-   `sort` (string, optional) - Сортировка: `updated_at.desc`, `updated_at.asc`, `published_at.desc`, `published_at.asc`, `title.asc`, `title.desc`. Default: `updated_at.desc`
-   `per_page` (int, optional) - Количество элементов (10-100). Default: 15

**Ответ (200):**

```json
{
  "data": [
    {
      "id": 42,
      "post_type": "article",
      "title": "Headless CMS launch checklist",
      "slug": "launch-checklist",
      "status": "draft",
      "content_json": {},
      "meta_json": {},
      "is_published": false,
      "published_at": null,
      "template_override": null,
      "author": {
        "id": 7,
        "name": "Admin User"
      },
      "terms": [
        {
          "id": 3,
          "name": "Guides",
          "taxonomy": 1
        }
      ],
      "created_at": "2025-01-10T12:00:00+00:00",
      "updated_at": "2025-01-10T12:00:00+00:00",
      "deleted_at": null
    }
  ],
  "links": {...},
  "meta": {...}
}
```

### POST `/entries`

Создать запись.

**Body:**

```json
{
    "post_type": "article", // required, string, exists:post_types,slug
    "title": "Headless CMS...", // required, string, max:500
    "slug": "launch-checklist", // optional, string, max:255, regex: /^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/, unique в рамках post_type
    "content_json": {}, // optional, array
    "meta_json": {}, // optional, array
    "is_published": false, // optional, boolean
    "published_at": "2025-02-10T08:00:00Z", // optional, datetime (ISO 8601)
    "template_override": "templates.landing", // optional, string, max:255
    "term_ids": [3, 8] // optional, array of integers, exists:terms,id
}
```

**Ограничения:**

-   `slug`: только строчные буквы, цифры, дефисы и слэши
-   `slug`: должен быть уникальным в рамках `post_type`
-   `slug`: не должен быть зарезервированным путём
-   При `is_published: true` требуется валидный `slug`

**Ответ (201):**

```json
{
  "data": {
    "id": 42,
    "post_type": "article",
    "title": "Headless CMS launch checklist",
    "slug": "launch-checklist",
    "status": "published",
    "is_published": true,
    "published_at": "2025-02-10T08:00:00+00:00",
    "content_json": {...},
    "meta_json": {...},
    "template_override": "templates.landing",
    "author": {...},
    "terms": [],
    "created_at": "2025-02-10T08:00:00+00:00",
    "updated_at": "2025-02-10T08:00:00+00:00",
    "deleted_at": null
  }
}
```

### GET `/entries/{id}`

Просмотр записи (включая удалённые).

**Ответ (200):** Аналогично созданию

### PUT `/entries/{id}`

Обновить запись.

**Body:** (все поля optional)

```json
{
    "title": "Updated title", // string, max:500
    "slug": "updated-slug", // string, max:255, regex, unique в рамках post_type
    "content_json": {}, // array
    "meta_json": {}, // array
    "is_published": false, // boolean
    "published_at": "2025-02-10T08:00:00Z", // datetime (ISO 8601)
    "template_override": "templates.landing", // string, max:255
    "term_ids": [3, 8] // array of integers, exists:terms,id
}
```

### DELETE `/entries/{id}`

Удалить запись (soft delete).

**Ответ (200):**

```json
{
    "message": "Entry удалён"
}
```

### POST `/entries/{id}/restore`

Восстановить удалённую запись.

**Ответ (200):**

```json
{
  "data": {...}
}
```

### GET `/entries/statuses`

Получить список статусов.

**Ответ (200):**

```json
{
    "data": ["draft", "published", "scheduled", "trashed"]
}
```

---

## PostType API

### GET `/post-types`

Список типов записей.

**Ответ (200):**

```json
{
    "data": [
        {
            "id": 1,
            "slug": "article",
            "name": "Статьи",
            "options_json": {},
            "blueprint_id": 1,
            "created_at": "2025-01-10T12:00:00+00:00",
            "updated_at": "2025-01-10T12:00:00+00:00"
        }
    ]
}
```

### POST `/post-types`

Создать тип записи.

**Body:**

```json
{
    "slug": "article", // required, string, max:64, regex: /^[a-z0-9_-]+$/, unique
    "name": "Статьи", // required, string, max:255
    "options_json": {
        // optional, object (не массив)
        "taxonomies": [1, 2] // optional, array of integers, exists:taxonomies,id
    },
    "blueprint_id": 1 // optional, integer, exists:blueprints,id
}
```

**Ограничения:**

-   `slug`: только строчные буквы, цифры, подчёркивания и дефисы
-   `slug`: не должен быть зарезервированным путём
-   `options_json`: должен быть объектом, не массивом

**Ответ (201):**

```json
{
    "data": {
        "slug": "article",
        "name": "Статьи",
        "options_json": {},
        "created_at": "2025-01-10T12:00:00+00:00",
        "updated_at": "2025-01-10T12:00:00+00:00"
    }
}
```

**Примечание:** PostType НЕ возвращает `id` и `blueprint_id` в API. Slug используется как идентификатор.

### GET `/post-types/{slug}`

Просмотр типа записи.

**Ответ (200):**

```json
{
    "data": {
        "slug": "article",
        "name": "Статьи",
        "options_json": {},
        "created_at": "2025-01-10T12:00:00+00:00",
        "updated_at": "2025-01-10T12:00:00+00:00"
    }
}
```

### PUT `/post-types/{slug}`

Обновить тип записи.

**Body:** (все поля optional)

```json
{
    "slug": "article_updated", // string, max:64, regex, unique
    "name": "Статьи обновлённые", // string, max:255
    "options_json": {} // object
}
```

**Примечание:** `blueprint_id` также НЕ передаётся при обновлении через этот endpoint.

### DELETE `/post-types/{slug}`

Удалить тип записи.

**Ограничения:**

-   Нельзя удалить, если есть связанные Entry

---

## Template API

### GET `/templates`

Список доступных шаблонов.

**Ответ (200):**

```json
{
    "data": [
        {
            "name": "pages.show",
            "path": "pages/show.blade.php",
            "exists": true,
            "created_at": null,
            "updated_at": null
        }
    ]
}
```

**Примечание:** Исключаются системные директории: `admin`, `errors`, `layouts`, `partials`, `vendor`

### GET `/templates/{name}`

Просмотр шаблона.

**URL параметр:** `name` - имя шаблона в dot notation (например, `pages.article`)

**Ответ (200):**

```json
{
    "data": {
        "name": "pages.article",
        "path": "pages/article.blade.php",
        "exists": true,
        "content": "<div>Template content</div>",
        "created_at": null,
        "updated_at": "2025-01-10T12:45:00+00:00"
    }
}
```

### POST `/templates`

Создать шаблон.

**Body:**

```json
{
    "name": "pages.article", // required, string (dot notation)
    "content": "<div>Hello</div>" // required, string
}
```

**Ограничения:**

-   Шаблон не должен существовать (409 Conflict)

**Ответ (201):**

```json
{
    "data": {
        "name": "pages.article",
        "path": "pages/article.blade.php",
        "exists": true,
        "created_at": "2025-01-10T12:45:00+00:00",
        "updated_at": "2025-01-10T12:45:00+00:00"
    }
}
```

### PUT `/templates/{name}`

Обновить шаблон.

**Body:**

```json
{
    "content": "<div>Updated</div>" // required, string
}
```

---

## Media API

### GET `/media`

Список медиа-файлов.

**Query параметры:**

-   `search` (string, optional) - Поиск
-   `mime_type` (string, optional) - Фильтр по MIME типу
-   `per_page` (int, optional) - Записей на страницу (10-100). Default: 15

**Rate Limit:** 60 запросов в минуту

### POST `/media`

Загрузить медиа-файл.

**Body:** `multipart/form-data`

-   `file` (file, required) - Файл для загрузки

**Rate Limit:** 20 запросов в минуту

### GET `/media/{id}`

Просмотр медиа-файла.

**Rate Limit:** 60 запросов в минуту

### PUT `/media/{id}`

Обновить метаданные медиа-файла.

**Rate Limit:** 20 запросов в минуту

### DELETE `/media/bulk`

Массовое удаление медиа-файлов.

**Body:**

```json
{
    "ids": [1, 2, 3] // required, array of integers
}
```

**Rate Limit:** 20 запросов в минуту

---

## Taxonomy & Terms API

### GET `/taxonomies`

Список таксономий.

**Ответ (200):**

```json
{
    "data": [
        {
            "id": 1,
            "label": "Categories",
            "hierarchical": true,
            "options_json": {},
            "created_at": "2025-01-10T12:00:00+00:00",
            "updated_at": "2025-01-10T12:00:00+00:00"
        }
    ]
}
```

### POST `/taxonomies`

Создать таксономию.

**Body:**

```json
{
    "label": "Categories", // required, string, max:255
    "hierarchical": true, // optional, boolean
    "options_json": {} // optional, object
}
```

### GET `/taxonomies/{taxonomy}/terms/tree`

Получить дерево термов таксономии.

**Ответ (200):**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Technology",
      "slug": "technology",
      "parent_id": null,
      "children": [...]
    }
  ]
}
```

### POST `/taxonomies/{taxonomy}/terms`

Создать терм.

**Body:**

```json
{
    "name": "Technology", // required, string, max:255
    "slug": "technology", // optional, string, max:255
    "parent_id": null // optional, integer, exists:terms,id
}
```

### PUT `/entries/{entry}/terms/sync`

Синхронизировать термы записи.

**Body:**

```json
{
    "term_ids": [1, 2, 3] // required, array of integers, exists:terms,id
}
```

---

## Options API

### GET `/options/{namespace}`

Получить опции по namespace.

**Query параметры:**

-   `keys[]` (string[], optional) - Фильтр по ключам

**Rate Limit:** 120 запросов в минуту

**Ответ (200):**

```json
{
    "data": [
        {
            "namespace": "site",
            "key": "title",
            "value": "My Site",
            "created_at": "2025-01-10T12:00:00+00:00",
            "updated_at": "2025-01-10T12:00:00+00:00"
        }
    ]
}
```

### GET `/options/{namespace}/{key}`

Получить опцию.

**Rate Limit:** 120 запросов в минуту

### PUT `/options/{namespace}/{key}`

Создать/обновить опцию.

**Body:**

```json
{
    "value": "My Site" // required, mixed
}
```

**Rate Limit:** 30 запросов в минуту

### DELETE `/options/{namespace}/{key}`

Удалить опцию (soft delete).

**Rate Limit:** 30 запросов в минуту

### POST `/options/{namespace}/{key}/restore`

Восстановить удалённую опцию.

**Rate Limit:** 30 запросов в минуту

---

## Ограничения системы

### Общие ограничения

#### Rate Limiting

-   **Общий лимит:** 120 запросов в минуту на IP/пользователя
-   **Логин/Refresh:** 20 запросов в минуту
-   **Поиск (публичный):** 240 запросов в минуту
-   **Поиск (реиндексация):** 10 запросов в минуту
-   **Media (чтение):** 60 запросов в минуту
-   **Media (запись):** 20 запросов в минуту
-   **Options (чтение):** 120 запросов в минуту
-   **Options (запись):** 30 запросов в минуту

#### Аутентификация

-   Все запросы требуют JWT токен в заголовке `Authorization: Bearer {token}`
-   Токен должен быть валидным и не истёкшим

### Blueprint ограничения

#### Код Blueprint (`code`)

-   **Формат:** только строчные буквы, цифры и подчёркивания (`/^[a-z0-9_]+$/`)
-   **Длина:** максимум 255 символов
-   **Уникальность:** должен быть уникальным в таблице `blueprints`

#### Название Blueprint (`name`)

-   **Длина:** максимум 255 символов

#### Описание Blueprint (`description`)

-   **Длина:** максимум 1000 символов

#### Удаление Blueprint

-   Нельзя удалить, если используется в PostType
-   Нельзя удалить, если встроен в другие Blueprint

#### Встраивание Blueprint

-   **Максимальная глубина встраивания:** 5 уровней (MAX_EMBED_DEPTH = 5)
-   Нельзя встроить Blueprint в самого себя
-   Нельзя создать циклическую зависимость
-   Нельзя создать конфликт путей (одинаковые `full_path`)
-   При превышении глубины выбрасывается `MaxDepthExceededException`

### Path ограничения

#### Имя поля (`name`)

-   **Формат:** только строчные буквы, цифры и подчёркивания (`/^[a-z0-9_]+$/`)
-   **Длина:** максимум 255 символов

#### Тип данных (`data_type`)

-   **Допустимые значения:** `string`, `text`, `int`, `float`, `bool`, `date`, `datetime`, `json`, `ref`
-   Только один из указанных типов

#### Кардинальность (`cardinality`)

-   **Допустимые значения:** `one`, `many`
-   Default: `one`

#### Порядок сортировки (`sort_order`)

-   **Минимум:** 0
-   **Тип:** integer

#### Редактирование/Удаление Path

-   Нельзя редактировать скопированные поля (`is_readonly: true`)
-   Нельзя удалить скопированные поля
-   Изменения нужно вносить в исходный Blueprint

### Entry ограничения

#### Заголовок (`title`)

-   **Длина:** максимум 500 символов
-   **Обязательность:** required при создании

#### Slug (`slug`)

-   **Формат:** `/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/`
    -   Строчные буквы, цифры, дефисы
    -   Может содержать слэши для вложенных путей
-   **Длина:** максимум 255 символов
-   **Уникальность:** должен быть уникальным в рамках `post_type`
-   **Зарезервированные пути:** не должен совпадать с зарезервированными путями
-   **Публикация:** при `is_published: true` требуется валидный `slug`

#### Шаблон (`template_override`)

-   **Длина:** максимум 255 символов
-   **Формат:** имя Blade шаблона в dot notation (например, `pages.article`)

#### Контент (`content_json`, `meta_json`)

-   **Тип:** объект JSON (всегда возвращается как объект, даже пустой = `{}`)
-   В запросе передаётся как массив/объект, в ответе всегда объект
-   Валидация структуры зависит от Blueprint (если назначен)

#### Термы (`term_ids`)

-   **Тип:** массив целых чисел
-   Все ID должны существовать в таблице `terms`

### PostType ограничения

#### Slug PostType (`slug`)

-   **Формат:** `/^[a-z0-9_-]+$/` (строчные буквы, цифры, подчёркивания, дефисы)
-   **Длина:** максимум 64 символа
-   **Уникальность:** должен быть уникальным
-   **Зарезервированные пути:** не должен совпадать с зарезервированными путями

#### Название PostType (`name`)

-   **Длина:** максимум 255 символов

#### Опции (`options_json`)

-   **Тип:** объект (не массив)
-   **Taxonomies:** массив целых чисел, все ID должны существовать

#### Удаление PostType

-   Нельзя удалить, если есть связанные Entry

### Template ограничения

#### Имя шаблона (`name`)

-   **Формат:** dot notation (например, `pages.article.show`)
-   Преобразуется в путь: `pages/article/show.blade.php`

#### Создание шаблона

-   Шаблон не должен существовать (409 Conflict при попытке создать существующий)

#### Системные директории

-   Исключаются из списка: `admin`, `errors`, `layouts`, `partials`, `vendor`

### Media ограничения

#### Загрузка файлов

-   **Rate Limit:** 20 запросов в минуту
-   Размер файла ограничен настройками сервера/PHP

### Taxonomy & Terms ограничения

#### Slug таксономии (`slug`)

-   **Длина:** максимум 255 символов
-   **Уникальность:** должен быть уникальным

#### Название таксономии (`name`)

-   **Длина:** максимум 255 символов

#### Терм

-   **Название:** максимум 255 символов
-   **Slug:** максимум 255 символов (автогенерация, если не указан)
-   **Родитель:** должен существовать в той же таксономии

### Ошибки валидации

Все ошибки валидации возвращаются в формате:

```json
{
    "type": "https://stupidcms.dev/problems/validation-error",
    "title": "Validation Error",
    "status": 422,
    "code": "VALIDATION_ERROR",
    "detail": "The given data was invalid.",
    "meta": {
        "request_id": "...",
        "errors": {
            "field_name": ["Error message 1", "Error message 2"]
        }
    },
    "trace_id": "..."
}
```

### Коды ошибок

-   `UNAUTHORIZED` (401) - Требуется аутентификация
-   `FORBIDDEN` (403) - Недостаточно прав
-   `NOT_FOUND` (404) - Ресурс не найден
-   `VALIDATION_ERROR` (422) - Ошибка валидации
-   `CONFLICT` (409) - Конфликт (например, дубликат)
-   `RATE_LIMIT_EXCEEDED` (429) - Превышен лимит запросов

---

## Примечания

1. **Время:** Все даты/время в формате ISO 8601 (UTC)
2. **Пагинация:** Все списковые endpoints поддерживают пагинацию через `links` и `meta`
3. **Мягкое удаление:** Entry, Media, Options поддерживают soft delete и восстановление
4. **Автогенерация:** Slug для Entry генерируется автоматически из `title`, если не указан
5. **Каскадные изменения:** Изменения в Blueprint автоматически применяются к встроенным Blueprint через события

---

**Последнее обновление:** 2025-01-10
