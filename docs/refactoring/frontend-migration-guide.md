# Руководство по миграции фронтенда: PostType slug → ID

**Дата:** 2025-12-04  
**Версия API:** v1  
**Статус:** Breaking Changes

## Краткое резюме

Произведен рефакторинг взаимодействия с PostType: теперь везде используется `post_type_id` вместо `post_type` slug. Это **breaking change** - требует обновления фронтенда.

---

## 🔴 Breaking Changes

### 1. Создание и обновление Entry

**Было:**

```json
POST /api/v1/admin/entries
{
  "post_type": "article",
  "title": "My Article",
  ...
}
```

**Стало:**

```json
POST /api/v1/admin/entries
{
  "post_type_id": 1,
  "title": "My Article",
  ...
}
```

### 2. Ответы API для Entry

**Было:**

```json
{
  "data": {
    "id": 42,
    "post_type": "article",
    "title": "My Article",
    ...
  }
}
```

**Стало:**

```json
{
  "data": {
    "id": 42,
    "post_type_id": 1,
    "title": "My Article",
    ...
  }
}
```

### 3. Фильтрация записей по типу

**Было:**

```
GET /api/v1/admin/entries?post_type=article
```

**Стало:**

```
GET /api/v1/admin/entries?post_type_id=1
```

### 4. FormConfig API - изменение URL и данных

**Было:**

```
GET /api/v1/admin/post-types/article/form-config/{blueprint}
PUT /api/v1/admin/post-types/article/form-config/{blueprint}
DELETE /api/v1/admin/post-types/article/form-config/{blueprint}
GET /api/v1/admin/post-types/article/form-configs
```

**Стало:**

```
GET /api/v1/admin/post-types/1/form-config/{blueprint}
PUT /api/v1/admin/post-types/1/form-config/{blueprint}
DELETE /api/v1/admin/post-types/1/form-config/{blueprint}
GET /api/v1/admin/post-types/1/form-configs
```

**Ответ был:**

```json
{
  "data": {
    "post_type_slug": "article",
    "blueprint_id": 1,
    ...
  }
}
```

**Ответ стал:**

```json
{
  "data": {
    "post_type_id": 1,
    "blueprint_id": 1,
    ...
  }
}
```

---

## ✅ Без изменений

### Публичный поиск

Публичный API поиска продолжает использовать slug для фильтрации (для удобства):

```
GET /api/v1/search?post_type[]=article&post_type[]=page
```

Это **не меняется** - можно продолжать использовать slug в публичном API.

---

## 📋 Чек-лист миграции фронтенда

### 1. Создание/обновление записей

-   [ ] Заменить `post_type: "article"` на `post_type_id: 1` в запросах создания
-   [ ] Обновить формы создания записи - использовать ID вместо slug
-   [ ] Обновить валидацию форм

### 2. Отображение записей

-   [ ] Заменить обращение к `entry.post_type` на `entry.post_type_id`
-   [ ] Если нужно отобразить название типа - загружать PostType по ID
-   [ ] Обновить типы TypeScript/интерфейсы

### 3. Фильтрация и поиск

-   [ ] Заменить `?post_type=article` на `?post_type_id=1` в запросах списка
-   [ ] Обновить компоненты фильтрации - работать с ID вместо slug
-   [ ] Добавить загрузку списка PostTypes для отображения в фильтрах

### 4. FormConfig API

-   [ ] Обновить все запросы к FormConfig - использовать ID в URL
-   [ ] Обновить обработку ответов - использовать `post_type_id` вместо `post_type_slug`
-   [ ] Обновить типы данных

### 5. PostType управление (show/update/delete)

-   [ ] Обновить URL для получения PostType: `/post-types/article` → `/post-types/1`
-   [ ] Обновить URL для обновления PostType: `/post-types/article` → `/post-types/1`
-   [ ] Обновить URL для удаления PostType: `/post-types/article` → `/post-types/1`
-   [ ] Обновить обработку ошибок 404 - теперь используется ID вместо slug

### 6. Обработка ошибок

-   [ ] Обновить обработку ошибок валидации - проверять `post_type_id` вместо `post_type`
-   [ ] Обновить сообщения об ошибках

---

## 🔧 Примеры кода

### Получение списка PostTypes

Перед созданием записи или фильтрацией нужно получить список PostTypes:

```typescript
// Получить список всех типов записей
const response = await fetch("/api/v1/admin/post-types", {
    headers: { "Authorization": `Bearer ${token}` }
});
const { data: postTypes } = await response.json();

// PostType теперь содержит id в ответе API
// postTypes = [
//   { id: 1, slug: "article", name: "Articles", ... },
//   { id: 2, slug: "page", name: "Pages", ... },
//   ...
// ]

// Использовать ID из ответа
const articleId = postTypes.find((pt: PostType) => pt.slug === "article")?.id;
```

### Создание записи

```typescript
// Было
const createEntry = async (data: {
  post_type: string;  // ❌
  title: string;
  ...
}) => {
  await fetch('/api/v1/admin/entries', {
    method: 'POST',
    body: JSON.stringify(data)
  });
};

// Стало
const createEntry = async (data: {
  post_type_id: number;  // ✅
  title: string;
  ...
}) => {
  await fetch('/api/v1/admin/entries', {
    method: 'POST',
    body: JSON.stringify(data)
  });
};
```

### Фильтрация записей

```typescript
// Было
const getEntries = async (postTypeSlug: string) => {
    return fetch(`/api/v1/admin/entries?post_type=${postTypeSlug}`);
};

// Стало
const getEntries = async (postTypeId: number) => {
    return fetch(`/api/v1/admin/entries?post_type_id=${postTypeId}`);
};
```

### Работа с FormConfig

```typescript
// Было
const getFormConfig = async (postTypeSlug: string, blueprintId: number) => {
    return fetch(
        `/api/v1/admin/post-types/${postTypeSlug}/form-config/${blueprintId}`
    );
};

// Стало
const getFormConfig = async (postTypeId: number, blueprintId: number) => {
    return fetch(
        `/api/v1/admin/post-types/${postTypeId}/form-config/${blueprintId}`
    );
};
```

### Управление PostType (show/update/delete)

```typescript
// Было
const getPostType = async (slug: string) => {
    return fetch(`/api/v1/admin/post-types/${slug}`);
};

const updatePostType = async (slug: string, data: object) => {
    return fetch(`/api/v1/admin/post-types/${slug}`, {
        method: "PUT",
        body: JSON.stringify(data),
    });
};

const deletePostType = async (slug: string) => {
    return fetch(`/api/v1/admin/post-types/${slug}`, {
        method: "DELETE",
    });
};

// Стало
const getPostType = async (id: number) => {
    return fetch(`/api/v1/admin/post-types/${id}`);
};

const updatePostType = async (id: number, data: object) => {
    return fetch(`/api/v1/admin/post-types/${id}`, {
        method: "PUT",
        body: JSON.stringify(data),
    });
};

const deletePostType = async (id: number) => {
    return fetch(`/api/v1/admin/post-types/${id}`, {
        method: "DELETE",
    });
};
```

---

## 📊 Структура данных Entry

### Поля Entry (изменения)

```typescript
interface Entry {
    id: number;
    post_type_id: number; // ✅ Изменено с post_type: string
    title: string;
    slug: string; // Теперь глобально уникален
    status: "draft" | "published";
    content_json: object;
    meta_json: object;
    is_published: boolean;
    published_at: string | null;
    template_override: string | null;
    author: {
        id: number;
        name: string;
    } | null;
    terms: Array<{
        id: number;
        name: string;
        taxonomy: number;
    }>;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
}
```

### Структура FormConfig (изменения)

```typescript
interface FormConfig {
    post_type_id: number; // ✅ Изменено с post_type_slug: string
    blueprint_id: number;
    config_json: object;
    created_at: string;
    updated_at: string;
}
```

---

## ⚠️ Важные замечания

### 1. Глобальная уникальность slug

Slug записей теперь уникален **глобально** (не только в рамках типа). Это означает:

-   Два разных типа записей не могут иметь одинаковый slug
-   При генерации slug нужно проверять глобальную уникальность

### 2. Плоские URL

Все записи теперь имеют плоские URL:

```
/some-slug  (вместо /article/some-slug)
```

Это не требует изменений на фронтенде, если используется API для получения данных.

### 3. Получение PostType по ID

Если нужно отобразить название типа записи:

```typescript
// Загрузить PostType по ID
const postType = await fetch(`/api/v1/admin/post-types?slug=${slug}`);
// Или получить все типы и найти нужный
const postTypes = await fetch("/api/v1/admin/post-types");
const postType = postTypes.find((pt) => pt.id === entry.post_type_id);
```

---

## 🔄 Миграционная стратегия

### Вариант 1: Полная миграция (рекомендуется)

1. Получить список всех PostTypes и создать маппинг slug → ID
2. Обновить все компоненты одновременно
3. Удалить старый код

### Вариант 2: Постепенная миграция

1. Создать адаптер, который преобразует slug → ID
2. Постепенно обновлять компоненты
3. Удалить адаптер после завершения

### Пример адаптера

```typescript
class PostTypeAdapter {
    private slugToIdMap: Map<string, number> = new Map();

    async init() {
        const postTypes = await fetch("/api/v1/admin/post-types").then((r) =>
            r.json()
        );
        postTypes.data.forEach((pt: PostType) => {
            this.slugToIdMap.set(pt.slug, pt.id);
        });
    }

    slugToId(slug: string): number | null {
        return this.slugToIdMap.get(slug) || null;
    }
}
```

---

## 📞 Поддержка

При возникновении вопросов обращайтесь к бэкенд-команде или проверяйте:

-   Swagger документацию: `/docs`
-   Примеры запросов в тестах: `tests/Feature/Api/`

---

## ✅ Готовность

После выполнения миграции проверьте:

-   [ ] Все формы создания/редактирования работают
-   [ ] Фильтрация записей работает
-   [ ] FormConfig загружается корректно
-   [ ] Нет ошибок в консоли браузера
-   [ ] Тесты фронтенда проходят
