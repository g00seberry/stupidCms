# Изменения API для фронтенда

## Дата: 2025-11-14

### Критические изменения

#### 1. Удалено поле `slug` из таксономий и термов

**Что изменилось:**

-   Поле `slug` полностью удалено из моделей `Taxonomy` и `Term`
-   Все взаимодействие с таксономиями и термами теперь происходит только через `id`

**Затронутые эндпоинты:**

-   `GET /api/v1/admin/taxonomies` — ответ больше не содержит `slug`
-   `GET /api/v1/admin/taxonomies/{id}` — ответ больше не содержит `slug`
-   `POST /api/v1/admin/taxonomies` — запрос больше не принимает `slug`
-   `PUT /api/v1/admin/taxonomies/{id}` — запрос больше не принимает `slug`
-   `GET /api/v1/admin/taxonomies/{taxonomy}/terms` — ответ больше не содержит `slug`
-   `GET /api/v1/admin/terms/{term}` — ответ больше не содержит `slug`
-   `POST /api/v1/admin/taxonomies/{taxonomy}/terms` — запрос больше не принимает `slug`
-   `PUT /api/v1/admin/terms/{term}` — запрос больше не принимает `slug`
-   `GET /api/v1/admin/entries/{entry}/terms` — термы в ответе больше не содержат `slug`

**Пример изменения ответа:**

**Было:**

```json
{
    "id": 1,
    "name": "Categories",
    "slug": "categories",
    "hierarchical": true
}
```

**Стало:**

```json
{
    "id": 1,
    "name": "Categories",
    "hierarchical": true
}
```

#### 2. Изменен формат фильтра поиска по термам

**Что изменилось:**

-   Формат фильтра изменен с `taxonomy_id:slug` на `taxonomy_id:term_id`

**Затронутый эндпоинт:**

-   `GET /api/v1/search?term=...`

**Пример изменения:**

**Было:**

```
GET /api/v1/search?term=1:news
```

**Стало:**

```
GET /api/v1/search?term=1:5
```

Где `1` — ID таксономии, `5` — ID терма.

### Новые данные в сидерах

#### Дополнительные типы постов:

-   `article` (ID: 2)
-   `product` (ID: 3)

#### Дополнительные таксономии:

-   `Regions` (ID: 3, hierarchical: true)
-   `Topics` (ID: 4, hierarchical: false)

#### Примеры термов:

-   **Categories**: Technology, Science, Arts (с подкатегориями)
-   **Tags**: News, Tutorial, Review, Guide, Announcement, Update
-   **Regions**: Europe, Asia, Americas (с подрегионами)
-   **Topics**: Programming, Design, Marketing, Business, Education, Health

#### Примеры записей:

-   **Pages**: About, Contact, Privacy Policy
-   **Articles**: 3 статьи с привязкой к категориям и тегам
-   **Products**: 2 товара с привязкой к категориям и регионам

### Рекомендации для миграции

1. **Удалить все ссылки на `slug` в таксономиях и термах:**

    - Использовать `id` вместо `slug` для идентификации
    - Обновить роутинг, если использовался `slug` в URL

2. **Обновить фильтры поиска:**

    - Заменить формат `taxonomy_id:slug` на `taxonomy_id:term_id`
    - Обновить UI для отображения/выбора термов по ID

3. **Обновить формы создания/редактирования:**

    - Убрать поля `slug` из форм таксономий и термов
    - Использовать только `name` для отображения

4. **Обновить валидацию:**
    - Убрать валидацию `slug` на фронтенде
    - Использовать `id` для всех операций с таксономиями и термами

### Примеры кода

#### Поиск таксономии по ID (вместо slug):

```javascript
// Было
const taxonomy = await api.get(`/api/v1/admin/taxonomies?slug=categories`);

// Стало
const taxonomy = await api.get(`/api/v1/admin/taxonomies/1`);
```

#### Поиск термов по ID таксономии:

```javascript
// Было
const terms = await api.get(`/api/v1/admin/taxonomies?slug=categories/terms`);

// Стало
const terms = await api.get(`/api/v1/admin/taxonomies/1/terms`);
```

#### Фильтр поиска:

```javascript
// Было
const results = await api.get(`/api/v1/search?term=1:news`);

// Стало
const results = await api.get(`/api/v1/search?term=1:5`);
```

### Обратная совместимость

⚠️ **Breaking change**: Эти изменения несовместимы с предыдущими версиями API. Требуется обновление фронтенда.

### Дополнительная информация

-   Все изменения протестированы (535 тестов проходят)
-   Документация API обновлена
-   Сидеры расширены для тестирования
