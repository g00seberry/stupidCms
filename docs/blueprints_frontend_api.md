# Blueprint System — API для фронтенда

Исчерпывающий справочник по работе с Blueprint API stupidCms для фронтенд-разработчиков.

---

## Оглавление

1. [Обзор системы](#обзор-системы)
2. [Архитектура](#архитектура)
3. [Аутентификация](#аутентификация)
4. [API Endpoints](#api-endpoints)
5. [Модель данных](#модель-данных)
6. [Валидация и ограничения](#валидация-и-ограничения)
7. [Механизм индексации](#механизм-индексации)
8. [Работа с компонентами](#работа-с-компонентами)
9. [Коды ошибок](#коды-ошибок)
10. [Производительность и кеширование](#производительность-и-кеширование)

---

## Обзор системы

### Назначение

Blueprint System — динамическая система управления схемами полей для Entry (записей контента). Позволяет создавать, модифицировать и переиспользовать структуры данных без изменения кода.

### Ключевые возможности

- Создание произвольных схем полей для Entry любого PostType
- Переиспользование компонентов схем между различными Blueprint
- Автоматическая индексация произвольных JSON-полей для быстрого поиска
- Материализация полей из компонентов с префиксами
- Вложенные структуры данных в Entry через dot-notation
- Типизированные поля с валидацией
- Связи между Entry через ref-поля
- Динамическое изменение схем без простоя

### Базовый URL

```
/api/v1/admin/blueprints
```

### Концептуальная модель

**Blueprint** → схема полей  
**Path** → описание одного поля (тип, индексация, валидация)  
**Entry** → запись контента с data_json, валидируемым по Blueprint  
**DocValue** → индексированное скалярное значение из Entry  
**DocRef** → индексированная ссылка Entry → Entry

---

## Архитектура

### Типы Blueprint

#### Full Blueprint
- Привязан к конкретному PostType через `post_type_id`
- Используется для создания Entry
- Может содержать собственные Paths и подключать компоненты
- `type: "full"`

#### Component Blueprint
- Независим от PostType (`post_type_id: null`)
- Переиспользуемый набор Paths
- Не может использоваться для создания Entry напрямую
- Подключается к Full Blueprint через композицию
- `type: "component"`

### Типы Path

#### Собственные Paths (Own Paths)
- Созданы напрямую в Blueprint
- `source_component_id: null`
- Могут редактироваться и удаляться

#### Материализованные Paths (Materialized Paths)
- Копии Paths из подключенных компонентов
- `source_component_id: <id компонента>`
- `source_path_id: <id оригинального Path>`
- Автоматически синхронизируются с источником
- Удаляются при отключении компонента
- Только для чтения, изменяются через компонент-источник

### Материализация компонентов

При подключении компонента к Full Blueprint:
1. Все Paths компонента копируются в Full Blueprint с префиксом `path_prefix`
2. Создается связь в таблице `blueprint_components`
3. `full_path` материализованных Paths = `{path_prefix}.{component_path.full_path}`
4. Запускается реиндексация всех Entry этого Blueprint (асинхронно)

При отключении компонента:
1. Все материализованные Paths удаляются
2. Связь из `blueprint_components` удаляется
3. Индексы (`doc_values`, `doc_refs`) очищаются через реиндексацию

---

## Аутентификация

Все endpoints требуют JWT аутентификацию через заголовок:

```
Authorization: Bearer <JWT_TOKEN>
```

### Middleware
- `jwt.auth` — проверка валидности JWT токена
- `throttle:api` — rate limiting 120 запросов/минуту

### Коды при ошибках авторизации
- `401 Unauthorized` — токен отсутствует или невалиден
- `429 Too Many Requests` — превышен лимит запросов

---

## API Endpoints

### Blueprint Management

#### `GET /api/v1/admin/blueprints`

Получить список всех Blueprint с пагинацией.

**Query параметры:**
- `post_type_id` (integer, optional) — фильтр по PostType
- `type` (string, optional) — фильтр по типу: `full` или `component`
- `page` (integer, optional, default: 1) — номер страницы
- `per_page` (integer, optional, default: 20) — количество на странице

**Ответ:** `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "post_type_id": 1,
      "slug": "article_full",
      "name": "Article Full",
      "description": "Полная схема статьи",
      "type": "full",
      "is_default": true,
      "created_at": "2025-11-19T10:00:00.000000Z",
      "updated_at": "2025-11-19T10:00:00.000000Z",
      "post_type": {
        "id": 1,
        "slug": "article",
        "name": "Article"
      }
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

---

#### `GET /api/v1/admin/blueprints/{id}`

Получить Blueprint по ID с подробной информацией.

**Path параметры:**
- `id` (integer, required) — ID Blueprint

**Ответ:** `200 OK`

```json
{
  "data": {
    "id": 1,
    "post_type_id": 1,
    "slug": "article_full",
    "name": "Article Full",
    "description": "Полная схема статьи",
    "type": "full",
    "is_default": true,
    "created_at": "2025-11-19T10:00:00.000000Z",
    "updated_at": "2025-11-19T10:00:00.000000Z",
    "post_type": {
      "id": 1,
      "slug": "article",
      "name": "Article"
    },
    "paths": [...],
    "components": [...]
  }
}
```

**Загружаемые связи:**
- `post_type` — информация о PostType
- `paths` — все Paths (собственные + материализованные)
- `components` — подключенные компоненты

**Коды ошибок:**
- `404 Not Found` — Blueprint не найден

---

#### `POST /api/v1/admin/blueprints`

Создать новый Blueprint.

**Body (Full Blueprint):**

```json
{
  "post_type_id": 1,
  "slug": "my-blueprint",
  "name": "My Blueprint",
  "description": "Описание",
  "type": "full",
  "is_default": false
}
```

**Body (Component Blueprint):**

```json
{
  "slug": "gallery",
  "name": "Gallery Component",
  "description": "Переиспользуемая галерея",
  "type": "component"
}
```

**Обязательные поля:**
- `slug` — уникальный идентификатор (a-z0-9_-)
- `name` — человекочитаемое имя
- `type` — тип Blueprint: `full` или `component`

**Опциональные поля:**
- `description` — описание Blueprint
- `post_type_id` — ID PostType (обязателен для `type: full`, должен быть `null` для `type: component`)
- `is_default` — флаг Blueprint по умолчанию для PostType

**Ответ:** `201 Created`

```json
{
  "data": {
    "id": 5,
    "slug": "my-blueprint",
    "name": "My Blueprint",
    "type": "full",
    ...
  }
}
```

**Коды ошибок:**
- `422 Unprocessable Entity` — ошибка валидации
- `409 Conflict` — Blueprint с таким slug уже существует

---

#### `PUT /api/v1/admin/blueprints/{id}`

Обновить Blueprint.

**Path параметры:**
- `id` (integer, required) — ID Blueprint

**Body:**

```json
{
  "name": "Updated Name",
  "description": "Updated description"
}
```

**Изменяемые поля:**
- `name` — имя
- `description` — описание

**Неизменяемые поля:**
- `slug` — не изменяется после создания
- `type` — не изменяется после создания
- `post_type_id` — не изменяется после создания

**Ответ:** `200 OK`

```json
{
  "data": { ... }
}
```

**Коды ошибок:**
- `404 Not Found` — Blueprint не найден
- `422 Unprocessable Entity` — ошибка валидации

---

#### `DELETE /api/v1/admin/blueprints/{id}`

Удалить Blueprint.

**Path параметры:**
- `id` (integer, required) — ID Blueprint

**Ограничения:**
- Нельзя удалить Blueprint, если существуют Entry с этим Blueprint

**Ответ:** `200 OK`

```json
{
  "message": "Blueprint deleted"
}
```

**Коды ошибок:**
- `404 Not Found` — Blueprint не найден
- `422 Unprocessable Entity` — существуют связанные Entry

```json
{
  "message": "Cannot delete Blueprint with existing entries",
  "entries_count": 42
}
```

---

### Path Management

#### `GET /api/v1/admin/blueprints/{blueprint}/paths`

Получить список Paths Blueprint.

**Path параметры:**
- `blueprint` (integer, required) — ID Blueprint

**Query параметры:**
- `own_only` (boolean, optional, default: false) — показать только собственные Paths (без материализованных)

**Ответ:** `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "blueprint_id": 1,
      "source_component_id": null,
      "source_path_id": null,
      "parent_id": null,
      "name": "title",
      "full_path": "title",
      "data_type": "string",
      "cardinality": "one",
      "is_indexed": true,
      "is_required": true,
      "ref_target_type": null,
      "validation_rules": null,
      "ui_options": null,
      "created_at": "2025-11-19T10:00:00.000000Z",
      "updated_at": "2025-11-19T10:00:00.000000Z",
      "is_materialized": false,
      "is_ref": false,
      "is_many": false
    }
  ]
}
```

---

#### `GET /api/v1/admin/blueprints/{blueprint}/paths/{path}`

Получить Path по ID.

**Path параметры:**
- `blueprint` (integer, required) — ID Blueprint
- `path` (integer, required) — ID Path

**Ответ:** `200 OK`

```json
{
  "data": { ... }
}
```

**Коды ошибок:**
- `404 Not Found` — Path не найден

---

#### `POST /api/v1/admin/blueprints/{blueprint}/paths`

Создать новый Path в Blueprint.

**Path параметры:**
- `blueprint` (integer, required) — ID Blueprint

**Body:**

```json
{
  "blueprint_id": 1,
  "name": "excerpt",
  "full_path": "excerpt",
  "data_type": "text",
  "cardinality": "one",
  "is_indexed": true,
  "is_required": false,
  "validation_rules": null,
  "ui_options": null
}
```

**Обязательные поля:**
- `blueprint_id` — ID Blueprint
- `name` — имя поля (a-zA-Z_][a-zA-Z0-9_]*)
- `full_path` — полный путь (dot-notation для вложенных, уникален в Blueprint)
- `data_type` — тип данных (см. [Типы данных](#типы-данных))
- `cardinality` — количество значений: `one` или `many`

**Опциональные поля:**
- `is_indexed` (boolean, default: false) — индексировать ли поле
- `is_required` (boolean, default: false) — обязательное ли поле
- `ref_target_type` (string, optional) — для `data_type: ref` указывает тип целевого Entry
- `validation_rules` (object, optional) — правила валидации (JSON)
- `ui_options` (object, optional) — опции для UI (JSON)
- `parent_id` (integer, optional) — ID родительского Path (для вложенных полей, запрещено в component Blueprint)

**Ответ:** `201 Created`

```json
{
  "data": { ... }
}
```

**Побочные эффекты:**
1. Инвалидация кеша Paths Blueprint
2. Если Path создан в component Blueprint:
   - Материализация в все Full Blueprints, использующие этот компонент
   - Запуск реиндексации Entry этих Blueprint (асинхронно)

**Коды ошибок:**
- `404 Not Found` — Blueprint не найден
- `422 Unprocessable Entity` — ошибка валидации

---

#### `PUT /api/v1/admin/blueprints/{blueprint}/paths/{path}`

Обновить Path.

**Path параметры:**
- `blueprint` (integer, required) — ID Blueprint
- `path` (integer, required) — ID Path

**Body:**

```json
{
  "name": "Updated name",
  "is_indexed": true
}
```

**Изменяемые поля:**
- `name` — имя поля
- `is_indexed` — флаг индексации
- `is_required` — флаг обязательности
- `validation_rules` — правила валидации
- `ui_options` — опции UI

**Критические изменения (триггерят реиндексацию):**
- Изменение `data_type`
- Изменение `cardinality`
- Изменение `is_indexed`

**Неизменяемые поля:**
- `blueprint_id` — не изменяется
- `full_path` — не изменяется после создания
- `source_component_id` — системное поле (только чтение)
- `source_path_id` — системное поле (только чтение)

**Ответ:** `200 OK`

```json
{
  "data": { ... }
}
```

**Побочные эффекты:**
1. Инвалидация кеша Paths Blueprint
2. При критических изменениях: реиндексация Entry этого Blueprint (асинхронно)
3. Если Path принадлежит component Blueprint: изменения распространяются на материализованные копии

**Коды ошибок:**
- `404 Not Found` — Path не найден
- `422 Unprocessable Entity` — ошибка валидации

---

#### `DELETE /api/v1/admin/blueprints/{blueprint}/paths/{path}`

Удалить Path.

**Path параметры:**
- `blueprint` (integer, required) — ID Blueprint
- `path` (integer, required) — ID Path

**Ответ:** `200 OK`

```json
{
  "message": "Path deleted"
}
```

**Побочные эффекты:**
1. Удаление всех индексов (`doc_values`, `doc_refs`) для этого Path
2. Если Path из component Blueprint: удаление всех материализованных копий в Full Blueprints
3. Инвалидация кеша Paths Blueprint

**Коды ошибок:**
- `404 Not Found` — Path не найден

---

### Component Management

#### `GET /api/v1/admin/blueprints/{blueprint}/components`

Получить список компонентов, подключенных к Blueprint.

**Path параметры:**
- `blueprint` (integer, required) — ID Blueprint

**Ответ:** `200 OK`

```json
{
  "data": [
    {
      "id": 2,
      "slug": "seo_fields",
      "name": "SEO Fields",
      "type": "component",
      ...
    }
  ]
}
```

---

#### `POST /api/v1/admin/blueprints/{blueprint}/components`

Подключить компонент к Blueprint.

**Path параметры:**
- `blueprint` (integer, required) — ID Full Blueprint

**Body:**

```json
{
  "component_id": 2,
  "path_prefix": "seo"
}
```

**Обязательные поля:**
- `component_id` — ID component Blueprint
- `path_prefix` — префикс для материализованных Paths (a-zA-Z_][a-zA-Z0-9_]*)

**Валидация:**
- `component_id` должен быть `type: component`
- Нельзя подключить Blueprint к самому себе
- Проверка циклических зависимостей
- `path_prefix` должен быть уникален в рамках Blueprint
- Проверка конфликтов `full_path` с существующими Paths

**Ответ:** `200 OK`

```json
{
  "message": "Component attached successfully",
  "blueprint": { ... }
}
```

**Побочные эффекты:**
1. Материализация всех Paths компонента в Full Blueprint с префиксом
2. Создание связи в `blueprint_components`
3. Реиндексация всех Entry этого Blueprint (асинхронно)
4. Инвалидация кеша Paths

**Коды ошибок:**
- `404 Not Found` — Blueprint или компонент не найдены
- `422 Unprocessable Entity` — ошибка валидации (конфликт, цикл и т.д.)

---

#### `DELETE /api/v1/admin/blueprints/{blueprint}/components/{component}`

Отключить компонент от Blueprint.

**Path параметры:**
- `blueprint` (integer, required) — ID Full Blueprint
- `component` (integer, required) — ID component Blueprint

**Ответ:** `200 OK`

```json
{
  "message": "Component detached successfully"
}
```

**Побочные эффекты:**
1. Удаление всех материализованных Paths компонента
2. Удаление связи из `blueprint_components`
3. Реиндексация всех Entry этого Blueprint (асинхронно)
4. Инвалидация кеша Paths

**Коды ошибок:**
- `404 Not Found` — Blueprint или компонент не найдены

---

## Модель данных

### Blueprint

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | integer | Уникальный ID |
| `post_type_id` | integer\|null | ID PostType (обязателен для `full`, null для `component`) |
| `slug` | string | Уникальный идентификатор (a-z0-9_-) |
| `name` | string | Имя Blueprint |
| `description` | string\|null | Описание |
| `type` | enum | Тип: `full` или `component` |
| `is_default` | boolean | Blueprint по умолчанию для PostType |
| `created_at` | datetime | Дата создания |
| `updated_at` | datetime | Дата обновления |
| `deleted_at` | datetime\|null | Дата удаления (soft delete) |

**Связи:**
- `post_type` — связь с PostType (belongsTo)
- `paths` — все Paths (hasMany)
- `own_paths` — только собственные Paths (hasMany)
- `materialized_paths` — только материализованные Paths (hasMany)
- `entries` — Entry, использующие этот Blueprint (hasMany)
- `components` — подключенные компоненты (belongsToMany через `blueprint_components`)
- `used_in_blueprints` — Full Blueprints, где этот компонент используется (belongsToMany)

---

### Path

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | integer | Уникальный ID |
| `blueprint_id` | integer | ID Blueprint |
| `source_component_id` | integer\|null | ID компонента-источника (для материализованных) |
| `source_path_id` | integer\|null | ID оригинального Path (для материализованных) |
| `parent_id` | integer\|null | ID родительского Path (для вложенных) |
| `name` | string | Имя поля |
| `full_path` | string | Полный путь (dot-notation, уникален в Blueprint) |
| `data_type` | enum | Тип данных (см. [Типы данных](#типы-данных)) |
| `cardinality` | enum | `one` или `many` |
| `is_indexed` | boolean | Индексировать ли поле |
| `is_required` | boolean | Обязательное ли поле |
| `ref_target_type` | string\|null | Тип целевого Entry для `data_type: ref` |
| `validation_rules` | object\|null | Правила валидации (JSON) |
| `ui_options` | object\|null | Опции для UI (JSON) |
| `created_at` | datetime | Дата создания |
| `updated_at` | datetime | Дата обновления |
| `deleted_at` | datetime\|null | Дата удаления (soft delete) |

**Вычисляемые поля (в ресурсе):**
- `is_materialized` — `source_component_id !== null`
- `is_ref` — `data_type === 'ref'`
- `is_many` — `cardinality === 'many'`

**Связи:**
- `blueprint` — Blueprint (belongsTo)
- `parent` — родительский Path (belongsTo)
- `children` — дочерние Paths (hasMany)
- `values` — индексированные значения (hasMany DocValue)
- `refs` — индексированные ссылки (hasMany DocRef)

---

### DocValue

Индексированное скалярное значение из `data_json` Entry.

| Поле | Тип | Описание |
|------|-----|----------|
| `entry_id` | integer | ID Entry (composite PK) |
| `path_id` | integer | ID Path (composite PK) |
| `idx` | integer | Индекс для `cardinality: many` (composite PK) |
| `value_string` | string\|null | Значение для `data_type: string` |
| `value_int` | integer\|null | Значение для `data_type: int` |
| `value_float` | float\|null | Значение для `data_type: float` |
| `value_bool` | boolean\|null | Значение для `data_type: bool` |
| `value_text` | text\|null | Значение для `data_type: text` |
| `value_json` | jsonb\|null | Значение для `data_type: json` |
| `created_at` | datetime | Дата создания |

**Primary Key:** (`entry_id`, `path_id`, `idx`)

**Индексы:**
- `idx_doc_values_path_string` на (`path_id`, `value_string`)
- `idx_doc_values_path_int` на (`path_id`, `value_int`)
- `idx_doc_values_path_float` на (`path_id`, `value_float`)
- `idx_doc_values_path_bool` на (`path_id`, `value_bool`)
- `idx_doc_values_path_text` на (`path_id`, `value_text`) — GIN index для полнотекстового поиска
- `idx_doc_values_path_json` на (`path_id`, `value_json`) — GIN index

---

### DocRef

Индексированная ссылка Entry → Entry для `data_type: ref`.

| Поле | Тип | Описание |
|------|-----|----------|
| `entry_id` | integer | ID Entry-владельца (composite PK) |
| `path_id` | integer | ID Path (composite PK) |
| `idx` | integer | Индекс для `cardinality: many` (composite PK) |
| `target_entry_id` | integer | ID целевого Entry |
| `created_at` | datetime | Дата создания |

**Primary Key:** (`entry_id`, `path_id`, `idx`)

**Индексы:**
- `idx_doc_refs_path_target` на (`path_id`, `target_entry_id`)
- `idx_doc_refs_target` на (`target_entry_id`)

---

### blueprint_components (pivot)

Связь Blueprint ↔ Component Blueprint.

| Поле | Тип | Описание |
|------|-----|----------|
| `blueprint_id` | integer | ID Full Blueprint (composite PK) |
| `component_id` | integer | ID Component Blueprint (composite PK) |
| `path_prefix` | string | Префикс для материализованных Paths |
| `created_at` | datetime | Дата создания |
| `updated_at` | datetime | Дата обновления |

**Primary Key:** (`blueprint_id`, `component_id`)

---

## Валидация и ограничения

### Blueprint

#### Создание

**slug:**
- Формат: `^[a-z0-9_-]+$`
- Длина: max 255
- Уникальность: в пределах (`type`, `post_type_id`) для `full`; глобально для `component`

**name:**
- Длина: max 255
- Обязателен

**type:**
- Значения: `full`, `component`
- Неизменяем после создания

**post_type_id:**
- Обязателен для `type: full`
- Должен быть `null` для `type: component`
- Должен существовать в `post_types`
- Неизменяем после создания

#### Обновление

Изменяемые поля: `name`, `description`, `is_default`

Неизменяемые поля: `slug`, `type`, `post_type_id`

#### Удаление

Нельзя удалить Blueprint, если:
- Существуют Entry с `blueprint_id = <id>`

---

### Path

#### Создание

**name:**
- Формат: `^[a-zA-Z_][a-zA-Z0-9_]*$`
- Длина: max 100
- Обязателен

**full_path:**
- Длина: max 500
- Уникален в пределах `blueprint_id`
- Обязателен

**data_type:**
- Значения: `string`, `int`, `float`, `bool`, `text`, `json`, `ref`
- Обязателен

**cardinality:**
- Значения: `one`, `many`
- Обязателен

**ref_target_type:**
- Обязателен для `data_type: ref`
- Должен быть `null` для других типов

**parent_id:**
- Должен существовать в `paths`
- Запрещен для component Blueprint

#### Обновление

Критические изменения (триггерят реиндексацию):
- `data_type`
- `cardinality`
- `is_indexed`

Неизменяемые поля: `blueprint_id`, `full_path`, `source_component_id`, `source_path_id`

#### Удаление

Побочные эффекты:
- Каскадное удаление всех `doc_values` и `doc_refs` для этого Path
- Каскадное удаление материализованных копий (если Path в component Blueprint)

---

### Component Attachment

**component_id:**
- Должен существовать в `blueprints`
- Должен иметь `type: component`
- Не может быть равен `blueprint_id` (запрет self-reference)
- Проверка циклических зависимостей

**path_prefix:**
- Формат: `^[a-zA-Z_][a-zA-Z0-9_]*$`
- Длина: max 100
- Уникален в пределах Blueprint
- Обязателен

**Конфликты full_path:**
- Для каждого Path компонента проверяется, что `{path_prefix}.{path.full_path}` не существует в целевом Blueprint

---

## Типы данных

### string
- Короткая строка (до 500 символов)
- Хранится в `doc_values.value_string` (VARCHAR)
- Индексируется через B-Tree
- Поддерживает операторы: `=`, `!=`, `LIKE`, `IN`

### int
- Целое число (32-bit signed integer)
- Хранится в `doc_values.value_int` (INTEGER)
- Индексируется через B-Tree
- Поддерживает операторы: `=`, `!=`, `<`, `>`, `<=`, `>=`, `IN`

### float
- Число с плавающей точкой (double precision)
- Хранится в `doc_values.value_float` (DOUBLE)
- Индексируется через B-Tree
- Поддерживает операторы: `=`, `!=`, `<`, `>`, `<=`, `>=`

### bool
- Логическое значение
- Хранится в `doc_values.value_bool` (BOOLEAN)
- Индексируется через B-Tree
- Поддерживает операторы: `=`, `!=`

### text
- Длинный текст (без ограничения)
- Хранится в `doc_values.value_text` (TEXT)
- Индексируется через GIN (полнотекстовый поиск)
- Поддерживает операторы: `LIKE`, `ILIKE`, полнотекстовый поиск

### json
- Произвольный JSON
- Хранится в `doc_values.value_json` (JSONB)
- Индексируется через GIN
- Поддерживает JSON-операторы PostgreSQL

### ref
- Ссылка на другой Entry
- Хранится в `doc_refs.target_entry_id` (INTEGER)
- Индексируется через B-Tree
- Поддерживает поиск по связям
- Требует `ref_target_type` (опционально: slug PostType для валидации)

---

## Механизм индексации

### Когда происходит индексация

1. При создании Entry с `blueprint_id`
2. При обновлении `data_json` Entry
3. При изменении критических полей Path (реиндексация всех Entry)
4. При подключении/отключении компонента (реиндексация всех Entry)

### Процесс индексации Entry

1. **Извлечение индексируемых Paths**
   - Получение всех Paths Blueprint с `is_indexed: true`
   
2. **Парсинг data_json**
   - Для каждого индексируемого Path извлекается значение по `full_path` через dot-notation
   
3. **Создание записей DocValue (для скалярных типов)**
   - Если `cardinality: one` → 1 запись с `idx: 0`
   - Если `cardinality: many` → N записей с `idx: 0..N-1`
   - Значение помещается в соответствующее поле `value_*` по `data_type`
   
4. **Создание записей DocRef (для ref-типов)**
   - Аналогично DocValue, но `target_entry_id` вместо `value_*`
   
5. **Удаление старых индексов**
   - Перед созданием новых все существующие `doc_values`/`doc_refs` для этого Entry удаляются

### Пример data_json

```json
{
  "title": "My Article",
  "content": "Long text...",
  "seo": {
    "metaTitle": "SEO Title",
    "metaDescription": "SEO Description"
  },
  "relatedArticles": [10, 15, 20],
  "author": {
    "name": "John Doe"
  }
}
```

**Индексируемые Paths:**
- `title` (string, one, indexed)
- `seo.metaTitle` (string, one, indexed)
- `author.name` (string, one, indexed)
- `relatedArticles` (ref, many, indexed)

**Результат индексации:**

`doc_values`:
```
entry_id | path_id | idx | value_string
---------|---------|-----|-------------
42       | 1       | 0   | "My Article"
42       | 2       | 0   | "SEO Title"
42       | 5       | 0   | "John Doe"
```

`doc_refs`:
```
entry_id | path_id | idx | target_entry_id
---------|---------|-----|----------------
42       | 4       | 0   | 10
42       | 4       | 1   | 15
42       | 4       | 2   | 20
```

### Асинхронная реиндексация

При массовых изменениях (подключение компонента, изменение Path) реиндексация Entry выполняется асинхронно через очередь:
- Job: `ReindexBlueprintEntries`
- Обрабатывает Entry батчами
- Не блокирует API-запросы

---

## Работа с компонентами

### Жизненный цикл компонента

#### 1. Создание component Blueprint

```http
POST /api/v1/admin/blueprints
{
  "slug": "seo_fields",
  "name": "SEO Fields",
  "type": "component"
}
```

#### 2. Добавление Paths в компонент

```http
POST /api/v1/admin/blueprints/{component_id}/paths
{
  "blueprint_id": {component_id},
  "name": "metaTitle",
  "full_path": "metaTitle",
  "data_type": "string",
  "is_indexed": true
}
```

#### 3. Подключение компонента к Full Blueprint

```http
POST /api/v1/admin/blueprints/{full_blueprint_id}/components
{
  "component_id": {component_id},
  "path_prefix": "seo"
}
```

**Результат:**
- В Full Blueprint появятся материализованные Paths: `seo.metaTitle`, ...
- Запустится реиндексация Entry
- Entry смогут использовать поля компонента в `data_json`:

```json
{
  "seo": {
    "metaTitle": "...",
    "metaDescription": "..."
  }
}
```

#### 4. Изменение компонента

При изменении Paths в component Blueprint:
- Изменения автоматически распространяются на материализованные копии
- Запускается реиндексация всех Entry всех Full Blueprints, использующих компонент

#### 5. Отключение компонента

```http
DELETE /api/v1/admin/blueprints/{full_blueprint_id}/components/{component_id}
```

**Результат:**
- Удаляются все материализованные Paths компонента
- Удаляются индексы из `doc_values`/`doc_refs`
- Данные в `data_json` Entry сохраняются, но перестают индексироваться

---

### Стратегии использования компонентов

#### Стратегия 1: SEO-поля для всех типов контента

Создать компонент `seo_fields` и подключить ко всем Full Blueprints с префиксом `seo`.

**Преимущества:**
- Единая структура SEO-данных
- Изменения в одном месте → распространяются везде
- Переиспользование UI-компонентов фронтенда

#### Стратегия 2: Вложенные блоки контента

Создать компоненты для переиспользуемых блоков: `gallery`, `video_embed`, `cta_block`.

Подключать к Blueprint с разными префиксами:

```json
{
  "hero": {
    "gallery": { ... }
  },
  "footer": {
    "cta_block": { ... }
  }
}
```

#### Стратегия 3: Адреса, контакты, мета-информация

Создать компоненты `address_fields`, `contact_fields` для разных сущностей (пользователи, организации, точки продаж).

---

## Коды ошибок

### Общие коды

| Код | Значение | Когда возникает |
|-----|----------|------------------|
| `200 OK` | Успешный запрос | GET, PUT, DELETE |
| `201 Created` | Ресурс создан | POST |
| `400 Bad Request` | Некорректный запрос | Синтаксическая ошибка JSON |
| `401 Unauthorized` | Не авторизован | Отсутствует/невалиден JWT токен |
| `403 Forbidden` | Доступ запрещен | Недостаточно прав |
| `404 Not Found` | Ресурс не найден | Несуществующий ID |
| `409 Conflict` | Конфликт | Дубликат уникального поля |
| `422 Unprocessable Entity` | Ошибка валидации | Невалидные данные |
| `429 Too Many Requests` | Превышен лимит | Rate limiting |
| `500 Internal Server Error` | Внутренняя ошибка | Ошибка сервера |

### Специфичные ошибки Blueprint

#### Удаление Blueprint с Entry

```json
{
  "message": "Cannot delete Blueprint with existing entries",
  "entries_count": 42
}
```

Код: `422 Unprocessable Entity`

#### Дубликат slug

```json
{
  "errors": {
    "slug": ["Component Blueprint с таким slug уже существует."]
  }
}
```

Код: `422 Unprocessable Entity`

#### post_type_id для component

```json
{
  "errors": {
    "post_type_id": ["post_type_id должен быть null для component Blueprint."]
  }
}
```

Код: `422 Unprocessable Entity`

### Специфичные ошибки Path

#### Дубликат full_path

```json
{
  "errors": {
    "full_path": ["Path с full_path 'title' уже существует в этом Blueprint."]
  }
}
```

Код: `422 Unprocessable Entity`

#### Невалидное имя

```json
{
  "errors": {
    "name": ["Имя поля должно начинаться с буквы или подчёркивания и содержать только буквы, цифры и подчёркивания."]
  }
}
```

Код: `422 Unprocessable Entity`

#### parent_id в component

```json
{
  "errors": {
    "parent_id": ["Вложенные Paths (parent_id) не поддерживаются в component Blueprint."]
  }
}
```

Код: `422 Unprocessable Entity`

### Специфичные ошибки компонентов

#### Self-reference

```json
{
  "errors": {
    "component_id": ["Нельзя прикрепить Blueprint сам к себе."]
  }
}
```

Код: `422 Unprocessable Entity`

#### Циклическая зависимость

```json
{
  "errors": {
    "component_id": ["Обнаружен цикл: компонент уже использует данный Blueprint."]
  }
}
```

Код: `422 Unprocessable Entity`

#### Конфликт full_path

```json
{
  "errors": {
    "path_prefix": ["Конфликт: Path 'seo.metaTitle' уже существует в Blueprint."]
  }
}
```

Код: `422 Unprocessable Entity`

#### Дубликат path_prefix

```json
{
  "errors": {
    "path_prefix": ["path_prefix 'seo' уже используется в этом Blueprint."]
  }
}
```

Код: `422 Unprocessable Entity`

#### Не component тип

```json
{
  "errors": {
    "component_id": ["Можно прикрепить только component Blueprint."]
  }
}
```

Код: `422 Unprocessable Entity`

---

## Производительность и кеширование

### Кеширование Paths

**Механизм:**
- `Blueprint::getAllPaths()` кеширует результат на 1 час
- Ключ кеша: `blueprint:{blueprint_id}:all_paths`
- Инвалидация при любых изменениях Paths (создание, обновление, удаление, attach/detach компонентов)

**Рекомендации:**
- Фронтенд может агрессивно кешировать список Paths для построения форм
- При изменении Blueprint инвалидировать локальный кеш

### Пагинация

**GET /api/v1/admin/blueprints:**
- Default: 20 записей на странице
- Настраивается через `?per_page=N`
- Max: 100 записей на странице

**GET /api/v1/admin/blueprints/{id}/paths:**
- Без пагинации (возвращает все Paths)
- Обычно < 100 Paths на Blueprint

### Rate Limiting

**Throttle:** 120 запросов/минуту на IP

**Заголовки ответа:**
```
X-RateLimit-Limit: 120
X-RateLimit-Remaining: 115
X-RateLimit-Reset: 1732022400
```

**При превышении лимита:**
```
HTTP/1.1 429 Too Many Requests
Retry-After: 60

{
  "message": "Too Many Requests"
}
```

### Асинхронные операции

**Реиндексация Entry:**
- Выполняется через очередь (queue)
- Не блокирует API-ответ
- Статус реиндексации недоступен через API (фоновая операция)

**Триггеры реиндексации:**
- Подключение/отключение компонента
- Изменение критических полей Path (`data_type`, `cardinality`, `is_indexed`)
- Добавление/удаление Path в component Blueprint

**Рекомендации:**
- После операций с компонентами учитывать задержку (несколько секунд) перед проверкой индексов
- Для критичных сценариев использовать polling или websocket для отслеживания статуса

### Оптимизация запросов

**Eager Loading:**
- `GET /api/v1/admin/blueprints/{id}` загружает связи через `with(['postType', 'paths', 'components'])`
- Избегайте N+1 запросов: всегда запрашивайте полный ресурс с нужными связями

**Фильтрация:**
- Используйте `?own_only=true` для получения только собственных Paths (без материализованных)
- Используйте `?type=component` для получения только компонентов

---

## Использование с Entry API

### Создание Entry с Blueprint

```http
POST /api/v1/admin/entries
{
  "post_type_id": 1,
  "blueprint_id": 1,
  "title": "My Article",
  "slug": "my-article",
  "status": "published",
  "data_json": {
    "content": "Article content...",
    "seo": {
      "metaTitle": "SEO Title",
      "metaDescription": "SEO Description"
    },
    "relatedArticles": [2, 3, 4]
  }
}
```

**Валидация `data_json`:**
- Структура должна соответствовать Paths Blueprint
- Обязательные поля (`is_required: true`) должны присутствовать
- Типы данных должны соответствовать `data_type`
- Ссылки (`ref`) должны указывать на существующие Entry

**Автоматическая индексация:**
- При сохранении Entry автоматически создаются записи в `doc_values` и `doc_refs` для всех `is_indexed: true` Paths

### Поиск Entry по индексам

**Фронтенд не выполняет SQL-запросы напрямую**, но Entry API предоставляет query-параметры для фильтрации:

```http
GET /api/v1/admin/entries?filter[path][seo.metaTitle]=SEO Title
GET /api/v1/admin/entries?filter[ref][relatedArticles]=5
```

Детали фильтрации по Paths уточняйте в Entry API документации.

---

## Workflow для фронтенд-разработчика

### 1. Получение списка Blueprint для PostType

```http
GET /api/v1/admin/blueprints?post_type_id=1&type=full
```

Использование: выбор схемы при создании Entry.

### 2. Получение Paths для построения формы

```http
GET /api/v1/admin/blueprints/{blueprint_id}/paths
```

Использование:
- Построение динамической формы редактирования Entry
- Определение полей, их типов, валидации (`validation_rules`), UI-опций (`ui_options`)

### 3. Создание/редактирование Entry

```http
POST /api/v1/admin/entries
{
  "blueprint_id": 1,
  "data_json": { ... }
}
```

Использование: сохранение данных из динамической формы.

### 4. Управление Blueprint (админ-панель)

**Создание нового Blueprint:**
```http
POST /api/v1/admin/blueprints
```

**Добавление Paths:**
```http
POST /api/v1/admin/blueprints/{id}/paths
```

**Подключение компонентов:**
```http
POST /api/v1/admin/blueprints/{id}/components
```

### 5. Переиспользование компонентов

**Получение списка доступных компонентов:**
```http
GET /api/v1/admin/blueprints?type=component
```

**Просмотр Paths компонента:**
```http
GET /api/v1/admin/blueprints/{component_id}/paths
```

**Подключение к Blueprint:**
```http
POST /api/v1/admin/blueprints/{blueprint_id}/components
{
  "component_id": {component_id},
  "path_prefix": "prefix"
}
```

---

## Безопасность

### Аутентификация и авторизация

- Все endpoints требуют JWT токен
- Права доступа проверяются через Laravel Gates/Policies (детали уточняйте в документации авторизации)

### Валидация данных

- Все входные данные валидируются через FormRequest классы
- Предотвращение SQL-инъекций через Eloquent ORM
- Санитизация строковых данных

### Rate Limiting

- 120 запросов/минуту защищают от DDoS и злоупотреблений

### Soft Deletes

- Blueprint и Path удаляются через soft delete (`deleted_at`)
- Возможно восстановление через прямые SQL-запросы (нет API endpoint для restore)

---

## Глоссарий

**Blueprint** — схема полей для Entry; определяет структуру `data_json`  
**Path** — описание одного поля в Blueprint  
**Full Blueprint** — схема, привязанная к PostType, используется для Entry  
**Component Blueprint** — переиспользуемый набор Paths  
**Own Path** — Path, созданный напрямую в Blueprint  
**Materialized Path** — копия Path из компонента, префиксированная и синхронизируемая  
**path_prefix** — префикс для материализованных Paths при подключении компонента  
**full_path** — уникальный идентификатор Path в Blueprint (dot-notation для вложенных)  
**data_type** — тип данных поля (string, int, float, bool, text, json, ref)  
**cardinality** — количество значений: one (одно) или many (массив)  
**is_indexed** — флаг, указывающий, что поле индексируется в `doc_values`/`doc_refs`  
**DocValue** — индексированное скалярное значение из `data_json`  
**DocRef** — индексированная ссылка Entry → Entry  
**Реиндексация** — пересоздание индексов (`doc_values`, `doc_refs`) для Entry

---

**Документ создан:** 2025-11-19  
**Версия API:** v1  
**Контакт:** stupidCms Development Team

