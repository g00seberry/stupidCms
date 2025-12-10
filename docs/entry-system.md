# Система Entry

Исчерпывающее описание системы управления записями контента (Entry) в headless CMS.

**Дата создания:** 2025-12-09  
**Версия Laravel:** 12  
**Версия PHP:** 8.3+

---

## Содержание

1. [Обзор системы](#обзор-системы)
2. [Модель Entry](#модель-entry)
3. [База данных](#база-данных)
4. [API Endpoints](#api-endpoints)
5. [Валидация](#валидация)
6. [Индексация данных](#индексация-данных)
7. [Observer и автоматизация](#observer-и-автоматизация)
8. [Связи с другими сущностями](#связи-с-другими-сущностями)
9. [Авторизация](#авторизация)
10. [API Resources](#api-resources)
11. [Фильтрация и поиск](#фильтрация-и-поиск)
12. [Особенности реализации](#особенности-реализации)

---

## Обзор системы

Entry — центральная сущность CMS, представляющая единицу контента: статьи, страницы, посты и т.д.

### Основные возможности

-   **CRUD операции** — полный набор операций для управления записями
-   **Мягкое удаление** — записи можно восстанавливать
-   **Публикация по расписанию** — поддержка scheduled publishing
-   **Структурированные данные** — гибкая структура через `data_json` и Blueprint
-   **Автоматическая индексация** — индексация данных для быстрого поиска
-   **Связи с термами** — категории, теги через таксономии
-   **Валидация на основе Blueprint** — динамическая валидация структуры данных
-   **HTML санитизация** — автоматическая очистка HTML полей

### Архитектура

```
Entry Model
  ├── PostType (тип записи)
  │   └── Blueprint (структура данных)
  ├── Author (User)
  ├── Terms (категории, теги)
  ├── DocValues (индексированные скалярные значения)
  └── DocRefs (индексированные ссылки на другие Entry)
```

---

## Модель Entry

### Класс

**Файл:** `app/Models/Entry.php`

**Traits:**

-   `HasFactory` — фабрики для тестирования
-   `SoftDeletes` — мягкое удаление
-   `HasDocumentData` — scopes для фильтрации по индексированным данным

### Свойства модели

#### Основные поля

| Поле                | Тип            | Описание                                             |
| ------------------- | -------------- | ---------------------------------------------------- |
| `id`                | `int`          | Уникальный идентификатор                             |
| `post_type_id`      | `int`          | ID типа записи (FK → `post_types.id`)                |
| `title`             | `string`       | Заголовок записи (максимум 500 символов)             |
| `status`            | `string`       | Статус: `'draft'` или `'published'`                  |
| `data_json`         | `array`        | Произвольные структурированные данные контента       |
| `seo_json`          | `array\|null`  | SEO-метаданные (title, description, keywords и т.д.) |
| `published_at`      | `Carbon\|null` | Дата и время публикации (UTC)                        |
| `template_override` | `string\|null` | Кастомный шаблон Blade для рендеринга                |
| `author_id`         | `int\|null`    | ID автора записи (FK → `users.id`)                   |
| `version`           | `int`          | Версия записи (по умолчанию 1)                       |
| `created_at`        | `Carbon`       | Дата создания                                        |
| `updated_at`        | `Carbon`       | Дата обновления                                      |
| `deleted_at`        | `Carbon\|null` | Дата мягкого удаления                                |

#### Константы статусов

```php
public const STATUS_DRAFT = 'draft';
public const STATUS_PUBLISHED = 'published';
```

#### Методы получения статусов

```php
public static function getStatuses(): array
// Возвращает: ['draft', 'published']
```

### Связи (Relations)

#### `postType()`

**Тип:** `BelongsTo<PostType>`

**Описание:** Связь с типом записи.

**Использование:**

```php
$entry->postType; // PostType
$entry->postType->blueprint; // Blueprint через PostType
```

#### `author()`

**Тип:** `BelongsTo<User>`

**Описание:** Связь с автором записи.

**Использование:**

```php
$entry->author; // User
$entry->author->name; // Имя автора
```

#### `terms()`

**Тип:** `BelongsToMany<Term>`

**Описание:** Связь с термами (категории, теги) через промежуточную таблицу `entry_term`.

**Использование:**

```php
$entry->terms; // Collection<Term>
$entry->terms()->sync([1, 2, 3]); // Синхронизация термов
```

#### `docValues()`

**Тип:** `HasMany<DocValue>`

**Описание:** Индексированные скалярные значения из `data_json`.

**Использование:**

```php
$entry->docValues; // Collection<DocValue>
```

#### `docRefs()`

**Тип:** `HasMany<DocRef>`

**Описание:** Индексированные ссылки на другие Entry (исходящие ссылки).

**Использование:**

```php
$entry->docRefs; // Collection<DocRef>
```

#### `docRefsIncoming()`

**Тип:** `HasMany<DocRef>`

**Описание:** Входящие ссылки (кто ссылается на этот Entry).

**Использование:**

```php
$entry->docRefsIncoming; // Collection<DocRef>
```

#### `blueprint()`

**Тип:** `Blueprint|null` (accessor)

**Описание:** Получить Blueprint через PostType.

**Использование:**

```php
$entry->blueprint; // Blueprint|null
```

### Scopes

#### `scopePublished(Builder $q): Builder`

**Описание:** Фильтрует только опубликованные записи.

**Условия:**

-   `status = 'published'`
-   `published_at IS NOT NULL`
-   `published_at <= NOW()`

**Использование:**

```php
Entry::published()->get(); // Только опубликованные
```

#### `scopeOfType(Builder $q, int $postTypeId): Builder`

**Описание:** Фильтрует записи определённого типа.

**Использование:**

```php
Entry::ofType(1)->get(); // Записи типа 1
```

---

## База данных

### Таблица `entries`

**Миграция:** `database/migrations/2025_11_06_000020_create_entries_table.php`

#### Структура

```sql
CREATE TABLE entries (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    post_type_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    status ENUM('draft', 'published') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    author_id BIGINT UNSIGNED NULL,
    data_json JSON NOT NULL,
    seo_json JSON NULL,
    template_override VARCHAR(255) NULL,
    version INT UNSIGNED DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    FOREIGN KEY (post_type_id) REFERENCES post_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX entries_status_published_at_idx (status, published_at),
    INDEX (published_at)
);
```

#### Индексы

1. **`entries_status_published_at_idx`** — составной индекс для оптимизации запросов `scopePublished()`
2. **`published_at`** — индекс для фильтрации по дате публикации

#### Foreign Keys

-   `post_type_id` → `post_types.id` (RESTRICT ON DELETE)
-   `author_id` → `users.id` (SET NULL ON DELETE)

### Промежуточная таблица `entry_term`

**Миграция:** `database/migrations/2025_11_06_000033_create_entry_term_table.php`

**Структура:**

-   `entry_id` — FK → `entries.id`
-   `term_id` — FK → `terms.id`
-   `created_at`, `updated_at` — timestamps

**Назначение:** Связь many-to-many между Entry и Term.

---

## API Endpoints

### Базовый путь

`/api/v1/admin/entries`

### Endpoints

#### 1. `GET /api/v1/admin/entries/statuses`

**Описание:** Получение списка возможных статусов записей.

**Авторизация:** `can:viewAny,Entry`

**Ответ:**

```json
{
    "data": ["draft", "published"]
}
```

#### 2. `GET /api/v1/admin/entries`

**Описание:** Список записей с фильтрами и пагинацией.

**Авторизация:** `can:viewAny,Entry`

**Параметры запроса:**

-   `post_type_id` (int, optional) — фильтр по ID PostType
-   `status` (string, optional) — фильтр по статусу: `all`, `draft`, `published`, `scheduled`, `trashed`
-   `q` (string, optional, max:500) — поиск по названию
-   `author_id` (int, optional) — фильтр по ID автора
-   `term[]` (int[], optional) — фильтр по ID термов
-   `date_field` (string, optional) — поле даты: `updated`, `published` (default: `updated`)
-   `date_from` (date, optional) — начальная дата (ISO 8601)
-   `date_to` (date, optional) — конечная дата (>= date_from)
-   `sort` (string, optional) — сортировка: `updated_at.desc`, `updated_at.asc`, `published_at.desc`, `published_at.asc`, `title.asc`, `title.desc` (default: `updated_at.desc`)
-   `per_page` (int, optional) — количество на странице (10-100, default: 15)

**Ответ:**

```json
{
  "data": [
    {
      "id": 42,
      "post_type_id": 1,
      "title": "Headless CMS launch checklist",
      "status": "draft",
      "data_json": null,
      "meta_json": null,
      "is_published": false,
      "published_at": null,
      "template_override": null,
      "created_at": "2025-01-10T12:00:00+00:00",
      "updated_at": "2025-01-10T12:00:00+00:00",
      "deleted_at": null
    }
  ],
  "links": {...},
  "meta": {...}
}
```

#### 3. `GET /api/v1/admin/entries/{id}`

**Описание:** Получение записи по ID (включая удалённые).

**Авторизация:** `can:view,Entry`

**Ответ:**

```json
{
  "data": {
    "id": 42,
    "post_type_id": 1,
    "title": "Headless CMS launch checklist",
    "status": "published",
    "is_published": true,
    "published_at": "2025-02-10T08:00:00+00:00",
    "data_json": {...},
    "meta_json": {...},
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
    "created_at": "2025-02-09T10:15:00+00:00",
    "updated_at": "2025-02-10T08:05:00+00:00",
    "deleted_at": null
  }
}
```

#### 4. `POST /api/v1/admin/entries`

**Описание:** Создание записи.

**Авторизация:** `can:create,Entry`

**Тело запроса:**

```json
{
    "post_type_id": 1,
    "title": "Headless CMS launch checklist",
    "data_json": {
        "hero": {
            "title": "Launch"
        }
    },
    "meta_json": {
        "title": "Launch",
        "description": "Checklist"
    },
    "is_published": false,
    "published_at": "2025-02-10T08:00:00Z",
    "template_override": "templates.landing",
    "term_ids": [3, 8]
}
```

**Ответ:** 201 Created с данными созданной записи.

#### 5. `PUT /api/v1/admin/entries/{id}`

**Описание:** Обновление записи.

**Авторизация:** `can:update,Entry`

**Тело запроса:** Все поля опциональны (partial update).

**Ответ:** 200 OK с обновлёнными данными.

#### 6. `DELETE /api/v1/admin/entries/{id}`

**Описание:** Мягкое удаление записи.

**Авторизация:** `can:delete,Entry`

**Ответ:** 204 No Content.

#### 7. `POST /api/v1/admin/entries/{id}/restore`

**Описание:** Восстановление мягко удалённой записи.

**Авторизация:** `can:restore,Entry`

**Ответ:** 200 OK с восстановленными данными.

### Entry Terms Endpoints

#### 8. `GET /api/v1/admin/entries/{entry}/terms`

**Описание:** Получение термов записи, сгруппированных по таксономиям.

**Авторизация:** `can:manage.terms`

**Ответ:**

```json
{
    "data": {
        "entry_id": 42,
        "terms_by_taxonomy": [
            {
                "taxonomy": {
                    "id": 1,
                    "label": "Categories",
                    "hierarchical": true,
                    "options_json": {},
                    "created_at": "2025-01-10T12:00:00+00:00",
                    "updated_at": "2025-01-10T12:00:00+00:00"
                },
                "terms": [
                    {
                        "id": 3,
                        "name": "Guides",
                        "meta_json": {},
                        "created_at": "2025-01-10T12:00:00+00:00",
                        "updated_at": "2025-01-10T12:00:00+00:00",
                        "deleted_at": null
                    }
                ]
            }
        ]
    }
}
```

#### 9. `PUT /api/v1/admin/entries/{entry}/terms/sync`

**Описание:** Полная синхронизация термов записи.

**Авторизация:** `can:manage.terms`

**Тело запроса:**

```json
{
    "term_ids": [3, 8]
}
```

**Ответ:** 200 OK с обновлёнными термами.

---

## Валидация

### Request классы

#### `StoreEntryRequest`

**Файл:** `app/Http/Requests/Admin/StoreEntryRequest.php`

**Правила валидации:**

-   `post_type_id` — required, integer, exists:post_types,id
-   `title` — required, string, max:500
-   `data_json` — nullable, array (динамические правила из Blueprint)
-   `meta_json` — nullable, array
-   `is_published` — boolean
-   `published_at` — nullable, date
-   `template_override` — nullable, string, max:255
-   `term_ids` — nullable, array
-   `term_ids.*` — integer, exists:terms,id

**Особенности:**

-   Автоматически устанавливает `published_at` в текущее время, если `is_published=true` и `published_at` не указан
-   Добавляет динамические правила валидации для `data_json` из Blueprint через `BlueprintValidationTrait`

#### `UpdateEntryRequest`

**Файл:** `app/Http/Requests/Admin/UpdateEntryRequest.php`

**Правила валидации:** Все поля опциональны (`sometimes`):

-   `title` — sometimes, required, string, max:500
-   `data_json` — sometimes, nullable, array (динамические правила из Blueprint)
-   `meta_json` — sometimes, nullable, array
-   `is_published` — sometimes, boolean
-   `published_at` — sometimes, nullable, date
-   `template_override` — sometimes, nullable, string, max:255
-   `term_ids` — sometimes, nullable, array
-   `term_ids.*` — integer, exists:terms,id

**Особенности:**

-   Загружает Entry с `postType.blueprint` для динамической валидации
-   Добавляет правила валидации на основе текущего Blueprint Entry

#### `IndexEntriesRequest`

**Файл:** `app/Http/Requests/Admin/IndexEntriesRequest.php`

**Правила валидации:**

-   `post_type_id` — nullable, integer, exists:post_types,id
-   `status` — nullable, string, in:all,draft,published,scheduled,trashed
-   `q` — nullable, string, max:500
-   `author_id` — nullable, integer, exists:users,id
-   `term` — nullable, array
-   `term.*` — integer, exists:terms,id
-   `date_from` — nullable, date
-   `date_to` — nullable, date, after_or_equal:date_from
-   `date_field` — nullable, string, in:updated,published
-   `sort` — nullable, string, in:updated_at.desc,updated_at.asc,published_at.desc,published_at.asc,title.asc,title.desc
-   `per_page` — nullable, integer, min:10, max:100

### EntryValidationService

**Файл:** `app/Domain/Blueprint/Validation/EntryValidationService.php`

**Назначение:** Доменный сервис для построения правил валидации на основе Blueprint.

**Методы:**

#### `buildRulesFor(Blueprint $blueprint): RuleSet`

**Описание:** Строит RuleSet для поля `data_json` на основе структуры Path в Blueprint.

**Логика:**

1. Загружает все Path из Blueprint (включая скопированные)
2. Преобразует `validation_rules` каждого Path в Rule объекты
3. Преобразует `full_path` в точечную нотацию для Laravel валидации
4. Автоматически добавляет правила типов данных на основе `data_type`, если они не указаны явно
5. Для `cardinality='many'` добавляет правило `array` для самого поля и правила для элементов массива (с `.*`)

**Пример:**

```php
// Blueprint с Path: full_path='hero.title', data_type='string', cardinality='one'
// Создаёт правило: 'data_json.hero.title' => ['string', ...]

// Blueprint с Path: full_path='tags', data_type='string', cardinality='many'
// Создаёт правила:
//   'data_json.tags' => ['array', ...]
//   'data_json.tags.*' => ['string', ...]
```

---

## Индексация данных

### EntryIndexer

**Файл:** `app/Services/Entry/EntryIndexer.php`

**Назначение:** Сервис индексации данных Entry в `doc_values` и `doc_refs` для быстрого поиска.

### Правила индексации

1. **Скалярные типы** (string, int, float, bool, datetime, text, json) → `doc_values`
2. **Ref-типы** → только `doc_refs` (запись в `doc_values` запрещена)
3. **Явное сопоставление** `data_type` → целевая колонка `value_*` с очисткой остальных
4. **array_index:**
    - `NULL` для `cardinality='one'`
    - Обязателен (1-based) для `cardinality='many'`

### Процесс индексации

#### 1. `index(Entry $entry): void`

**Описание:** Индексирует Entry.

**Логика:**

1. Получает Blueprint через PostType
2. Если PostType без Blueprint — пропускает индексацию (legacy записи)
3. В транзакции:
    - Удаляет старые индексы (`DocValue`, `DocRef`)
    - Получает все Path из Blueprint с `is_indexed=true`
    - Для каждого Path вызывает `indexPath()`
    - Обновляет `indexed_structure_version` (если поле существует)

#### 2. `indexPath(Entry $entry, Path $path): void`

**Описание:** Индексирует одно поле.

**Логика:**

1. Извлекает значение из `data_json` по `full_path`
2. Если значение `null` — пропускает
3. Если `data_type='ref'` → вызывает `indexRefPath()`
4. Иначе → вызывает `indexValuePath()`

#### 3. `indexValuePath(Entry $entry, Path $path, mixed $value): void`

**Описание:** Индексирует скалярное поле.

**Логика:**

1. Проверяет, что `data_type !== 'ref'` (защита)
2. Определяет целевую колонку `value_*` по `data_type`
3. Создаёт базовую структуру записи с явной очисткой всех `value_*` полей
4. Если `cardinality='one'`:
    - `array_index = NULL`
    - Создаёт одну запись `DocValue`
5. Если `cardinality='many'`:
    - Проверяет, что значение — массив
    - Для каждого элемента создаёт запись `DocValue` с `array_index = idx + 1` (1-based)

#### 4. `indexRefPath(Entry $entry, Path $path, mixed $value): void`

**Описание:** Индексирует ref-поле (ссылка на другой Entry).

**Логика:**

1. Если `cardinality='one'`:
    - Проверяет, что значение — int или numeric
    - Создаёт одну запись `DocRef` с `array_index = NULL`
2. Если `cardinality='many'`:
    - Проверяет, что значение — массив
    - Для каждого элемента создаёт запись `DocRef` с `array_index = idx + 1` (1-based)

### Маппинг типов данных

```php
'string' => 'value_string'
'int' => 'value_int'
'float' => 'value_float'
'bool' => 'value_bool'
'datetime' => 'value_datetime'
'text' => 'value_text'
'ref' => 'doc_refs' (не doc_values!)
```

---

## Observer и автоматизация

### EntryObserver

**Файл:** `app/Observers/EntryObserver.php`

**Назначение:** Обрабатывает события жизненного цикла Entry.

### События

#### `creating(Entry $entry): void`

**Описание:** Обрабатывает создание Entry.

**Действия:**

-   Санитизирует HTML поля (`body_html`, `excerpt_html`) из `data_json`

#### `updating(Entry $entry): void`

**Описание:** Обрабатывает обновление Entry.

**Действия:**

-   Санитизирует HTML поля при изменении `data_json`

#### `saved(Entry $entry): void`

**Описание:** Обрабатывает сохранение Entry (создание и обновление).

**Действия:**

-   Автоматически индексирует Entry через `EntryIndexer`, если PostType имеет Blueprint
-   Логирует ошибки индексации

#### `deleted(Entry $entry): void`

**Описание:** Обрабатывает удаление Entry.

**Действия:**

-   Очищает индексы (`DocValue`, `DocRef`) при удалении

### HTML санитизация

**Сервис:** `App\Domain\Sanitizer\RichTextSanitizer`

**Поля:**

-   `body_html` → `body_html_sanitized`
-   `excerpt_html` → `excerpt_html_sanitized`

**Назначение:** Сохраняет очищенный HTML для безопасного отображения на фронтенде.

---

## Связи с другими сущностями

### PostType

**Связь:** `belongsTo(PostType::class)`

**Назначение:** Определяет тип записи и связанный Blueprint.

**Использование:**

```php
$entry->postType; // PostType
$entry->postType->blueprint; // Blueprint
$entry->blueprint; // Blueprint (accessor)
```

### User (Author)

**Связь:** `belongsTo(User::class, 'author_id')`

**Назначение:** Автор записи.

**Использование:**

```php
$entry->author; // User|null
$entry->author->name; // Имя автора
```

### Term

**Связь:** `belongsToMany(Term::class, 'entry_term')`

**Назначение:** Категории, теги и другие термы.

**Использование:**

```php
$entry->terms; // Collection<Term>
$entry->terms()->sync([1, 2, 3]); // Синхронизация
```

### DocValue

**Связь:** `hasMany(DocValue::class)`

**Назначение:** Индексированные скалярные значения из `data_json`.

**Использование:**

```php
$entry->docValues; // Collection<DocValue>
```

### DocRef

**Связи:**

-   `hasMany(DocRef::class)` — исходящие ссылки
-   `hasMany(DocRef::class, 'target_entry_id')` — входящие ссылки

**Назначение:** Индексированные ссылки на другие Entry.

**Использование:**

```php
$entry->docRefs; // Исходящие ссылки
$entry->docRefsIncoming; // Входящие ссылки
```

---

## Авторизация

### EntryPolicy

**Файл:** `app/Policies/EntryPolicy.php`

**Назначение:** Политика авторизации для Entry.

### Методы политики

Все методы требуют права `manage.entries`:

-   `viewAny()` — просмотр списка записей
-   `view()` — просмотр конкретной записи
-   `create()` — создание записи
-   `update()` — обновление записи
-   `delete()` — удаление записи
-   `restore()` — восстановление записи
-   `forceDelete()` — окончательное удаление
-   `publish()` — публикация/снятие с публикации
-   `attachMedia()` — привязка медиа
-   `manageTerms()` — управление термами

### Использование в контроллере

```php
$this->authorize('viewAny', Entry::class); // Список
$this->authorize('view', $entry); // Просмотр
$this->authorize('create', Entry::class); // Создание
$this->authorize('update', $entry); // Обновление
$this->authorize('delete', $entry); // Удаление
$this->authorize('restore', $entry); // Восстановление
```

---

## API Resources

### EntryResource

**Файл:** `app/Http/Resources/Admin/EntryResource.php`

**Назначение:** Форматирует Entry для ответа API.

### Структура ответа

```json
{
  "id": 42,
  "post_type_id": 1,
  "title": "Headless CMS launch checklist",
  "status": "published",
  "data_json": {...},
  "meta_json": {...},
  "is_published": true,
  "published_at": "2025-02-10T08:00:00+00:00",
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
  "blueprint": {...},
  "created_at": "2025-02-09T10:15:00+00:00",
  "updated_at": "2025-02-10T08:05:00+00:00",
  "deleted_at": null
}
```

### Особенности

-   **JSON преобразование:** Ассоциативные массивы преобразуются в объекты `stdClass`, пустые массивы — в `null`
-   **Условная загрузка:** `author`, `terms`, `blueprint` включаются только при их загрузке через `with()`
-   **HTTP статус:** Автоматически устанавливает 201 Created для только что созданных записей

### EntryCollection

**Файл:** `app/Http/Resources/Admin/EntryCollection.php`

**Назначение:** Коллекция Entry для пагинации.

---

## Фильтрация и поиск

### HasDocumentData Trait

**Файл:** `app/Traits/HasDocumentData.php`

**Назначение:** Предоставляет scopes для фильтрации Entry по индексированным данным.

### Scopes

#### `scopeWherePath(Builder $query, string $fullPath, string $operator, mixed $value): Builder`

**Описание:** Фильтрует Entry по значению индексированного поля.

**Примеры:**

```php
Entry::wherePath('author.name', '=', 'John')->get();
Entry::wherePath('price', '>', 100)->get();
```

#### `scopeWherePathIn(Builder $query, string $fullPath, array $values): Builder`

**Описание:** Фильтрует по значениям из списка (IN).

**Пример:**

```php
Entry::wherePathIn('category', ['tech', 'science'])->get();
```

#### `scopeWhereRef(Builder $query, string $fullPath, int $targetEntryId): Builder`

**Описание:** Фильтрует Entry, у которых есть ссылка на указанный Entry.

**Пример:**

```php
Entry::whereRef('relatedArticles', 42)->get();
```

#### `scopeReferencedBy(Builder $query, string $fullPath, int $ownerEntryId): Builder`

**Описание:** Фильтрует Entry, на которые ссылается указанный Entry (обратный запрос).

**Пример:**

```php
Entry::referencedBy('relatedArticles', 1)->get();
```

#### `scopeWherePathExists(Builder $query, string $fullPath): Builder`

**Описание:** Фильтрует Entry с любым значением в указанном поле (NOT NULL).

**Пример:**

```php
Entry::wherePathExists('author.bio')->get();
```

#### `scopeWherePathMissing(Builder $query, string $fullPath): Builder`

**Описание:** Фильтрует Entry, у которых поле НЕ заполнено (NULL).

**Пример:**

```php
Entry::wherePathMissing('author.bio')->get();
```

#### `scopeOrderByPath(Builder $query, string $fullPath, string $direction = 'asc'): Builder`

**Описание:** Сортирует по индексированному полю.

**Особенности:**

-   Определяет колонку `value_*` по `data_type` из Path для корректной сортировки всех типов данных
-   Использует JOIN с `doc_values` и `paths` для определения типа

**Примеры:**

```php
Entry::orderByPath('price', 'desc')->get();
Entry::orderByPath('body', 'asc')->get(); // для text-полей
```

---

## Особенности реализации

### 1. Мягкое удаление

**Реализация:** `SoftDeletes` trait

**Особенности:**

-   Записи не удаляются физически, помечаются `deleted_at`
-   Можно восстанавливать через `restore()`
-   В контроллере используется `withTrashed()` для доступа к удалённым записям

### 2. Публикация по расписанию

**Реализация:** Поле `published_at` + scope `published()`

**Логика:**

-   Запись считается опубликованной, если:
    -   `status = 'published'`
    -   `published_at IS NOT NULL`
    -   `published_at <= NOW()`
-   Записи с `published_at > NOW()` считаются запланированными (`scheduled`)

### 3. Динамическая валидация на основе Blueprint

**Реализация:** `EntryValidationService` + `BlueprintValidationTrait`

**Особенности:**

-   Правила валидации строятся динамически на основе структуры Blueprint
-   Поддерживает вложенные структуры и массивы
-   Автоматически добавляет правила типов данных

### 4. Автоматическая индексация

**Реализация:** `EntryObserver` + `EntryIndexer`

**Особенности:**

-   Индексация происходит автоматически при сохранении Entry
-   Индексируются только поля с `is_indexed=true` в Path
-   Поддерживает скалярные типы и ссылки на другие Entry
-   Очищает старые индексы перед созданием новых

### 5. HTML санитизация

**Реализация:** `EntryObserver` + `RichTextSanitizer`

**Особенности:**

-   Автоматически санитизирует `body_html` и `excerpt_html`
-   Сохраняет очищенный HTML в `body_html_sanitized` и `excerpt_html_sanitized`
-   Выполняется при создании и обновлении Entry

### 6. Синхронизация термов

**Реализация:** `EntryTermsController` + `ManagesEntryTerms` trait

**Особенности:**

-   Полная синхронизация через `sync()` (заменяет все термы)
-   Проверка, что термы принадлежат разрешённым таксономиям для PostType
-   Группировка термов по таксономиям в ответе API

### 7. Транзакции

**Использование:** Все операции изменения данных выполняются в транзакциях:

-   Создание Entry с термами
-   Обновление Entry с термами
-   Индексация Entry

**Цель:** Обеспечение целостности данных.

### 8. Версионирование

**Поле:** `version` (по умолчанию 1)

**Назначение:** Отслеживание версий записей (в будущем может использоваться для истории изменений).

---

## Резюме

Система Entry предоставляет:

1. **Гибкость** — структурированные данные через `data_json` и Blueprint
2. **Производительность** — автоматическая индексация для быстрого поиска
3. **Безопасность** — валидация на основе Blueprint, HTML санитизация
4. **Удобство** — мягкое удаление, публикация по расписанию
5. **Расширяемость** — связи с термами, медиа, другими Entry

Все компоненты документированы, протестированы и следуют принципам Laravel 12 и PSR-12.

---

**Последнее обновление:** 2025-12-09
