# Blueprint System API Guide

Руководство по использованию Blueprint API для stupidCms.

---

## Обзор

Blueprint система позволяет:

-   Создавать динамические схемы полей для Entry
-   Использовать переиспользуемые компоненты (component Blueprints)
-   Индексировать произвольные JSON поля для быстрого поиска
-   Выполнять запросы по вложенным полям

---

## Endpoints

### Базовый URL

```
/api/v1/admin/blueprints
```

Все endpoints требуют JWT аутентификацию.

---

## Blueprints

### 1. Список всех Blueprints

```http
GET /api/v1/admin/blueprints
```

**Query параметры:**

-   `post_type_id` - фильтр по PostType
-   `type` - фильтр по типу (`full` или `component`)

**Ответ:**

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
            "updated_at": "2025-11-19T10:00:00.000000Z"
        }
    ]
}
```

---

### 2. Получить Blueprint

```http
GET /api/v1/admin/blueprints/{id}
```

**Ответ включает:**

-   Основные данные Blueprint
-   Список всех Paths (собственные + материализованные)
-   Список компонентов

```json
{
  "data": {
    "id": 1,
    "slug": "article_full",
    "name": "Article Full",
    "type": "full",
    "paths": [...],
    "components": [...]
  }
}
```

---

### 3. Создать Blueprint

```http
POST /api/v1/admin/blueprints
```

**Тело запроса (Full Blueprint):**

```json
{
    "post_type_id": 1,
    "slug": "my-blueprint",
    "name": "My Blueprint",
    "description": "Description",
    "type": "full",
    "is_default": false
}
```

**Тело запроса (Component Blueprint):**

```json
{
    "slug": "gallery",
    "name": "Gallery Component",
    "type": "component"
}
```

**Правила:**

-   `post_type_id` обязателен для `type=full`
-   `post_type_id` должен быть `null` для `type=component`
-   `slug` уникален в пределах `type` и `post_type_id`

---

### 4. Обновить Blueprint

```http
PUT /api/v1/admin/blueprints/{id}
```

**Тело запроса:**

```json
{
    "name": "Updated Name",
    "description": "Updated description"
}
```

---

### 5. Удалить Blueprint

```http
DELETE /api/v1/admin/blueprints/{id}
```

**Примечание:** Нельзя удалить Blueprint с существующими Entry.

---

## Paths

### 1. Список Paths в Blueprint

```http
GET /api/v1/admin/blueprints/{blueprint_id}/paths
```

**Query параметры:**

-   `own_only=true` - показать только собственные Paths (без материализованных)

**Ответ:**

```json
{
    "data": [
        {
            "id": 1,
            "blueprint_id": 1,
            "name": "metaTitle",
            "full_path": "seo.metaTitle",
            "data_type": "string",
            "cardinality": "one",
            "is_indexed": true,
            "is_required": false,
            "is_materialized": true
        }
    ]
}
```

---

### 2. Создать Path

```http
POST /api/v1/admin/blueprints/{blueprint_id}/paths
```

**Тело запроса:**

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

**Типы данных (`data_type`):**

-   `string` - короткая строка (до 500 символов)
-   `int` - целое число
-   `float` - число с плавающей точкой
-   `bool` - boolean
-   `text` - длинный текст
-   `json` - произвольный JSON
-   `ref` - ссылка на другой Entry

**Cardinality:**

-   `one` - одно значение
-   `many` - массив значений

**Правила:**

-   `full_path` уникален в рамках Blueprint
-   `parent_id` запрещен в component Blueprints
-   `ref_target_type` обязателен для `data_type=ref`

---

### 3. Обновить Path

```http
PUT /api/v1/admin/blueprints/{blueprint_id}/paths/{path_id}
```

**Примечание:** При изменении `data_type`, `cardinality` или `is_indexed` автоматически запускается реиндексация Entry.

---

### 4. Удалить Path

```http
DELETE /api/v1/admin/blueprints/{blueprint_id}/paths/{path_id}
```

**Примечание:** Материализованные копии удаляются автоматически.

---

## Components

### 1. Список компонентов Blueprint

```http
GET /api/v1/admin/blueprints/{blueprint_id}/components
```

---

### 2. Прикрепить компонент

```http
POST /api/v1/admin/blueprints/{blueprint_id}/components
```

**Тело запроса:**

```json
{
    "component_id": 2,
    "path_prefix": "seo"
}
```

**Что происходит:**

1. Создаются материализованные Paths с префиксом `seo.*`
2. Компонент привязывается к Blueprint
3. Запускается реиндексация всех Entry этого Blueprint

**Правила:**

-   `component_id` должен быть `type=component`
-   Нельзя прикрепить Blueprint сам к себе
-   Проверяется отсутствие циклов
-   `path_prefix` уникален в рамках Blueprint

---

### 3. Отсоединить компонент

```http
DELETE /api/v1/admin/blueprints/{blueprint_id}/components/{component_id}
```

**Что происходит:**

1. Удаляются все материализованные Paths компонента
2. Компонент отвязывается от Blueprint
3. Запускается реиндексация всех Entry

---

## Использование в Entry

### Пример создания Entry с Blueprint

```json
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

### Автоматическая индексация

При сохранении Entry автоматически:

1. Извлекаются все индексируемые поля из `data_json`
2. Значения записываются в `doc_values` и `doc_refs`
3. Становятся доступны для быстрого поиска

### Запросы по индексированным полям

**Скаляры:**

```php
Entry::wherePath('seo.metaTitle', '=', 'SEO Title')->get();
Entry::wherePathTyped('content', 'text', 'LIKE', '%keyword%')->get();
```

**Ссылки:**

```php
Entry::whereRef('relatedArticles', 5)->get(); // Найти Entry, которые ссылаются на Entry с ID=5
```

---

## Artisan Commands

### Реиндексация Entry

```bash
# Все Entry
php artisan entries:reindex

# Конкретный PostType
php artisan entries:reindex --post-type=article

# Конкретный Blueprint
php artisan entries:reindex --blueprint=article_full

# Асинхронно через очередь
php artisan entries:reindex --queue
```

---

### Миграция существующих Entry

```bash
# Dry run (без изменений)
php artisan entries:migrate-to-blueprints --dry-run

# Реальная миграция
php artisan entries:migrate-to-blueprints
```

Создает default Blueprint для каждого PostType и привязывает к ним Entry.

---

### Валидация миграции

```bash
php artisan entries:validate-migration
```

Проверяет:

-   Все ли Entry имеют `blueprint_id`
-   Все ли full Blueprints имеют `post_type_id`
-   Статистику индексации

---

### Экспорт/импорт Blueprint

```bash
# Экспорт
php artisan blueprint:export article_full
php artisan blueprint:export article_full --output=/path/to/export.json

# Импорт
php artisan blueprint:import /path/to/blueprint.json --post-type=article
php artisan blueprint:import /path/to/blueprint.json --force
```

---

### Диагностика Blueprint

```bash
php artisan blueprint:diagnose article_full
```

Показывает:

-   Количество собственных и материализованных Paths
-   Количество компонентов
-   Количество Entry
-   Статистику по типам полей

---

## Примеры сценариев

### Сценарий 1: Создание переиспользуемого SEO компонента

```bash
# 1. Создать component Blueprint
POST /api/v1/admin/blueprints
{
  "slug": "seo_fields",
  "name": "SEO Fields",
  "type": "component"
}

# 2. Добавить Paths
POST /api/v1/admin/blueprints/1/paths
{
  "name": "metaTitle",
  "full_path": "metaTitle",
  "data_type": "string",
  "is_indexed": true
}

POST /api/v1/admin/blueprints/1/paths
{
  "name": "metaDescription",
  "full_path": "metaDescription",
  "data_type": "text",
  "is_indexed": false
}

# 3. Прикрепить к разным full Blueprints
POST /api/v1/admin/blueprints/2/components
{
  "component_id": 1,
  "path_prefix": "seo"
}

POST /api/v1/admin/blueprints/3/components
{
  "component_id": 1,
  "path_prefix": "metadata"
}
```

---

### Сценарий 2: Поиск Entry по вложенным полям

```php
// Найти статьи с конкретным SEO заголовком
$entries = Entry::wherePath('seo.metaTitle', '=', 'My SEO Title')->get();

// Найти статьи, которые ссылаются на конкретную статью
$references = Entry::whereRef('relatedArticles', 123)->get();

// Комбинированный запрос
$entries = Entry::wherePath('seo.metaTitle', 'LIKE', '%keyword%')
    ->whereRef('category', 5)
    ->published()
    ->get();
```

---

### Сценарий 3: Изменение схемы без простоя

```bash
# 1. Добавить новое поле в component
POST /api/v1/admin/blueprints/1/paths
{
  "name": "ogImage",
  "full_path": "ogImage",
  "data_type": "string",
  "is_indexed": true
}

# 2. PathObserver автоматически:
#    - Создаст материализованные копии во всех full Blueprints
#    - Запустит реиндексацию Entry (асинхронно)

# 3. Новое поле сразу доступно в API
GET /api/v1/admin/blueprints/2/paths?own_only=false
```

---

## Best Practices

### 1. Используйте компоненты для переиспользования

❌ **Плохо:** Дублировать поля SEO в каждом Blueprint

```json
{
  "blueprint": "article",
  "paths": ["metaTitle", "metaDescription", "ogImage", ...]
}
{
  "blueprint": "page",
  "paths": ["metaTitle", "metaDescription", "ogImage", ...] // дубликат!
}
```

✅ **Хорошо:** Создать SEO компонент

```json
{
  "component": "seo_fields",
  "paths": ["metaTitle", "metaDescription", "ogImage"]
}

// Использовать везде:
POST /blueprints/article/components {"component_id": 1, "path_prefix": "seo"}
POST /blueprints/page/components {"component_id": 1, "path_prefix": "seo"}
```

---

### 2. Выбирайте правильные типы данных

-   `string` - для коротких текстов (title, slug, category)
-   `text` - для длинных текстов (content, description)
-   `int`/`float` - для чисел (price, rating, quantity)
-   `bool` - для флагов (is_featured, is_published)
-   `json` - для сложных структур (settings, metadata)
-   `ref` - для связей (author, category, related_posts)

---

### 3. Индексируйте только нужные поля

`is_indexed=true` создает записи в `doc_values`/`doc_refs` при каждом сохранении Entry.

❌ **Плохо:** Индексировать всё

```json
{"full_path": "longContent", "is_indexed": true} // не нужно
{"full_path": "internalNotes", "is_indexed": true} // не нужно
```

✅ **Хорошо:** Индексировать только поля для поиска

```json
{"full_path": "title", "is_indexed": true} // ✓ для поиска
{"full_path": "category", "is_indexed": true} // ✓ для фильтрации
{"full_path": "longContent", "is_indexed": false} // не для поиска
```

---

### 4. Используйте path_prefix для изоляции

```json
POST /blueprints/1/components
{
  "component_id": 2,
  "path_prefix": "seo" // ✓ изолирует поля: seo.metaTitle, seo.metaDescription
}
```

Это предотвращает конфликты имен и делает структуру данных понятнее.

---

## Troubleshooting

### Проблема: Entry не индексируются

**Причина:** Отсутствует `blueprint_id` у Entry.

**Решение:**

```bash
php artisan entries:migrate-to-blueprints
php artisan entries:reindex --queue
```

---

### Проблема: Поиск по полю не находит Entry

**Причина:** Поле не индексировано (`is_indexed=false`).

**Решение:**

```http
PUT /api/v1/admin/blueprints/1/paths/5
{
  "is_indexed": true
}
```

Затем:

```bash
php artisan entries:reindex --blueprint=my-blueprint
```

---

### Проблема: Конфликт имен полей при attach компонента

**Причина:** `path_prefix` создает конфликт с существующими полями.

**Решение:** Используйте другой `path_prefix`:

```json
{
    "component_id": 1,
    "path_prefix": "metadata" // вместо "seo"
}
```

---

## Roadmap

### В планах:

-   [ ] Batch операции для оптимизации индексации больших объемов данных
-   [ ] Поддержка nested Paths в компонентах (с учетом материализации)
-   [ ] UI для визуального редактирования Blueprint схем
-   [ ] Валидация данных на основе `validation_rules` в Path
-   [ ] Автоматическая генерация API ресурсов из Blueprint

---

## Support

Для вопросов и поддержки обращайтесь к документации:

-   [Архитектурный план v2](./document_path_index_laravel_plan_v2_fixed.md)
-   [План реализации](./implementation_plan_blueprint_system.md)
