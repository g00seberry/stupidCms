# План реализации связи между Term и Entry на фронтенде

## Обзор задачи

Реализовать UI для управления связями между записями (Entry) и термами (Term) в админ-панели.

## API Endpoints

### Получение термов записи

-   **GET** `/api/v1/admin/entries/{entry}/terms`
-   **Ответ:**

```json
{
    "data": {
        "entry_id": 42,
        "terms": [
            {
                "id": 3,
                "name": "Guides",
                "slug": "guides",
                "taxonomy": "category"
            }
        ],
        "terms_by_taxonomy": {
            "category": [
                {
                    "id": 3,
                    "name": "Guides",
                    "slug": "guides"
                }
            ]
        }
    }
}
```

### Привязка термов (attach)

-   **POST** `/api/v1/admin/entries/{entry}/terms/attach`
-   **Body:** `{ "term_ids": [3, 8] }`
-   **Ответ:** аналогичен GET `/entries/{entry}/terms`

### Отвязка термов (detach)

-   **POST** `/api/v1/admin/entries/{entry}/terms/detach`
-   **Body:** `{ "term_ids": [3, 8] }`
-   **Ответ:** аналогичен GET `/entries/{entry}/terms`

### Синхронизация термов (sync)

-   **PUT** `/api/v1/admin/entries/{entry}/terms/sync`
-   **Body:** `{ "term_ids": [3, 8] }` (может быть пустым массивом)
-   **Ответ:** аналогичен GET `/entries/{entry}/terms`

### Получение доступных термов для выбора

-   **GET** `/api/v1/admin/taxonomies/{taxonomy}/terms` - список термов таксономии
-   **GET** `/api/v1/admin/taxonomies/{taxonomy}/terms/tree` - дерево термов (для иерархических таксономий)

### Получение списка таксономий

-   **GET** `/api/v1/admin/taxonomies` - список всех таксономий

## Структура компонентов

### 1. Компонент управления термами записи

**Файл:** `components/EntryTermsManager.vue` (или аналогичный)

**Функционал:**

-   Отображение текущих термов записи, сгруппированных по таксономиям
-   Добавление новых термов через поиск/выбор
-   Удаление привязанных термов
-   Валидация: показывать только термы из разрешённых таксономий для post_type

**Props:**

-   `entryId: number` - ID записи
-   `postTypeSlug: string` - slug типа записи (для определения разрешённых таксономий)

**State:**

-   `currentTerms: Term[]` - текущие термы записи
-   `availableTaxonomies: Taxonomy[]` - доступные таксономии
-   `loading: boolean` - состояние загрузки
-   `error: string | null` - ошибка

**Methods:**

-   `loadEntryTerms()` - загрузка текущих термов
-   `loadAvailableTaxonomies()` - загрузка доступных таксономий
-   `attachTerms(termIds: number[])` - привязка термов
-   `detachTerms(termIds: number[])` - отвязка термов
-   `syncTerms(termIds: number[])` - синхронизация термов

### 2. Компонент выбора термов

**Файл:** `components/TermSelector.vue`

**Функционал:**

-   Поиск термов по названию
-   Отображение термов в виде дерева (для иерархических таксономий) или списка
-   Множественный выбор с чекбоксами
-   Фильтрация по таксономии

**Props:**

-   `taxonomySlug: string` - slug таксономии
-   `selectedTermIds: number[]` - уже выбранные термы
-   `allowedTermIds?: number[]` - разрешённые термы (для валидации)

**State:**

-   `terms: Term[]` - список термов
-   `searchQuery: string` - поисковый запрос
-   `selectedIds: number[]` - выбранные ID

**Methods:**

-   `loadTerms()` - загрузка термов таксономии
-   `filterTerms(query: string)` - фильтрация по поисковому запросу
-   `toggleTerm(termId: number)` - переключение выбора терма

### 3. Компонент отображения термов

**Файл:** `components/TermList.vue`

**Функционал:**

-   Отображение списка термов с группировкой по таксономиям
-   Кнопка удаления для каждого терма
-   Визуальное отображение иерархии (для иерархических термов)

**Props:**

-   `terms: Term[]` - список термов
-   `groupedByTaxonomy: boolean` - группировать по таксономиям
-   `removable: boolean` - показывать кнопки удаления

**Events:**

-   `@remove(termId: number)` - событие удаления терма

## Этапы реализации

### Этап 1: Базовый функционал загрузки и отображения

1. Создать API-клиент методы:

    - `getEntryTerms(entryId: number)`
    - `attachEntryTerms(entryId: number, termIds: number[])`
    - `detachEntryTerms(entryId: number, termIds: number[])`
    - `syncEntryTerms(entryId: number, termIds: number[])`
    - `getTaxonomyTerms(taxonomySlug: string)`
    - `getTaxonomyTermsTree(taxonomySlug: string)`

2. Создать компонент `TermList.vue`:

    - Отображение списка термов
    - Группировка по таксономиям
    - Кнопки удаления

3. Создать компонент `EntryTermsManager.vue`:
    - Загрузка и отображение текущих термов
    - Базовая структура UI

### Этап 2: Добавление термов

1. Создать компонент `TermSelector.vue`:

    - Поиск термов
    - Отображение списка/дерева
    - Множественный выбор

2. Интегрировать `TermSelector` в `EntryTermsManager`:
    - Модальное окно или выпадающий список
    - Кнопка "Добавить термы"
    - Вызов API `attachEntryTerms`

### Этап 3: Удаление термов

1. Добавить обработчик удаления в `TermList`:
    - Подтверждение удаления
    - Вызов API `detachEntryTerms`
    - Обновление списка после удаления

### Этап 4: Валидация и фильтрация

1. Получить разрешённые таксономии из post_type:

    - Загрузить post_type записи
    - Извлечь `options_json.taxonomies`
    - Фильтровать доступные таксономии

2. Валидация в `TermSelector`:
    - Показывать только термы из разрешённых таксономий
    - Показывать предупреждение при попытке выбрать неразрешённый терм

### Этап 5: Улучшение UX

1. Оптимистичные обновления:

    - Обновлять UI до получения ответа от сервера
    - Откатывать изменения при ошибке

2. Индикаторы загрузки:

    - Skeleton loaders для списков
    - Spinner для операций

3. Обработка ошибок:

    - Показывать понятные сообщения об ошибках
    - Валидационные ошибки от API (422)
    - Сетевые ошибки

4. Кэширование:
    - Кэшировать список таксономий
    - Кэшировать термы таксономий

## Интеграция в форму редактирования Entry

### Вариант 1: Отдельная секция в форме

```vue
<template>
    <div class="entry-form">
        <!-- Основные поля записи -->
        <EntryFormFields v-model="entry" />

        <!-- Секция термов -->
        <EntryTermsManager
            :entry-id="entry.id"
            :post-type-slug="entry.post_type"
        />
    </div>
</template>
```

### Вариант 2: Табы

```vue
<template>
    <div class="entry-form">
        <Tabs>
            <Tab name="content">
                <EntryFormFields v-model="entry" />
            </Tab>
            <Tab name="terms">
                <EntryTermsManager
                    :entry-id="entry.id"
                    :post-type-slug="entry.post_type"
                />
            </Tab>
        </Tabs>
    </div>
</template>
```

## Типы данных (TypeScript)

```typescript
interface Term {
    id: number;
    name: string;
    slug: string;
    taxonomy: string;
    meta_json?: Record<string, any>;
    created_at?: string;
    updated_at?: string;
    deleted_at?: string | null;
}

interface EntryTermsResponse {
    data: {
        entry_id: number;
        terms: Term[];
        terms_by_taxonomy: Record<string, Omit<Term, "taxonomy">[]>;
    };
}

interface Taxonomy {
    slug: string;
    label: string;
    hierarchical: boolean;
    // ... другие поля
}
```

## Обработка ошибок

### Валидационные ошибки (422)

```json
{
    "type": "https://stupidcms.dev/problems/validation-error",
    "title": "Validation Error",
    "status": 422,
    "code": "VALIDATION_ERROR",
    "detail": "Taxonomy 'tags' is not allowed for the entry post type.",
    "meta": {
        "errors": {
            "term_ids": [
                "Taxonomy 'tags' is not allowed for the entry post type."
            ]
        }
    }
}
```

**Обработка:**

-   Показывать сообщение из `detail` или `meta.errors.term_ids`
-   Подсвечивать невалидные термы в селекторе
-   Блокировать возможность выбора неразрешённых термов

### Ошибка "Entry not found" (404)

-   Показывать сообщение об ошибке
-   Перенаправлять на список записей

### Rate limit (429)

-   Показывать сообщение с временем повтора
-   Блокировать повторные запросы до истечения времени

## Тестирование

### Unit тесты

-   Компоненты: рендеринг, обработка событий
-   API-клиент: корректность запросов и обработка ответов

### Integration тесты

-   Полный цикл: загрузка → добавление → удаление термов
-   Валидация: попытка добавить неразрешённый терм
-   Обработка ошибок: сетевые ошибки, 422, 404

### E2E тесты

-   Сценарий: создание записи → добавление термов → сохранение
-   Сценарий: редактирование записи → изменение термов → сохранение

## Дополнительные улучшения (опционально)

1. **Drag & Drop** для изменения порядка термов (если потребуется)
2. **Bulk operations**: массовое добавление/удаление термов
3. **История изменений**: показывать, какие термы были добавлены/удалены
4. **Автодополнение** в поиске термов
5. **Клавиатурная навигация** в селекторе термов
6. **Горячие клавиши** для быстрых действий
