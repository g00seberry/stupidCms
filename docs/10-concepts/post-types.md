---
owner: "@backend-team"
system_of_record: "narrative"
review_cycle_days: 60
last_reviewed: 2025-11-08
related_code:
    - "app/Models/PostType.php"
    - "app/Http/Controllers/Admin/PostTypeController.php"
---

# Post Types

**PostType** — это шаблон типа контента в stupidCms. Вместо жёстко заданных сущностей (блог, новости, страницы), вы создаёте гибкие типы с настройками.

## Концепция

PostType определяет:

-   **Идентификатор** (`slug`) — например, `article`, `event`, `product`
-   **Название** (`name`) — для админки ("Статья", "Событие")
-   **Шаблон** (`template`) — Blade-шаблон для рендеринга записей этого типа (опционально)
-   **Настройки** (`options_json`) — какие поля, таксономии, медиа поддерживаются

### Шаблоны (Templates)

Поле `template` в PostType задаёт Blade-шаблон по умолчанию для всех записей этого типа. Формат значения — dot notation (например, `pages.show` соответствует `resources/views/pages/show.blade.php`).

**Примеры**:
- `pages.show` — базовый шаблон для страниц
- `pages.types.article` — шаблон для статей из поддиректории
- `single-product` — шаблон для товаров

**Приоритет выбора шаблона** (при рендеринге Entry):
1. `Entry.template_override` — переопределение для конкретной записи (если задан)
2. `PostType.template` — шаблон типа поста (если задан)
3. `pages.show` (default) — если оба не заданы

См. `BladeTemplateResolver` для деталей реализации.

**Получение списка доступных шаблонов**: `GET /api/v1/admin/utils/templates`

## Модель данных

**Таблица**: `post_types`

```php
PostType {
  id: bigint (PK)
  slug: string (unique)      // 'article', 'page', 'event'
  name: string               // 'Статья', 'Страница', 'Событие'
  template: ?string          // 'single-article', 'page'
  options_json: json         // настройки (см. ниже)
  created_at: datetime
  updated_at: datetime
}
```

**Файл**: `app/Models/PostType.php`

## Структура options_json

```json
{
    "fields": ["subtitle", "featured", "custom_data"],
    "taxonomies": ["categories", "tags"],
    "media_support": true,
    "hierarchical": false,
    "slugs": {
        "prefix": "articles", // URL prefix (опционально)
        "hierarchical": false
    },
    "publishing": {
        "requires_approval": false,
        "allow_scheduling": true
    }
}
```

### Поля

-   **`fields`** — массив дополнительных полей, которые будут в `Entry.data_json`
-   **`taxonomies`** — список slug таксономий (например, `["categories", "tags"]`)
-   **`media_support`** — поддержка прикрепления медиафайлов
-   **`hierarchical`** — поддержка parent-child структуры entries

### Slugs

-   **`prefix`** — префикс URL (например, `/articles/my-post` вместо `/my-post`)
-   **`hierarchical`** — поддержка вложенных URL (`/parent/child`)

### Publishing

-   **`requires_approval`** — требуется ли одобрение перед публикацией
-   **`allow_scheduling`** — можно ли планировать публикацию

## Примеры Post Types

### Article (статья блога)

```php
PostType::create([
    'slug' => 'article',
    'name' => 'Статья',
    'template' => 'single-article',
    'options_json' => [
        'fields' => ['subtitle', 'featured', 'read_time'],
        'taxonomies' => ['categories', 'tags'],
        'media_support' => true,
        'hierarchical' => false,
        'slugs' => [
            'prefix' => 'articles',
        ],
    ],
]);
```

**Результат**:

-   Entry имеет `data_json` с полями `subtitle`, `featured`, `read_time`
-   Может быть привязан к категориям и тегам
-   Может иметь медиафайлы
-   URL: `/articles/{slug}`

---

### Page (статическая страница)

```php
PostType::create([
    'slug' => 'page',
    'name' => 'Страница',
    'template' => 'page',
    'options_json' => [
        'fields' => ['blocks'],  // для page builder
        'taxonomies' => [],
        'media_support' => true,
        'hierarchical' => true,   // поддержка /about/team
        'slugs' => [
            'prefix' => null,      // плоские URL: /about, /contacts
            'hierarchical' => true,
        ],
    ],
]);
```

**Результат**:

-   Entry может иметь родителя (parent_id)
-   URL: `/{slug}` или `/{parent-slug}/{slug}`
-   Без таксономий

---

### Event (событие)

```php
PostType::create([
    'slug' => 'event',
    'name' => 'Событие',
    'template' => 'single-event',
    'options_json' => [
        'fields' => ['event_date', 'location', 'registration_url'],
        'taxonomies' => ['event-categories'],
        'media_support' => true,
        'hierarchical' => false,
        'slugs' => [
            'prefix' => 'events',
        ],
        'publishing' => [
            'allow_scheduling' => true,  // авто-публикация в дату события
        ],
    ],
]);
```

**Результат**:

-   `data_json` содержит `event_date`, `location`, `registration_url`
-   URL: `/events/{slug}`
-   Поддержка планирования публикации

## API

### Создание PostType

**Endpoint**: `POST /api/admin/post-types`

**Request**:

```json
{
    "slug": "product",
    "name": "Товар",
    "template": "single-product",
    "options_json": {
        "fields": ["price", "sku", "stock"],
        "taxonomies": ["product-categories"],
        "media_support": true,
        "hierarchical": false
    }
}
```

**Response**: `201 Created`

```json
{
  "data": {
    "id": 4,
    "slug": "product",
    "name": "Товар",
    "template": "single-product",
    "options_json": { ... },
    "created_at": "2025-11-08T12:00:00Z",
    "updated_at": "2025-11-08T12:00:00Z"
  }
}
```

### Получение списка

**Endpoint**: `GET /api/post-types`

**Response**:

```json
{
  "data": [
    {
      "id": 1,
      "slug": "article",
      "name": "Статья",
      "options_json": { ... }
    },
    {
      "id": 2,
      "slug": "page",
      "name": "Страница",
      "options_json": { ... }
    }
  ]
}
```

### Обновление PostType

**Endpoint**: `PUT /api/v1/admin/post-types/{slug}`

**Request**:

Все поля опциональны, кроме `options_json`:

```json
{
    "slug": "article-updated",
    "name": "Статьи (обновлено)",
    "options_json": {
        "fields": ["subtitle", "featured", "read_time", "new_field"],
        "taxonomies": ["categories", "tags", "regions"],
        "media_support": true
    }
}
```

**Response**: `200 OK`

```json
{
  "data": {
    "slug": "article-updated",
    "name": "Статьи (обновлено)",
    "options_json": { ... },
    "updated_at": "2025-11-08T14:00:00Z"
  }
}
```

> 💡 **Примечание**: Поля `slug` и `name` опциональны. Если они не указаны, остаются без изменений. `options_json` обязателен.

### Получение списка доступных шаблонов

**Endpoint**: `GET /api/v1/admin/utils/templates`

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

**Описание**: Возвращает список всех доступных Blade-шаблонов из `resources/views` для назначения PostType или Entry. Исключает системные директории (`admin`, `errors`, `layouts`, `partials`, `vendor`). Результаты отсортированы по алфавиту.

**Использование**:
- Шаблоны из этого списка можно назначить в поле `PostType.template` или `Entry.template_override`
- Формат шаблонов: dot notation (например, `pages.show` соответствует `resources/views/pages/show.blade.php`)
- Шаблоны из вложенных директорий также включаются (например, `pages.types.article`)

## Использование в коде

### Создание Entry для PostType

```php
$postType = PostType::where('slug', 'article')->first();

$entry = Entry::create([
    'post_type_id' => $postType->id,
    'author_id' => auth()->id(),
    'title' => 'Моя статья',
    'slug' => 'moya-statya',
    'data_json' => [
        'subtitle' => 'Краткое описание',
        'featured' => true,
        'read_time' => 5,
    ],
    'status' => 'draft',
]);
```

### Валидация полей

Admin API должен валидировать, что поля в `Entry.data_json` соответствуют `PostType.options_json['fields']`.

```php
$allowedFields = $entry->postType->options_json['fields'] ?? [];
$invalidFields = array_diff(
    array_keys($request->input('data_json', [])),
    $allowedFields
);

if ($invalidFields) {
    throw ValidationException::withMessages([
        'data_json' => "Unknown fields: " . implode(', ', $invalidFields),
    ]);
}
```

### Проверка таксономий

```php
$allowedTaxonomies = $entry->postType->options_json['taxonomies'] ?? [];
$requestedTerms = Term::findMany($request->input('term_ids', []));

foreach ($requestedTerms as $term) {
    if (!in_array($term->taxonomy->slug, $allowedTaxonomies)) {
        throw ValidationException::withMessages([
            'term_ids' => "Taxonomy '{$term->taxonomy->slug}' not allowed for this post type",
        ]);
    }
}
```

## Встроенные Post Types

При начальном сиде создаются базовые типы:

| Slug      | Name     | Описание                          |
| --------- | -------- | --------------------------------- |
| `article` | Статья   | Блог-посты с категориями и тегами |
| `page`    | Страница | Статические страницы с иерархией  |

См. `database/seeders/PostTypesTaxonomiesSeeder.php`

## Ограничения

### Изменяемые поля

Через Admin API можно обновить:

-   `slug` — можно изменить (с проверкой уникальности и зарезервированных значений)
-   `name` — можно изменить
-   `options_json` — можно изменить (обязательное поле при обновлении)

> ⚠️ **Важно**: Изменение `slug` может повлиять на URL существующих entries и интеграции. Используйте с осторожностью.

### Неизменяемые поля

-   `template` — не обновляется через API (может быть привязан к логике рендеринга)

### Удаление PostType

PostType **нельзя удалить**, если есть связанные entries. Необходимо:

1. Удалить/переместить все entries
2. Или пометить PostType как `deprecated` (кастомное поле)

## Расширения (будущее)

### Custom Fields Schema

Вместо просто списка `fields`, можно добавить схему валидации:

```json
{
    "fields": {
        "subtitle": { "type": "string", "max": 255, "required": false },
        "featured": { "type": "boolean", "default": false },
        "price": { "type": "number", "min": 0, "required": true }
    }
}
```

Это позволит валидировать `Entry.data_json` на уровне PostType.

### Permissions per PostType

```json
{
    "permissions": {
        "create": ["editor", "admin"],
        "publish": ["admin"]
    }
}
```

### Versioning

Хранить версии `options_json` для откатов.

## How-to Guides

-   [Добавить новый PostType](../20-how-to/add-post-type.md)
-   [Мигрировать PostType](../20-how-to/migrate-post-type.md) _(TODO)_

## Связанные страницы

-   [Entries](entries.md) — работа с записями
-   [Модель данных](domain-model.md) — полная схема
-   Scribe API Reference (`../_generated/api-docs/index.html`) — endpoints

---

> 💡 **Tip**: Проектируйте PostTypes заранее. Изменение структуры после создания тысяч entries может быть сложным.
