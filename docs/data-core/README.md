# Документная система с path-индексацией

> **Реализация динамических структур данных через Blueprint → PostType → Entry**

---

## Навигация по документам

### Must Have (196 часов) — Основной функционал ✅

| Блок  | Документ                                        | Трудоёмкость | Описание                                    |
| ----- | ----------------------------------------------- | ------------ | ------------------------------------------- |
| **A** | [Схема БД](./block-a-database-schema.md)       | 18 ч         | 7 миграций, 5 моделей, интеграция PostType  |
| **B** | [Граф зависимостей](./block-b-dependency-graph.md) | 12 ч         | Валидатор циклов, BFS обход, closure table  |
| **C** | [Материализация](./block-c-materialization.md) | 40 ч         | Рекурсивный копировщик, PRE-CHECK, защита   |
| **D** | [Каскадные события](./block-d-cascade-events.md) | 32 ч         | Event, Listener, версионирование структуры  |
| **F+G** | [Entry и индексация](./block-fg-entry-indexing.md) | 46 ч         | HasDocumentData, EntryIndexer, wherePath    |
| **H** | [BlueprintStructureService](./block-h-structure-service.md) | 48 ч         | Центральный сервис координации CRUD         |

### Should Have (34 часа) — REST API ✅

| Блок  | Документ                                        | Трудоёмкость | Описание                                    |
| ----- | ----------------------------------------------- | ------------ | ------------------------------------------- |
| **I** | [API контроллеры](./block-i-api-controllers.md) | 34 ч         | CRUD Blueprint/Path/Embed, Resources, роуты |

### Could Have (88 часов) — Комплексное тестирование ✅

| Блок  | Документ                                        | Трудоёмкость | Описание                                    |
| ----- | ----------------------------------------------- | ------------ | ------------------------------------------- |
| **J** | [Тестирование](./block-j-testing.md)           | 88 ч         | Unit, Feature, Integration, Performance тесты |

---

## Архитектура интеграции с stupidCMS

```
PostType (существующая)
   ↓ добавить blueprint_id (nullable)
   ↓
Blueprint (новая)
   ↓ 1:n
Path (новая)
   ↓
Entry (существующая)
   ↓ индексация через postType->blueprint
   ↓
DocValue, DocRef (новые)
```

**Ключевые решения:**

- ✅ Blueprint крепится к PostType через `post_types.blueprint_id` (nullable)
- ✅ Entry наследует blueprint через связь: `$entry->postType->blueprint`
- ✅ Используется **существующая** таблица `entries` (минимум изменений)
- ✅ Гибридный режим: Entry может работать с blueprint или без него
- ✅ Индексация выполняется только для Entry с назначенным blueprint
- ✅ Обратная совместимость: существующие Entry продолжают работать

---

## Порядок реализации

### Этап 1: БД и модели (33 часа) — Блок A

**Миграции (строгий порядок):**

1. `create_blueprints_table`
2. `create_paths_table` (БЕЗ FK `blueprint_embed_id`)
3. `create_blueprint_embeds_table`
4. `add_blueprint_embed_fk_to_paths`
5. `add_blueprint_id_to_post_types`
6. `create_doc_values_table`
7. `create_doc_refs_table`

**Модели:**

- Blueprint
- Path
- BlueprintEmbed
- PostType (обновить)
- DocValue, DocRef

**Команды:**

```bash
# Создать миграции
php artisan make:migration create_blueprints_table
# ... остальные миграции

# Запустить миграции
php artisan migrate

# Проверить структуру
php artisan schema:dump
```

---

### Этап 2: Встраивание (92 часа) — Блоки B+C

**Компоненты:**

- `CyclicDependencyException`
- `PathConflictException`
- `MaxDepthExceededException`
- `DependencyGraphService` (BFS обход графа)
- `CyclicDependencyValidator`
- `PathConflictValidator` (PRE-CHECK)
- `MaterializationService` (рекурсивный копировщик)

**Тесты:**

```bash
php artisan test --filter=CyclicDependency
php artisan test --filter=PathConflict
php artisan test --filter=Materialization
```

---

### Этап 3: Каскады (26 часов) — Блок D

**Компоненты:**

- `BlueprintStructureChanged` (Event)
- `RematerializeEmbeds` (Listener)
- `PathObserver` (опционально)
- `EventServiceProvider` (регистрация)

**Тесты:**

```bash
php artisan test --filter=RematerializeEmbeds
php artisan test --filter=Versioning
```

---

### Этап 4: Индексация (46 часов) — Блоки F+G

**Компоненты:**

- `HasDocumentData` (trait для Entry)
- `EntryIndexer` (сервис)
- `ReindexBlueprintEntries` (Job)
- `EntryObserver`

**Тесты:**

```bash
php artisan test --filter=EntryIndexer
php artisan test --filter=WherePath
```

---

### Этап 5: Сервисный слой (48 часов) — Блок H

**Компоненты:**

- `BlueprintStructureService` (центральный сервис)
  - CRUD Blueprint
  - CRUD Path (с пересчётом full_path)
  - CRUD BlueprintEmbed (с валидацией и материализацией)

**Тесты:**

```bash
php artisan test --filter=BlueprintStructureService
```

---

### Этап 6: REST API (34 часа) — Блок I

**Компоненты:**

- `BlueprintController` (CRUD + вспомогательные endpoints)
- `PathController` (дерево полей + CRUD)
- `BlueprintEmbedController` (создание/удаление встраиваний)
- API Resources (`BlueprintResource`, `PathResource`, `BlueprintEmbedResource`)
- FormRequest классы для валидации

**Команды:**

```bash
# Создать контроллеры и ресурсы
php artisan make:controller Admin/BlueprintController --api
php artisan make:resource Admin/BlueprintResource

# Тесты
php artisan test --filter=BlueprintController

# Документация API
composer scribe:gen
```

---

## Критические моменты реализации

| №   | Момент                         | Последствия при ошибке                    | Блок |
| --- | ------------------------------ | ----------------------------------------- | ---- |
| 1   | **Рекурсивная материализация** | Глубокие пути не работают                 | C    |
| 2   | **PRE-CHECK конфликтов**       | SQL errors вместо доменных исключений     | C    |
| 3   | **Каскадные события**          | Обновляется только один уровень           | D    |
| 4   | **Защита полей**               | `$guarded` для служебных полей Path       | A    |
| 5   | **UNIQUE constraint**          | Нельзя сохранять paths с пустым full_path | C    |
| 6   | **Взаимные FK**                | 5 миграций в строгой последовательности   | A    |
| 7   | **MySQL 8.0.16+**              | CHECK constraints обязательны             | A    |

---

## Примеры использования

### Создание Blueprint с полями

```php
use App\Services\Blueprint\BlueprintStructureService;

$service = app(BlueprintStructureService::class);

// Создать blueprint
$blueprint = $service->createBlueprint([
    'name' => 'Article',
    'code' => 'article',
    'description' => 'Blog article structure',
]);

// Добавить поля
$title = $service->createPath($blueprint, [
    'name' => 'title',
    'data_type' => 'string',
    'is_required' => true,
    'is_indexed' => true,
]);

$author = $service->createPath($blueprint, [
    'name' => 'author',
    'data_type' => 'json',
]);

$authorName = $service->createPath($blueprint, [
    'name' => 'name',
    'parent_id' => $author->id,
    'data_type' => 'string',
    'is_indexed' => true,
]);
```

### Встраивание Blueprint

```php
// Создать Address blueprint
$address = $service->createBlueprint(['name' => 'Address', 'code' => 'address']);
$service->createPath($address, ['name' => 'street', 'data_type' => 'string']);
$service->createPath($address, ['name' => 'city', 'data_type' => 'string']);

// Создать Company blueprint
$company = $service->createBlueprint(['name' => 'Company', 'code' => 'company']);
$office = $service->createPath($company, ['name' => 'office', 'data_type' => 'json']);
$legal = $service->createPath($company, ['name' => 'legal', 'data_type' => 'json']);

// Встроить Address дважды
$embed1 = $service->createEmbed($company, $address, $office);
$embed2 = $service->createEmbed($company, $address, $legal);

// Результат: company.office.street, company.office.city,
//            company.legal.street, company.legal.city
```

### Привязка Blueprint к PostType

```php
$postType = PostType::create([
    'slug' => 'article',
    'name' => 'Статьи',
    'blueprint_id' => $blueprint->id, // ← Привязка
]);
```

### Создание Entry с индексацией

```php
$entry = Entry::create([
    'post_type_id' => $postType->id,
    'title' => 'My Article',
    'data_json' => [
        'title' => 'How to Build CMS',
        'author' => [
            'name' => 'John Doe',
        ],
    ],
]);

// → Автоматическая индексация через EntryObserver
```

### Запросы к индексированным данным

```php
use App\Models\Entry;

// Поиск по автору
$entries = Entry::wherePath('author.name', '=', 'John Doe')->get();

// Фильтрация по цене
$expensive = Entry::wherePath('price', '>', 100)->get();

// Сортировка по дате
$recent = Entry::orderByPath('published_at', 'desc')->get();

// Проверка заполненности
$withBio = Entry::wherePathExists('author.bio')->get();

// Поиск по ссылке
$related = Entry::whereRef('relatedArticles', 42)->get();
```

---

## Защита и валидация

### Нельзя встроить в самого себя

```php
$service->createEmbed($blueprint, $blueprint);
// → CyclicDependencyException: "Нельзя встроить blueprint в самого себя"
```

### Нельзя создать цикл

```php
$service->createEmbed($a, $b); // A → B
$service->createEmbed($b, $a); // B → A
// → CyclicDependencyException: "Циклическая зависимость"
```

### Нельзя создать конфликт путей

```php
// host имеет поле 'email'
// embedded имеет поле 'email'
$service->createEmbed($host, $embedded, null);
// → PathConflictException: "конфликт путей: 'email'"
```

### Нельзя редактировать скопированные поля

```php
$copiedPath = Path::where('blueprint_embed_id', $embed->id)->first();
$service->updatePath($copiedPath, ['name' => 'new_name']);
// → LogicException: "Невозможно редактировать скопированное поле"
```

---

## Оптимизация (опционально)

### Closure Table для больших графов (>100 blueprint)

```bash
php artisan make:migration create_blueprint_deps_table
```

Синхронизация через `ClosureTableSyncService`.

### Кэширование структуры Blueprint

```php
Cache::remember("blueprint.{$id}.paths", 3600, fn() => $blueprint->paths);
```

### Партиционирование doc_values

```sql
PARTITION BY HASH(path_id) PARTITIONS 16;
```

---

## Тестирование

### Unit тесты (56 часов)

- Валидация циклов
- PRE-CHECK конфликтов
- Материализация (простая, транзитивная, множественная)
- Каскадные события
- Индексация Entry

### Feature тесты (20 часов)

- CRUD Blueprint/Path/Embed
- Запросы wherePath
- Версионирование структуры

### Integration тесты (12 часов)

- Full flow: создание графа → встраивание → изменение → каскады → индексация
- Транзитивные зависимости D → C → A → B

---

## Версионирование

### Опциональные поля для отслеживания

```sql
ALTER TABLE blueprints ADD COLUMN structure_version INT UNSIGNED DEFAULT 1;
ALTER TABLE entries ADD COLUMN indexed_structure_version INT UNSIGNED NULL;
```

### Проверка устаревших Entry

```php
if ($entry->isIndexOutdated()) {
    dispatch(new ReindexEntry($entry->id));
}
```

---

## Мониторинг

### Метрики производительности

- Время материализации (Blueprint::materialize.duration)
- Время индексации (Entry::index.duration)
- Количество doc_values/doc_refs
- Глубина графа зависимостей

### Логирование

```php
Log::info("Материализация blueprint '{$blueprint->code}'", [
    'embed_id' => $embed->id,
    'paths_created' => $copiesCount,
    'duration_ms' => $duration,
]);
```

---

## FAQ

### Q: Можно ли встроить один blueprint несколько раз?

**A:** Да, под разными `host_path`. Пример: Address → Company (office, legal).

### Q: Что происходит при изменении встроенного blueprint?

**A:** Каскадное событие триггерит автоматическую рематериализацию всех зависимых + реиндексацию Entry.

### Q: Работает ли система без blueprint?

**A:** Да, гибридный режим. Entry с `postType.blueprint_id = NULL` работают как раньше (legacy).

### Q: Максимальная глубина вложенности?

**A:** 5 уровней (MAX_EMBED_DEPTH). Защита от переполнения стека.

### Q: Можно ли изменить скопированное поле?

**A:** Нет. Изменения нужно вносить в исходный blueprint. Копии обновятся автоматически через каскад.

---

## Созданные документы реализации

**Must Have + Should Have (230 часов):**

1. ✅ [Блок A: Схема БД](./v-block-a-database-schema.md) — 18 ч
2. ✅ [Блок B: Граф зависимостей](./v-block-b-dependency-graph.md) — 12 ч
3. ✅ [Блок C: Материализация](./v-block-c-materialization.md) — 40 ч
4. ✅ [Блок D: Каскадные события](./block-d-cascade-events.md) — 32 ч
5. ✅ [Блок F+G: Entry и индексация](./block-fg-entry-indexing.md) — 46 ч
6. ✅ [Блок H: BlueprintStructureService](./block-h-structure-service.md) — 48 ч
7. ✅ [Блок I: API контроллеры](./block-i-api-controllers.md) — 34 ч

**Could Have (88 часов):**
8. ✅ [Блок J: Комплексное тестирование](./block-j-testing.md) — 88 ч

**Опционально (Could Have+):**
- Блок K-M: Оптимизация и мониторинг (92 ч)

## Исходные документы

- `document_path1.md` — полная спецификация (6566 строк)
- `document_path1_task_breakdown_plan.md` — план разбиения (1596 строк)

---

## Лицензия

stupidCMS — документная система с path-индексацией  
© 2025, интеграция через PostType → Blueprint → Entry

