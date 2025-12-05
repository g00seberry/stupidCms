# API Changes Summary

## Breaking Changes

### 1. PostType API

**Удалено поле:**

-   `slug` (string) — больше не существует

**Добавлено поле:**

-   `template` (string|null) — путь к шаблону Blade

**Пример ответа:**

```json
{
    "id": 1,
    "name": "Articles",
    "template": "templates.article",
    "options_json": {},
    "blueprint_id": null
}
```

**Валидация `template`:**

-   Должен начинаться с `templates.`
-   Примеры: `templates.article`, `templates.pages.custom`
-   Максимум 255 символов
-   Опциональное поле (nullable)

**Endpoints:**

-   `POST /api/v1/admin/post-types` — создание
-   `PUT /api/v1/admin/post-types/{id}` — обновление
-   `GET /api/v1/admin/post-types` — список (сортировка по `name` вместо `slug`)

### 2. Search API

**Изменение параметра `post_type`:**

-   **Было:** `post_type[]` — массив строк (slug'ов)
-   **Стало:** `post_type[]` — массив целых чисел (ID)

**Пример запроса:**

```
GET /api/v1/search?q=test&post_type[]=1&post_type[]=2
```

**Валидация:**

-   Каждый элемент должен быть `integer >= 1`
-   Максимум 10 элементов

**Ответ:**

```json
{
  "data": [
    {
      "id": "entries:42",
      "post_type": 1,
      "slug": "article-slug",
      "title": "Article Title",
      ...
    }
  ]
}
```

**Изменение:** `post_type` в ответе теперь `integer` (ID), а не `string` (slug).

### 3. Templates API

**Изменение:** Все шаблоны теперь находятся только в папке `templates/`.

**Имена шаблонов:**

-   Все имена должны начинаться с префикса `templates.`
-   Примеры: `templates.index`, `templates.pages.custom`, `templates.article`

**Endpoints:**

-   `GET /api/v1/admin/templates` — список шаблонов (только из `templates/`)
-   `GET /api/v1/admin/templates/{name}` — просмотр шаблона (имя должно начинаться с `templates.`)
-   `POST /api/v1/admin/templates` — создание шаблона (имя должно начинаться с `templates.` или будет добавлено автоматически)
-   `PUT /api/v1/admin/templates/{name}` — обновление шаблона (имя должно начинаться с `templates.`)

**Пример ответа (GET /api/v1/admin/templates):**

```json
{
    "data": [
        {
            "name": "templates.index",
            "path": "templates/index.blade.php",
            "exists": true
        },
        {
            "name": "templates.pages.custom",
            "path": "templates/pages/custom.blade.php",
            "exists": true
        }
    ]
}
```

**Валидация:**

-   Имя шаблона должно быть в формате `templates.*`
-   Если префикс не указан при создании, он добавляется автоматически
-   Максимум 255 символов

## Migration Guide

### PostType

**До:**

```typescript
interface PostType {
  id: number;
  slug: string;
  name: string;
  ...
}
```

**После:**

```typescript
interface PostType {
  id: number;
  name: string;
  template: string | null;
  ...
}
```

### Search

**До:**

```typescript
const searchParams = {
    q: "test",
    post_type: ["article", "page"], // string[]
};
```

**После:**

```typescript
const searchParams = {
    q: "test",
    post_type: [1, 2], // number[]
};
```

### Templates

**До:**

```typescript
// Создание шаблона
POST /api/v1/admin/templates
{
  "name": "pages.custom",
  "content": "<div>...</div>"
}

// Просмотр шаблона
GET /api/v1/admin/templates/pages.custom
```

**После:**

```typescript
// Создание шаблона (префикс templates. обязателен)
POST /api/v1/admin/templates
{
  "name": "templates.pages.custom",  // или "pages.custom" (префикс добавится автоматически)
  "content": "<div>...</div>"
}

// Просмотр шаблона
GET /api/v1/admin/templates/templates.pages.custom
```

## Notes

-   Все шаблоны должны находиться в папке `templates/` (namespace `templates.`)
-   Дефолтный шаблон: `templates.index`
-   Обратная совместимость не поддерживается
