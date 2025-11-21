# Генерация форм редактирования на основе Path

## Обзор

Документация описывает архитектуру и схему данных для создания форм редактирования Entry на основе структуры Path из Blueprint.

## Схема Path

### Структура данных Path

Path представляет поле в Blueprint с материализованным `full_path`. Каждый Path имеет следующие характеристики:

**Важно:** `full_path` всегда использует точечную нотацию (например, `author.contacts.phone`), **без** `[]`. Символы `[]` используются только при генерации имени поля для формы (например, `gallery[]` для массива), но не в самом `full_path`.

```json
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
    "validation_rules": {
        "min": 1,
        "max": 500
    },
    "children": []
}
```

### Типы данных (data_type)

-   `string` - короткая строка (input, textarea)
-   `text` - длинный текст (textarea, rich text editor)
-   `int` - целое число
-   `float` - число с плавающей точкой
-   `bool` - булево значение (checkbox, toggle)
-   `date` - дата (date picker)
-   `datetime` - дата и время (datetime picker)
-   `json` - группа полей (container для вложенных paths)
-   `ref` - ссылка на другую Entry (reference picker)

### Кардинальность (cardinality)

-   `one` - одно значение (по умолчанию)
-   `many` - массив значений (repeater, list)

### Примеры схем Path

#### 1. Простое поле (корневой уровень)

```json
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
    "validation_rules": {
        "min": 1,
        "max": 500
    },
    "children": []
}
```

**Форма:** `<input type="text" name="title" required maxlength="500" />`

#### 2. Вложенное поле (один уровень вложенности)

```json
{
    "id": 2,
    "blueprint_id": 1,
    "parent_id": 1,
    "name": "phone",
    "full_path": "author.contacts.phone",
    "data_type": "string",
    "cardinality": "one",
    "is_required": false,
    "is_indexed": true,
    "is_readonly": false,
    "sort_order": 0,
    "validation_rules": {
        "pattern": "^\\+?[1-9]\\d{1,14}$"
    },
    "children": []
}
```

**Форма:** Вложенное в группу `author.contacts`:

```html
<div class="field-group" data-path="author.contacts">
    <input
        type="tel"
        name="author.contacts.phone"
        pattern="^\+?[1-9]\d{1,14}$"
    />
</div>
```

#### 3. Группа полей (data_type: json)

```json
{
    "id": 3,
    "blueprint_id": 1,
    "parent_id": null,
    "name": "author",
    "full_path": "author",
    "data_type": "json",
    "cardinality": "one",
    "is_required": false,
    "is_indexed": false,
    "is_readonly": false,
    "sort_order": 0,
    "validation_rules": null,
    "children": [
        {
            "id": 4,
            "name": "name",
            "full_path": "author.name",
            "data_type": "string",
            "is_required": true,
            "children": []
        },
        {
            "id": 5,
            "name": "email",
            "full_path": "author.email",
            "data_type": "string",
            "is_required": true,
            "children": []
        },
        {
            "id": 6,
            "name": "contacts",
            "full_path": "author.contacts",
            "data_type": "json",
            "children": [
                {
                    "id": 7,
                    "name": "phone",
                    "full_path": "author.contacts.phone",
                    "data_type": "string",
                    "children": []
                }
            ]
        }
    ]
}
```

**Форма:** Группа с вложенными полями:

```html
<fieldset class="field-group" data-path="author">
    <legend>Author</legend>
    <input type="text" name="author.name" required />
    <input type="email" name="author.email" required />
    <fieldset class="field-group" data-path="author.contacts">
        <legend>Contacts</legend>
        <input type="tel" name="author.contacts.phone" />
    </fieldset>
</fieldset>
```

#### 4. Массив значений (cardinality: many)

```json
{
    "id": 8,
    "blueprint_id": 1,
    "parent_id": null,
    "name": "tags",
    "full_path": "tags",
    "data_type": "string",
    "cardinality": "many",
    "is_required": false,
    "is_indexed": true,
    "is_readonly": false,
    "sort_order": 0,
    "validation_rules": null,
    "children": []
}
```

**Форма:** Repeater для массива значений:

```html
<div class="field-repeater" data-path="tags">
    <button type="button" class="add-item">Add tag</button>
    <div class="repeater-items">
        <div class="repeater-item">
            <input type="text" name="tags[0]" />
            <button type="button" class="remove-item">Remove</button>
        </div>
    </div>
</div>
```

#### 5. Массив групп (cardinality: many + data_type: json)

```json
{
    "id": 9,
    "blueprint_id": 1,
    "parent_id": null,
    "name": "gallery",
    "full_path": "gallery",
    "data_type": "json",
    "cardinality": "many",
    "is_required": false,
    "is_indexed": false,
    "is_readonly": false,
    "sort_order": 0,
    "validation_rules": null,
    "children": [
        {
            "id": 10,
            "name": "image",
            "full_path": "gallery.image",
            "data_type": "ref",
            "cardinality": "one",
            "is_required": true,
            "children": []
        },
        {
            "id": 11,
            "name": "caption",
            "full_path": "gallery.caption",
            "data_type": "string",
            "cardinality": "one",
            "is_required": false,
            "children": []
        }
    ]
}
```

**Форма:** Repeater для массива объектов:

```html
<div class="field-repeater" data-path="gallery">
    <button type="button" class="add-item">Add image</button>
    <div class="repeater-items">
        <div class="repeater-item">
            <fieldset>
                <input type="hidden" name="gallery[0].image" required />
                <input type="text" name="gallery[0].caption" />
                <button type="button" class="remove-item">Remove</button>
            </fieldset>
        </div>
    </div>
</div>
```

#### 6. Ссылка на Entry (data_type: ref)

```json
{
    "id": 12,
    "blueprint_id": 1,
    "parent_id": null,
    "name": "featured_image",
    "full_path": "featured_image",
    "data_type": "ref",
    "cardinality": "one",
    "is_required": false,
    "is_indexed": true,
    "is_readonly": false,
    "sort_order": 0,
    "validation_rules": null,
    "children": []
}
```

**Форма:** Reference picker:

```html
<div class="field-reference" data-path="featured_image">
    <input type="hidden" name="featured_image" />
    <button type="button" class="select-entry">Select entry</button>
    <div class="selected-entry"></div>
</div>
```

#### 7. Ссылка на массив Entry (data_type: ref, cardinality: many)

```json
{
    "id": 13,
    "blueprint_id": 1,
    "parent_id": null,
    "name": "related_articles",
    "full_path": "related_articles",
    "data_type": "ref",
    "cardinality": "many",
    "is_required": false,
    "is_indexed": true,
    "is_readonly": false,
    "sort_order": 0,
    "validation_rules": null,
    "children": []
}
```

**Форма:** Multi-select reference picker:

```html
<div class="field-reference-multi" data-path="related_articles">
    <input type="hidden" name="related_articles[]" multiple />
    <button type="button" class="select-entries">Select entries</button>
    <div class="selected-entries"></div>
</div>
```

#### 8. Скопированное поле (is_readonly: true)

```json
{
    "id": 14,
    "blueprint_id": 2,
    "parent_id": 3,
    "name": "phone",
    "full_path": "author.contacts.phone",
    "data_type": "string",
    "cardinality": "one",
    "is_required": false,
    "is_indexed": true,
    "is_readonly": true,
    "sort_order": 0,
    "validation_rules": null,
    "source_blueprint_id": 1,
    "source_blueprint": {
        "id": 1,
        "code": "contact_info",
        "name": "Contact Info"
    },
    "blueprint_embed_id": 5,
    "children": []
}
```

**Форма:** Только для чтения (disabled):

```html
<div class="field-readonly" data-path="author.contacts.phone">
    <input type="tel" name="author.contacts.phone" disabled />
    <span class="readonly-hint">Из blueprint: Contact Info</span>
</div>
```

#### 9. Многоуровневая вложенность

```json
{
    "id": 15,
    "blueprint_id": 1,
    "parent_id": null,
    "name": "content",
    "full_path": "content",
    "data_type": "json",
    "cardinality": "one",
    "is_required": false,
    "is_indexed": false,
    "is_readonly": false,
    "sort_order": 0,
    "validation_rules": null,
    "children": [
        {
            "id": 16,
            "name": "sections",
            "full_path": "content.sections",
            "data_type": "json",
            "cardinality": "many",
            "children": [
                {
                    "id": 17,
                    "name": "title",
                    "full_path": "content.sections.title",
                    "data_type": "string",
                    "is_required": true,
                    "children": []
                },
                {
                    "id": 18,
                    "name": "blocks",
                    "full_path": "content.sections.blocks",
                    "data_type": "json",
                    "cardinality": "many",
                    "children": [
                        {
                            "id": 19,
                            "name": "type",
                            "full_path": "content.sections.blocks.type",
                            "data_type": "string",
                            "is_required": true,
                            "children": []
                        },
                        {
                            "id": 20,
                            "name": "data",
                            "full_path": "content.sections.blocks.data",
                            "data_type": "json",
                            "cardinality": "one",
                            "children": [
                                {
                                    "id": 21,
                                    "name": "text",
                                    "full_path": "content.sections.blocks.data.text",
                                    "data_type": "text",
                                    "children": []
                                }
                            ]
                        }
                    ]
                }
            ]
        }
    ]
}
```

**Форма:** Многоуровневая структура с вложенными repeater'ами:

```html
<fieldset class="field-group" data-path="content">
    <div class="field-repeater" data-path="content.sections">
        <button type="button" class="add-item">Add section</button>
        <div class="repeater-items">
            <div class="repeater-item">
                <input type="text" name="content.sections[0].title" required />
                <div
                    class="field-repeater"
                    data-path="content.sections[0].blocks"
                >
                    <button type="button" class="add-item">Add block</button>
                    <div class="repeater-items">
                        <div class="repeater-item">
                            <input
                                type="text"
                                name="content.sections[0].blocks[0].type"
                                required
                            />
                            <fieldset
                                class="field-group"
                                data-path="content.sections[0].blocks[0].data"
                            >
                                <textarea
                                    name="content.sections[0].blocks[0].data.text"
                                ></textarea>
                            </fieldset>
                            <button type="button" class="remove-item">
                                Remove
                            </button>
                        </div>
                    </div>
                </div>
                <button type="button" class="remove-item">Remove</button>
            </div>
        </div>
    </div>
</fieldset>
```

#### 10. Смешанная структура (все типы данных)

```json
{
    "id": 22,
    "blueprint_id": 1,
    "parent_id": null,
    "name": "article",
    "full_path": "article",
    "data_type": "json",
    "cardinality": "one",
    "is_required": false,
    "is_indexed": false,
    "is_readonly": false,
    "sort_order": 0,
    "validation_rules": null,
    "children": [
        {
            "id": 23,
            "name": "title",
            "full_path": "article.title",
            "data_type": "string",
            "is_required": true,
            "children": []
        },
        {
            "id": 24,
            "name": "content",
            "full_path": "article.content",
            "data_type": "text",
            "is_required": false,
            "children": []
        },
        {
            "id": 25,
            "name": "published",
            "full_path": "article.published",
            "data_type": "bool",
            "is_required": false,
            "children": []
        },
        {
            "id": 26,
            "name": "views",
            "full_path": "article.views",
            "data_type": "int",
            "is_required": false,
            "children": []
        },
        {
            "id": 27,
            "name": "rating",
            "full_path": "article.rating",
            "data_type": "float",
            "is_required": false,
            "validation_rules": {
                "min": 0,
                "max": 5
            },
            "children": []
        },
        {
            "id": 28,
            "name": "published_at",
            "full_path": "article.published_at",
            "data_type": "datetime",
            "is_required": false,
            "children": []
        },
        {
            "id": 29,
            "name": "created_date",
            "full_path": "article.created_date",
            "data_type": "date",
            "is_required": false,
            "children": []
        },
        {
            "id": 30,
            "name": "author",
            "full_path": "article.author",
            "data_type": "ref",
            "is_required": false,
            "children": []
        },
        {
            "id": 31,
            "name": "tags",
            "full_path": "article.tags",
            "data_type": "string",
            "cardinality": "many",
            "is_required": false,
            "children": []
        },
        {
            "id": 32,
            "name": "metadata",
            "full_path": "article.metadata",
            "data_type": "json",
            "cardinality": "one",
            "children": [
                {
                    "id": 33,
                    "name": "seo_title",
                    "full_path": "article.metadata.seo_title",
                    "data_type": "string",
                    "children": []
                },
                {
                    "id": 34,
                    "name": "seo_description",
                    "full_path": "article.metadata.seo_description",
                    "data_type": "text",
                    "children": []
                }
            ]
        }
    ]
}
```

**Форма:** Комплексная форма со всеми типами полей:

```html
<fieldset class="field-group" data-path="article">
    <input type="text" name="article.title" required />
    <textarea name="article.content"></textarea>
    <input type="checkbox" name="article.published" />
    <input type="number" name="article.views" />
    <input type="number" name="article.rating" min="0" max="5" step="0.1" />
    <input type="datetime-local" name="article.published_at" />
    <input type="date" name="article.created_date" />
    <div class="field-reference" data-path="article.author">
        <input type="hidden" name="article.author" />
        <button type="button" class="select-entry">Select author</button>
    </div>
    <div class="field-repeater" data-path="article.tags">
        <button type="button" class="add-item">Add tag</button>
        <div class="repeater-items"></div>
    </div>
    <fieldset class="field-group" data-path="article.metadata">
        <legend>Metadata</legend>
        <input type="text" name="article.metadata.seo_title" />
        <textarea name="article.metadata.seo_description"></textarea>
    </fieldset>
</fieldset>
```

## Архитектура системы генерации форм

### Компоненты системы

#### 1. Источник данных: Blueprint Paths

**Модель:** `App\Models\Path`

**API Endpoint:** `GET /api/v1/admin/blueprints/{blueprint}/paths`

**Структура ответа:**

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
            "validation_rules": {
                "min": 1,
                "max": 500
            },
            "children": []
        }
    ]
}
```

#### 2. Трансформация Path → Form Schema

**Процесс:**

1. **Получение дерева Paths** из API
2. **Рекурсивная обработка** дерева paths
3. **Генерация схемы формы** с учетом:
    - Типа данных (`data_type`)
    - Кардинальности (`cardinality`)
    - Вложенности (`parent_id`, `children`)
    - Валидации (`is_required`, `validation_rules`)
    - Только для чтения (`is_readonly`)

**Схема формы:**

```typescript
interface FormField {
    path: string; // full_path из Path
    name: string; // имя поля для формы
    type: string; // тип поля (input, textarea, select, etc.)
    dataType: string; // data_type из Path
    cardinality: "one" | "many";
    required: boolean;
    readonly: boolean;
    validation?: {
        min?: number;
        max?: number;
        pattern?: string;
        [key: string]: any;
    };
    children?: FormField[]; // для групп и вложенных полей
}
```

#### 3. Генерация HTML формы

**Алгоритм:**

1. **Обработка корневых полей** (parent_id === null)
2. **Рекурсивная обработка групп** (data_type === 'json')
3. **Обработка массивов** (cardinality === 'many')
4. **Применение валидации** и атрибутов

**Маппинг data_type → HTML элемент:**

| data_type  | cardinality | HTML элемент                               |
| ---------- | ----------- | ------------------------------------------ |
| `string`   | `one`       | `<input type="text">`                      |
| `string`   | `many`      | Repeater с `<input type="text">`           |
| `text`     | `one`       | `<textarea>`                               |
| `text`     | `many`      | Repeater с `<textarea>`                    |
| `int`      | `one`       | `<input type="number" step="1">`           |
| `int`      | `many`      | Repeater с `<input type="number">`         |
| `float`    | `one`       | `<input type="number" step="0.01">`        |
| `float`    | `many`      | Repeater с `<input type="number">`         |
| `bool`     | `one`       | `<input type="checkbox">`                  |
| `bool`     | `many`      | Repeater с `<input type="checkbox">`       |
| `date`     | `one`       | `<input type="date">`                      |
| `date`     | `many`      | Repeater с `<input type="date">`           |
| `datetime` | `one`       | `<input type="datetime-local">`            |
| `datetime` | `many`      | Repeater с `<input type="datetime-local">` |
| `json`     | `one`       | `<fieldset>` с вложенными полями           |
| `json`     | `many`      | Repeater с `<fieldset>`                    |
| `ref`      | `one`       | Reference picker (custom component)        |
| `ref`      | `many`      | Multi-select reference picker              |

#### 4. Обработка данных формы

**Структура данных Entry:**

```json
{
    "id": 1,
    "post_type_id": 1,
    "title": "Article Title",
    "slug": "article-slug",
    "status": "published",
    "data_json": {
        "title": "Article Title",
        "author": {
            "name": "John Doe",
            "email": "john@example.com",
            "contacts": {
                "phone": "+1234567890"
            }
        },
        "tags": ["tag1", "tag2"],
        "gallery": [
            {
                "image": 5,
                "caption": "Image 1"
            }
        ]
    }
}
```

**Маппинг формы → data_json:**

1. **Плоские поля** (`full_path` без точек) → `data_json[full_path]`
2. **Вложенные поля** (`full_path` с точками) → `data_json[path.to.field]`
3. **Массивы** (`cardinality: many`) → `data_json[field][]` или `data_json[field][index]`
4. **Группы** (`data_type: json`) → вложенный объект в `data_json`

**Пример:**

```html
<!-- Форма -->
<input name="title" value="Article Title" />
<input name="author.name" value="John Doe" />
<input name="author.email" value="john@example.com" />
<input name="tags[0]" value="tag1" />
<input name="tags[1]" value="tag2" />
```

```json
// data_json
{
    "title": "Article Title",
    "author": {
        "name": "John Doe",
        "email": "john@example.com"
    },
    "tags": ["tag1", "tag2"]
}
```

### Поток данных

```
┌─────────────────┐
│   Blueprint     │
│   (PostType)    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  GET /paths     │
│  API Endpoint   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Path Tree      │
│  (JSON)         │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Transform      │
│  Path → Schema  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Form Schema    │
│  (TypeScript)    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Generate       │
│  HTML Form      │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  User Input     │
│  (Form Data)    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Transform      │
│  Form → data_json│
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  POST/PUT       │
│  /entries       │
└─────────────────┘
```

### Особые случаи

#### 1. Скопированные поля (is_readonly: true)

-   **Отображение:** Поле только для чтения (disabled)
-   **Валидация:** Не применяется (поле не редактируется)
-   **Источник:** Указывается `source_blueprint.code` для подсказки пользователю

#### 2. Вложенные массивы

-   **full_path:** `content.sections.blocks.type` (без `[]`)
-   **Имя поля в форме:** `content.sections[0].blocks[0].type` (с индексами)
-   **Обработка:** Рекурсивная обработка индексов массивов
-   **Валидация:** Применяется к каждому элементу массива
-   **Примечание:** `full_path` не содержит `[]` - это просто путь через точки. `[]` добавляется только при генерации имени поля для формы на основе `cardinality: many`.

#### 3. Ссылки на Entry (ref)

-   **Одиночная ссылка:** `featured_image` → `data_json.featured_image = entry_id`
-   **Множественные ссылки:** `related_articles` → `data_json.related_articles = [entry_id1, entry_id2]`
-   **Компонент:** Требуется специальный UI компонент для выбора Entry

#### 4. Валидация

-   **is_required:** Добавляет атрибут `required` к полю
-   **validation_rules:** Преобразуются в HTML5 атрибуты:
    -   `min`, `max` → `min`, `max`
    -   `pattern` → `pattern`
    -   `minLength`, `maxLength` → `minlength`, `maxlength`

### API для работы с формами

#### Получение схемы формы для Entry

```http
GET /api/v1/admin/entries/{entry}/form-schema
```

**Ответ:**

```json
{
    "entry_id": 1,
    "blueprint_id": 1,
    "schema": {
        "fields": [
            {
                "path": "title",
                "name": "title",
                "type": "input",
                "dataType": "string",
                "cardinality": "one",
                "required": true,
                "readonly": false,
                "validation": {
                    "min": 1,
                    "max": 500
                },
                "value": "Article Title"
            }
        ]
    }
}
```

#### Сохранение данных формы

```http
PUT /api/v1/admin/entries/{entry}
Content-Type: application/json

{
  "title": "Updated Title",
  "data_json": {
    "title": "Updated Title",
    "author": {
      "name": "John Doe"
    }
  }
}
```

## Реализация

### Backend (Laravel)

#### 1. Сервис генерации схемы формы

**Файл:** `app/Services/Form/FormSchemaService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Form;

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;

/**
 * Сервис для генерации схемы формы на основе Path.
 */
class FormSchemaService
{
    /**
     * Генерирует схему формы для Entry.
     *
     * @param Entry $entry
     * @return array<string, mixed>
     */
    public function generateForEntry(Entry $entry): array
    {
        $blueprint = $entry->blueprint();

        if (!$blueprint) {
            return ['fields' => []];
        }

        $paths = $blueprint->paths()
            ->orderBy('sort_order')
            ->get();

        $tree = $this->buildTree($paths);
        $data = $entry->data_json ?? [];

        return [
            'entry_id' => $entry->id,
            'blueprint_id' => $blueprint->id,
            'schema' => [
                'fields' => $this->transformPaths($tree, $data),
            ],
        ];
    }

    /**
     * Трансформирует Path в схему поля формы.
     *
     * @param \Illuminate\Support\Collection<int, Path> $paths
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function transformPaths($paths, array $data): array
    {
        return $paths->map(function (Path $path) use ($data) {
            $value = data_get($data, $path->full_path);

            $field = [
                'path' => $path->full_path,
                'name' => $this->generateFieldName($path),
                'type' => $this->mapDataTypeToInputType($path->data_type),
                'dataType' => $path->data_type,
                'cardinality' => $path->cardinality,
                'required' => $path->is_required,
                'readonly' => $path->is_readonly,
                'value' => $value,
            ];

            if ($path->validation_rules) {
                $field['validation'] = $path->validation_rules;
            }

            if ($path->is_readonly && $path->sourceBlueprint) {
                $field['sourceBlueprint'] = [
                    'code' => $path->sourceBlueprint->code,
                    'name' => $path->sourceBlueprint->name,
                ];
            }

            if ($path->children->isNotEmpty()) {
                $field['children'] = $this->transformPaths($path->children, $data);
            }

            return $field;
        })->all();
    }

    /**
     * Генерирует имя поля для формы.
     *
     * @param Path $path
     * @return string
     */
    private function generateFieldName(Path $path): string
    {
        if ($path->cardinality === 'many') {
            // Для массивов используем нотацию с индексами
            return $path->full_path . '[]';
        }

        return $path->full_path;
    }

    /**
     * Маппинг data_type в тип HTML input.
     *
     * @param string $dataType
     * @return string
     */
    private function mapDataTypeToInputType(string $dataType): string
    {
        return match ($dataType) {
            'string' => 'input',
            'text' => 'textarea',
            'int', 'float' => 'number',
            'bool' => 'checkbox',
            'date' => 'date',
            'datetime' => 'datetime-local',
            'json' => 'fieldset',
            'ref' => 'reference',
            default => 'input',
        };
    }

    /**
     * Строит дерево paths.
     *
     * @param \Illuminate\Support\Collection<int, Path> $paths
     * @return \Illuminate\Support\Collection<int, Path>
     */
    private function buildTree($paths): \Illuminate\Support\Collection
    {
        $grouped = $paths->groupBy('parent_id');

        $buildChildren = function ($parentId = null) use ($grouped, &$buildChildren) {
            if (!isset($grouped[$parentId])) {
                return collect();
            }

            return $grouped[$parentId]->map(function ($path) use ($buildChildren) {
                $path->children = $buildChildren($path->id);
                return $path;
            });
        };

        return $buildChildren(null);
    }
}
```

#### 2. Контроллер для схемы формы

**Файл:** `app/Http/Controllers/Admin/V1/EntryFormController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Services\Form\FormSchemaService;
use Illuminate\Http\JsonResponse;

/**
 * Контроллер для работы со схемами форм Entry.
 */
class EntryFormController extends Controller
{
    public function __construct(
        private readonly FormSchemaService $schemaService
    ) {}

    /**
     * Получить схему формы для Entry.
     *
     * @param Entry $entry
     * @return JsonResponse
     */
    public function schema(Entry $entry): JsonResponse
    {
        $schema = $this->schemaService->generateForEntry($entry);

        return response()->json($schema);
    }
}
```

#### 3. Роут

**Файл:** `routes/api_admin.php`

```php
Route::get('/entries/{entry}/form-schema', [EntryFormController::class, 'schema'])
    ->name('admin.v1.entries.form-schema');
```

### Frontend (пример на TypeScript/React)

```typescript
interface FormField {
    path: string;
    name: string;
    type: string;
    dataType: string;
    cardinality: "one" | "many";
    required: boolean;
    readonly: boolean;
    value?: any;
    validation?: Record<string, any>;
    sourceBlueprint?: {
        code: string;
        name: string;
    };
    children?: FormField[];
}

interface FormSchema {
    entry_id: number;
    blueprint_id: number;
    schema: {
        fields: FormField[];
    };
}

function FormFieldComponent({ field }: { field: FormField }) {
    if (field.dataType === "json" && field.cardinality === "one") {
        return (
            <fieldset>
                <legend>{field.name}</legend>
                {field.children?.map((child) => (
                    <FormFieldComponent key={child.path} field={child} />
                ))}
            </fieldset>
        );
    }

    if (field.cardinality === "many") {
        return <RepeaterField field={field} />;
    }

    if (field.dataType === "ref") {
        return <ReferenceField field={field} />;
    }

    return <InputField field={field} />;
}
```

## Заключение

Система генерации форм на основе Path обеспечивает:

1. **Гибкость:** Поддержка всех типов данных и структур
2. **Расширяемость:** Легко добавлять новые типы полей
3. **Валидация:** Автоматическая генерация правил валидации
4. **Типобезопасность:** Строгая типизация на всех уровнях
5. **Производительность:** Эффективная обработка больших структур
