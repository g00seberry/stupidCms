# API Blueprint: Документация для фронтенда

> **Система динамических шаблонов данных stupidCMS**
>
> Версия: 1.0  
> Дата: 2025-11-20

---

## Содержание

1. [Введение](#введение)
2. [Основные концепции](#основные-концепции)
3. [API Endpoints](#api-endpoints)
    - [Blueprint API](#blueprint-api)
    - [Path API](#path-api)
    - [Blueprint Embed API](#blueprint-embed-api)
4. [Структура данных](#структура-данных)
5. [Ограничения системы](#ограничения-системы)
6. [Коды ошибок](#коды-ошибок)
7. [Примеры использования](#примеры-использования)

---

## Введение

Blueprint — это система динамических шаблонов данных для stupidCMS. Она позволяет:

-   Создавать гибкие структуры данных (схемы) без изменения кода
-   Встраивать одни blueprint'ы в другие для переиспользования структур
-   Автоматически индексировать данные Entry для быстрого поиска
-   Привязывать blueprint'ы к типам контента (PostType)

**Архитектура:**

```
PostType → Blueprint → Path (поля структуры)
                    ↓
              Entry → DocValue/DocRef (индексированные данные)
```

---

## Основные концепции

### Blueprint (шаблон)

Схема данных с именованными полями. Каждый blueprint имеет:

-   `id` — уникальный идентификатор
-   `code` — уникальный код (a-z0-9\_)
-   `name` — человекочитаемое название
-   `description` — описание (опционально)

**Примеры:** `article`, `address`, `product`, `author`

### Path (поле)

Поле в структуре blueprint. Характеристики:

-   `name` — имя поля (a-z0-9\_)
-   `full_path` — полный путь (например, `author.contacts.email`)
-   `data_type` — тип данных: `string`, `text`, `int`, `float`, `bool`, `date`, `datetime`, `json`, `ref`
-   `cardinality` — кардинальность: `one` (одно значение) или `many` (массив)
-   `is_required` — обязательность
-   `is_indexed` — создавать индекс для поиска
-   `is_readonly` — поле скопировано из другого blueprint (нельзя редактировать)
-   `parent_id` — родительское поле (для вложенности)

### Blueprint Embed (встраивание)

Механизм переиспользования: blueprint A можно встроить в blueprint B.

При встраивании все поля A копируются в B с сохранением структуры.

**Особенности:**

-   Можно встроить один blueprint несколько раз под разные `host_path`
-   Поддерживается транзитивное встраивание (A → B → C)
-   Запрещены циклические зависимости (A → B → A)
-   Скопированные поля (`is_readonly = true`) нельзя редактировать напрямую

---

## API Endpoints

**Base URL:** `/api/admin/v1`

**Аутентификация:** все запросы требуют авторизации через Bearer Token

---

## Blueprint API

### 1. Список Blueprint

```http
GET /api/admin/v1/blueprints
```

**Query параметры:**

| Параметр   | Тип    | Обязательный | Описание                                  | Пример    |
| ---------- | ------ | ------------ | ----------------------------------------- | --------- |
| `search`   | string | Нет          | Поиск по name/code                        | `article` |
| `sort_by`  | string | Нет          | Поле сортировки (default: `created_at`)   | `name`    |
| `sort_dir` | string | Нет          | Направление сортировки (default: `desc`)  | `asc`     |
| `per_page` | int    | Нет          | Записей на страницу (10-100, default: 15) | `25`      |

**Ответ (200 OK):**

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
            "post_types_count": 3,
            "created_at": "2025-01-10T12:00:00+00:00",
            "updated_at": "2025-01-10T12:00:00+00:00"
        },
        {
            "id": 2,
            "name": "Address",
            "code": "address",
            "description": null,
            "paths_count": 3,
            "embeds_count": 0,
            "post_types_count": 0,
            "created_at": "2025-01-10T13:00:00+00:00",
            "updated_at": "2025-01-10T13:00:00+00:00"
        }
    ],
    "links": {
        "first": "http://example.com/api/admin/v1/blueprints?page=1",
        "last": "http://example.com/api/admin/v1/blueprints?page=1",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "per_page": 15,
        "to": 2,
        "total": 2
    }
}
```

**Примечание:** В списке НЕ загружаются:

-   `embedded_in_count` — доступно только в `/blueprints/{id}` (show)
-   `post_types` — связь доступна только в show

---

### 2. Создать Blueprint

```http
POST /api/admin/v1/blueprints
```

**Body (application/json):**

| Поле          | Тип    | Обязательный | Описание                  | Пример                   |
| ------------- | ------ | ------------ | ------------------------- | ------------------------ |
| `name`        | string | **Да**       | Название blueprint        | `Article`                |
| `code`        | string | **Да**       | Уникальный код (a-z0-9\_) | `article`                |
| `description` | string | Нет          | Описание blueprint        | `Blog article structure` |

**Пример запроса:**

```json
{
    "name": "Article",
    "code": "article",
    "description": "Blog article structure"
}
```

**Ответ (201 Created):**

```json
{
    "data": {
        "id": 1,
        "name": "Article",
        "code": "article",
        "description": "Blog article structure",
        "created_at": "2025-01-10T12:00:00+00:00",
        "updated_at": "2025-01-10T12:00:00+00:00"
    }
}
```

**Возможные ошибки:**

-   `422 Unprocessable Entity` — валидационная ошибка (например, code уже существует)

---

### 3. Просмотр Blueprint

```http
GET /api/admin/v1/blueprints/{id}
```

**URL параметры:**

| Параметр | Тип | Описание     |
| -------- | --- | ------------ |
| `id`     | int | ID blueprint |

**Ответ (200 OK):**

```json
{
    "data": {
        "id": 1,
        "name": "Article",
        "code": "article",
        "description": "Blog article structure",
        "paths_count": 5,
        "embeds_count": 2,
        "embedded_in_count": 1,
        "post_types_count": 3,
        "post_types": [{ "id": 1, "slug": "article", "name": "Статьи" }],
        "created_at": "2025-01-10T12:00:00+00:00",
        "updated_at": "2025-01-10T12:00:00+00:00"
    }
}
```

---

### 4. Обновить Blueprint

```http
PUT /api/admin/v1/blueprints/{id}
```

**Body (application/json):**

| Поле          | Тип    | Обязательный | Описание                  |
| ------------- | ------ | ------------ | ------------------------- |
| `name`        | string | Нет          | Название blueprint        |
| `code`        | string | Нет          | Уникальный код (a-z0-9\_) |
| `description` | string | Нет          | Описание blueprint        |

**Пример запроса:**

```json
{
    "name": "Article Updated",
    "description": "Updated description"
}
```

**Ответ (200 OK):**

```json
{
    "data": {
        "id": 1,
        "name": "Article Updated",
        "code": "article",
        "description": "Updated description",
        "created_at": "2025-01-10T12:00:00+00:00",
        "updated_at": "2025-01-10T13:00:00+00:00"
    }
}
```

---

### 5. Удалить Blueprint

```http
DELETE /api/admin/v1/blueprints/{id}
```

**Ответ (200 OK):**

```json
{
    "message": "Blueprint удалён"
}
```

**Возможные ошибки:**

```json
{
    "message": "Невозможно удалить blueprint",
    "reasons": ["Используется в 3 PostType", "Встроен в 2 других blueprint"]
}
```

**Ограничения удаления:**

-   Нельзя удалить blueprint, если он привязан к PostType
-   Нельзя удалить blueprint, если он встроен в другие blueprint
-   Сначала нужно удалить все связи (см. [Проверка возможности удаления](#6-проверка-возможности-удаления))

---

### 6. Проверка возможности удаления

```http
GET /api/admin/v1/blueprints/{id}/can-delete
```

**Ответ (200 OK):**

```json
{
    "can_delete": false,
    "reasons": ["Используется в 3 PostType", "Встроен в 2 других blueprint"]
}
```

Если `can_delete: true`, blueprint можно удалить безопасно.

---

### 7. Граф зависимостей Blueprint

```http
GET /api/admin/v1/blueprints/{id}/dependencies
```

**Описание:** Возвращает список blueprint'ов, от которых зависит текущий, и которые зависят от него.

**Ответ (200 OK):**

```json
{
    "depends_on": [
        { "id": 2, "code": "address", "name": "Address" },
        { "id": 3, "code": "geo", "name": "Geo" }
    ],
    "depended_by": [
        { "id": 5, "code": "company", "name": "Company" },
        { "id": 7, "code": "department", "name": "Department" }
    ]
}
```

**Пояснение:**

-   `depends_on` — blueprint'ы, которые встроены в текущий (прямо или транзитивно)
-   `depended_by` — blueprint'ы, в которые встроен текущий

---

### 8. Список Blueprint для встраивания

```http
GET /api/admin/v1/blueprints/{id}/embeddable
```

**Описание:** Возвращает список blueprint'ов, которые можно безопасно встроить в текущий (без создания циклов).

**Ответ (200 OK):**

```json
{
    "data": [
        { "id": 2, "code": "address", "name": "Address" },
        { "id": 3, "code": "geo", "name": "Geo" }
    ]
}
```

**Исключает:**

-   Сам blueprint (нельзя встроить в себя)
-   Blueprint'ы, которые создадут циклическую зависимость

---

## Path API

### 1. Список полей Blueprint (дерево)

```http
GET /api/admin/v1/blueprints/{blueprintId}/paths
```

**Описание:** Возвращает иерархическое дерево полей blueprint (собственные + материализованные).

**Ответ (200 OK):**

```json
{
    "data": [
        {
            "id": 1,
            "blueprint_id": 1,
            "name": "title",
            "full_path": "title",
            "data_type": "string",
            "cardinality": "one",
            "is_required": true,
            "is_indexed": true,
            "is_readonly": false,
            "sort_order": 0,
            "children": []
        },
        {
            "id": 2,
            "name": "author",
            "full_path": "author",
            "data_type": "json",
            "is_readonly": false,
            "children": [
                {
                    "id": 3,
                    "name": "name",
                    "full_path": "author.name",
                    "data_type": "string",
                    "is_indexed": true,
                    "is_readonly": false,
                    "children": []
                },
                {
                    "id": 4,
                    "name": "contacts",
                    "full_path": "author.contacts",
                    "data_type": "json",
                    "is_readonly": false,
                    "children": [
                        {
                            "id": 5,
                            "name": "phone",
                            "full_path": "author.contacts.phone",
                            "data_type": "string",
                            "is_readonly": true,
                            "source_blueprint_id": 3,
                            "source_blueprint": {
                                "id": 3,
                                "code": "contact_info",
                                "name": "Contact Info"
                            }
                        }
                    ]
                }
            ]
        }
    ]
}
```

**Важно:**

-   Поля с `is_readonly: true` — скопированы из другого blueprint при встраивании
-   Для скопированных полей указывается `source_blueprint` — источник
-   Дерево строится рекурсивно через `parent_id`

---

### 2. Создать поле

```http
POST /api/admin/v1/blueprints/{blueprintId}/paths
```

**Body (application/json):**

| Поле               | Тип    | Обязательный | Описание                                     | Пример        |
| ------------------ | ------ | ------------ | -------------------------------------------- | ------------- |
| `name`             | string | **Да**       | Имя поля (a-z0-9\_)                          | `title`       |
| `parent_id`        | int    | Нет          | ID родительского поля (для вложенности)      | `5`           |
| `data_type`        | string | **Да**       | Тип данных (см. [Типы данных](#типы-данных)) | `string`      |
| `cardinality`      | string | Нет          | `one` или `many` (default: `one`)            | `one`         |
| `is_required`      | bool   | Нет          | Обязательное поле (default: `false`)         | `true`        |
| `is_indexed`       | bool   | Нет          | Создать индекс для поиска (default: `false`) | `true`        |
| `sort_order`       | int    | Нет          | Порядок сортировки (default: `0`)            | `10`          |
| `validation_rules` | array  | Нет          | Правила валидации Laravel                    | `["max:255"]` |

**Типы данных (`data_type`):**

-   `string` — строка (до 500 символов)
-   `text` — длинный текст
-   `int` — целое число
-   `float` — число с плавающей точкой
-   `bool` — логическое значение
-   `date` — дата (без времени)
-   `datetime` — дата и время
-   `json` — JSON-объект (группа)
-   `ref` — ссылка на другую Entry

**Пример запроса:**

```json
{
    "name": "title",
    "data_type": "string",
    "is_required": true,
    "is_indexed": true
}
```

**Ответ (201 Created):**

```json
{
    "data": {
        "id": 1,
        "blueprint_id": 1,
        "name": "title",
        "full_path": "title",
        "data_type": "string",
        "cardinality": "one",
        "is_required": true,
        "is_indexed": true,
        "is_readonly": false,
        "sort_order": 0,
        "created_at": "2025-01-10T12:00:00+00:00",
        "updated_at": "2025-01-10T12:00:00+00:00"
    }
}
```

**Автоматическое вычисление `full_path`:**

-   Корневое поле: `full_path = name`
-   Вложенное поле: `full_path = parent.full_path + '.' + name`

Например:

```json
{
    "name": "email",
    "parent_id": 5
}
```

→ если parent имеет `full_path = "author.contacts"`, то новое поле получит `full_path = "author.contacts.email"`

---

### 3. Просмотр поля

```http
GET /api/admin/v1/paths/{id}
```

**Ответ (200 OK):**

```json
{
  "data": {
    "id": 5,
    "blueprint_id": 1,
    "parent_id": 4,
    "name": "phone",
    "full_path": "author.contacts.phone",
    "data_type": "string",
    "cardinality": "one",
    "is_required": false,
    "is_indexed": true,
    "is_readonly": true,
    "source_blueprint_id": 3,
    "blueprint_embed_id": 2,
    "source_blueprint": {
      "id": 3,
      "code": "contact_info",
      "name": "Contact Info"
    },
    "children": [...],
    "created_at": "2025-01-10T12:00:00+00:00",
    "updated_at": "2025-01-10T12:00:00+00:00"
  }
}
```

---

### 4. Обновить поле

```http
PUT /api/admin/v1/paths/{id}
```

**Body (application/json):**

Принимает те же параметры, что и создание (все опциональны).

**Ограничения:**

-   ❌ Нельзя редактировать поля с `is_readonly: true` (скопированные)
-   ⚠️ При изменении `name` или `parent_id` автоматически пересчитывается `full_path` для всех дочерних полей
-   ⚠️ Изменение структуры триггерит реиндексацию всех Entry данного blueprint

**Ошибка при попытке редактировать скопированное поле (422):**

```json
{
    "message": "Невозможно редактировать скопированное поле 'author.contacts.phone'. Измените исходное поле в blueprint 'contact_info'."
}
```

---

### 5. Удалить поле

```http
DELETE /api/admin/v1/paths/{id}
```

**Ответ (200 OK):**

```json
{
    "message": "Path удалён"
}
```

**Ограничения:**

-   ❌ Нельзя удалить поля с `is_readonly: true` (скопированные)
-   ⚠️ При удалении поля удаляются все дочерние поля (CASCADE)
-   ⚠️ Удаление триггерит реиндексацию всех Entry

**Ошибка при попытке удалить скопированное поле (422):**

```json
{
    "message": "Невозможно удалить скопированное поле 'author.contacts.phone'. Удалите встраивание в blueprint 'article'."
}
```

---

## Blueprint Embed API

### 1. Список встраиваний Blueprint

```http
GET /api/admin/v1/blueprints/{blueprintId}/embeds
```

**Описание:** Возвращает список всех встраиваний данного blueprint.

**Ответ (200 OK):**

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

---

### 2. Создать встраивание

```http
POST /api/admin/v1/blueprints/{blueprintId}/embeds
```

**Body (application/json):**

| Поле                    | Тип | Обязательный | Описание                                         |
| ----------------------- | --- | ------------ | ------------------------------------------------ |
| `embedded_blueprint_id` | int | **Да**       | ID blueprint'а для встраивания                   |
| `host_path_id`          | int | Нет          | ID поля-контейнера (NULL = встраивание в корень) |

**Пример запроса (встраивание в корень):**

```json
{
    "embedded_blueprint_id": 2
}
```

**Пример запроса (встраивание под поле):**

```json
{
    "embedded_blueprint_id": 2,
    "host_path_id": 5
}
```

**Ответ (201 Created):**

```json
{
    "data": {
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
        "created_at": "2025-01-10T12:00:00+00:00"
    }
}
```

**Что происходит при создании встраивания:**

1. **Валидация циклов:** проверяется, что встраивание не создаст циклическую зависимость
2. **Валидация конфликтов:** проверяется, что не будет конфликтов `full_path`
3. **Материализация:** все поля embedded blueprint копируются в host blueprint с установкой:
    - `is_readonly = true`
    - `source_blueprint_id = embedded_blueprint_id`
    - `blueprint_embed_id = embed.id`
4. **Каскадные события:** триггерится реиндексация Entry и рематериализация зависимых blueprint'ов

**Возможные ошибки:**

**Циклическая зависимость (422):**

```json
{
    "message": "Циклическая зависимость: 'address' уже зависит от 'article' (прямо или транзитивно). Встраивание невозможно."
}
```

**Конфликт путей (422):**

```json
{
    "message": "Невозможно встроить blueprint 'address' в 'article': конфликт путей: 'email'"
}
```

**Дублирование встраивания (422):**

```json
{
    "message": "Blueprint 'address' уже встроен в 'article' в корень."
}
```

---

### 3. Просмотр встраивания

```http
GET /api/admin/v1/embeds/{id}
```

**Ответ (200 OK):**

```json
{
    "data": {
        "id": 1,
        "blueprint_id": 1,
        "embedded_blueprint_id": 2,
        "host_path_id": 5,
        "blueprint": {
            "id": 1,
            "code": "article",
            "name": "Article"
        },
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
        "created_at": "2025-01-10T12:00:00+00:00"
    }
}
```

---

### 4. Удалить встраивание

```http
DELETE /api/admin/v1/embeds/{id}
```

**Ответ (200 OK):**

```json
{
    "message": "Встраивание удалено"
}
```

**Что происходит при удалении:**

1. Все скопированные поля (с `blueprint_embed_id = embed.id`) удаляются автоматически (CASCADE)
2. Триггерится реиндексация Entry
3. Каскадно обновляются зависимые blueprint'ы

---

## Структура данных

### Blueprint Resource

```typescript
interface Blueprint {
    id: number;
    name: string;
    code: string;
    description: string | null;
    paths_count?: number; // если загружено
    embeds_count?: number; // если загружено
    embedded_in_count?: number; // если загружено
    post_types_count?: number; // если загружено
    post_types?: PostType[]; // если загружено
    created_at: string; // ISO 8601
    updated_at: string; // ISO 8601
}
```

### Path Resource

```typescript
interface Path {
    id: number;
    blueprint_id: number;
    parent_id: number | null;
    name: string;
    full_path: string;
    data_type:
        | "string"
        | "text"
        | "int"
        | "float"
        | "bool"
        | "date"
        | "datetime"
        | "json"
        | "ref";
    cardinality: "one" | "many";
    is_required: boolean;
    is_indexed: boolean;
    is_readonly: boolean;
    sort_order: number;
    validation_rules: any[] | null;

    // Если скопировано из другого blueprint:
    source_blueprint_id: number | null;
    source_blueprint?: {
        id: number;
        code: string;
        name: string;
    };
    blueprint_embed_id: number | null;

    // Дочерние поля (если загружено)
    children?: Path[];

    created_at: string;
    updated_at: string;
}
```

### Blueprint Embed Resource

```typescript
interface BlueprintEmbed {
    id: number;
    blueprint_id: number;
    embedded_blueprint_id: number;
    host_path_id: number | null;

    // Связи (если загружено):
    blueprint?: {
        id: number;
        code: string;
        name: string;
    };
    embedded_blueprint?: {
        id: number;
        code: string;
        name: string;
    };
    host_path?: {
        id: number;
        name: string;
        full_path: string;
    } | null;

    created_at: string;
    updated_at: string;
}
```

---

## Ограничения системы

### 1. Ограничения Blueprint

| Ограничение                 | Описание                                                                                 |
| --------------------------- | ---------------------------------------------------------------------------------------- |
| **Уникальность `code`**     | Код blueprint должен быть уникальным в системе                                           |
| **Формат `code`**           | Только строчные буквы, цифры и подчёркивания (`a-z0-9_`)                                 |
| **Удаление**                | Нельзя удалить blueprint, если он используется в PostType или встроен в другие blueprint |
| **Максимальное количество** | Рекомендуется не более 100 blueprint для оптимальной производительности                  |

### 2. Ограничения Path

| Ограничение                            | Описание                                                                    |
| -------------------------------------- | --------------------------------------------------------------------------- |
| **Уникальность `full_path`**           | В рамках одного blueprint не может быть двух полей с одинаковым `full_path` |
| **Формат `name`**                      | Только строчные буквы, цифры и подчёркивания (`a-z0-9_`)                    |
| **Максимальная глубина вложенности**   | Рекомендуется не более 5 уровней                                            |
| **Редактирование скопированных полей** | Нельзя редактировать/удалять поля с `is_readonly: true`                     |
| **Длина `full_path`**                  | Максимум 2048 символов                                                      |

### 3. Ограничения Blueprint Embed

| Ограничение                           | Описание                                                                       |
| ------------------------------------- | ------------------------------------------------------------------------------ |
| **Запрет самовстраивания**            | Нельзя встроить blueprint в самого себя                                        |
| **Запрет циклов**                     | Нельзя создавать циклические зависимости (A → B → A)                           |
| **Конфликты путей**                   | При встраивании не должно быть конфликтов `full_path`                          |
| **Множественное встраивание**         | Можно встроить один blueprint несколько раз, но только под разные `host_path`  |
| **Максимальная глубина зависимостей** | Рекомендуется не более 5 уровней транзитивных зависимостей (A → B → C → D → E) |
| **Тип `host_path`**                   | Поле-контейнер должно иметь `data_type = 'json'`                               |
| **Встраивание в скопированные поля**  | Нельзя использовать скопированные поля (`is_readonly: true`) как `host_path`   |

### 4. Ограничения производительности

| Метрика                          | Рекомендация     | Критический порог |
| -------------------------------- | ---------------- | ----------------- |
| **Количество Path в Blueprint**  | До 50 полей      | 100 полей         |
| **Глубина вложенности Path**     | До 3 уровней     | 5 уровней         |
| **Количество Embed в Blueprint** | До 5 встраиваний | 10 встраиваний    |
| **Транзитивные зависимости**     | До 3 уровней     | 5 уровней         |
| **Общее количество Blueprint**   | До 50 шт         | 100 шт            |

**При превышении рекомендуемых значений:**

-   Снижается скорость материализации при изменении структуры
-   Увеличивается время реиндексации Entry
-   Рекомендуется внедрение Closure Table для оптимизации графа зависимостей

### 5. Каскадные изменения

**При изменении Blueprint триггерятся следующие автоматические процессы:**

| Действие                            | Последствия                                                       |
| ----------------------------------- | ----------------------------------------------------------------- |
| **Изменение/удаление Path**         | Реиндексация всех Entry данного PostType                          |
| **Изменение собственного Path**     | Рематериализация всех blueprint'ов, которые встраивают текущий    |
| **Добавление/удаление Embed**       | Рематериализация + реиндексация Entry                             |
| **Изменение встроенного Blueprint** | Транзитивная рематериализация всей цепочки зависимых blueprint'ов |

**Время выполнения:**

-   Материализация: ~50-200ms на blueprint (зависит от количества полей)
-   Реиндексация Entry: ~5-20ms на Entry
-   Полный каскад при изменении корневого blueprint: может занять несколько секунд при большом количестве зависимых Entry

---

## Коды ошибок

### HTTP статусы

| Код   | Описание                            |
| ----- | ----------------------------------- |
| `200` | Успешный запрос                     |
| `201` | Ресурс успешно создан               |
| `401` | Неавторизован (нет/неверный токен)  |
| `403` | Доступ запрещён (недостаточно прав) |
| `404` | Ресурс не найден                    |
| `422` | Ошибка валидации                    |
| `500` | Внутренняя ошибка сервера           |

### Типы доменных ошибок

#### 1. Валидационные ошибки (422)

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "code": ["Blueprint с таким кодом уже существует."],
        "name": ["The name field is required."]
    }
}
```

#### 2. Циклическая зависимость (422)

```json
{
    "message": "Циклическая зависимость: 'address' уже зависит от 'article' (прямо или транзитивно). Встраивание невозможно."
}
```

**Когда возникает:** при попытке создать встраивание, которое создаст цикл в графе зависимостей.

**Решение:** проверьте граф зависимостей через `/blueprints/{id}/dependencies`

#### 3. Конфликт путей (422)

```json
{
    "message": "Невозможно встроить blueprint 'address' в 'article': конфликт путей: 'email'"
}
```

**Когда возникает:** при попытке встроить blueprint, у которого есть поле с таким же `full_path`, как у host blueprint.

**Решение:**

-   Переименуйте конфликтующее поле в одном из blueprint'ов
-   Используйте `host_path` для встраивания в конкретное поле (вместо корня)

#### 4. Редактирование скопированного поля (422)

```json
{
    "message": "Невозможно редактировать скопированное поле 'author.contacts.phone'. Измените исходное поле в blueprint 'contact_info'."
}
```

**Когда возникает:** при попытке изменить/удалить поле с `is_readonly: true`.

**Решение:** измените исходное поле в blueprint-источнике, изменения автоматически распространятся.

#### 5. Blueprint используется (422)

```json
{
    "message": "Невозможно удалить blueprint",
    "reasons": ["Используется в 3 PostType", "Встроен в 2 других blueprint"]
}
```

**Когда возникает:** при попытке удалить blueprint, на который есть ссылки.

**Решение:**

1. Отвяжите blueprint от PostType
2. Удалите все встраивания через `/embeds/{id}`
3. Попробуйте удалить снова

---

## Примеры использования

### Пример 1: Создание простого Blueprint

**Задача:** создать blueprint "Article" с полями `title`, `content`, `published_at`.

**Шаги:**

1. **Создать blueprint:**

```http
POST /api/admin/v1/blueprints
Content-Type: application/json

{
  "name": "Article",
  "code": "article",
  "description": "Blog article structure"
}
```

2. **Добавить поля:**

```http
POST /api/admin/v1/blueprints/1/paths
{
  "name": "title",
  "data_type": "string",
  "is_required": true,
  "is_indexed": true
}
```

```http
POST /api/admin/v1/blueprints/1/paths
{
  "name": "content",
  "data_type": "text",
  "is_required": true
}
```

```http
POST /api/admin/v1/blueprints/1/paths
{
  "name": "published_at",
  "data_type": "datetime",
  "is_indexed": true
}
```

**Результат:**

```
Blueprint "article"
├── title (string, required, indexed)
├── content (text, required)
└── published_at (datetime, indexed)
```

---

### Пример 2: Вложенные поля

**Задача:** создать blueprint "Article" с вложенным объектом `author.name`, `author.email`.

**Шаги:**

1. Создать blueprint "Article"

2. **Создать группу `author`:**

```http
POST /api/admin/v1/blueprints/1/paths
{
  "name": "author",
  "data_type": "json"
}
```

Ответ: `{ "id": 2, "full_path": "author" }`

3. **Создать вложенные поля:**

```http
POST /api/admin/v1/blueprints/1/paths
{
  "name": "name",
  "parent_id": 2,
  "data_type": "string",
  "is_indexed": true
}
```

Ответ: `{ "id": 3, "full_path": "author.name" }`

```http
POST /api/admin/v1/blueprints/1/paths
{
  "name": "email",
  "parent_id": 2,
  "data_type": "string",
  "is_indexed": true
}
```

Ответ: `{ "id": 4, "full_path": "author.email" }`

**Результат:**

```
Blueprint "article"
└── author (json)
    ├── name (string, indexed)
    └── email (string, indexed)
```

---

### Пример 3: Встраивание Blueprint

**Задача:** переиспользовать структуру "Address" в blueprint "Company" дважды (для `office` и `legal`).

**Шаги:**

1. **Создать blueprint "Address":**

```http
POST /api/admin/v1/blueprints
{
  "name": "Address",
  "code": "address"
}
```

2. **Добавить поля в "Address":**

```http
POST /api/admin/v1/blueprints/2/paths
{ "name": "street", "data_type": "string" }

POST /api/admin/v1/blueprints/2/paths
{ "name": "city", "data_type": "string" }

POST /api/admin/v1/blueprints/2/paths
{ "name": "zip", "data_type": "string" }
```

3. **Создать blueprint "Company":**

```http
POST /api/admin/v1/blueprints
{
  "name": "Company",
  "code": "company"
}
```

4. **Создать группы-контейнеры:**

```http
POST /api/admin/v1/blueprints/3/paths
{ "name": "office", "data_type": "json" }
```

Ответ: `{ "id": 10, "full_path": "office" }`

```http
POST /api/admin/v1/blueprints/3/paths
{ "name": "legal", "data_type": "json" }
```

Ответ: `{ "id": 11, "full_path": "legal" }`

5. **Встроить "Address" дважды:**

```http
POST /api/admin/v1/blueprints/3/embeds
{
  "embedded_blueprint_id": 2,
  "host_path_id": 10
}
```

```http
POST /api/admin/v1/blueprints/3/embeds
{
  "embedded_blueprint_id": 2,
  "host_path_id": 11
}
```

**Результат:**

```
Blueprint "company"
├── office (json)
│   ├── street (string, readonly, from "address")
│   ├── city (string, readonly, from "address")
│   └── zip (string, readonly, from "address")
└── legal (json)
    ├── street (string, readonly, from "address")
    ├── city (string, readonly, from "address")
    └── zip (string, readonly, from "address")
```

**Получить дерево полей:**

```http
GET /api/admin/v1/blueprints/3/paths
```

---

### Пример 4: Проверка возможности встраивания

**Задача:** перед встраиванием убедиться, что не будет цикла.

**Шаги:**

1. **Получить список доступных для встраивания blueprint'ов:**

```http
GET /api/admin/v1/blueprints/1/embeddable
```

Ответ:

```json
{
    "data": [
        { "id": 2, "code": "address", "name": "Address" },
        { "id": 3, "code": "geo", "name": "Geo" }
    ]
}
```

Если blueprint не в списке — его встраивание создаст цикл.

2. **Проверить граф зависимостей:**

```http
GET /api/admin/v1/blueprints/1/dependencies
```

Ответ:

```json
{
    "depends_on": [{ "id": 2, "code": "address", "name": "Address" }],
    "depended_by": []
}
```

`depends_on` — blueprint'ы, которые встроены в текущий  
`depended_by` — blueprint'ы, в которые встроен текущий

---

### Пример 5: Изменение встроенного Blueprint

**Сценарий:**

1. Blueprint "Address" встроен в "Company"
2. Нужно добавить новое поле `country` в "Address"

**Шаги:**

1. **Добавить поле в исходный blueprint:**

```http
POST /api/admin/v1/blueprints/2/paths
{
  "name": "country",
  "data_type": "string",
  "is_indexed": true
}
```

2. **Автоматически:**

-   Поле `country` скопируется во все blueprint'ы, которые встраивают "Address"
-   В "Company" появятся поля `office.country` и `legal.country` (оба с `is_readonly: true`)
-   Все Entry типа "Company" будут автоматически реиндексированы

**Проверить результат:**

```http
GET /api/admin/v1/blueprints/3/paths
```

Ответ покажет новые поля:

```json
{
    "data": [
        {
            "full_path": "office.country",
            "is_readonly": true,
            "source_blueprint_id": 2
        },
        {
            "full_path": "legal.country",
            "is_readonly": true,
            "source_blueprint_id": 2
        }
    ]
}
```

---

### Пример 6: Обработка ошибки конфликта путей

**Сценарий:** при попытке встраивания получена ошибка конфликта.

**Ошибка:**

```json
{
    "message": "Невозможно встроить blueprint 'address' в 'article': конфликт путей: 'email'"
}
```

**Причина:** в blueprint "Article" уже есть поле `email`, и в "Address" тоже есть `email`.

**Решения:**

**Вариант 1:** встроить "Address" не в корень, а под поле:

```http
POST /api/admin/v1/blueprints/1/paths
{ "name": "shipping", "data_type": "json" }
```

```http
POST /api/admin/v1/blueprints/1/embeds
{
  "embedded_blueprint_id": 2,
  "host_path_id": <id поля "shipping">
}
```

Теперь поля будут: `shipping.email`, `shipping.street` и т.д. (конфликта нет).

**Вариант 2:** переименовать поле в одном из blueprint'ов:

```http
PUT /api/admin/v1/paths/<id поля "email" в "Address">
{
  "name": "contact_email"
}
```

Теперь конфликта не будет.

---

## Глоссарий

| Термин                       | Описание                                                                         |
| ---------------------------- | -------------------------------------------------------------------------------- |
| **Blueprint**                | Схема данных, определяющая структуру полей                                       |
| **Path**                     | Поле в структуре blueprint (может быть вложенным)                                |
| **full_path**                | Полный путь к полю (например, `author.contacts.email`)                           |
| **Embed (Встраивание)**      | Копирование всех полей одного blueprint в другой                                 |
| **Host blueprint**           | Blueprint, в который встраивают                                                  |
| **Embedded blueprint**       | Blueprint, который встраивают                                                    |
| **host_path**                | Поле-контейнер, под которое встраивается blueprint (NULL = встраивание в корень) |
| **Материализация**           | Процесс копирования полей при встраивании                                        |
| **Скопированное поле**       | Поле с `is_readonly: true`, созданное при встраивании                            |
| **Циклическая зависимость**  | Ситуация, когда A зависит от B, а B зависит от A (прямо или транзитивно)         |
| **Транзитивная зависимость** | Если A зависит от B, а B от C, то A транзитивно зависит от C                     |
| **Каскадное событие**        | Автоматическое распространение изменений по цепочке зависимостей                 |
| **DocValue / DocRef**        | Индексированные значения Entry для быстрого поиска                               |

---

## Рекомендации по UI/UX

### 1. Список Blueprint

**Рекомендуемое отображение:**

-   Таблица/карточки с полями: name, code, количество полей, количество встраиваний
-   Поиск по name/code
-   Фильтры: "Используется в PostType", "Встроен в другие", "Самостоятельный"
-   Сортировка: по дате создания, имени, количеству полей

**Действия:**

-   Создать новый Blueprint
-   Редактировать (иконка или по клику)
-   Удалить (с предварительной проверкой через `/can-delete`)
-   Просмотр зависимостей (граф)

### 2. Редактор Blueprint

**Основная информация:**

-   Форма: name, code, description
-   Кнопки: Сохранить, Отменить

**Вкладки:**

1. **Поля (Paths):**

    - Древовидная структура с drag&drop для изменения `sort_order`
    - Индикаторы: `is_readonly` (замочек), `is_indexed` (индекс), `is_required` (звёздочка)
    - Действия: Добавить корневое поле, Добавить вложенное, Редактировать, Удалить
    - Для скопированных полей — отключённые кнопки + тултип "Скопировано из {source_blueprint.name}"

2. **Встраивания (Embeds):**

    - Список текущих встраиваний с информацией: embedded blueprint, host_path
    - Кнопка "Добавить встраивание"
    - При добавлении:
        - Выбор из списка `/blueprints/{id}/embeddable` (только безопасные)
        - Выбор host_path (опционально, с подсказкой "Оставьте пустым для встраивания в корень")
    - Кнопка "Удалить встраивание"

3. **Зависимости:**

    - Визуализация графа зависимостей (дерево или диаграмма)
    - Разделы: "Зависит от", "Зависимы от текущего"

4. **Использование:**
    - Список PostType, использующих данный blueprint
    - Количество Entry

**Предупреждения:**

-   При изменении структуры: "Изменения структуры приведут к реиндексации всех Entry. Продолжить?"
-   При удалении поля: "Удаление поля приведёт к потере данных в Entry. Продолжить?"

### 3. Форма добавления поля

**Поля формы:**

-   `name` (input, валидация: a-z0-9\_)
-   `parent_id` (select tree, опционально)
-   `data_type` (select с описанием типов)
-   `cardinality` (radio: one/many, default: one)
-   `is_required` (checkbox)
-   `is_indexed` (checkbox + подсказка "Включите для полей, по которым будет поиск")
-   `validation_rules` (опционально, textarea с примерами)

**Предпросмотр `full_path`:**

При выборе `parent_id` и вводе `name` показывать вычисленный `full_path`.

Пример: `parent_id = 5 (author.contacts)`, `name = email` → `full_path = author.contacts.email`

### 4. Форма добавления встраивания

**Шаг 1:** Выбор blueprint для встраивания

-   Список blueprint'ов из `/blueprints/{id}/embeddable`
-   Поиск по name/code
-   Превью структуры (количество полей, краткое описание)

**Шаг 2:** Выбор host_path (опционально)

-   Dropdown с древовидной структурой полей текущего blueprint
-   Только поля с `data_type = 'json'` и `is_readonly = false`
-   Опция: "Встроить в корень"

**Предпросмотр:**

-   Показать, какие поля будут добавлены
-   Предупреждение о возможных конфликтах (через PRE-CHECK на фронте)

**Кнопки:** Создать встраивание, Отменить

### 5. Обработка ошибок

**Циклическая зависимость:**

```
❌ Невозможно встроить "address" в "article"

Причина: это создаст циклическую зависимость.

"address" уже зависит от "article" (через "company").

Граф зависимостей:
article → company → address

[Просмотреть граф] [Закрыть]
```

**Конфликт путей:**

```
❌ Конфликт путей при встраивании "address"

Следующие поля конфликтуют:
• email (уже существует в "article")
• phone (уже существует в "article")

Решения:
1. Встройте "address" под конкретное поле (например, "shipping")
2. Переименуйте конфликтующие поля в одном из blueprint'ов

[Выбрать host_path] [Отменить]
```

---

## Дополнительные ресурсы

-   **Документация Scribe:** `/docs/` — автоматически сгенерированная документация API
-   **Репозиторий:** `/docs/data-core/` — подробная техническая документация системы
-   **Тесты:** `/tests/Feature/Admin/BlueprintControllerTest.php` — примеры использования API

---

## Changelog

### v1.0 (2025-11-20)

-   Первая версия документации
-   Описание всех API endpoints
-   Полный перечень ограничений системы
-   Примеры использования
