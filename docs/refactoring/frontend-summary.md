# Техническое саммари: Рефакторинг PostType slug → ID

**Дата:** 2025-12-04  
**Версия API:** v1  
**Тип:** Breaking Changes  
**Приоритет:** 🔴 Высокий

---

## 📌 Общая информация

### Суть изменений

Произведен масштабный рефакторинг взаимодействия с `PostType`:

1. **PostType slug используется только для определения шаблона вывода entry**

    - Все остальное взаимодействие с PostType происходит через **ID**

2. **Глобальная уникальность entry.slug**

    - Уникальность slug записей изменена с локальной (в рамках `post_type_id`) на **глобальную**
    - Две записи разных типов не могут иметь одинаковый slug

3. **Плоские URL для всех записей**
    - Все записи имеют плоские URL вида `/{slug}` (вместо `/{post_type}/{slug}`)

### Область изменений

-   **База данных:** Миграции, индексы, триггеры
-   **API Endpoints:** Все endpoints, связанные с Entry и PostType
-   **Структура данных:** Ответы API, валидация
-   **Бизнес-логика:** Уникальность, фильтрация, генерация URL

---

## 🔴 Breaking Changes

### 1. Entry API

#### Создание и обновление Entry

**Было:**

```json
POST /api/v1/admin/entries
{
  "post_type": "article",
  "title": "My Article",
  "slug": "my-article",
  ...
}
```

**Стало:**

```json
POST /api/v1/admin/entries
{
  "post_type_id": 1,
  "title": "My Article",
  "slug": "my-article",
  ...
}
```

#### Ответ API для Entry

**Было:**

```json
{
  "data": {
    "id": 42,
    "post_type": "article",
    "title": "My Article",
    "slug": "my-article",
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
    "slug": "my-article",
    ...
  }
}
```

#### Фильтрация записей

**Было:**

```
GET /api/v1/admin/entries?post_type=article
```

**Стало:**

```
GET /api/v1/admin/entries?post_type_id=1
```

---

### 2. PostType Management API

#### Получение, обновление и удаление PostType

**Было:**

```
GET    /api/v1/admin/post-types/article
PUT    /api/v1/admin/post-types/article
DELETE /api/v1/admin/post-types/article
```

**Стало:**

```
GET    /api/v1/admin/post-types/1
PUT    /api/v1/admin/post-types/1
DELETE /api/v1/admin/post-types/1
```

**Важно:** ID теперь обязателен и должен быть числом. Валидация роута: `[0-9]+`

#### Создание и список PostType

**Без изменений:**

```
POST   /api/v1/admin/post-types
GET    /api/v1/admin/post-types
```

---

### 3. FormConfig API

#### URL изменен

**Было:**

```
GET    /api/v1/admin/post-types/article/form-config/{blueprint}
PUT    /api/v1/admin/post-types/article/form-config/{blueprint}
DELETE /api/v1/admin/post-types/article/form-config/{blueprint}
GET    /api/v1/admin/post-types/article/form-configs
```

**Стало:**

```
GET    /api/v1/admin/post-types/1/form-config/{blueprint}
PUT    /api/v1/admin/post-types/1/form-config/{blueprint}
DELETE /api/v1/admin/post-types/1/form-config/{blueprint}
GET    /api/v1/admin/post-types/1/form-configs
```

#### Структура ответа изменена

**Было:**

```json
{
  "data": {
    "post_type_slug": "article",
    "blueprint_id": 1,
    "config_json": {...},
    ...
  }
}
```

**Стало:**

```json
{
  "data": {
    "post_type_id": 1,
    "blueprint_id": 1,
    "config_json": {...},
    ...
  }
}
```

---

## 📊 Изменения в структуре данных

### Entry (изменения)

```typescript
// ❌ БЫЛО
interface Entry {
    id: number;
    post_type: string;        // "article"
    title: string;
    slug: string;             // Уникален в рамках post_type
    status: "draft" | "published";
    ...
}

// ✅ СТАЛО
interface Entry {
    id: number;
    post_type_id: number;     // 1
    title: string;
    slug: string;             // Глобально уникален
    status: "draft" | "published";
    ...
}
```

### FormConfig (изменения)

```typescript
// ❌ БЫЛО
interface FormConfig {
    post_type_slug: string; // "article"
    blueprint_id: number;
    config_json: object;
    created_at: string;
    updated_at: string;
}

// ✅ СТАЛО
interface FormConfig {
    post_type_id: number; // 1
    blueprint_id: number;
    config_json: object;
    created_at: string;
    updated_at: string;
}
```

### PostType (структура ответа API)

```typescript
// ✅ Структура ответа PostType API
interface PostType {
    id: number; // ✅ Возвращается в ответах API (критично для работы с ID)
    slug: string; // Используется только для шаблонов
    name: string;
    options_json: object;
    blueprint_id: number | null;
    created_at: string;
    updated_at: string;
}
```

**Важно:**

-   Поле `id` теперь возвращается в ответах API для **всех** операций с PostType (create, read, update, list)
-   Это необходимо для работы с новым API, где все взаимодействие происходит через ID
-   При получении списка PostTypes (`GET /post-types`) каждый элемент содержит `id`, который можно использовать для дальнейших операций

---

## ✅ Что НЕ изменилось

### 1. Публичный поиск

Публичный API поиска продолжает использовать slug для фильтрации:

```
GET /api/v1/search?post_type[]=article&post_type[]=page
```

**Причина:** Slug удобнее для публичного API, где пользователи могут не знать ID.

### 2. Шаблоны (BladeTemplateResolver)

PostType slug продолжает использоваться для определения шаблона вывода:

-   `entry--{postTypeSlug}--{entrySlug}`
-   `entry--{postTypeSlug}`
-   `entry` (fallback)

**Причина:** Это единственное место, где slug используется корректно - для определения шаблона.

### 3. Поисковый индекс (EntryToSearchDoc)

Поисковый индекс может содержать `post_type` slug для удобства фильтрации (опционально).

### 4. Структура других полей Entry

Все остальные поля Entry остались без изменений:

-   `title`, `content_json`, `meta_json`
-   `status`, `is_published`, `published_at`
-   `author`, `terms`, `blueprint`
-   Даты создания/обновления

### 5. Аутентификация и авторизация

Методы аутентификации и авторизации не изменились.

---

## 🔧 Детальные изменения API

### Полная таблица изменений endpoints

| Endpoint                                   | Метод  | Было                      | Стало                  | Статус           |
| ------------------------------------------ | ------ | ------------------------- | ---------------------- | ---------------- |
| `/entries`                                 | POST   | `post_type: "article"`    | `post_type_id: 1`      | ✅ Изменено      |
| `/entries`                                 | GET    | `?post_type=article`      | `?post_type_id=1`      | ✅ Изменено      |
| `/entries/{id}`                            | GET    | `post_type: "article"`    | `post_type_id: 1`      | ✅ Изменено      |
| `/entries/{id}`                            | PUT    | `post_type: "article"`    | `post_type_id: 1`      | ✅ Изменено      |
| `/post-types/{id}`                         | GET    | `/post-types/article`     | `/post-types/1`        | ✅ Изменено      |
| `/post-types/{id}`                         | PUT    | `/post-types/article`     | `/post-types/1`        | ✅ Изменено      |
| `/post-types/{id}`                         | DELETE | `/post-types/article`     | `/post-types/1`        | ✅ Изменено      |
| `/post-types/{id}/form-config/{blueprint}` | GET    | `/post-types/article/...` | `/post-types/1/...`    | ✅ Изменено      |
| `/post-types/{id}/form-config/{blueprint}` | PUT    | `/post-types/article/...` | `/post-types/1/...`    | ✅ Изменено      |
| `/post-types/{id}/form-config/{blueprint}` | DELETE | `/post-types/article/...` | `/post-types/1/...`    | ✅ Изменено      |
| `/post-types/{id}/form-configs`            | GET    | `/post-types/article/...` | `/post-types/1/...`    | ✅ Изменено      |
| `/post-types`                              | GET    | Без изменений             | Без изменений          | ✅ Без изменений |
| `/post-types`                              | POST   | Без изменений             | Без изменений          | ✅ Без изменений |
| `/search`                                  | GET    | `?post_type[]=article`    | `?post_type[]=article` | ✅ Без изменений |

---

## 🗄️ Изменения в базе данных

### Таблица `entries`

**Изменения:**

-   ✅ Удален уникальный индекс `entries_unique_active_slug` по `(post_type_id, slug, is_active)`
-   ✅ Создан уникальный индекс по `(slug, is_active)` - **глобальная уникальность**
-   ✅ Обновлены триггеры для проверки глобальной уникальности
-   ✅ Убрана привязка к `post_type.slug = 'page'` в триггерах

**Результат:**

-   Две записи разных типов не могут иметь одинаковый slug
-   Проверка уникальности происходит на уровне БД

### Таблица `form_configs`

**Изменения:**

-   ✅ Колонка `post_type_slug` (string) заменена на `post_type_id` (foreignId)
-   ✅ Обновлен уникальный индекс с `(post_type_slug, blueprint_id)` на `(post_type_id, blueprint_id)`
-   ✅ Добавлен foreign key constraint на `post_types.id` с `restrictOnDelete`

**Результат:**

-   Связь с PostType через ID вместо slug
-   Целостность данных на уровне БД

---

## 💡 Практические примеры

### 1. Получение списка PostTypes

Перед работой с записями нужно получить список PostTypes:

```typescript
// Получить все типы записей
const response = await fetch("/api/v1/admin/post-types", {
    headers: {
        Authorization: `Bearer ${token}`,
    },
});

const { data: postTypes } = await response.json();

// Создать маппинг slug → ID
const postTypeMap = new Map(postTypes.map((pt: PostType) => [pt.slug, pt.id]));

// Использовать ID
const articleId = postTypeMap.get("article"); // 1
```

---

### 2. Создание новой записи

```typescript
// ❌ БЫЛО
const createEntry = async (data: {
    post_type: string;
    title: string;
    slug: string;
}) => {
    await fetch("/api/v1/admin/entries", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
            post_type: "article",
            title: data.title,
            slug: data.slug,
        }),
    });
};

// ✅ СТАЛО
const createEntry = async (data: {
    post_type_id: number;
    title: string;
    slug: string;
}) => {
    await fetch("/api/v1/admin/entries", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
            post_type_id: data.post_type_id,
            title: data.title,
            slug: data.slug,
        }),
    });
};
```

---

### 3. Фильтрация записей по типу

```typescript
// ❌ БЫЛО
const getEntries = async (postTypeSlug: string) => {
    return fetch(`/api/v1/admin/entries?post_type=${postTypeSlug}`, {
        headers: { Authorization: `Bearer ${token}` },
    });
};

// ✅ СТАЛО
const getEntries = async (postTypeId: number) => {
    return fetch(`/api/v1/admin/entries?post_type_id=${postTypeId}`, {
        headers: { Authorization: `Bearer ${token}` },
    });
};

// Пример использования
const articleEntries = await getEntries(1); // ID типа "article"
```

---

### 4. Работа с FormConfig

```typescript
// ❌ БЫЛО
const getFormConfig = async (postTypeSlug: string, blueprintId: number) => {
    return fetch(
        `/api/v1/admin/post-types/${postTypeSlug}/form-config/${blueprintId}`,
        {
            headers: { Authorization: `Bearer ${token}` },
        }
    );
};

// ✅ СТАЛО
const getFormConfig = async (postTypeId: number, blueprintId: number) => {
    return fetch(
        `/api/v1/admin/post-types/${postTypeId}/form-config/${blueprintId}`,
        {
            headers: { Authorization: `Bearer ${token}` },
        }
    );
};

// Пример использования
const config = await getFormConfig(1, 5); // post_type_id=1, blueprint_id=5
```

---

### 5. Управление PostType

```typescript
// ❌ БЫЛО
const getPostType = async (slug: string) => {
    return fetch(`/api/v1/admin/post-types/${slug}`, {
        headers: { Authorization: `Bearer ${token}` },
    });
};

const updatePostType = async (slug: string, data: object) => {
    return fetch(`/api/v1/admin/post-types/${slug}`, {
        method: "PUT",
        headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify(data),
    });
};

const deletePostType = async (slug: string) => {
    return fetch(`/api/v1/admin/post-types/${slug}`, {
        method: "DELETE",
        headers: { Authorization: `Bearer ${token}` },
    });
};

// ✅ СТАЛО
const getPostType = async (id: number) => {
    return fetch(`/api/v1/admin/post-types/${id}`, {
        headers: { Authorization: `Bearer ${token}` },
    });
};

const updatePostType = async (id: number, data: object) => {
    return fetch(`/api/v1/admin/post-types/${id}`, {
        method: "PUT",
        headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify(data),
    });
};

const deletePostType = async (id: number) => {
    return fetch(`/api/v1/admin/post-types/${id}`, {
        method: "DELETE",
        headers: { Authorization: `Bearer ${token}` },
    });
};
```

---

### 6. Адаптер для миграции (опционально)

Если нужно постепенное внедрение изменений:

```typescript
class PostTypeAdapter {
    private slugToIdMap: Map<string, number> = new Map();
    private initialized = false;

    async init() {
        if (this.initialized) return;

        const response = await fetch("/api/v1/admin/post-types", {
            headers: { Authorization: `Bearer ${token}` },
        });
        const { data: postTypes } = await response.json();

        postTypes.forEach((pt: PostType) => {
            this.slugToIdMap.set(pt.slug, pt.id);
        });

        this.initialized = true;
    }

    slugToId(slug: string): number | null {
        return this.slugToIdMap.get(slug) || null;
    }

    idToSlug(id: number): string | null {
        for (const [slug, postTypeId] of this.slugToIdMap.entries()) {
            if (postTypeId === id) return slug;
        }
        return null;
    }
}

// Использование
const adapter = new PostTypeAdapter();
await adapter.init();

const articleId = adapter.slugToId("article"); // 1
const articleSlug = adapter.idToSlug(1); // "article"
```

---

## ⚠️ Важные изменения в поведении

### 1. Глобальная уникальность slug

**Было:**

-   Две записи разных типов могли иметь одинаковый slug
-   Пример: `/article/my-post` и `/page/my-post` были возможны

**Стало:**

-   Slug уникален глобально
-   Пример: Если запись "article" имеет slug "my-post", то запись "page" не может иметь такой же slug

**Последствия:**

-   При генерации slug нужно проверять глобальную уникальность
-   Возможны конфликты при переносе записей между типами

---

### 2. Плоские URL для всех записей

**Было:**

-   URL могли быть иерархическими: `/article/my-post`
-   Или плоскими: `/my-post` (зависило от типа)

**Стало:**

-   Все URL плоские: `/my-post`
-   Тип записи не влияет на структуру URL

**Последствия:**

-   SEO-структура изменилась
-   Старые ссылки с иерархической структурой могут не работать (если использовались)

---

### 3. Обработка ошибок 404

**Было:**

-   При несуществующем PostType по slug: `404 Not Found` с сообщением `"Unknown post type slug: article"`

**Стало:**

-   При несуществующем PostType по ID: `404 Not Found` (стандартный ответ Laravel)
-   Валидация ID на уровне роута (только числа)

---

## 📋 Чек-лист миграции

### Подготовка

-   [ ] Получить список всех PostTypes для создания маппинга slug → ID
-   [ ] Изучить все места использования старого API
-   [ ] Подготовить обновления TypeScript интерфейсов

### Entry API

-   [ ] Обновить формы создания записи - использовать `post_type_id` вместо `post_type`
-   [ ] Обновить формы редактирования записи
-   [ ] Обновить валидацию форм (проверка на number вместо string)
-   [ ] Обновить обработку ответов - использовать `entry.post_type_id`
-   [ ] Обновить компоненты отображения записи
-   [ ] Обновить фильтрацию записей - использовать `?post_type_id=1`

### PostType Management API

-   [ ] Обновить URL для получения PostType: `/post-types/{id}` вместо `/post-types/{slug}`
-   [ ] Обновить URL для обновления PostType
-   [ ] Обновить URL для удаления PostType
-   [ ] Обновить обработку ошибок 404

### FormConfig API

-   [ ] Обновить все запросы к FormConfig - использовать ID в URL
-   [ ] Обновить обработку ответов - использовать `post_type_id` вместо `post_type_slug`
-   [ ] Обновить типы данных в TypeScript

### Вспомогательные изменения

-   [ ] Обновить все TypeScript/JavaScript интерфейсы
-   [ ] Обновить все компоненты, использующие PostType
-   [ ] Обновить утилиты и хелперы
-   [ ] Обновить кэширование (если используется)

### Тестирование

-   [ ] Протестировать создание записей
-   [ ] Протестировать обновление записей
-   [ ] Протестировать фильтрацию
-   [ ] Протестировать FormConfig API
-   [ ] Протестировать PostType Management API
-   [ ] Проверить обработку ошибок
-   [ ] Проверить валидацию форм

### Финальная проверка

-   [ ] Все формы создания/редактирования работают
-   [ ] Фильтрация записей работает корректно
-   [ ] FormConfig загружается корректно
-   [ ] Нет ошибок в консоли браузера
-   [ ] Тесты фронтенда проходят
-   [ ] Все ссылки и навигация работают

---

## 🎯 Стратегии миграции

### Вариант 1: Полная миграция (рекомендуется)

**Описание:**
Обновить все компоненты одновременно после подготовки.

**Шаги:**

1. Получить список всех PostTypes и создать маппинг slug → ID
2. Обновить все TypeScript интерфейсы
3. Обновить все компоненты одновременно
4. Удалить старый код
5. Протестировать

**Преимущества:**

-   Чистая кодовая база
-   Нет дублирования логики
-   Легче поддерживать

**Недостатки:**

-   Требует остановки работы на время миграции
-   Больше рисков при одновременных изменениях

---

### Вариант 2: Постепенная миграция

**Описание:**
Создать адаптер и обновлять компоненты постепенно.

**Шаги:**

1. Создать адаптер (см. пример выше)
2. Обновлять компоненты по одному
3. После завершения - удалить адаптер
4. Протестировать

**Преимущества:**

-   Можно мигрировать постепенно
-   Меньше рисков
-   Не требует остановки работы

**Недостатки:**

-   Временное дублирование логики
-   Адаптер нужно поддерживать

---

## 📞 Поддержка и ресурсы

### Документация

-   **Полное руководство по миграции:** [frontend-migration-guide.md](./frontend-migration-guide.md)
-   **План рефакторинга:** [posttype-slug-to-id-plan.md](./posttype-slug-to-id-plan.md)
-   **Ревью изменений:** [posttype-controller-deep-review.md](./posttype-controller-deep-review.md)

### API Документация

-   **Swagger/Scribe:** `/docs` (после генерации)
-   **Примеры в тестах:** `tests/Feature/Api/`

### Обратная связь

При возникновении вопросов или проблем обращайтесь к бэкенд-команде.

---

## ⚡ Быстрый старт

### 1. Получить ID PostType

```typescript
// Загрузить все PostTypes один раз при инициализации
const postTypes = await fetch("/api/v1/admin/post-types");
const { data } = await postTypes.json();

// Создать маппинг
const postTypeMap = new Map(data.map((pt: PostType) => [pt.slug, pt.id]));

// Использовать
const articleId = postTypeMap.get("article"); // 1
```

### 2. Обновить создание записи

```typescript
// ❌ Было
{
    post_type: "article";
}

// ✅ Стало
{
    post_type_id: 1;
}
```

### 3. Обновить фильтрацию

```typescript
// ❌ Было
`/entries?post_type=article` // ✅ Стало
`/entries?post_type_id=1`;
```

---

## 🎓 Дополнительная информация

### Почему эти изменения?

1. **Консистентность:** ID более стабильны, чем slug (slug может изменяться)
2. **Производительность:** Поиск по ID быстрее, чем по slug
3. **Целостность данных:** Foreign keys обеспечивают целостность на уровне БД
4. **Глобальная уникальность:** Упрощает логику и проверки

### Где slug все еще используется?

1. **Шаблоны (BladeTemplateResolver)** - для определения шаблона вывода
2. **Публичный поиск** - для удобства пользователей
3. **Поисковый индекс (опционально)** - для удобства фильтрации

Это соответствует требованию: "PostType slug используется только для определения шаблона вывода entry".

---

**Последнее обновление:** 2025-12-04  
**Статус:** ✅ Готово к миграции
