# Документная система с path-индексацией (v3: интеграция с stupidCMS через PostType)

> **🎯 Интеграция с stupidCMS:**
>
> Документная система интегрируется в stupidCMS через существующую архитектуру **PostType → Entry**.  
> Blueprint крепится к PostType через `post_types.blueprint_id` (nullable), Entry наследует blueprint через связь: `$entry->postType->blueprint`.  
> **Гибридный режим:** Entry может работать с blueprint или без него (обратная совместимость).
>
> Подробности в разделе **0. Интеграция с stupidCMS**.

> **⚠️ Критические моменты реализации:**
>
> 1. **Рекурсивная материализация:** встраивание `B → A` должно развернуть не только поля `A`, но и все транзитивные embed'ы (если `A → C`, то и поля `C`). Без рекурсии индексация по глубоким путям не работает.
> 2. **PRE-CHECK конфликтов:** проверка конфликтов `full_path` должна быть ДО вставки (не после), иначе БД выбросит SQL-ошибку вместо доменного исключения.
> 3. **Каскадные события:** изменение blueprint'а должно триггерить цепочку событий для транзитивной рематериализации всех зависимых. Без каскада обновится только один уровень вверх.
> 4. **Защита полей:** `source_blueprint_id`, `blueprint_embed_id`, `is_readonly`, **`full_path`** должны быть в `$guarded`, не в `$fillable`.
> 5. **UNIQUE constraint:** копии paths нужно сохранять ТОЛЬКО после вычисления `full_path` (не `''` или `NULL`).
> 6. **Взаимные FK:** требуют **5 миграций** в строгой последовательности (`blueprints` → `paths` → `blueprint_embeds` → FK `paths.blueprint_embed_id` → `post_types.blueprint_id`).
> 7. **Требования к БД:** MySQL 8.0.16+ или MariaDB 10.2.1+ для CHECK constraints (или триггеры для старых версий).
>
> Подробности в разделе **8.0**.

---

## Оглавление

**Часть 0. Интеграция с stupidCMS**

0. [Интеграция с stupidCMS](#0-интеграция-с-stupidcms) — PostType → Blueprint → Entry, гибридный режим

**Часть I. Архитектура и БД**

1. [Основные сущности](#1-основные-сущности) — Blueprint, Path, Entry, DocValue, DocRef
2. [Схема БД](#2-схема-бд) — таблицы, индексы, FK, CHECK constraints
3. [Встраивание blueprint-ов](#3-встраивание-blueprint-ов-и-запрет-циклических-зависимостей) — множественное, многоуровневое, рекурсивное
4. [Материализация полей](#4-материализация-полей-при-встраивании) — алгоритм, PRE-CHECK конфликтов
5. [Обработка изменений](#5-обработка-изменений-в-исходном-шаблоне) — каскадные события, транзитивность

**Часть II. Laravel-реализация**

6. [Поведение при редактировании](#6-поведение-при-редактировании-целевого-шаблона-host-blueprint) — разрешённые/запрещённые операции
7. [Модели и связи](#7-laravel-уровень-модели-и-связи) — Blueprint, Path, Entry, DocValue, DocRef, HasDocumentData
8. [Edge-cases](#8-edge-cases-и-важные-детали) — критические моменты, конфликты, защита полей

**Часть III. Оптимизация и масштабирование**

9. [Closure Table (опционально)](#9-оптимизация-closure-table-для-зависимостей-опционально) — для больших графов зависимостей

**Часть IV. Практика**

10. [Итоговые команды](#11-команды-для-реализации) — миграции, модели, сервисы, фабрики, сидеры
11. [Тестирование](#12-тестирование) — unit, feature, integration, performance
12. [Чек-лист реализации](#13-приоритетный-чек-лист-реализации) — что внедрять в первую очередь
13. [Мониторинг и API](#132-rest-api-и-scribe-документация) — производительность, REST API, Scribe
14. [Итог](#14-итог) — сводка по архитектуре, материализации, производительности

---

## 0. Интеграция с stupidCMS

### 0.1. Архитектура интеграции

Документная система с path-индексацией интегрируется в **существующую архитектуру stupidCMS** через модель `PostType`.

**Существующая архитектура stupidCMS:**

```
PostType (id, slug, name, options_json)
    ↓ post_type_id (NOT NULL)
Entry (id, post_type_id, title, slug, data_json, status, ...)
```

**Новая архитектура с Blueprint:**

```
PostType (id, slug, name, options_json, blueprint_id)
    ↓ blueprint_id (NULLABLE)
Blueprint (id, name, code, description)
    ↓ 1:n
Path (blueprint_id, full_path, data_type, cardinality, ...)
    ↓
Entry (id, post_type_id, ...)  → наследует blueprint через postType
    ↓ индексация (если postType.blueprint_id NOT NULL)
DocValue, DocRef (entry_id, path_id, value_*)
```

### 0.2. Ключевые решения

#### 1. Blueprint крепится к PostType (не к Entry напрямую)

```php
// PostType
class PostType extends Model {
    protected $fillable = ['slug', 'name', 'options_json', 'blueprint_id'];

    public function blueprint() {
        return $this->belongsTo(Blueprint::class);
    }
}

// Entry получает blueprint через PostType
class Entry extends Model {
    public function blueprint(): ?Blueprint {
        return $this->postType?->blueprint;
    }
}
```

**Преимущества:**

-   ✅ Централизованное управление: все Entry одного типа используют один blueprint
-   ✅ Минимум изменений в существующей базе данных
-   ✅ Обратная совместимость: существующие Entry продолжают работать
-   ✅ Простота миграции: можно подключать blueprint постепенно, по типам контента

#### 2. Гибридный режим работы

**PostType с blueprint:**

```php
$postType = PostType::create([
    'slug' => 'article',
    'name' => 'Статьи',
    'blueprint_id' => $articleBlueprint->id,  // Привязан к blueprint
]);

// Entry этого типа будут индексироваться по paths из blueprint
$entry = Entry::create([
    'post_type_id' => $postType->id,
    'title' => 'Моя статья',
    'data_json' => [
        'author' => ['name' => 'John', 'email' => 'john@example.com'],
        'content' => '...',
    ],
]);
// → автоматическая индексация в doc_values/doc_refs
```

**PostType без blueprint (legacy):**

```php
$postType = PostType::create([
    'slug' => 'news',
    'name' => 'Новости',
    'blueprint_id' => null,  // БЕЗ blueprint (как раньше)
]);

// Entry этого типа работают в обычном режиме
$entry = Entry::create([
    'post_type_id' => $postType->id,
    'title' => 'Новость',
    'data_json' => ['arbitrary' => 'data'],  // произвольная структура
]);
// → индексация НЕ выполняется, data_json остается как есть
```

#### 3. Таблица `entries` остается без изменений

**Существующая структура:**

```sql
CREATE TABLE entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_type_id BIGINT UNSIGNED NOT NULL,  -- FK к post_types
    title VARCHAR(500),
    slug VARCHAR(500),
    status ENUM('draft', 'published'),
    published_at TIMESTAMP NULL,
    author_id BIGINT UNSIGNED NULL,
    data_json JSON NOT NULL,                -- структурированные данные
    seo_json JSON NULL,
    template_override VARCHAR(255) NULL,
    version INT UNSIGNED DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,              -- SoftDeletes

    CONSTRAINT fk_entries_post_type
        FOREIGN KEY (post_type_id) REFERENCES post_types(id) ON DELETE RESTRICT
);
```

**БЕЗ добавления `blueprint_id`** — blueprint наследуется через PostType.

### 0.3. Миграция `post_types.blueprint_id`

**Новая миграция:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint as SchemaBlueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('post_types', function (SchemaBlueprint $table) {
            $table->foreignId('blueprint_id')
                ->nullable()
                ->after('options_json')
                ->constrained('blueprints')
                ->restrictOnDelete();

            $table->index('blueprint_id');
        });
    }

    public function down(): void
    {
        Schema::table('post_types', function (SchemaBlueprint $table) {
            $table->dropForeign(['blueprint_id']);
            $table->dropColumn('blueprint_id');
        });
    }
};
```

**Порядок миграций:**

1. `create_blueprints_table`
2. `create_paths_table` (БЕЗ FK `blueprint_embed_id`)
3. `create_blueprint_embeds_table`
4. `add_blueprint_embed_fk_to_paths`
5. **`add_blueprint_id_to_post_types`** ← новая

### 0.4. Индексация Entry через PostType

**EntryIndexer (обновленный):**

```php
class EntryIndexer
{
    public function index(Entry $entry): void
    {
        // Получаем blueprint через PostType
        $blueprint = $entry->postType?->blueprint;

        // Если blueprint не назначен, индексация не выполняется
        if (!$blueprint) {
            return;
        }

        // Очистка старых индексов
        DocValue::where('entry_id', $entry->id)->delete();
        DocRef::where('entry_id', $entry->id)->delete();

        // Извлечение значений из data_json по paths blueprint'а
        foreach ($blueprint->paths()->where('is_indexed', true)->get() as $path) {
            $value = data_get($entry->data_json, $path->full_path);

            if ($value === null) {
                continue;
            }

            // Обработка cardinality='many'
            if ($path->cardinality === 'many' && is_array($value)) {
                foreach ($value as $index => $item) {
                    $this->indexValue($entry, $path, $item, $index);
                }
            } else {
                $this->indexValue($entry, $path, $value, null);
            }
        }
    }

    private function indexValue(Entry $entry, Path $path, mixed $value, ?int $arrayIndex): void
    {
        if ($path->data_type === 'ref') {
            DocRef::create([
                'entry_id' => $entry->id,
                'path_id' => $path->id,
                'array_index' => $arrayIndex,
                'target_entry_id' => $value,
            ]);
        } else {
            DocValue::create([
                'entry_id' => $entry->id,
                'path_id' => $path->id,
                'array_index' => $arrayIndex,
                "value_{$path->data_type}" => $value,
            ]);
        }
    }
}
```

### 0.5. Observer для автоматической индексации

**EntryObserver (обновленный):**

```php
class EntryObserver
{
    public function __construct(
        private EntryIndexer $indexer
    ) {}

    public function saved(Entry $entry): void
    {
        // Индексация только если PostType имеет blueprint
        if ($entry->postType?->blueprint_id) {
            $this->indexer->index($entry);
        }
    }

    public function deleted(Entry $entry): void
    {
        // Очистка индексов (если были)
        DocValue::where('entry_id', $entry->id)->delete();
        DocRef::where('entry_id', $entry->id)->delete();
    }
}
```

### 0.6. Смена blueprint у PostType

**Сценарий:** PostType меняет blueprint (или удаляет его).

```php
// Было
$postType->blueprint_id = $oldBlueprint->id;

// Стало
$postType->blueprint_id = $newBlueprint->id;
$postType->save();

// → Требуется реиндексация ВСЕХ Entry этого PostType
```

**Решение: Job для реиндексации**

```php
class ReindexPostTypeEntries implements ShouldQueue
{
    public function __construct(
        public int $postTypeId
    ) {}

    public function handle(EntryIndexer $indexer): void
    {
        Entry::where('post_type_id', $this->postTypeId)
            ->chunk(100, function ($entries) use ($indexer) {
                foreach ($entries as $entry) {
                    $indexer->index($entry);
                }
            });
    }
}
```

**Использование:**

```php
// PostTypeController
public function update(Request $request, PostType $postType)
{
    $oldBlueprintId = $postType->blueprint_id;
    $newBlueprintId = $request->input('blueprint_id');

    if ($oldBlueprintId !== $newBlueprintId) {
        $postType->update(['blueprint_id' => $newBlueprintId]);

        // Асинхронная реиндексация всех Entry
        ReindexPostTypeEntries::dispatch($postType->id);
    }

    return new PostTypeResource($postType);
}
```

### 0.7. Edge Cases

#### 1. Entry без blueprint (legacy)

```php
$entry = Entry::create([
    'post_type_id' => $legacyPostType->id,  // blueprint_id = NULL
    'data_json' => ['any' => 'structure'],
]);

// ✅ Индексация пропускается
// ✅ wherePath() не работает для таких Entry
// ✅ data_json остается произвольным
```

#### 2. Удаление blueprint у PostType

```php
$postType->blueprint_id = null;
$postType->save();

// ✅ FK ON DELETE RESTRICT защищает от удаления используемого blueprint
// ✅ Нужно сначала отвязать blueprint от PostType
// ✅ Entry остаются, но индексация больше не выполняется
```

#### 3. Запрос Entry с/без blueprint

```php
// Только Entry с blueprint
$entriesWithBlueprint = Entry::query()
    ->whereHas('postType', fn($q) => $q->whereNotNull('blueprint_id'))
    ->get();

// Только legacy Entry (без blueprint)
$legacyEntries = Entry::query()
    ->whereHas('postType', fn($q) => $q->whereNull('blueprint_id'))
    ->get();
```

### 0.8. Преимущества интеграции через PostType

| Аспект                     | Решение                                                         |
| -------------------------- | --------------------------------------------------------------- |
| **Обратная совместимость** | ✅ Существующие Entry продолжают работать без изменений         |
| **Минимум миграций**       | ✅ Только 1 дополнительная миграция (`post_types.blueprint_id`) |
| **Изменения в коде**       | ✅ Минимальные (trait + Observer для Entry)                     |
| **Гибкость**               | ✅ Можно подключать blueprint постепенно, по типам контента     |
| **Централизация**          | ✅ Все Entry одного типа используют единую структуру            |
| **Производительность**     | ✅ Индексация только для Entry с blueprint                      |
| **Простота API**           | ✅ `$entry->postType->blueprint` — понятная семантика           |

---

## 1. Основные сущности

### 1.2. Обработка cardinality 'many'

Для полей с cardinality = 'many' (массивы), full_path в paths остается статичным (шаблонным, e.g., 'author.contacts.phone').
Реальные индексы массивов (e.g., 'author.contacts[0].phone') обрабатываются на уровне индексации в doc_values/doc_refs
в runtime, с добавлением столбца array_index в doc_values для хранения позиции в массиве.
Добавьте миграцию для array_index INT NULL в doc_values и doc_refs.

-   `blueprints` — шаблоны (структуры данных для Entry).
-   `paths` — поля/пути внутри blueprint с материализованным `full_path`.
-   `blueprint_embeds` — связи «какой blueprint встроен в какой и под каким полем».
-   `entries` — документы, которые подчиняются конкретному blueprint.
-   `doc_values` — индекс скалярных значений по path'ам.
-   `doc_refs` — индекс ссылочных значений (ref -> другой Entry).

Все остальные сущности (например, типы данных, карточность, валидаторы) остаются такими же, как в предыдущем решении и завязаны на `paths`.

### 1.1. Ключевые возможности встраивания

-   **Множественное встраивание:** один и тот же blueprint A можно встроить в blueprint B **несколько раз** под разными полями.
    -   Пример: blueprint `Address` можно встроить в `Company` дважды — как `office_address` и как `legal_address`.
-   **Многоуровневое встраивание:** встраивание возможно **на любом уровне** структуры, не только в корень.
    -   Пример: blueprint `Person` содержит поле `contacts` (группа), внутри которой встроен blueprint `ContactInfo`.
-   **Транзитивные зависимости (рекурсивная материализация):**
    -   Если `A` встроен в `B`, а внутри `A` есть встраивание `A → C`, то при материализации `B → A` автоматически разворачиваются и поля `C`.
    -   Пример: `D → C → A → B` — при встраивании `A` в `B` все поля из `C` и `D` материализуются в `B` с правильными путями (`B.group_a.group_c.group_d.field_d1`).
    -   Изменение структуры любого шаблона в цепочке автоматически распространяется на все зависимые blueprint'ы транзитивно.
    -   **Защита от циклов:** рекурсия безопасна благодаря проверке на этапе создания embed'а.

---

## 2. Схема БД

-   Добавьте SoftDeletes trait к моделям Blueprint и Path для предотвращения каскадных удалений в production.

### 2.0. Требования к СУБД

**Минимальные версии:**

-   **MySQL:** 8.0.16+ (для корректной работы CHECK constraints)
-   **MariaDB:** 10.2.1+ (для корректной работы CHECK constraints)
-   **PostgreSQL:** 9.3+ (CHECK constraints поддерживаются)

**Альтернатива для старых версий MySQL/MariaDB:**

Если требуется поддержка MySQL < 8.0.16, необходимо:

1. Удалить CHECK constraints из миграций.
2. Создать триггеры для валидации инвариантов:

```sql
DELIMITER $$

CREATE TRIGGER paths_readonly_check_insert
BEFORE INSERT ON paths
FOR EACH ROW
BEGIN
    IF (NEW.source_blueprint_id IS NOT NULL
        AND (NEW.blueprint_embed_id IS NULL OR NEW.is_readonly != 1))
    THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Скопированное поле должно иметь blueprint_embed_id и is_readonly = 1';
    END IF;
END$$

CREATE TRIGGER paths_readonly_check_update
BEFORE UPDATE ON paths
FOR EACH ROW
BEGIN
    IF (NEW.source_blueprint_id IS NOT NULL
        AND (NEW.blueprint_embed_id IS NULL OR NEW.is_readonly != 1))
    THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Скопированное поле должно иметь blueprint_embed_id и is_readonly = 1';
    END IF;
END$$

DELIMITER ;
```

3. Продублировать валидацию в доменном слое (`BlueprintStructureService`).

### 2.1. Таблица `blueprints`

Без разделения на full/component:

```sql
CREATE TABLE blueprints (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### 2.2. Таблица `paths`

`paths` описывает структуру полей отдельных `Blueprint`-ов.  
Добавляем поля: `source_blueprint_id`, `is_readonly` и **`blueprint_embed_id`** для привязки копий к конкретному встраиванию.

```sql
CREATE TABLE paths (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blueprint_id BIGINT UNSIGNED NOT NULL,          -- владелец поля (куда оно "принадлежит")
    source_blueprint_id BIGINT UNSIGNED NULL,       -- откуда поле скопировано (если встраивание)
    blueprint_embed_id BIGINT UNSIGNED NULL,        -- к какому embed привязано (если это копия)
    parent_id BIGINT UNSIGNED NULL,                 -- parent path в том же blueprint
    name VARCHAR(255) NOT NULL,                     -- локальное имя поля
    full_path VARCHAR(2048) NOT NULL,               -- материализованный путь в рамках blueprint
    data_type ENUM('string','text','int','float','bool','date','datetime','json','ref') NOT NULL,
    cardinality ENUM('one','many') NOT NULL DEFAULT 'one',
    is_required BOOLEAN NOT NULL DEFAULT FALSE,
    is_indexed BOOLEAN NOT NULL DEFAULT FALSE,
    is_readonly BOOLEAN NOT NULL DEFAULT FALSE,     -- нельзя редактировать, если true
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_paths_blueprint FOREIGN KEY (blueprint_id)
        REFERENCES blueprints(id) ON DELETE CASCADE,

    CONSTRAINT fk_paths_source_blueprint FOREIGN KEY (source_blueprint_id)
        REFERENCES blueprints(id) ON DELETE RESTRICT,

    CONSTRAINT fk_paths_blueprint_embed FOREIGN KEY (blueprint_embed_id)
        REFERENCES blueprint_embeds(id) ON DELETE CASCADE,

    CONSTRAINT fk_paths_parent FOREIGN KEY (parent_id)
        REFERENCES paths(id) ON DELETE CASCADE,

    CONSTRAINT uq_paths_full_path_per_blueprint
        UNIQUE (blueprint_id, full_path),

    -- Инвариант: скопированные поля всегда readonly
    -- ВАЖНО: CHECK constraints работают только в MySQL 8.0.16+, MariaDB 10.2.1+
    -- Для старых версий продублировать триггером или валидацией в коде
    CONSTRAINT chk_paths_readonly_consistency
        CHECK (
            (source_blueprint_id IS NULL AND blueprint_embed_id IS NULL)
            OR (source_blueprint_id IS NOT NULL AND blueprint_embed_id IS NOT NULL AND is_readonly = 1)
        ),

    -- Индексы под реальные запросы
    INDEX idx_paths_blueprint (blueprint_id),
    INDEX idx_paths_source_blueprint (source_blueprint_id),
    INDEX idx_paths_blueprint_parent (blueprint_id, parent_id, sort_order),
    INDEX idx_paths_embed (blueprint_embed_id)
);
```

Семантика:

-   `blueprint_embed_id IS NULL` — поле определено **непосредственно в blueprint**, можно редактировать.
-   `blueprint_embed_id = E.id` — поле **материализовано в рамках конкретного BlueprintEmbed E**:
    -   `source_blueprint_id` автоматически равен `E.embedded_blueprint_id`,
    -   `is_readonly = 1` (UI запрещает менять свойства поля),
    -   удаление всех копий — просто `WHERE blueprint_embed_id = E.id`.

### 2.3. Таблица `blueprint_embeds`

Связь «B встраивает A под конкретным полем/группой `host_path`».

```sql
CREATE TABLE blueprint_embeds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    blueprint_id BIGINT UNSIGNED NOT NULL,          -- кто встраивает (B)
    embedded_blueprint_id BIGINT UNSIGNED NOT NULL, -- кого встраиваем (A)
    host_path_id BIGINT UNSIGNED NULL,              -- под каким полем в B живёт A (может быть NULL для встраивания в корень)

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_embeds_blueprint FOREIGN KEY (blueprint_id)
        REFERENCES blueprints(id) ON DELETE CASCADE,

    CONSTRAINT fk_embeds_embedded FOREIGN KEY (embedded_blueprint_id)
        REFERENCES blueprints(id) ON DELETE RESTRICT,

    CONSTRAINT fk_embeds_host_path FOREIGN KEY (host_path_id)
        REFERENCES paths(id) ON DELETE CASCADE,

    CONSTRAINT uq_blueprint_embed UNIQUE (blueprint_id, embedded_blueprint_id, host_path_id),

    -- Индексы под обход графа зависимостей
    INDEX idx_embeds_embedded (embedded_blueprint_id),
    INDEX idx_embeds_blueprint (blueprint_id)
);
```

**Валидация на уровне приложения:**

При создании/обновлении `BlueprintEmbed` проверять:

-   `host_path_id IS NULL` или `host_path.blueprint_id = blueprint_id`;
-   `host_path` имеет подходящий тип (`data_type = 'json'` или аналог, если есть концепция «группы/контейнера»).

Примеры:

#### Пример 1: Встраивание под конкретную группу

В B есть поле-группа `author` (Path `host_path` с `full_path = 'author'`).  
 В `blueprint_embeds` создаётся запись:

    -   `blueprint_id = B.id`,

-   `embedded_blueprint_id = A.id` (например, blueprint `Person`),
-   `host_path_id = path(author) в B`.

После материализации в B появятся поля:

-   `author.name` (скопировано из `Person.name`)
-   `author.email` (скопировано из `Person.email`)

#### Пример 2: Множественное встраивание одного blueprint'а

Blueprint `Address` имеет поля:

-   `street`
-   `city`
-   `zip_code`

Blueprint `Company` имеет:

-   `name` (собственное поле)
-   `office_address` (группа)
-   `legal_address` (группа)

Создаём **два** embed'а:

1. `{blueprint_id: Company, embedded_blueprint_id: Address, host_path_id: path(office_address)}`
2. `{blueprint_id: Company, embedded_blueprint_id: Address, host_path_id: path(legal_address)}`

После материализации в `Company` будет:

-   `name`
-   `office_address.street` (из Address, `blueprint_embed_id = embed1`)
-   `office_address.city` (из Address, `blueprint_embed_id = embed1`)
-   `office_address.zip_code` (из Address, `blueprint_embed_id = embed1`)
-   `legal_address.street` (из Address, `blueprint_embed_id = embed2`)
-   `legal_address.city` (из Address, `blueprint_embed_id = embed2`)
-   `legal_address.zip_code` (из Address, `blueprint_embed_id = embed2`)

Constraint `UNIQUE (blueprint_id, embedded_blueprint_id, host_path_id)` гарантирует, что под одним `host_path` можно встроить один blueprint только один раз, но под разными `host_path` — сколько угодно.

#### Пример 3: Встраивание в корень

Если нужно встраивать A в корень B (без отдельной группы), `host_path_id = NULL`, а при материализации скопированным path'ам родителем становится `NULL`.

Пример: встроить `Metadata` (поля `created_by`, `updated_by`) в корень `Article`:

-   `{blueprint_id: Article, embedded_blueprint_id: Metadata, host_path_id: NULL}`

После материализации в `Article`:

-   `title` (собственное)
-   `content` (собственное)
-   `created_by` (из Metadata, родитель = NULL)
-   `updated_by` (из Metadata, родитель = NULL)

#### Пример 4: Многоуровневое встраивание

Blueprint `ContactInfo`:

-   `phone`
-   `email`

Blueprint `Article`:

-   `title`
-   `content`
-   `author` (группа)
    -   `name`
    -   `bio`
    -   `contacts` (группа, вложена в `author`)

Встраиваем `ContactInfo` **внутрь группы `author.contacts`**:

-   `host_path_id = path('author.contacts')` (поле с `full_path = 'author.contacts'` и `parent_id = path('author')`)

После материализации в `Article`:

```
Article.title                          (собственное)
Article.content                        (собственное)
Article.author                         (собственное, группа)
Article.author.name                    (собственное)
Article.author.bio                     (собственное)
Article.author.contacts                (собственное, группа)
Article.author.contacts.phone          (копия, из ContactInfo)
Article.author.contacts.email          (копия, из ContactInfo)
```

Это демонстрирует, что `host_path` может находиться **на любом уровне вложенности**, а не только в корне или на первом уровне.

### 2.4. Порядок создания таблиц (взаимные FK)

**Проблема:** между `paths` и `blueprint_embeds` есть взаимные FK:

-   `paths.blueprint_embed_id` → `blueprint_embeds.id`
-   `blueprint_embeds.host_path_id` → `paths.id`

**Решение:** создавать таблицы и FK в правильном порядке:

```php
// Миграция 1: создать blueprints
Schema::create('blueprints', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code')->unique();
    $table->text('description')->nullable();
    $table->timestamps();
});

// Миграция 2: создать paths БЕЗ blueprint_embed_id FK
Schema::create('paths', function (Blueprint $table) {
    $table->id();
    $table->foreignId('blueprint_id')->constrained()->cascadeOnDelete();
    $table->foreignId('source_blueprint_id')->nullable()
        ->constrained('blueprints')->restrictOnDelete();
    $table->unsignedBigInteger('blueprint_embed_id')->nullable(); // пока без FK
    $table->foreignId('parent_id')->nullable()
        ->constrained('paths')->cascadeOnDelete();

    $table->string('name');
    $table->string('full_path', 2048);
    $table->enum('data_type', ['string','text','int','float','bool','date','datetime','json','ref']);
    $table->enum('cardinality', ['one','many'])->default('one');
    $table->boolean('is_required')->default(false);
    $table->boolean('is_indexed')->default(false);
    $table->boolean('is_readonly')->default(false);
    $table->integer('sort_order')->default(0);
    $table->timestamps();

    $table->unique(['blueprint_id', 'full_path']);

    // CHECK constraint для инварианта readonly
    DB::statement('ALTER TABLE paths ADD CONSTRAINT chk_paths_readonly_consistency CHECK (
        (source_blueprint_id IS NULL AND blueprint_embed_id IS NULL)
        OR (source_blueprint_id IS NOT NULL AND blueprint_embed_id IS NOT NULL AND is_readonly = 1)
    )');

    // Индексы
    $table->index('blueprint_id');
    $table->index('source_blueprint_id');
    $table->index(['blueprint_id', 'parent_id', 'sort_order']);
});

// Миграция 3: создать blueprint_embeds
Schema::create('blueprint_embeds', function (Blueprint $table) {
    $table->id();
    $table->foreignId('blueprint_id')->constrained()->cascadeOnDelete();
    $table->foreignId('embedded_blueprint_id')
        ->constrained('blueprints')->restrictOnDelete();
    $table->foreignId('host_path_id')->nullable()
        ->constrained('paths')->cascadeOnDelete();
    $table->timestamps();

    $table->unique(['blueprint_id', 'embedded_blueprint_id', 'host_path_id']);
    $table->index('embedded_blueprint_id');
    $table->index('blueprint_id');
});

// Миграция 4: добавить FK для paths.blueprint_embed_id
Schema::table('paths', function (Blueprint $table) {
    $table->foreign('blueprint_embed_id')
        ->references('id')
        ->on('blueprint_embeds')
        ->cascadeOnDelete();

    $table->index('blueprint_embed_id');
});
```

Итого: **4 миграции** в строгой последовательности.

### 2.5. `entries`, `doc_values`, `doc_refs`

#### 2.5.1. Таблица `entries` (интеграция с stupidCMS)

> **⚠️ ВАЖНО:** Используется **существующая таблица** `entries` из stupidCMS.  
> Blueprint наследуется через `PostType`, **БЕЗ прямой связи** `entries.blueprint_id`.

**Существующая структура stupidCMS:**

```sql
CREATE TABLE entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_type_id BIGINT UNSIGNED NOT NULL,  -- FK к post_types (NOT NULL)

    -- Базовые поля (НЕ индексируются через paths)
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(500) NOT NULL,
    status ENUM('draft', 'published') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    author_id BIGINT UNSIGNED NULL,

    -- Динамические данные (индексируются через paths, если есть blueprint)
    data_json JSON NOT NULL,
    seo_json JSON NULL,

    -- Дополнительные поля stupidCMS
    template_override VARCHAR(255) NULL,
    version INT UNSIGNED DEFAULT 1,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,  -- SoftDeletes

    CONSTRAINT fk_entries_post_type
        FOREIGN KEY (post_type_id)
        REFERENCES post_types(id) ON DELETE RESTRICT,

    CONSTRAINT fk_entries_author
        FOREIGN KEY (author_id)
        REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_post_type (post_type_id),
    INDEX idx_status (status),
    INDEX idx_published (published_at),
    INDEX idx_slug (slug),
    INDEX idx_author (author_id)
) ENGINE=InnoDB;
```

**Связь с Blueprint:**

```php
// Entry получает blueprint через PostType
$entry->postType->blueprint;  // может быть NULL

// PostType может иметь или не иметь blueprint
$postType->blueprint_id;  // nullable
```

**Разделение ответственности:**

-   **Entry-колонки** (`title`, `slug`, `status`, `published_at`, `author_id`) — базовые поля, доступные напрямую через Eloquent, НЕ требуют Path.
-   **`data_json`** — динамические поля:
    -   **Если `postType.blueprint_id` NOT NULL:** структурированы по `paths`, индексируются в `doc_values`/`doc_refs`
    -   **Если `postType.blueprint_id` IS NULL:** произвольная структура, индексация не выполняется (legacy режим)

**Версионирование структуры (опционально):**

Для отслеживания устаревших Entry при изменении blueprint можно добавить поле:

```sql
ALTER TABLE entries ADD COLUMN indexed_structure_version INT UNSIGNED NULL;
```

Но это **не обязательно** для базовой функциональности.

#### 2.5.2. Таблица `doc_values`

Индекс скалярных значений из `data_json` по путям.

**Зачем отдельная таблица:** MySQL не позволяет эффективно индексировать произвольные JSON-пути. `doc_values` материализует значения в реляционном виде для быстрых запросов.

```sql
CREATE TABLE doc_values (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id BIGINT UNSIGNED NOT NULL,
    path_id BIGINT UNSIGNED NOT NULL,

    -- Индекс массива (0 для cardinality=one, 1+ для many)
    array_index INT UNSIGNED NOT NULL DEFAULT 0,

    -- Значения разных типов (только одно заполнено на строку)
    value_string VARCHAR(2048) NULL,
    value_int BIGINT NULL,
    value_float DOUBLE NULL,
    value_bool BOOLEAN NULL,
    value_date DATE NULL,
    value_datetime DATETIME NULL,
    value_text TEXT NULL, -- для больших строк
    value_json JSON NULL, -- для вложенных объектов

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_doc_values_entry FOREIGN KEY (entry_id)
        REFERENCES entries(id) ON DELETE CASCADE,

    CONSTRAINT fk_doc_values_path FOREIGN KEY (path_id)
        REFERENCES paths(id) ON DELETE CASCADE,

    -- Уникальность: одна запись на (entry, path, array_index)
    UNIQUE KEY uq_entry_path_idx (entry_id, path_id, array_index),

    -- Индексы для быстрых запросов
    INDEX idx_doc_values_path (path_id),
    INDEX idx_doc_values_string (path_id, value_string(255)),
    INDEX idx_doc_values_int (path_id, value_int),
    INDEX idx_doc_values_float (path_id, value_float),
    INDEX idx_doc_values_bool (path_id, value_bool),
    INDEX idx_doc_values_date (path_id, value_date),
    INDEX idx_doc_values_datetime (path_id, value_datetime)
) ENGINE=InnoDB;
```

**Ключевые моменты:**

1. **`array_index`** — позиция элемента в массиве:
    - `0` для полей с `cardinality = 'one'`
    - `1, 2, 3...` для `cardinality = 'many'` (1-based индексация)
2. **Разные `value_*` колонки** — одна запись хранит значение только в одной колонке, в зависимости от `path.data_type`.
3. **Составной UNIQUE** — гарантирует, что для каждого элемента массива есть только одна запись.

#### 2.5.3. Таблица `doc_refs`

Индекс ссылок между Entry (ref-поля).

```sql
CREATE TABLE doc_refs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id BIGINT UNSIGNED NOT NULL,
    path_id BIGINT UNSIGNED NOT NULL,

    -- Индекс массива (0 для one, 1+ для many)
    array_index INT UNSIGNED NOT NULL DEFAULT 0,

    target_entry_id BIGINT UNSIGNED NOT NULL,

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_doc_refs_entry FOREIGN KEY (entry_id)
        REFERENCES entries(id) ON DELETE CASCADE,

    CONSTRAINT fk_doc_refs_path FOREIGN KEY (path_id)
        REFERENCES paths(id) ON DELETE CASCADE,

    CONSTRAINT fk_doc_refs_target_entry FOREIGN KEY (target_entry_id)
        REFERENCES entries(id) ON DELETE RESTRICT,

    UNIQUE KEY uq_entry_path_idx (entry_id, path_id, array_index),

    INDEX idx_doc_refs_path (path_id),
    INDEX idx_doc_refs_target (target_entry_id),
    INDEX idx_doc_refs_path_target (path_id, target_entry_id)
) ENGINE=InnoDB;
```

**ON DELETE поведение:**

-   `entry_id` → CASCADE — при удалении Entry удаляются все его ссылки.
-   `target_entry_id` → RESTRICT — нельзя удалить Entry, на который ссылаются другие (защита целостности).
-   Альтернатива: `SET NULL` или `CASCADE` в зависимости от бизнес-логики.

#### 2.5.4. Пример данных (с интеграцией PostType)

**PostType с blueprint:**

```json
{
    "id": 5,
    "slug": "article",
    "name": "Статьи",
    "blueprint_id": 10,
    "options_json": {
        "taxonomies": [1, 2]
    }
}
```

**Entry:**

```json
{
    "id": 1,
    "post_type_id": 5,
    "title": "How to Build CMS",
    "slug": "how-to-build-cms",
    "status": "published",
    "data_json": {
        "content": "<p>Long article content...</p>",
        "excerpt": "Short description",
        "author": {
            "name": "John Doe",
            "bio": "Developer",
            "contacts": {
                "phone": "+1234567890",
                "email": "john@example.com"
            }
        },
        "tags": ["cms", "laravel", "php"],
        "relatedArticles": [42, 77, 91]
    }
}
```

**Связь:** `Entry.post_type_id = 5` → `PostType.blueprint_id = 10` → `Blueprint(id=10)`

````

**Paths для Blueprint 10:**

| id  | blueprint_id | full_path             | data_type | cardinality | is_indexed |
| --- | ------------ | --------------------- | --------- | ----------- | ---------- |
| 100 | 10           | content               | text      | one         | false      |
| 101 | 10           | excerpt               | string    | one         | true       |
| 102 | 10           | author.name           | string    | one         | true       |
| 103 | 10           | author.bio            | text      | one         | false      |
| 104 | 10           | author.contacts.phone | string    | one         | true       |
| 105 | 10           | author.contacts.email | string    | one         | true       |
| 106 | 10           | tags                  | string    | many        | true       |
| 107 | 10           | relatedArticles       | ref       | many        | true       |

**doc_values после индексации:**

| entry_id | path_id | array_index | value_string      | value_text |
| -------- | ------- | ----------- | ----------------- | ---------- |
| 1        | 100     | 0           | NULL              | <p>Long... |
| 1        | 101     | 0           | Short description | NULL       |
| 1        | 102     | 0           | John Doe          | NULL       |
| 1        | 104     | 0           | +1234567890       | NULL       |
| 1        | 105     | 0           | john@example.com  | NULL       |
| 1        | 106     | 1           | cms               | NULL       |
| 1        | 106     | 2           | laravel           | NULL       |
| 1        | 106     | 3           | php               | NULL       |

**doc_refs после индексации:**

| entry_id | path_id | array_index | target_entry_id |
| -------- | ------- | ----------- | --------------- |
| 1        | 107     | 1           | 42              |
| 1        | 107     | 2           | 77              |
| 1        | 107     | 3           | 91              |

---

## 3. Встраивание blueprint-ов и запрет циклических зависимостей

### 3.1. Граф зависимостей

Каждое встраивание создаёт ориентированное ребро графа:

-   `blueprint_id (B) -> embedded_blueprint_id (A)`

**Важно:**

-   Один blueprint A может быть встроен в blueprint B **многократно** — под разными `host_path_id`.
-   `host_path` может находиться **на любом уровне вложенности** в структуре B (не только в корне).
-   Граф зависимостей строится по **уникальным парам** `(blueprint_id, embedded_blueprint_id)`, независимо от того, сколько раз A встроен в B.

Запрет циклов означает:

-   нельзя встроить blueprint сам в себя (A в A);
-   нельзя создать цепочку A → B → C → A;
-   проверка выполняется **на уровне blueprint'ов**, а не отдельных embed'ов (если A уже встроен в B один раз, нельзя создать ещё одно встраивание, которое приведёт к циклу, но можно создать несколько встраиваний A в B под разными полями, если цикла нет).

### 3.2. Проверка перед созданием `blueprint_embeds`

При добавлении новой записи (B встраивает A под `host_path`) нужно:

1. Проверить, что `B.id != A.id` (нельзя встроить сам в себя).
2. Проверить, что **не существует уже пути `A -> ... -> B`** в графе встраиваний (запрет циклов).
3. Проверить, что комбинация `(B, A, host_path)` уникальна (UNIQUE constraint в БД).

**Важно:** проверка циклов выполняется на уровне **blueprint'ов**, а не отдельных embed'ов:

-   ✅ Разрешено: встроить A в B несколько раз под разными `host_path` (например, `office_address` и `legal_address`).
-   ❌ Запрещено: встроить A в B, если B уже встроен в A (прямо или транзитивно).

Примерная реализация (псевдокод):

```php
public function ensureNoCyclicDependency(Blueprint $parent, Blueprint $embedded): void
{
    if ($parent->id === $embedded->id) {
        throw new LogicException('Нельзя встроить blueprint сам в себя');
    }

    if ($this->hasPathTo($embedded->id, $parent->id)) {
        throw new LogicException(
            "Циклическая зависимость: {$embedded->code} уже зависит от {$parent->code}"
        );
    }
}

protected function hasPathTo(int $fromId, int $targetId): bool
{
    $visited = [];
    $queue = [$fromId];  // Use queue for BFS

    while ($queue) {
        $current = array_shift($queue);
        if (isset($visited[$current])) {
            continue;
        }
        $visited[$current] = true;

        if ($current === $targetId) {
            return true;
        }

        // Все blueprint'ы, которые current встраивает
        $children = BlueprintEmbed::query()
            ->where('blueprint_id', $current)
            ->pluck('embedded_blueprint_id')
            ->unique()
            ->all();

        foreach ($children as $childId) {
            if (!isset($visited[$childId])) {
                $queue[] = $childId;
            }
        }
    }

    return false;
}
````

Вызываем это перед сохранением нового `BlueprintEmbed`.

**Пример валидации:**

-   ✅ Можно создать:
    -   `embed1: Company -> Address (host: office_address)`
    -   `embed2: Company -> Address (host: legal_address)`
-   ❌ Нельзя создать:
    -   `embed3: Address -> Company (host: company)` — создаст цикл `Company -> Address -> Company`

---

## 4. Материализация полей при встраивании

### 4.1. Цель материализации

Для каждого встраивания `B` ← `A` под полем `host_path` нужно:

-   **Рекурсивно** скопировать всю структуру `A` в `B`, включая:
    -   Собственные поля `A`,
    -   Все транзитивные встраивания (если `A` → `C`, то и поля `C` должны попасть в `B`).
-   Поставить у **всех** скопированных полей (из `A`, `C`, `D`, ...):
    -   `blueprint_id = B.id` — все поля физически принадлежат B,
    -   `blueprint_embed_id = embed(B→A).id` — все привязаны к одному корневому embed'у,
    -   `source_blueprint_id` — различается в зависимости от исходного шаблона:
        -   поля из `A` → `source_blueprint_id = A.id`,
        -   поля из `C` → `source_blueprint_id = C.id`,
    -   `is_readonly = 1`,
    -   пересчитать `parent_id` и `full_path` с учётом вложенности.
-   При повторной материализации (после изменения A или любого транзитивного шаблона) **все копии удаляются одной командой**:
    ```php
    Path::where('blueprint_embed_id', $embed->id)->delete();
    ```

**Пример транзитивного встраивания:**

```
Blueprint C:
  - fc1
  - fc2

Blueprint A:
  - fa1
  - groupCa (группа) ← встроен C

Blueprint B:
  - fb1
  - groupA (группа) ← встроен A
  - fb2
```

После материализации `B → A` в B должны появиться:

```
B.fb1                         (собственное)
B.groupA                      (собственное, группа)
B.groupA.fa1                  (из A, source_blueprint_id = A)
B.groupA.groupCa              (из A, source_blueprint_id = A, группа)
B.groupA.groupCa.fc1          (из C, source_blueprint_id = C, через транзитивность)
B.groupA.groupCa.fc2          (из C, source_blueprint_id = C, через транзитивность)
B.fb2                         (собственное)
```

Все поля `groupA.*` имеют `blueprint_embed_id = embed(B→A).id`, включая транзитивные из `C`.

### 4.2. Алгоритм рекурсивной материализации

Пусть есть запись `BlueprintEmbed embed`:

-   `embed.blueprint` — B,
-   `embed.embeddedBlueprint` — A,
-   `embed.hostPath` — поле-группа (или NULL для корня).

#### 4.2.1. Верхний уровень: `materializeEmbeddedBlueprint`

```php
/**
 * Материализует встраивание blueprint'а со всеми транзитивными зависимостями.
 */
public function materializeEmbeddedBlueprint(BlueprintEmbed $embed): void
{
    $hostBlueprint     = $embed->blueprint;          // B
    $embeddedBlueprint = $embed->embeddedBlueprint;  // A
    $hostPath          = $embed->hostPath;           // path в B или null

    DB::transaction(function () use ($embed, $hostBlueprint, $embeddedBlueprint, $hostPath) {
        $baseParentId   = $hostPath?->id;
        $baseParentPath = $hostPath?->full_path;

        // 1. PRE-CHECK: проверяем конфликты full_path ДО начала копирования
        $this->validateNoPathConflictsBeforeMaterialization(
            $embeddedBlueprint,
            $hostBlueprint,
            $baseParentPath
        );

        // 2. Удаляем все старые копии этого embed'а
        //    (включая транзитивные из C, D, ...)
        Path::where('blueprint_embed_id', $embed->id)->delete();

        // 3. Рекурсивно копируем структуру A (с транзитивными embed'ами)
        $this->copyBlueprintRecursive(
            blueprint:       $embeddedBlueprint, // X = A
            hostBlueprint:   $hostBlueprint,     // B
            baseParentId:    $baseParentId,
            baseParentPath:  $baseParentPath,
            rootEmbed:       $embed             // B → A (один и тот же на всю рекурсию)
        );
    });

    // ПРИМЕЧАНИЕ: событие BlueprintStructureChanged($hostBlueprint)
    // триггерится вызывающим кодом (listener или сервис), а не здесь,
    // чтобы избежать дублирования событий в цепочке рематериализации
}
```

#### 4.2.2. Рекурсивный копировщик: `copyBlueprintRecursive`

```php
/**
 * Рекурсивно копирует структуру blueprint'а (включая транзитивные embed'ы).
 *
 * @param Blueprint $blueprint       Исходный blueprint (X: A, C, D, ...)
 * @param Blueprint $hostBlueprint   Целевой blueprint (B)
 * @param int|null $baseParentId     ID родительского path'а в B (или null для корня)
 * @param string|null $baseParentPath full_path родителя в B
 * @param BlueprintEmbed $rootEmbed  Корневой embed B→A (для blueprint_embed_id)
 */
private function copyBlueprintRecursive(
    Blueprint $blueprint,
    Blueprint $hostBlueprint,
    ?int $baseParentId,
    ?string $baseParentPath,
    BlueprintEmbed $rootEmbed
): void {
    // 1. Берём только собственные поля blueprint (X)
    $sourcePaths = $blueprint->paths()
        ->whereNull('source_blueprint_id')
        ->orderByRaw('LENGTH(full_path), full_path') // родитель всегда раньше детей
        ->get();

    // 2. Карта соответствия: id исходного path X → id/full_path копии в B
    $idMap   = [];
    $pathMap = [];

    foreach ($sourcePaths as $source) {
        $copy = $source->replicate([
            'blueprint_id',
            'parent_id',
            'full_path',
            'source_blueprint_id',
            'blueprint_embed_id',
            'is_readonly',
        ]);

        // Служебные поля
        $copy->blueprint_id        = $hostBlueprint->id;  // B
        $copy->source_blueprint_id = $blueprint->id;      // X (A, C, D, ...)
        $copy->blueprint_embed_id  = $rootEmbed->id;      // B→A (всегда корневой!)
        $copy->is_readonly         = true;

        // Вычисляем родителя и full_path в B
        if ($source->parent_id === null) {
            // Верхнеуровневое поле X → привязываем к baseParent
            $parentId   = $baseParentId;
            $parentPath = $baseParentPath;
        } else {
            // Ребёнок уже скопированного path'а
            $parentId   = $idMap[$source->parent_id] ?? null;
            $parentPath = $pathMap[$source->parent_id] ?? null;
        }

        $copy->parent_id = $parentId;
        $copy->full_path = $parentPath
            ? $parentPath . '.' . $copy->name
            : $copy->name;

        // ВАЖНО: сохраняем только с корректным full_path (UNIQUE constraint)
        $copy->save();

        // Запоминаем соответствие
        $idMap[$source->id]   = $copy->id;
        $pathMap[$source->id] = $copy->full_path;
    }

    // 3. Рекурсивно разворачиваем embed'ы, объявленные внутри X
    $innerEmbeds = $blueprint->embeds; // hasMany BlueprintEmbed где blueprint_id = X.id

    foreach ($innerEmbeds as $innerEmbed) {
        /** @var BlueprintEmbed $innerEmbed */
        $innerHostPath = $innerEmbed->hostPath; // path в X (или null)

        if ($innerHostPath) {
            // Embed X→Y привязан к path'у P в X; ищем его копию в B
            $sourceHostId = $innerHostPath->id;

            if (!isset($idMap[$sourceHostId])) {
                // Теоретически не должно случиться
                throw new \LogicException(
                    "Не найдена копия host_path для embed {$innerEmbed->id}"
                );
            }

            $childBaseParentId   = $idMap[$sourceHostId];
            $childBaseParentPath = $pathMap[$sourceHostId];
        } else {
            // Embed в корень X → в B он попадает туда же, куда и корень X
            $childBaseParentId   = $baseParentId;
            $childBaseParentPath = $baseParentPath;
        }

        $childBlueprint = $innerEmbed->embeddedBlueprint; // Y

        // Рекурсивно копируем структуру Y в B под соответствующий хост-узел
        $this->copyBlueprintRecursive(
            blueprint:       $childBlueprint,
            hostBlueprint:   $hostBlueprint,
            baseParentId:    $childBaseParentId,
            baseParentPath:  $childBaseParentPath,
            rootEmbed:       $rootEmbed // ВСЁ ЕЩЁ B→A (не меняется!)
        );
    }
}

/**
 * PRE-CHECK: проверяет конфликты full_path ДО начала материализации.
 *
 * Вычисляет, какие пути появятся в hostBlueprint, и сверяет с существующими.
 *
 * @throws EmbeddedBlueprintPathConflictException
 */
protected function validateNoPathConflictsBeforeMaterialization(
    Blueprint $embeddedBlueprint,
    Blueprint $hostBlueprint,
    ?string $baseParentPath
): void {
    // 1. Собираем все пути, которые появятся (включая транзитивные)
    $futurePaths = $this->collectFuturePathsRecursive(
        $embeddedBlueprint,
        $baseParentPath
    );

    // 2. Проверяем, нет ли таких путей уже в hostBlueprint
    $existingPaths = Path::query()
        ->where('blueprint_id', $hostBlueprint->id)
        ->whereIn('full_path', $futurePaths)
        ->pluck('full_path')
        ->toArray();

    if (!empty($existingPaths)) {
        throw new EmbeddedBlueprintPathConflictException(
            "Невозможно встроить blueprint '{$embeddedBlueprint->code}' в '{$hostBlueprint->code}': " .
            "конфликт путей: " . implode(', ', $existingPaths)
        );
    }
}

/**
 * Рекурсивно собирает все full_path, которые появятся при материализации.
 *
 * @return array<string>
 */
private function collectFuturePathsRecursive(
    Blueprint $blueprint,
    ?string $baseParentPath
): array {
    $paths = [];

    // Собираем собственные поля
    $ownPaths = $blueprint->paths()
        ->whereNull('source_blueprint_id')
        ->get(['name', 'full_path', 'id']);

    foreach ($ownPaths as $path) {
        $futureFullPath = $baseParentPath
            ? $baseParentPath . '.' . $path->name
            : $path->name;

        $paths[] = $futureFullPath;
    }

    // Рекурсивно обходим внутренние embed'ы
    foreach ($blueprint->embeds as $innerEmbed) {
        $innerHostPath = $innerEmbed->hostPath;

        if ($innerHostPath) {
            // Вычисляем новый базовый путь для вложенного embed'а
            $newBasePath = $baseParentPath
                ? $baseParentPath . '.' . $innerHostPath->name
                : $innerHostPath->name;
        } else {
            // Embed в корень → базовый путь остаётся тем же
            $newBasePath = $baseParentPath;
        }

        $childPaths = $this->collectFuturePathsRecursive(
            $innerEmbed->embeddedBlueprint,
            $newBasePath
        );

        $paths = array_merge($paths, $childPaths);
    }

    return $paths;
}
```

### 4.3. Пример 1: Транзитивное встраивание (A → C → D)

**Blueprint D:**

```
D.field_d1
D.field_d2
```

**Blueprint C:**

```
C.field_c1
C.group_d (группа) ← встроен D
```

После материализации `C → D`:

```
C.field_c1                    (собственное)
C.group_d                     (собственное, группа)
C.group_d.field_d1            (из D, source = D)
C.group_d.field_d2            (из D, source = D)
```

**Blueprint A:**

```
A.field_a1
A.group_c (группа) ← встроен C
```

После материализации `A → C`:

```
A.field_a1                    (собственное)
A.group_c                     (собственное, группа)
A.group_c.field_c1            (из C, source = C)
A.group_c.group_d             (из C, source = C, группа)
A.group_c.group_d.field_d1    (из D, source = D, через транзитивность)
A.group_c.group_d.field_d2    (из D, source = D, через транзитивность)
```

**Blueprint B:**

```
B.field_b1
B.group_a (группа) ← встроен A
B.field_b2
```

**После материализации `B → A` (рекурсивной):**

```
B.field_b1                              (собственное, source = NULL, embed = NULL)
B.group_a                               (собственное, source = NULL, embed = NULL)
B.group_a.field_a1                      (из A, source = A, embed = B→A)
B.group_a.group_c                       (из A, source = A, embed = B→A)
B.group_a.group_c.field_c1              (из C, source = C, embed = B→A)
B.group_a.group_c.group_d               (из C, source = C, embed = B→A)
B.group_a.group_c.group_d.field_d1      (из D, source = D, embed = B→A)
B.group_a.group_c.group_d.field_d2      (из D, source = D, embed = B→A)
B.field_b2                              (собственное, source = NULL, embed = NULL)
```

**Ключевые моменты:**

1. **Все транзитивные поля** (из A, C, D) материализованы в B.
2. **Все копии** имеют `blueprint_embed_id = embed(B→A).id` — одна точка удаления.
3. **`source_blueprint_id` различается:**
    - Поля из A → `source_blueprint_id = A.id`
    - Поля из C → `source_blueprint_id = C.id`
    - Поля из D → `source_blueprint_id = D.id`
4. **Индексация работает:** запрос `wherePath('group_a.group_c.group_d.field_d1', ...)` найдёт значения в `doc_values`.

### 4.4. Пример 2: Множественное встраивание Address в Company

**Blueprint Address:**

-   `street` → `full_path = 'street'`
-   `city` → `full_path = 'city'`
-   `zip_code` → `full_path = 'zip_code'`

**Blueprint Company (до встраивания):**

-   `name` → `full_path = 'name'`
-   `office_address` → `full_path = 'office_address'` (группа, data_type = 'json')
-   `legal_address` → `full_path = 'legal_address'` (группа, data_type = 'json')

**Создаём два embed'а:**

1. `embed1`: `{blueprint_id: Company, embedded_blueprint_id: Address, host_path_id: path(office_address)}`
2. `embed2`: `{blueprint_id: Company, embedded_blueprint_id: Address, host_path_id: path(legal_address)}`

**После материализации обоих embed'ов:**

```
Company.name                           (собственное)
Company.office_address                 (собственное, группа)
Company.office_address.street          (копия, blueprint_embed_id = embed1, source = Address)
Company.office_address.city            (копия, blueprint_embed_id = embed1, source = Address)
Company.office_address.zip_code        (копия, blueprint_embed_id = embed1, source = Address)
Company.legal_address                  (собственное, группа)
Company.legal_address.street           (копия, blueprint_embed_id = embed2, source = Address)
Company.legal_address.city             (копия, blueprint_embed_id = embed2, source = Address)
Company.legal_address.zip_code         (копия, blueprint_embed_id = embed2, source = Address)
```

**В таблице `paths`:**

| id  | blueprint_id | source_blueprint_id | blueprint_embed_id | parent_id | name           | full_path               | is_readonly |
| --- | ------------ | ------------------- | ------------------ | --------- | -------------- | ----------------------- | ----------- |
| 1   | Company      | NULL                | NULL               | NULL      | name           | name                    | 0           |
| 2   | Company      | NULL                | NULL               | NULL      | office_address | office_address          | 0           |
| 3   | Company      | Address             | embed1             | 2         | street         | office_address.street   | 1           |
| 4   | Company      | Address             | embed1             | 2         | city           | office_address.city     | 1           |
| 5   | Company      | Address             | embed1             | 2         | zip_code       | office_address.zip_code | 1           |
| 6   | Company      | NULL                | NULL               | NULL      | legal_address  | legal_address           | 0           |
| 7   | Company      | Address             | embed2             | 6         | street         | legal_address.street    | 1           |
| 8   | Company      | Address             | embed2             | 6         | city           | legal_address.city      | 1           |
| 9   | Company      | Address             | embed2             | 6         | zip_code       | legal_address.zip_code  | 1           |

**В редакторе UI Company видим:**

-   name
-   office_address (группа)
    -   street (read-only, из Address)
    -   city (read-only, из Address)
    -   zip_code (read-only, из Address)
-   legal_address (группа)
    -   street (read-only, из Address)
    -   city (read-only, из Address)
    -   zip_code (read-only, из Address)

**При изменении структуры Address** (например, добавлении поля `country`):

1. Запускается событие `BlueprintStructureChanged(Address)`.
2. Находятся все зависимые blueprint'ы (Company).
3. Рематериализуются **оба** embed'а (`embed1` и `embed2`):
    - Удаляются `paths WHERE blueprint_embed_id = embed1` (старые копии).
    - Удаляются `paths WHERE blueprint_embed_id = embed2` (старые копии).
    - Рекурсивно создаются новые копии с учётом нового поля `country`.
4. Реиндексируются все Entry blueprint'а Company.

**Пример с транзитивными зависимостями:**

Если `Address` имеет встраивание `Address → Geo` (координаты), то при материализации `Company → Address` также материализуются и поля `Geo`:

```
Company.office_address.street
Company.office_address.city
Company.office_address.geo.lat       (транзитивное из Geo)
Company.office_address.geo.lng       (транзитивное из Geo)
```

Все эти поля имеют `blueprint_embed_id = embed1(Company→Address).id`.

---

## 5. Обработка изменений в исходном шаблоне

### 5.1. Типы изменений

Под изменением структуры исходного blueprint A понимаем:

-   добавление path;
-   удаление path;
-   изменение `name`, `parent_id`, `data_type`, `cardinality`, `is_indexed`, обязательности и т.п.

При любом изменении «своего» path’а (где `source_blueprint_id IS NULL`) нужно:

1. Найти все blueprint’ы, которые **встраивают A транзитивно** (A → B → C → …).
2. Для каждого зависимого blueprint’а **рематериализовать** встраивания.
3. Переиндексировать Entries этих blueprint’ов.

### 5.2. Граф зависимостей (родители)

Для этого удобно иметь функцию:

```php
/**
 * Возвращает все blueprint'ы, которые зависят от $rootId (прямо или через цепочку встраиваний).
 */
public function getAllDependentBlueprintIds(int $rootId): array
{
    $dependents = [];
    $stack = [$rootId];

    while ($stack) {
        $current = array_pop($stack);

        // все blueprint'ы, которые встраивают current
        $parents = BlueprintEmbed::query()
            ->where('embedded_blueprint_id', $current)
            ->pluck('blueprint_id')
            ->unique()  // один blueprint может встраивать current несколько раз
            ->all();

        foreach ($parents as $parentId) {
            if (!in_array($parentId, $dependents, true)) {
                $dependents[] = $parentId;
                $stack[] = $parentId;
            }
        }
    }

    return $dependents;
}
```

**Пример с прямыми и транзитивными зависимостями:**

```
Граф встраиваний:
  D → нигде не встроен
  C → встроен в D
  A → встроен в C
  B → встроен в A
```

-   `Address` встроен в `Company` дважды (`office_address`, `legal_address`).
-   `Person` встроен в `Article` один раз (`author`).
-   `Address` встроен в `Person` один раз (`home_address`).

Граф зависимостей:

```
Address
  ├─> Company (прямая зависимость, 2 embed'а)
  └─> Person (прямая зависимость, 1 embed)
      └─> Article (транзитивная зависимость через Person)
```

**При изменении `Address`:**

-   `getAllDependentBlueprintIds(Address)` вернёт `[Company, Person, Article]`.
-   Будут рематериализованы:
    -   2 embed'а `Address` в `Company` (рекурсивно, включая транзитивные embed'ы Address),
    -   1 embed `Address` в `Person` (рекурсивно),
    -   1 embed `Person` в `Article` (рекурсивно, включая `Address` и его транзитивные зависимости).
-   Будут реиндексированы Entry всех трёх blueprint'ов: `Company`, `Person`, `Article`.

**Критично:** если `Address` имеет собственное встраивание `Address → Geo`, то изменение структуры `Geo`:

1. Запускает `getAllDependentBlueprintIds(Geo)` → `[Address]`.
2. Рематериализует `Address` (хотя у Address нет зависимых через прямые embed'ы в этом примере).
3. **НО**: чтобы изменения `Geo` попали в `Company`, `Person`, `Article`, нужно после рематериализации `Address` также найти зависимых от `Address` и рематериализовать их.

Это уже обрабатывается логикой: изменение `Geo` → `Address` → все зависимые от `Address` (транзитивно).

### 5.3. Доменные события вместо Observer (дебаунс и батчинг)

**Проблема:** если пользователь через UI правит 10 полей подряд в blueprint A — каждый `saved()` запустит полную рематериализацию и реиндексацию всех зависимых blueprint'ов.

**Решение:** использовать доменное событие `BlueprintStructureChanged`, которое запускается **один раз** после завершения batch операций.

#### 5.3.1. Событие

```php
class BlueprintStructureChanged
{
    public function __construct(public Blueprint $blueprint) {}
}
```

#### 5.3.2. Listener (с каскадными событиями для транзитивности)

```php
class RematerializeEmbeds
{
    public function __construct(
        private BlueprintStructureService $structureService
    ) {}

    public function handle(BlueprintStructureChanged $event): void
    {
        $blueprint = $event->blueprint;

        // Защита от зацикливания: проверяем, не обрабатывали ли уже этот blueprint
        // в текущей цепочке событий
        $processed = $event->getProcessedBlueprints() ?? [];

        if (in_array($blueprint->id, $processed, true)) {
            // Уже обработан в этой цепочке — пропускаем
            return;
        }

        $processed[] = $blueprint->id;

        // 1. Реиндексируем сам blueprint
        dispatch(new ReindexBlueprintEntries($blueprint));

        // 2. Находим ПРЯМЫЕ зависимые blueprint'ы (один уровень вверх)
        $directParents = BlueprintEmbed::query()
            ->where('embedded_blueprint_id', $blueprint->id)
            ->pluck('blueprint_id')
            ->unique();

        foreach ($directParents as $parentId) {
            $parent = Blueprint::find($parentId);

            // 3. Рематериализуем ВСЕ embed'ы, которые включают изменённый blueprint
            // ВАЖНО: один blueprint может быть встроен несколько раз под разными host_path
            foreach ($parent->embeds as $embed) {
                if ($embed->embedded_blueprint_id === $blueprint->id) {
                    // materializeEmbeddedBlueprint() внутри уже триггерит
                    // BlueprintStructureChanged($parent) с передачей $processed
                    $this->structureService->materializeEmbeddedBlueprint($embed);
                }
            }

            // 4. Реиндексация родителя — одна job на blueprint (не на embed!)
            dispatch(new ReindexBlueprintEntries($parent));

            // 5. КАСКАДНОЕ СОБЫТИЕ: триггерим изменение родителя для транзитивности
            //    Передаём список уже обработанных blueprint'ов
            event(new BlueprintStructureChanged($parent, $processed));
        }
    }
}
```

**Изменения в событии `BlueprintStructureChanged`:**

```php
class BlueprintStructureChanged
{
    public function __construct(
        public Blueprint $blueprint,
        public array $processedBlueprints = []
    ) {}

    public function getProcessedBlueprints(): array
    {
        return $this->processedBlueprints;
    }
}
```

**Пример работы транзитивной цепочки:**

```
Geo → Address → Company → Department

1. Изменяется Geo
2. Event: BlueprintStructureChanged(Geo, [])
3. Listener находит Address, рематериализует Geo → Address
4. Event: BlueprintStructureChanged(Address, [Geo])
5. Listener находит Company, рематериализует Address → Company
6. Event: BlueprintStructureChanged(Company, [Geo, Address])
7. Listener находит Department, рематериализует Company → Department
8. Event: BlueprintStructureChanged(Department, [Geo, Address, Company])
9. Department не имеет зависимых → цепочка завершается
```

**Защита от циклов:** массив `$processedBlueprints` предотвращает повторную обработку в рамках одной цепочки.

#### 5.3.3. Запуск события

В контроллере/сервисе после batch изменений:

```php
// Пример: массовое обновление полей
DB::transaction(function () use ($blueprint, $fieldsData) {
    foreach ($fieldsData as $fieldData) {
        $path = Path::updateOrCreate(
            ['blueprint_id' => $blueprint->id, 'name' => $fieldData['name']],
            $fieldData
        );
    }
});

// После транзакции — один раз запускаем событие
event(new BlueprintStructureChanged($blueprint));
```

#### 5.3.4. Опционально: версионирование структуры

Добавить в `blueprints`:

```sql
ALTER TABLE blueprints
    ADD COLUMN structure_version INT UNSIGNED NOT NULL DEFAULT 1;
```

При изменении структуры инкрементировать `structure_version`. В `entries` добавить:

```sql
ALTER TABLE entries
    ADD COLUMN indexed_structure_version INT UNSIGNED NULL;
```

При реиндексации обновлять `indexed_structure_version = blueprint.structure_version`. Тогда легко понять, какие Entry устарели и требуют реиндексации.

---

## 6. Поведение при редактировании целевого шаблона (host blueprint)

В blueprint B, который встраивает A, различаем два типа полей:

1. **Собственные поля** — `source_blueprint_id IS NULL`.
2. **Скопированные поля** — `source_blueprint_id = A.id`, `is_readonly = 1`.

### 6.1. Разрешённые операции в B

-   добавлять новые поля (группы, простые поля и т.п.), `source_blueprint_id = NULL`;
-   редактировать и удалять только **собственные** поля;
-   добавлять и удалять встраивания (`blueprint_embeds`), что приведёт к созданию/удалению дерева скопированных полей;
-   менять порядок (`sort_order`) и местоположение **собственных** полей.

### 6.2. Запрещённые операции в B

-   редактировать свойства скопированных полей (`source_blueprint_id != NULL`);
-   удалять скопированные поля напрямую (они удаляются только через пересоздание embed или удаление самого `blueprint_embeds`).

На уровне кода/валидации:

-   все операции, меняющие `name`, `data_type`, `cardinality`, `is_indexed`, `is_required`, `parent_id` для path’ов с `source_blueprint_id != NULL` — должны быть заблокированы;
-   UI показывает такие поля как read-only.

### 6.3. Индексация

Поскольку в B у скопированных полей есть свой `full_path` и свой `path_id`, индексирование происходит штатно:

-   при изменении `Entry` blueprint’а B:
    -   из `data_json` читаются значения;
    -   по `full_path` (включая `A.fa1`) находятся соответствующие `paths` и заполняются `doc_values`/`doc_refs`;
-   `wherePath('A.fa1', ...)` работает так же, как для собственных полей B.

При рематериализации (пересоздании копий полей A в B) старые `path_id` удаляются, создаются новые, и job реиндексации пересоздаёт все `doc_values`/`doc_refs` для Entry данного blueprint’а.

---

## 7. Laravel-уровень: модели и связи

### 7.0. Модель `PostType` (обновление для интеграции)

> **⚠️ Это существующая модель stupidCMS**, обновляется для интеграции с Blueprint.

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\AsPostTypeOptions;
use App\Domain\PostTypes\PostTypeOptions;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent модель для типов записей (PostType).
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property PostTypeOptions $options_json
 * @property int|null $blueprint_id  ← НОВОЕ поле
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read \App\Models\Blueprint|null $blueprint  ← НОВАЯ связь
 * @property-read \Illuminate\Database\Eloquent\Collection<Entry> $entries
 */
class PostType extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'options_json',
        'blueprint_id',  // ← ДОБАВИТЬ
    ];

    protected $casts = [
        'options_json' => AsPostTypeOptions::class,
    ];

    /**
     * Связь с Blueprint (опциональная).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Blueprint, PostType>
     */
    public function blueprint()
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Связь с записями этого типа.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Entry, PostType>
     */
    public function entries()
    {
        return $this->hasMany(Entry::class);
    }
}
```

**Изменения:**

-   ✅ Добавлено поле `blueprint_id` в `$fillable`
-   ✅ Добавлена связь `blueprint()` → `belongsTo(Blueprint::class)`
-   ✅ Существующие методы и связи остаются без изменений

### 7.1. Модель `Blueprint`

```php
class Blueprint extends Model
{
    protected $fillable = ['name', 'code', 'description'];

    public function paths()
    {
        return $this->hasMany(Path::class);
    }

    // Этот blueprint встраивает другие
    public function embeds()
    {
        return $this->hasMany(BlueprintEmbed::class, 'blueprint_id');
    }

    // Этот blueprint встроен в другие
    public function embeddedIn()
    {
        return $this->hasMany(BlueprintEmbed::class, 'embedded_blueprint_id');
    }
}
```

### 7.2. Модель `Path`

```php
class Path extends Model
{
    /**
     * Поля, доступные для mass assignment.
     *
     * ВАЖНО: служебные и вычисляемые поля НЕ включены в $fillable:
     * - source_blueprint_id, blueprint_embed_id, is_readonly — управляются сервисным слоем
     * - full_path — вычисляемое поле (parent + name), управляется сервисом
     */
    protected $fillable = [
        'blueprint_id',
        'parent_id',
        'name',
        // 'full_path' — НЕ в fillable!
        'data_type',
        'cardinality',
        'is_required',
        'is_indexed',
        'sort_order',
    ];

    /**
     * Служебные и вычисляемые поля, защищённые от mass assignment.
     * Устанавливаются только через BlueprintStructureService.
     */
    protected $guarded = [
        'source_blueprint_id',
        'blueprint_embed_id',
        'is_readonly',
        'full_path',  // вычисляемое поле
    ];

    public function blueprint()
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function sourceBlueprint()
    {
        return $this->belongsTo(Blueprint::class, 'source_blueprint_id');
    }

    public function blueprintEmbed()
    {
        return $this->belongsTo(BlueprintEmbed::class, 'blueprint_embed_id');
    }

    public function parent()
    {
        return $this->belongsTo(Path::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Path::class, 'parent_id');
    }

    // Удобные скоупы
    public function scopeOwn($query)
    {
        return $query->whereNull('source_blueprint_id');
    }

    public function scopeEmbedded($query)
    {
        return $query->whereNotNull('source_blueprint_id');
    }

    public function scopeReadonly($query)
    {
        return $query->where('is_readonly', true);
    }

    /**
     * Проверка, является ли поле скопированным из embed.
     */
    public function isEmbedded(): bool
    {
        return $this->blueprint_embed_id !== null;
    }

    /**
     * Проверка, является ли поле собственным для blueprint.
     */
    public function isOwn(): bool
    {
        return $this->blueprint_embed_id === null;
    }
}
```

### 7.3. Модель `BlueprintEmbed`

```php
class BlueprintEmbed extends Model
{
    protected $fillable = [
        'blueprint_id',
        'embedded_blueprint_id',
        'host_path_id',
    ];

    public function blueprint()
    {
        return $this->belongsTo(Blueprint::class, 'blueprint_id');
    }

    public function embeddedBlueprint()
    {
        return $this->belongsTo(Blueprint::class, 'embedded_blueprint_id');
    }

    public function hostPath()
    {
        return $this->belongsTo(Path::class, 'host_path_id');
    }
}
```

### 7.4. Модель Entry (обновление для интеграции)

> **⚠️ Это существующая модель stupidCMS**, обновляется для поддержки индексации через Blueprint.

Entry — основная модель документов, использует трейт `HasDocumentData` для индексации через PostType → Blueprint.

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasDocumentData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Модель документа (Entry).
 *
 * @property int $id
 * @property int $post_type_id  ← FK к PostType (NOT NULL)
 * @property string $title
 * @property string $slug
 * @property string $status
 * @property \Carbon\Carbon|null $published_at
 * @property int|null $author_id
 * @property array $data_json
 * @property array|null $seo_json
 * @property string|null $template_override
 * @property int $version
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read \App\Models\PostType $postType
 * @property-read \App\Models\Blueprint|null $blueprint  ← ВЫЧИСЛЯЕМОЕ через PostType
 * @property-read \App\Models\User $author
 */
class Entry extends Model
{
    use SoftDeletes, HasDocumentData;  // ← ДОБАВИТЬ HasDocumentData

    // Существующие поля stupidCMS (БЕЗ изменений)
    protected $guarded = [];  // или конкретный $fillable

    protected $casts = [
        'data_json' => 'array',
        'seo_json' => 'array',
        'published_at' => 'datetime',
    ];

    // Связи

    /**
     * Тип записи (PostType).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<PostType, Entry>
     */
    public function postType()
    {
        return $this->belongsTo(PostType::class);
    }

    /**
     * Blueprint через PostType (может быть NULL).
     *
     * Вычисляемая связь: $entry->postType->blueprint
     *
     * @return Blueprint|null
     */
    public function blueprint(): ?Blueprint
    {
        return $this->postType?->blueprint;
    }

    /**
     * Автор Entry.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, Entry>
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Термы (категории, теги).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Term, Entry>
     */
    public function terms()
    {
        return $this->belongsToMany(Term::class, 'entry_term')
            ->withTimestamps();
    }

    /**
     * Индексированные скалярные значения.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<DocValue, Entry>
     */
    public function values()
    {
        return $this->hasMany(DocValue::class, 'entry_id');
    }

    /**
     * Индексированные ссылки на другие Entry.
     */
    public function refs()
    {
        return $this->hasMany(DocRef::class, 'entry_id');
    }

    /**
     * Обратные ссылки (Entry, которые ссылаются на текущий).
     */
    public function referencedBy()
    {
        return $this->hasMany(DocRef::class, 'target_entry_id');
    }

    // Скоупы

    /**
     * Фильтр по статусу.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Фильтр по Blueprint.
     */
    public function scopeForBlueprint($query, int $blueprintId)
    {
        return $query->where('blueprint_id', $blueprintId);
    }

    // Хелперы

    /**
     * Проверить, опубликован ли Entry.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null;
    }

    /**
     * Получить URL Entry (если есть slug).
     */
    public function getUrl(): ?string
    {
        return $this->slug ? route('entries.show', $this->slug) : null;
    }
}
```

### 7.5. Модель DocValue

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Индексированное скалярное значение из Entry.
 *
 * @property int $id
 * @property int $entry_id
 * @property int $path_id
 * @property int $array_index
 * @property string|null $value_string
 * @property int|null $value_int
 * @property float|null $value_float
 * @property bool|null $value_bool
 * @property \Carbon\Carbon|null $value_date
 * @property \Carbon\Carbon|null $value_datetime
 * @property string|null $value_text
 * @property array|null $value_json
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DocValue extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'entry_id',
        'path_id',
        'array_index',
        'value_string',
        'value_int',
        'value_float',
        'value_bool',
        'value_date',
        'value_datetime',
        'value_text',
        'value_json',
    ];

    protected $casts = [
        'array_index' => 'integer',
        'value_int' => 'integer',
        'value_float' => 'float',
        'value_bool' => 'boolean',
        'value_date' => 'date',
        'value_datetime' => 'datetime',
        'value_json' => 'array',
    ];

    // Связи

    /**
     * Entry, к которому принадлежит значение.
     */
    public function entry()
    {
        return $this->belongsTo(Entry::class, 'entry_id');
    }

    /**
     * Path (поле), которое описывает значение.
     */
    public function path()
    {
        return $this->belongsTo(Path::class, 'path_id');
    }

    // Хелперы

    /**
     * Получить актуальное значение (независимо от типа).
     */
    public function getValue(): mixed
    {
        return $this->value_string
            ?? $this->value_int
            ?? $this->value_float
            ?? $this->value_bool
            ?? $this->value_date
            ?? $this->value_datetime
            ?? $this->value_text
            ?? $this->value_json;
    }

    /**
     * Является ли элементом массива (array_index > 0).
     */
    public function isArrayElement(): bool
    {
        return $this->array_index > 0;
    }
}
```

### 7.6. Модель DocRef

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ссылка между Entry (ref-поле).
 *
 * @property int $id
 * @property int $entry_id
 * @property int $path_id
 * @property int $array_index
 * @property int $target_entry_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DocRef extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'entry_id',
        'path_id',
        'array_index',
        'target_entry_id',
    ];

    protected $casts = [
        'array_index' => 'integer',
        'entry_id' => 'integer',
        'target_entry_id' => 'integer',
    ];

    // Связи

    /**
     * Entry-владелец (содержит ref-поле).
     */
    public function owner()
    {
        return $this->belongsTo(Entry::class, 'entry_id');
    }

    /**
     * Целевой Entry (на который ссылается поле).
     */
    public function target()
    {
        return $this->belongsTo(Entry::class, 'target_entry_id');
    }

    /**
     * Path (ref-поле).
     */
    public function path()
    {
        return $this->belongsTo(Path::class, 'path_id');
    }

    // Хелперы

    /**
     * Является ли элементом массива ссылок.
     */
    public function isArrayElement(): bool
    {
        return $this->array_index > 0;
    }
}
```

### 7.7. Трейт HasDocumentData

Трейт автоматизирует индексацию `data_json` в `doc_values` и `doc_refs`, предоставляет API для работы с динамическими полями и скоупы для запросов.

```php
<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\DocRef;
use App\Models\DocValue;
use App\Models\Path;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait HasDocumentData
{
    /**
     * Boot трейта: автоматическая индексация при сохранении.
     */
    protected static function bootHasDocumentData(): void
    {
        static::saved(function ($entry) {
            if ($entry->blueprint_id) {
                $entry->syncDocumentIndex();
            }
        });

        // CASCADE удаление обрабатывается FK в БД
    }

    // ======================
    // API для работы с data_json
    // ======================

    /**
     * Получить значение по пути в data_json.
     *
     * @param string $path Путь (dot-notation): 'author.name', 'tags'
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public function getPath(string $path, mixed $default = null): mixed
    {
        return data_get($this->data_json, $path, $default);
    }

    /**
     * Установить значение по пути в data_json.
     *
     * @param string $path Путь (dot-notation)
     * @param mixed $value Значение
     */
    public function setPath(string $path, mixed $value): void
    {
        $data = $this->data_json ?? [];
        data_set($data, $path, $value);
        $this->data_json = $data;
    }

    /**
     * Удалить значение по пути из data_json.
     *
     * @param string $path Путь (dot-notation)
     */
    public function forgetPath(string $path): void
    {
        $data = $this->data_json ?? [];
        data_forget($data, $path);
        $this->data_json = $data;
    }

    // ======================
    // Индексация
    // ======================

    /**
     * Синхронизировать индексы doc_values и doc_refs на основе data_json.
     *
     * Вызывается автоматически при сохранении Entry.
     */
    public function syncDocumentIndex(): void
    {
        if (!$this->blueprint_id) {
            return;
        }

        $data = $this->data_json ?? [];

        // Получаем индексируемые Paths (с кешированием)
        $paths = $this->getIndexedPaths();

        DB::transaction(function () use ($data, $paths) {
            // Удаляем старые индексы (FK CASCADE)
            $this->values()->delete();
            $this->refs()->delete();

            // Индексируем каждый Path
            foreach ($paths as $path) {
                $value = data_get($data, $path->full_path);

                if ($value === null) {
                    continue;
                }

                if ($path->data_type === 'ref') {
                    $this->syncRefPath($path, $value);
                } else {
                    $this->syncScalarPath($path, $value);
                }
            }
        });

        // Обновляем версию структуры (опционально)
        if ($this->blueprint->structure_version ?? false) {
            $this->update([
                'indexed_structure_version' => $this->blueprint->structure_version,
            ]);
        }
    }

    /**
     * Получить индексируемые Paths для Blueprint Entry.
     *
     * @return \Illuminate\Support\Collection<Path>
     */
    protected function getIndexedPaths()
    {
        $cacheKey = "blueprint:{$this->blueprint_id}:indexed_paths";

        return Cache::remember($cacheKey, 3600, function () {
            return $this->blueprint
                ->paths()
                ->where('is_indexed', true)
                ->get();
        });
    }

    /**
     * Синхронизировать скалярное поле (string, int, float, bool, date, datetime, text, json).
     *
     * @param Path $path
     * @param mixed $value
     */
    protected function syncScalarPath(Path $path, mixed $value): void
    {
        $valueField = $this->getValueFieldForType($path->data_type);

        if ($path->cardinality === 'one') {
            // Одиночное значение
            DocValue::create([
                'entry_id' => $this->id,
                'path_id' => $path->id,
                'array_index' => 0,
                $valueField => $this->castValueForType($value, $path->data_type),
            ]);
        } else {
            // Массив значений (cardinality = 'many')
            if (!is_array($value)) {
                return;
            }

            foreach ($value as $idx => $item) {
                DocValue::create([
                    'entry_id' => $this->id,
                    'path_id' => $path->id,
                    'array_index' => $idx + 1, // 1-based для массивов
                    $valueField => $this->castValueForType($item, $path->data_type),
                ]);
            }
        }
    }

    /**
     * Синхронизировать ref-поле (ссылка на другой Entry).
     *
     * @param Path $path
     * @param mixed $value int|array<int>
     */
    protected function syncRefPath(Path $path, mixed $value): void
    {
        if ($path->cardinality === 'one') {
            // Одиночная ссылка
            DocRef::create([
                'entry_id' => $this->id,
                'path_id' => $path->id,
                'array_index' => 0,
                'target_entry_id' => (int) $value,
            ]);
        } else {
            // Массив ссылок
            if (!is_array($value)) {
                return;
            }

            foreach ($value as $idx => $targetId) {
                DocRef::create([
                    'entry_id' => $this->id,
                    'path_id' => $path->id,
                    'array_index' => $idx + 1,
                    'target_entry_id' => (int) $targetId,
                ]);
            }
        }
    }

    /**
     * Получить имя колонки value_* для типа данных.
     *
     * @param string $dataType
     * @return string
     */
    protected function getValueFieldForType(string $dataType): string
    {
        return match ($dataType) {
            'string' => 'value_string',
            'int' => 'value_int',
            'float' => 'value_float',
            'bool' => 'value_bool',
            'date' => 'value_date',
            'datetime' => 'value_datetime',
            'text' => 'value_text',
            'json' => 'value_json',
            default => throw new \InvalidArgumentException("Неизвестный data_type: {$dataType}"),
        };
    }

    /**
     * Привести значение к нужному типу для хранения.
     *
     * @param mixed $value
     * @param string $dataType
     * @return mixed
     */
    protected function castValueForType(mixed $value, string $dataType): mixed
    {
        return match ($dataType) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'date' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value,
            'datetime' => $value instanceof \DateTimeInterface ? $value : now()->parse($value),
            'json' => is_array($value) ? $value : json_decode($value, true),
            default => (string) $value,
        };
    }

    // ======================
    // Скоупы для запросов
    // ======================

    /**
     * Фильтровать Entry по значению индексированного поля.
     *
     * Автоматически определяет тип поля по значению.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fullPath Полный путь поля: 'author.name', 'tags'
     * @param string $operator Оператор: '=', '>', '<', 'like', etc.
     * @param mixed $value Значение для сравнения
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @example Entry::wherePath('author.name', '=', 'John Doe')->get()
     * @example Entry::wherePath('price', '>', 100)->get()
     */
    public function scopeWherePath($query, string $fullPath, string $operator, mixed $value)
    {
        return $query->whereHas('values', function ($q) use ($fullPath, $operator, $value) {
            // Фильтруем по Path
            $q->whereHas('path', function ($pathQuery) use ($fullPath) {
                $pathQuery->where('full_path', $fullPath);
            });

            // Определяем поле для фильтрации по типу значения
            $valueField = match (true) {
                is_int($value) => 'value_int',
                is_float($value) => 'value_float',
                is_bool($value) => 'value_bool',
                $value instanceof \DateTimeInterface => 'value_datetime',
                default => 'value_string',
            };

            $q->where($valueField, $operator, $value);
        });
    }

    /**
     * Фильтровать Entry по значению с явным указанием типа.
     *
     * Используйте, когда автоопределение типа не работает корректно.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fullPath
     * @param string $dataType Тип из Path: 'string', 'int', 'float', etc.
     * @param string $operator
     * @param mixed $value
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @example Entry::wherePathTyped('published_at', 'datetime', '>', now()->subDays(7))->get()
     */
    public function scopeWherePathTyped($query, string $fullPath, string $dataType, string $operator, mixed $value)
    {
        $valueField = $this->getValueFieldForType($dataType);

        return $query->whereHas('values', function ($q) use ($fullPath, $valueField, $operator, $value) {
            $q->whereHas('path', function ($pathQuery) use ($fullPath) {
                $pathQuery->where('full_path', $fullPath);
            })
            ->where($valueField, $operator, $value);
        });
    }

    /**
     * Фильтровать Entry, у которых есть ссылка на указанный Entry.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fullPath Полный путь ref-поля: 'article', 'relatedArticles'
     * @param int $targetEntryId ID целевого Entry
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @example Entry::whereRef('article', 42)->get()
     */
    public function scopeWhereRef($query, string $fullPath, int $targetEntryId)
    {
        return $query->whereHas('refs', function ($q) use ($fullPath, $targetEntryId) {
            $q->whereHas('path', fn($pq) => $pq->where('full_path', $fullPath))
              ->where('target_entry_id', $targetEntryId);
        });
    }

    /**
     * Фильтровать Entry, на которые ссылается указанный Entry через ref-поле.
     *
     * Обратный запрос к whereRef.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fullPath
     * @param int $ownerEntryId
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @example Entry::referencedBy('relatedArticles', 1)->get()
     */
    public function scopeReferencedBy($query, string $fullPath, int $ownerEntryId)
    {
        return $query->whereHas('referencedBy', function ($q) use ($fullPath, $ownerEntryId) {
            $q->whereHas('path', fn($pq) => $pq->where('full_path', $fullPath))
              ->where('entry_id', $ownerEntryId);
        });
    }

    /**
     * Фильтровать Entry с любым значением в указанном поле (NOT NULL).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fullPath
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @example Entry::wherePathExists('author.bio')->get()
     */
    public function scopeWherePathExists($query, string $fullPath)
    {
        return $query->whereHas('values', function ($q) use ($fullPath) {
            $q->whereHas('path', fn($pq) => $pq->where('full_path', $fullPath));
        });
    }

    /**
     * Фильтровать Entry, у которых поле НЕ заполнено (NULL).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fullPath
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @example Entry::wherePathMissing('author.bio')->get()
     */
    public function scopeWherePathMissing($query, string $fullPath)
    {
        return $query->whereDoesntHave('values', function ($q) use ($fullPath) {
            $q->whereHas('path', fn($pq) => $pq->where('full_path', $fullPath));
        });
    }
}
```

### 7.5. Исключения

```php
class EmbeddedBlueprintPathConflictException extends \DomainException
{
    public function __construct(string $message, array $conflictingPaths = [])
    {
        parent::__construct($message);
        $this->conflictingPaths = $conflictingPaths;
    }

    public function getConflictingPaths(): array
    {
        return $this->conflictingPaths ?? [];
    }
}
```

### 7.6. Сервисный слой: `BlueprintStructureService`

Выносим всю логику работы со структурой blueprint'ов в отдельный сервис, чтобы Observer/контроллеры были максимально тонкими.

```php
class BlueprintStructureService
{
    /**
     * Создаёт новое встраивание с полной валидацией и материализацией.
     *
     * @throws \LogicException|\InvalidArgumentException
     */
    public function createEmbed(
        Blueprint $parent,
        Blueprint $embedded,
        ?Path $hostPath = null
    ): BlueprintEmbed {
        // 1. Валидация
        $this->ensureNoCyclicDependency($parent, $embedded);
        $this->validateHostPath($parent, $hostPath);

        // 2. Проверка уникальности (blueprint_id, embedded_blueprint_id, host_path_id)
        $exists = BlueprintEmbed::query()
            ->where('blueprint_id', $parent->id)
            ->where('embedded_blueprint_id', $embedded->id)
            ->where('host_path_id', $hostPath?->id)
            ->exists();

        if ($exists) {
            $hostName = $hostPath ? "под полем '{$hostPath->full_path}'" : "в корень";
            throw new \LogicException(
                "Blueprint '{$embedded->code}' уже встроен в '{$parent->code}' {$hostName}"
            );
        }

        // 3. Создание embed'а
        $embed = BlueprintEmbed::create([
            'blueprint_id' => $parent->id,
            'embedded_blueprint_id' => $embedded->id,
            'host_path_id' => $hostPath?->id,
        ]);

        // 4. Материализация
        $this->materializeEmbeddedBlueprint($embed);

        // 5. Событие для реиндексации
        event(new BlueprintStructureChanged($parent));

        return $embed;
    }

    /**
     * Удаляет встраивание (поля удалятся автоматически через FK CASCADE).
     */
    public function deleteEmbed(BlueprintEmbed $embed): void
    {
        $parent = $embed->blueprint;

        $embed->delete();

        // Событие для реиндексации
        event(new BlueprintStructureChanged($parent));
    }

    /**
     * Проверяет, что встраивание не создаст цикл.
     *
     * @throws \LogicException
     */
    public function ensureNoCyclicDependency(Blueprint $parent, Blueprint $embedded): void
    {
        if ($parent->id === $embedded->id) {
            throw new \LogicException('Нельзя встроить blueprint сам в себя');
        }

        if ($this->hasPathTo($embedded->id, $parent->id)) {
            throw new \LogicException('Циклическая зависимость blueprint'ов');
        }
    }

    /**
     * Проверяет существование пути от $fromId к $targetId в графе встраиваний.
     */
    protected function hasPathTo(int $fromId, int $targetId): bool
    {
        // DFS по графу embeds (см. раздел 3.2)
    }

    /**
     * Возвращает все blueprint'ы, зависящие от $rootId.
     */
    public function getAllDependentBlueprintIds(int $rootId): array
    {
        // DFS по родителям (см. раздел 5.2)
    }

    /**
     * Материализует embedded blueprint со всеми транзитивными зависимостями.
     *
     * @see раздел 4.2 для деталей реализации
     */
    public function materializeEmbeddedBlueprint(BlueprintEmbed $embed): void
    {
        // Верхний уровень — см. раздел 4.2.1
    }

    /**
     * Рекурсивно копирует структуру blueprint (включая транзитивные embed'ы).
     *
     * @param Blueprint $blueprint       Исходный blueprint
     * @param Blueprint $hostBlueprint   Целевой blueprint
     * @param int|null $baseParentId     ID родителя в целевом blueprint
     * @param string|null $baseParentPath full_path родителя
     * @param BlueprintEmbed $rootEmbed  Корневой embed (для blueprint_embed_id)
     *
     * @see раздел 4.2.2 для деталей реализации
     */
    private function copyBlueprintRecursive(
        Blueprint $blueprint,
        Blueprint $hostBlueprint,
        ?int $baseParentId,
        ?string $baseParentPath,
        BlueprintEmbed $rootEmbed
    ): void {
        // Рекурсивный копировщик — см. раздел 4.2.2
    }

    /**
     * Валидирует, что host_path принадлежит blueprint'у и подходит по типу.
     *
     * @throws \InvalidArgumentException
     */
    public function validateHostPath(Blueprint $blueprint, ?Path $hostPath): void
    {
        if ($hostPath === null) {
            return; // встраивание в корень
        }

        if ($hostPath->blueprint_id !== $blueprint->id) {
            throw new \InvalidArgumentException(
                "host_path не принадлежит blueprint {$blueprint->code}"
            );
        }

        // Опционально: проверить, что host_path — группа (data_type = 'json')
        if ($hostPath->data_type !== 'json') {
            throw new \InvalidArgumentException(
                "host_path должен быть группой (data_type = 'json')"
            );
        }
    }
}
```

### 7.7. Пример использования API

#### Создание множественного встраивания

```php
$company = Blueprint::where('code', 'company')->first();
$address = Blueprint::where('code', 'address')->first();

$officeAddressPath = Path::where('blueprint_id', $company->id)
    ->where('full_path', 'office_address')
    ->first();

$legalAddressPath = Path::where('blueprint_id', $company->id)
    ->where('full_path', 'legal_address')
    ->first();

// Встроить Address под office_address
$embed1 = $structureService->createEmbed($company, $address, $officeAddressPath);

// Встроить Address под legal_address (второй раз!)
$embed2 = $structureService->createEmbed($company, $address, $legalAddressPath);

// Результат: в Company появились поля обоих embed'ов
```

#### Удаление одного из embed'ов

```php
// Удаляем только office_address embed
$structureService->deleteEmbed($embed1);

// legal_address.* поля остаются (они привязаны к $embed2)
```

#### Получение информации о встраиваниях

```php
// Все встраивания blueprint'а
$embeds = $company->embeds; // hasMany(BlueprintEmbed)

// Где используется blueprint
$usages = $address->embeddedIn; // hasMany(BlueprintEmbed, 'embedded_blueprint_id')

// Сколько раз Address встроен в Company
$count = BlueprintEmbed::query()
    ->where('blueprint_id', $company->id)
    ->where('embedded_blueprint_id', $address->id)
    ->count(); // 2 (office_address + legal_address)
```

### 7.8. Валидация данных Entry

При создании/обновлении Entry важно валидировать данные в `data_json` согласно определениям в `paths`.

**app/Http/Requests/StoreEntryRequest.php:**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Blueprint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'blueprint_id' => 'required|exists:blueprints,id',
            'title' => 'nullable|string|max:500',
            'slug' => [
                'nullable',
                'string',
                'max:500',
                Rule::unique('entries', 'slug')->ignore($this->entry),
            ],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
            'published_at' => 'nullable|date',
            'author_id' => 'nullable|exists:users,id',
            'data_json' => ['required', 'array', function ($attribute, $value, $fail) {
                $this->validateDataJson($value, $fail);
            }],
        ];
    }

    /**
     * Валидация data_json согласно Paths Blueprint.
     */
    protected function validateDataJson(array $data, $fail): void
    {
        $blueprintId = $this->input('blueprint_id');

        if (!$blueprintId) {
            return; // blueprint_id будет проверен основной валидацией
        }

        $blueprint = Blueprint::find($blueprintId);

        if (!$blueprint) {
            return;
        }

        // Получаем все Paths (включая материализованные)
        $paths = $blueprint->getAllPaths()->where('is_required', true);

        // Проверяем обязательные поля
        foreach ($paths as $path) {
            $value = data_get($data, $path->full_path);

            if ($value === null) {
                $fail("Поле '{$path->full_path}' обязательно для заполнения.");
            }

            // Проверка типа данных
            if (!$this->validatePathValue($path, $value)) {
                $fail("Поле '{$path->full_path}' имеет неверный тип данных. Ожидается: {$path->data_type}.");
            }

            // Проверка cardinality
            if ($path->cardinality === 'many' && !is_array($value)) {
                $fail("Поле '{$path->full_path}' должно быть массивом.");
            }

            if ($path->cardinality === 'one' && is_array($value)) {
                $fail("Поле '{$path->full_path}' не должно быть массивом.");
            }
        }
    }

    /**
     * Проверить соответствие значения типу Path.
     */
    protected function validatePathValue($path, $value): bool
    {
        // Если cardinality=many, проверяем первый элемент
        if ($path->cardinality === 'many' && is_array($value)) {
            if (empty($value)) {
                return true; // Пустой массив допустим
            }
            $value = $value[0];
        }

        return match ($path->data_type) {
            'string', 'text' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            'date', 'datetime' => is_string($value) || $value instanceof \DateTimeInterface,
            'json' => is_array($value) || is_object($value),
            'ref' => is_int($value),
            default => true,
        };
    }

    public function messages(): array
    {
        return [
            'blueprint_id.required' => 'Не указан Blueprint.',
            'blueprint_id.exists' => 'Указанный Blueprint не найден.',
            'slug.unique' => 'Entry с таким slug уже существует.',
            'status.in' => 'Неверный статус. Допустимые значения: draft, published, archived.',
            'data_json.required' => 'Данные обязательны для заполнения.',
            'data_json.array' => 'Данные должны быть в формате объекта/массива.',
        ];
    }
}
```

**app/Http/Requests/UpdateEntryRequest.php:**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

class UpdateEntryRequest extends StoreEntryRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        // При обновлении поля не обязательны (partial update)
        $rules['blueprint_id'] = 'sometimes|exists:blueprints,id';
        $rules['data_json'] = ['sometimes', 'array', function ($attribute, $value, $fail) {
            $this->validateDataJson($value, $fail);
        }];

        return $rules;
    }
}
```

### 7.9. Примеры использования API

#### 7.8.1. Создание Entry с индексацией

```php
use App\Models\Entry;
use App\Models\Blueprint;

// Получаем Blueprint
$blueprint = Blueprint::where('code', 'article')->first();

// Создаём Entry
$entry = Entry::create([
    'blueprint_id' => $blueprint->id,
    'title' => 'How to Build CMS',
    'slug' => 'how-to-build-cms',
    'status' => 'published',
    'published_at' => now(),
    'author_id' => 1,
    'data_json' => [
        'content' => '<p>Long article content...</p>',
        'excerpt' => 'Short description for SEO',
        'author' => [
            'name' => 'John Doe',
            'bio' => 'Senior Developer',
            'contacts' => [
                'phone' => '+1234567890',
                'email' => 'john@example.com',
            ],
        ],
        'tags' => ['cms', 'laravel', 'php'],
        'relatedArticles' => [42, 77, 91],
        'seo' => [
            'metaTitle' => 'Build CMS with Laravel',
            'metaDescription' => 'Complete guide to building...',
        ],
    ],
]);

// После save() автоматически создаются записи в doc_values и doc_refs
// благодаря HasDocumentData трейту
```

#### 7.8.2. Работа с динамическими полями

```php
// Получение значений
$authorName = $entry->getPath('author.name'); // 'John Doe'
$tags = $entry->getPath('tags'); // ['cms', 'laravel', 'php']
$metaTitle = $entry->getPath('seo.metaTitle'); // 'Build CMS with Laravel'

// Установка значений
$entry->setPath('author.bio', 'Updated bio');
$entry->setPath('tags', ['cms', 'laravel', 'php', 'mysql']);
$entry->save(); // Автоматическая реиндексация

// Удаление значений
$entry->forgetPath('author.contacts.phone');
$entry->save();
```

#### 7.8.3. Запросы с фильтрацией по полям

```php
// Простой запрос по строковому полю
$entries = Entry::wherePath('author.name', '=', 'John Doe')->get();

// Запрос по числовому полю (автоопределение типа)
$entries = Entry::wherePath('price', '>', 100)->get();

// Запрос с явным указанием типа
$entries = Entry::wherePathTyped('published_at', 'datetime', '>', now()->subDays(7))->get();

// LIKE-запрос
$entries = Entry::wherePath('author.email', 'like', '%@example.com')->get();

// Проверка наличия поля
$entries = Entry::wherePathExists('author.bio')->get();

// Проверка отсутствия поля
$entries = Entry::wherePathMissing('author.bio')->get();

// Комбинирование фильтров
$entries = Entry::query()
    ->wherePath('author.name', '=', 'John Doe')
    ->wherePath('status', '=', 'published')
    ->wherePathTyped('created_at', 'datetime', '>', now()->subMonth())
    ->get();
```

#### 7.8.4. Запросы по ref-полям (ссылкам)

```php
// Найти Entry, которые ссылаются на статью с ID 42
$entries = Entry::whereRef('relatedArticles', 42)->get();

// Обратный запрос: найти статьи, на которые ссылается Entry с ID 1
$relatedArticles = Entry::referencedBy('relatedArticles', 1)->get();

// Комбинирование с обычными фильтрами
$entries = Entry::query()
    ->whereRef('article', 42)
    ->wherePath('status', '=', 'published')
    ->get();
```

#### 7.8.5. Работа с Eloquent relationships через ref-поля

Можно создать динамические связи через DocRef:

```php
use App\Models\Entry;

class Entry extends Model
{
    // ... HasDocumentData ...

    /**
     * Получить связанные статьи (relatedArticles ref-поле).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function relatedArticles()
    {
        return $this->belongsToMany(
            Entry::class,
            'doc_refs',
            'entry_id',
            'target_entry_id'
        )
        ->wherePivot('path_id', function ($query) {
            $path = Path::where('full_path', 'relatedArticles')
                ->where('blueprint_id', $this->blueprint_id)
                ->first();
            return $path?->id;
        })
        ->withPivot('array_index')
        ->orderByPivot('array_index');
    }

    /**
     * Получить основную статью (article ref-поле, cardinality=one).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough
     */
    public function mainArticle()
    {
        return $this->hasOneThrough(
            Entry::class,
            DocRef::class,
            'entry_id', // FK в doc_refs
            'id', // FK в entries
            'id', // Local key в текущей модели
            'target_entry_id' // Local key в doc_refs
        )->whereHas('path', fn($q) => $q->where('full_path', 'article'));
    }
}

// Использование
$entry = Entry::with('relatedArticles', 'mainArticle')->find(1);
$related = $entry->relatedArticles; // Collection<Entry>
$main = $entry->mainArticle; // Entry|null
```

### 7.9. Команда реиндексации

Создайте Artisan-команду для массовой реиндексации Entry:

**app/Console/Commands/ReindexEntries.php:**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Models\Entry;
use Illuminate\Console\Command;

/**
 * Команда реиндексации Entry.
 *
 * Использование:
 * - php artisan entries:reindex
 * - php artisan entries:reindex --blueprint=article
 * - php artisan entries:reindex --entry=123
 */
class ReindexEntries extends Command
{
    protected $signature = 'entries:reindex
                            {--blueprint= : Код Blueprint для реиндексации}
                            {--entry= : ID конкретного Entry}
                            {--chunk=100 : Размер пачки для обработки}';

    protected $description = 'Реиндексировать doc_values и doc_refs для Entry';

    public function handle(): int
    {
        $blueprintCode = $this->option('blueprint');
        $entryId = $this->option('entry');
        $chunkSize = (int) $this->option('chunk');

        // Реиндексация одного Entry
        if ($entryId) {
            return $this->reindexSingleEntry($entryId);
        }

        // Реиндексация по Blueprint
        if ($blueprintCode) {
            return $this->reindexByBlueprint($blueprintCode, $chunkSize);
        }

        // Реиндексация всех Entry
        return $this->reindexAllEntries($chunkSize);
    }

    /**
     * Реиндексировать один Entry.
     */
    protected function reindexSingleEntry(int $entryId): int
    {
        $entry = Entry::find($entryId);

        if (!$entry) {
            $this->error("Entry с ID {$entryId} не найден.");
            return Command::FAILURE;
        }

        $this->info("Реиндексация Entry #{$entry->id}...");
        $entry->syncDocumentIndex();
        $this->info("✓ Готово.");

        return Command::SUCCESS;
    }

    /**
     * Реиндексировать Entry для Blueprint.
     */
    protected function reindexByBlueprint(string $blueprintCode, int $chunkSize): int
    {
        $blueprint = Blueprint::where('code', $blueprintCode)->first();

        if (!$blueprint) {
            $this->error("Blueprint '{$blueprintCode}' не найден.");
            return Command::FAILURE;
        }

        $total = $blueprint->entries()->count();

        if ($total === 0) {
            $this->info("Нет Entry для Blueprint '{$blueprintCode}'.");
            return Command::SUCCESS;
        }

        $this->info("Реиндексация {$total} Entry для Blueprint '{$blueprintCode}'...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $blueprint->entries()->chunkById($chunkSize, function ($entries) use ($bar) {
            foreach ($entries as $entry) {
                $entry->syncDocumentIndex();
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("✓ Реиндексировано {$total} Entry.");

        return Command::SUCCESS;
    }

    /**
     * Реиндексировать все Entry.
     */
    protected function reindexAllEntries(int $chunkSize): int
    {
        $total = Entry::count();

        if ($total === 0) {
            $this->info("Нет Entry для реиндексации.");
            return Command::SUCCESS;
        }

        if (!$this->confirm("Реиндексировать {$total} Entry? Это может занять время.")) {
            return Command::SUCCESS;
        }

        $this->info("Реиндексация {$total} Entry...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        Entry::chunkById($chunkSize, function ($entries) use ($bar) {
            foreach ($entries as $entry) {
                $entry->syncDocumentIndex();
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("✓ Реиндексировано {$total} Entry.");

        return Command::SUCCESS;
    }
}
```

**Регистрация команды** (автоматически в Laravel 11+, или в `app/Console/Kernel.php` для Laravel 10):

```php
protected $commands = [
    \App\Console\Commands\ReindexEntries::class,
];
```

**Использование:**

```bash
# Реиндексировать все Entry
php artisan entries:reindex

# Реиндексировать Entry для конкретного Blueprint
php artisan entries:reindex --blueprint=article

# Реиндексировать один Entry
php artisan entries:reindex --entry=123

# Изменить размер пачки
php artisan entries:reindex --chunk=500
```

### 7.10. Оптимизации индексации

#### 7.10.1. Batch Insert для больших массивов

Текущая реализация `syncScalarPath` / `syncRefPath` создаёт записи по одной через `DocValue::create()`. Для массивов с сотнями элементов это медленно.

**Оптимизированная версия с batch insert:**

```php
protected function syncScalarPath(Path $path, mixed $value): void
{
    $valueField = $this->getValueFieldForType($path->data_type);
    $batch = [];

    if ($path->cardinality === 'one') {
        $batch[] = [
            'entry_id' => $this->id,
            'path_id' => $path->id,
            'array_index' => 0,
            $valueField => $this->castValueForType($value, $path->data_type),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    } else {
        if (!is_array($value)) {
            return;
        }

        foreach ($value as $idx => $item) {
            $batch[] = [
                'entry_id' => $this->id,
                'path_id' => $path->id,
                'array_index' => $idx + 1,
                $valueField => $this->castValueForType($item, $path->data_type),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }

    if (!empty($batch)) {
        DB::table('doc_values')->insert($batch);
    }
}

protected function syncRefPath(Path $path, mixed $value): void
{
    $batch = [];

    if ($path->cardinality === 'one') {
        $batch[] = [
            'entry_id' => $this->id,
            'path_id' => $path->id,
            'array_index' => 0,
            'target_entry_id' => (int) $value,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    } else {
        if (!is_array($value)) {
            return;
        }

        foreach ($value as $idx => $targetId) {
            $batch[] = [
                'entry_id' => $this->id,
                'path_id' => $path->id,
                'array_index' => $idx + 1,
                'target_entry_id' => (int) $targetId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }

    if (!empty($batch)) {
        DB::table('doc_refs')->insert($batch);
    }
}
```

**Результат:** вставка 100 значений = 1 SQL-запрос вместо 100.

#### 7.10.2. Инвалидация кеша при изменении Paths

При изменении `paths` (добавление, удаление, изменение `is_indexed`) нужно инвалидировать кеш индексируемых полей:

```php
// PathObserver.php
class PathObserver
{
    public function saved(Path $path): void
    {
        // Инвалидировать кеш indexed_paths
        Cache::forget("blueprint:{$path->blueprint_id}:indexed_paths");

        // Если изменился is_indexed — реиндексировать Entry
        if ($path->wasChanged('is_indexed') || $path->wasRecentlyCreated) {
            dispatch(new ReindexBlueprintEntries($path->blueprint_id));
        }
    }

    public function deleted(Path $path): void
    {
        Cache::forget("blueprint:{$path->blueprint_id}:indexed_paths");
    }
}
```

---

## 8. Edge-cases и важные детали

### 8.0. Критические моменты реализации

#### 8.0.0. Рекурсивная материализация транзитивных зависимостей

**Проблема:** Если материализовать только один уровень (`B → A`, копируя только собственные поля `A`), то транзитивные embed'ы (`A → C`, `A → D`) не попадут в `B`.

**Пример поломки:**

```
Blueprint C:
  - fc1

Blueprint A:
  - fa1
  - group_c (группа) ← встроен C

Blueprint B:
  - fb1
  - group_a (группа) ← встроен A
```

**Без рекурсии** после материализации `B → A`:

```
B.fb1
B.group_a
B.group_a.fa1          ✅ есть
B.group_a.group_c      ✅ есть (как пустая группа)
B.group_a.group_c.fc1  ❌ НЕТ! (C не развернут)
```

Запрос `Entry::wherePath('group_a.group_c.fc1', ...)` не найдёт path и вернёт пустой результат, хотя логически такой путь должен существовать.

**Решение:** Рекурсивно обходить все `blueprint.embeds` и для каждого вложенного embed'а снова вызывать `copyBlueprintRecursive()`.

```php
// В copyBlueprintRecursive после копирования полей X:
foreach ($blueprint->embeds as $innerEmbed) {
    $childBlueprint = $innerEmbed->embeddedBlueprint; // Y

    $this->copyBlueprintRecursive(
        blueprint:       $childBlueprint,
        hostBlueprint:   $hostBlueprint,  // всё ещё B
        baseParentId:    /* копия host_path из X в B */,
        baseParentPath:  /* full_path копии */,
        rootEmbed:       $rootEmbed       // всё ещё B→A
    );
}
```

**Результат с рекурсией:**

```
B.fb1
B.group_a
B.group_a.fa1                          (source = A, embed = B→A)
B.group_a.group_c                      (source = A, embed = B→A)
B.group_a.group_c.fc1                  (source = C, embed = B→A) ✅
```

Все копии имеют `blueprint_embed_id = embed(B→A).id` → удаляются одной командой.

См. алгоритм в разделе 4.2.

#### 8.0.1. PRE-CHECK конфликтов full_path (раздел 8.0.1)

**Проблема:** UNIQUE constraint `(blueprint_id, full_path)` в таблице `paths` срабатывает **во время INSERT**. Если проверять конфликты после вставки, БД выбросит `Integrity constraint violation` раньше, чем сработает доменная валидация.

**Пример:**

```php
// ❌ НЕПРАВИЛЬНО (post-check)
$this->copyBlueprintRecursive(...); // вставляет копии
$this->validateNoPathConflicts();   // ← слишком поздно, БД уже упала
```

В `hostBlueprint` уже есть поле `meta.created_by`. При материализации `embeddedBlueprint`, у которого тоже есть `created_by` под `meta`, БД выбросит:

```
SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'blueprint_id-meta.created_by'
```

Пользователь получит техническую ошибку вместо понятного сообщения.

**Решение: PRE-CHECK перед вставкой**

```php
// ✅ ПРАВИЛЬНО (pre-check)
$this->validateNoPathConflictsBeforeMaterialization(
    $embeddedBlueprint,
    $hostBlueprint,
    $baseParentPath
); // вычисляет будущие пути и сверяет с существующими

$this->copyBlueprintRecursive(...); // только если конфликтов нет
```

Алгоритм:

1. Рекурсивно обходим структуру `embeddedBlueprint` (включая транзитивные embed'ы).
2. Вычисляем, какие `full_path` появятся в `hostBlueprint` (с учётом `baseParentPath`).
3. Одним запросом проверяем существующие пути:

```php
$existingPaths = Path::query()
    ->where('blueprint_id', $hostBlueprint->id)
    ->whereIn('full_path', $futurePaths)
    ->pluck('full_path');

if ($existingPaths->isNotEmpty()) {
    throw new EmbeddedBlueprintPathConflictException(
        "Конфликт путей: " . $existingPaths->implode(', ')
    );
}
```

4. Только если конфликтов нет → выполняем материализацию.

См. методы `validateNoPathConflictsBeforeMaterialization()` и `collectFuturePathsRecursive()` в разделе 4.2.1.

**Альтернатива (проще, но грубее):** ловить SQL-ошибку

```php
try {
    DB::transaction(function () {
        $this->copyBlueprintRecursive(...);
    });
} catch (QueryException $e) {
    if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'paths_full_path_unique')) {
        throw new EmbeddedBlueprintPathConflictException(
            "Конфликт путей при встраивании blueprint"
        );
    }
    throw $e;
}
```

Недостаток: не показывает, какие именно пути конфликтуют, и откатывает уже выполненную часть работы.

#### 8.0.2. Каскадные события для транзитивной рематериализации

**Проблема:** Без каскадных событий обновляется только **один уровень** зависимых blueprint'ов.

**Пример цепочки:**

```
Geo → Address → Company → Department
```

1. Изменяется `Geo` → событие `BlueprintStructureChanged(Geo)`.
2. Listener находит `Address`, рематериализует `Geo → Address`.
3. **НО:** `Company` и `Department` не получают событие → их копии полей `Geo` устарели!

**Решение:** каскадные события

После рематериализации каждого родителя явно триггерить событие для него:

```php
foreach ($directParents as $parentId) {
    $parent = Blueprint::find($parentId);

    // Рематериализуем embed'ы
    foreach ($parent->embeds as $embed) {
        if ($embed->embedded_blueprint_id === $blueprint->id) {
            $this->structureService->materializeEmbeddedBlueprint($embed);
        }
    }

    // КАСКАДНОЕ СОБЫТИЕ → запустит обновление следующего уровня
    event(new BlueprintStructureChanged($parent, $processed));
}
```

**Защита от зацикливания:** передавать в событии массив `$processedBlueprints`:

```php
class BlueprintStructureChanged
{
    public function __construct(
        public Blueprint $blueprint,
        public array $processedBlueprints = []
    ) {}
}
```

В listener'е проверять:

```php
if (in_array($blueprint->id, $processed, true)) {
    return; // уже обработан
}
```

См. раздел 5.3.2.

#### 8.0.3. UNIQUE constraint и порядок сохранения

**Проблема:** **Проблема:** UNIQUE constraint `(blueprint_id, full_path)` в таблице `paths`. Если сохранять копии с `full_path = ''` (или одинаковым временным значением), constraint будет нарушен.

**Решение:**

-   Создавать объекты Path в памяти БЕЗ сохранения.
-   Вычислять `full_path` для каждой копии.
-   Сохранять в порядке зависимостей (родители перед детьми), чтобы `parent_id` был корректным.
-   Каждое `save()` уже имеет финальный `full_path` → constraint не нарушается.

См. алгоритм в разделе 4.2.

#### 8.0.4. full_path — вычисляемое поле (должно быть guarded)

**Проблема:** `full_path` — служебное, вычисляемое поле (`parent.full_path` + `.` + `name`). Если оно в `$fillable`, пользователь может:

-   Задать произвольное значение через API/форму.
-   Нарушить согласованность дерева (`parent_id` / `name` ≠ `full_path`).
-   Случайно создать конфликт с UNIQUE constraint.

**Решение:**

1. **Убрать `full_path` из `$fillable`, добавить в `$guarded`:**

```php
protected $fillable = [
    'name', 'data_type', ...
    // 'full_path' — НЕ в fillable!
];

protected $guarded = [
    'source_blueprint_id', 'blueprint_embed_id', 'is_readonly',
    'full_path',  // ← вычисляемое поле
];
```

2. **Всегда вычислять `full_path` в одном месте:**

В `BlueprintStructureService` при создании/обновлении поля:

```php
$path->full_path = $path->parent
    ? $path->parent->full_path . '.' . $path->name
    : $path->name;
```

3. **Не принимать `full_path` из Request:**

```php
// Request validation
public function rules(): array
{
    return [
        'name' => 'required|string',
        'parent_id' => 'nullable|exists:paths,id',
        // 'full_path' — НЕ принимается из input
    ];
}
```

4. **Опционально:** mutator/accessor в модели

```php
// Игнорирует входящее значение и пересчитывает
public function setFullPathAttribute($value): void
{
    // Ничего не делаем — full_path вычисляется в сервисе
}

// Или можно сделать автовычисление (осторожно с производительностью!)
protected static function booted()
{
    static::saving(function (Path $path) {
        if (!$path->isEmbedded()) {
            $path->full_path = $path->parent
                ? $path->parent->full_path . '.' . $path->name
                : $path->name;
        }
    });
}
```

См. модель `Path` в разделе 7.2.

#### 8.0.5. Взаимные FK: порядок миграций

**Проблема:** `paths.blueprint_embed_id` ссылается на `blueprint_embeds.id`, а `blueprint_embeds.host_path_id` ссылается на `paths.id`. Создать обе таблицы с FK сразу невозможно.

**Решение:** 4 миграции в последовательности:

1. Создать `blueprints`.
2. Создать `paths` БЕЗ FK на `blueprint_embed_id` (только поле).
3. Создать `blueprint_embeds` с FK на `paths.id`.
4. Добавить FK `paths.blueprint_embed_id` → `blueprint_embeds.id`.

См. раздел 2.4.

#### 8.0.6. Требования к СУБД и CHECK constraints

**Проблема:** CHECK constraints в MySQL работают только с версии 8.0.16+. В старых версиях MySQL/MariaDB они либо игнорируются, либо работают некорректно.

**Риски:**

-   Инвариант «скопированное поле → readonly, source_blueprint_id/blueprint_embed_id NOT NULL» может нарушиться.
-   Данные станут несогласованными, если кто-то обойдёт слой приложения (прямой SQL, миграция, сидер).

**Решение:**

1. **Зафиксировать минимальные версии БД:**

    - MySQL 8.0.16+
    - MariaDB 10.2.1+
    - PostgreSQL 9.3+

2. **Для старых версий MySQL/MariaDB:** создать триггеры для валидации (см. раздел 2.0).

3. **Продублировать проверки в доменном слое:**

```php
// BlueprintStructureService
private function validatePathIntegrity(Path $path): void
{
    if ($path->source_blueprint_id !== null) {
        if ($path->blueprint_embed_id === null || !$path->is_readonly) {
            throw new \DomainException(
                'Скопированное поле должно иметь blueprint_embed_id и is_readonly = true'
            );
        }
    }
}
```

4. **Покрыть unit-тестами:**

```php
test('нельзя создать скопированное поле без blueprint_embed_id', function () {
    expect(fn() => Path::create([
        'source_blueprint_id' => 1,
        'blueprint_embed_id' => null,  // ← нарушение инварианта
        'is_readonly' => true,
    ]))->toThrow(\DomainException::class);
});
```

См. раздел 2.0 для полного примера триггеров.

#### 8.0.7. Защита служебных полей от ручного изменения

**Проблема:** пользователь может случайно или намеренно изменить `source_blueprint_id`, `blueprint_embed_id`, `is_readonly` через API, что сломает систему.

**Решение:**

#### 8.5.1. На уровне модели

```php
// Path.php
protected $guarded = [
    'source_blueprint_id',
    'blueprint_embed_id',
    'is_readonly',
];
```

#### 8.5.2. На уровне Request валидации

```php
// StorePathRequest.php / UpdatePathRequest.php
public function rules(): array
{
    return [
        'blueprint_id' => 'required|exists:blueprints,id',
        'parent_id' => 'nullable|exists:paths,id',
        'name' => 'required|string|max:255',
        'data_type' => 'required|in:string,text,int,float,bool,date,datetime,json,ref',
        'cardinality' => 'in:one,many',
        'is_required' => 'boolean',
        'is_indexed' => 'boolean',
        'sort_order' => 'integer',

        // Служебные поля НЕ принимаются из input
        // 'source_blueprint_id' - отсутствует
        // 'blueprint_embed_id' - отсутствует
        // 'is_readonly' - отсутствует
    ];
}

/**
 * Дополнительная проверка: нельзя редактировать скопированные поля.
 */
public function authorize(): bool
{
    if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
        $path = $this->route('path');

        if ($path && $path->isEmbedded()) {
            throw ValidationException::withMessages([
                'path' => 'Нельзя редактировать поля, скопированные из встроенного blueprint. '
                    . 'Измените исходный blueprint вместо этого.'
            ]);
        }
    }

    return true;
}
```

#### 8.5.3. На уровне контроллера

```php
// PathController.php
public function update(UpdatePathRequest $request, Path $path)
{
    // Дополнительная защита (дублирует authorize, но на всякий случай)
    if ($path->isEmbedded()) {
        return response()->json([
            'message' => 'Нельзя редактировать скопированные поля',
            'source_blueprint' => $path->sourceBlueprint->code,
        ], 403);
    }

    // Обновляем только разрешённые поля
    $path->update($request->validated());

    // Событие для рематериализации зависимых blueprint'ов
    event(new BlueprintStructureChanged($path->blueprint));

    return new PathResource($path);
}

public function destroy(Path $path)
{
    // Запрет удаления скопированных полей
    if ($path->isEmbedded()) {
        return response()->json([
            'message' => 'Нельзя удалить скопированное поле. Удалите встраивание вместо этого.',
            'embed_id' => $path->blueprint_embed_id,
        ], 403);
    }

    $path->delete();

    event(new BlueprintStructureChanged($path->blueprint));

    return response()->noContent();
}
```

#### 8.5.4. UI маркировка

В редакторе blueprint структуры визуально отмечать скопированные поля:

```json
{
    "id": 3,
    "name": "street",
    "full_path": "office_address.street",
    "data_type": "string",
    "is_readonly": true,
    "is_embedded": true,
    "source_blueprint": {
        "id": 2,
        "code": "address",
        "name": "Address"
    },
    "ui_hint": "readonly",
    "ui_message": "Это поле скопировано из blueprint 'Address'. Изменения можно вносить только в исходный blueprint."
}
```

### 8.6. Изменение типа/структуры поля в исходном blueprint'е

Если в A изменить `data_type` поля `fa1` с `string` на `int`:

1. Событие `BlueprintStructureChanged(A)` запускает рематериализацию в B, C, D...
2. Все копии `fa1` в B, C, D получают новый `data_type = 'int'`.
3. Запускается реиндексация Entry blueprint'ов B, C, D:
    - старые `doc_values` с `value_string` удаляются,
    - новые создаются с `value_int` (если данные конвертируемы).

**Потенциальная проблема:** Entry могут содержать данные, не совместимые с новым типом.

**Решение:**

-   Валидация на уровне изменения поля: предупредить пользователя, что изменение типа потребует переиндексации и может привести к ошибкам.
-   Опционально: запретить изменение `data_type`, если blueprint встроен в другие, и предложить создать новое поле.

---

## 9. Оптимизация: Closure Table для зависимостей (опционально)

При очень большом графе blueprint'ов DFS по `blueprint_embeds` может стать узким местом.

### 9.1. Таблица `blueprint_dependencies`

```sql
CREATE TABLE blueprint_dependencies (
    ancestor_id BIGINT UNSIGNED NOT NULL,      -- кто зависит (родитель)
    descendant_id BIGINT UNSIGNED NOT NULL,    -- от кого зависит (потомок)
    depth INT UNSIGNED NOT NULL,               -- глубина (1 = прямая связь)

    PRIMARY KEY (ancestor_id, descendant_id),

    CONSTRAINT fk_deps_ancestor FOREIGN KEY (ancestor_id)
        REFERENCES blueprints(id) ON DELETE CASCADE,

    CONSTRAINT fk_deps_descendant FOREIGN KEY (descendant_id)
        REFERENCES blueprints(id) ON DELETE CASCADE,

    INDEX idx_deps_descendant (descendant_id),
    INDEX idx_deps_ancestor (ancestor_id)
);
```

### 9.2. Поддержка при добавлении embed'а

При создании `BlueprintEmbed` (B встраивает A):

1. Вставить `{ancestor: B, descendant: A, depth: 1}`.
2. Для всех предков B вставить `{ancestor: ancestor(B), descendant: A, depth: depth+1}`.
3. Для всех потомков A вставить `{ancestor: B, descendant: descendant(A), depth: depth+1}`.

### 9.3. Проверка циклов

Вместо DFS:

```php
$exists = DB::table('blueprint_dependencies')
    ->where('ancestor_id', $embedded->id)
    ->where('descendant_id', $parent->id)
    ->exists();

if ($exists) {
    throw new \LogicException('Циклическая зависимость');
}
```

### 9.4. Получение всех зависимых

```php
$dependentIds = DB::table('blueprint_dependencies')
    ->where('descendant_id', $blueprintId)
    ->pluck('ancestor_id');
```

**Когда внедрять:** если количество blueprint'ов > 100 и глубина встраиваний > 3–4 уровней.

---

## 12. Тестирование

### 12.1. Unit-тесты для индексации

**tests/Unit/HasDocumentDataTest.php:**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HasDocumentDataTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_indexes_scalar_values_on_save(): void
    {
        $blueprint = Blueprint::factory()->create();

        $path = Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'author.name',
            'data_type' => 'string',
            'cardinality' => 'one',
            'is_indexed' => true,
        ]);

        $entry = Entry::create([
            'blueprint_id' => $blueprint->id,
            'data_json' => [
                'author' => ['name' => 'John Doe'],
            ],
        ]);

        // Проверяем, что создалась запись в doc_values
        $this->assertDatabaseHas('doc_values', [
            'entry_id' => $entry->id,
            'path_id' => $path->id,
            'array_index' => 0,
            'value_string' => 'John Doe',
        ]);
    }

    /** @test */
    public function it_indexes_array_values(): void
    {
        $blueprint = Blueprint::factory()->create();

        $path = Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'tags',
            'data_type' => 'string',
            'cardinality' => 'many',
            'is_indexed' => true,
        ]);

        $entry = Entry::create([
            'blueprint_id' => $blueprint->id,
            'data_json' => [
                'tags' => ['cms', 'laravel', 'php'],
            ],
        ]);

        // Проверяем, что созданы 3 записи с разными array_index
        $this->assertDatabaseHas('doc_values', [
            'entry_id' => $entry->id,
            'path_id' => $path->id,
            'array_index' => 1,
            'value_string' => 'cms',
        ]);

        $this->assertDatabaseHas('doc_values', [
            'entry_id' => $entry->id,
            'path_id' => $path->id,
            'array_index' => 2,
            'value_string' => 'laravel',
        ]);

        $this->assertDatabaseHas('doc_values', [
            'entry_id' => $entry->id,
            'path_id' => $path->id,
            'array_index' => 3,
            'value_string' => 'php',
        ]);
    }

    /** @test */
    public function it_indexes_ref_fields(): void
    {
        $blueprint = Blueprint::factory()->create();

        $path = Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'relatedArticles',
            'data_type' => 'ref',
            'cardinality' => 'many',
            'is_indexed' => true,
        ]);

        $targetEntry1 = Entry::factory()->create();
        $targetEntry2 = Entry::factory()->create();

        $entry = Entry::create([
            'blueprint_id' => $blueprint->id,
            'data_json' => [
                'relatedArticles' => [$targetEntry1->id, $targetEntry2->id],
            ],
        ]);

        // Проверяем doc_refs
        $this->assertDatabaseHas('doc_refs', [
            'entry_id' => $entry->id,
            'path_id' => $path->id,
            'array_index' => 1,
            'target_entry_id' => $targetEntry1->id,
        ]);

        $this->assertDatabaseHas('doc_refs', [
            'entry_id' => $entry->id,
            'path_id' => $path->id,
            'array_index' => 2,
            'target_entry_id' => $targetEntry2->id,
        ]);
    }

    /** @test */
    public function it_reindexes_on_update(): void
    {
        $blueprint = Blueprint::factory()->create();

        $path = Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'author.name',
            'data_type' => 'string',
            'is_indexed' => true,
        ]);

        $entry = Entry::create([
            'blueprint_id' => $blueprint->id,
            'data_json' => ['author' => ['name' => 'John']],
        ]);

        // Обновляем
        $entry->update([
            'data_json' => ['author' => ['name' => 'Jane']],
        ]);

        // Старое значение удалено
        $this->assertDatabaseMissing('doc_values', [
            'entry_id' => $entry->id,
            'value_string' => 'John',
        ]);

        // Новое значение создано
        $this->assertDatabaseHas('doc_values', [
            'entry_id' => $entry->id,
            'value_string' => 'Jane',
        ]);
    }

    /** @test */
    public function it_ignores_non_indexed_paths(): void
    {
        $blueprint = Blueprint::factory()->create();

        Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'internal_note',
            'data_type' => 'text',
            'is_indexed' => false, // НЕ индексируется
        ]);

        $entry = Entry::create([
            'blueprint_id' => $blueprint->id,
            'data_json' => ['internal_note' => 'Some note'],
        ]);

        // Не должно быть записей в doc_values
        $this->assertDatabaseMissing('doc_values', [
            'entry_id' => $entry->id,
        ]);
    }
}
```

### 12.2. Feature-тесты для запросов

**tests/Feature/EntryQueryTest.php:**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EntryQueryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_filters_entries_by_path_value(): void
    {
        $blueprint = Blueprint::factory()->create();

        Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'author.name',
            'data_type' => 'string',
            'is_indexed' => true,
        ]);

        $entry1 = Entry::create([
            'blueprint_id' => $blueprint->id,
            'data_json' => ['author' => ['name' => 'John Doe']],
        ]);

        $entry2 = Entry::create([
            'blueprint_id' => $blueprint->id,
            'data_json' => ['author' => ['name' => 'Jane Smith']],
        ]);

        // Запрос
        $results = Entry::wherePath('author.name', '=', 'John Doe')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($entry1->id, $results->first()->id);
    }

    /** @test */
    public function it_filters_by_ref_field(): void
    {
        $blueprint = Blueprint::factory()->create();

        Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'article',
            'data_type' => 'ref',
            'is_indexed' => true,
        ]);

        $targetEntry = Entry::factory()->create();

        $entry1 = Entry::create([
            'blueprint_id' => $blueprint->id,
            'data_json' => ['article' => $targetEntry->id],
        ]);

        $entry2 = Entry::create([
            'blueprint_id' => $blueprint->id,
            'data_json' => ['article' => 999],
        ]);

        // Запрос
        $results = Entry::whereRef('article', $targetEntry->id)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($entry1->id, $results->first()->id);
    }

    /** @test */
    public function it_combines_multiple_filters(): void
    {
        $blueprint = Blueprint::factory()->create();

        Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'author.name',
            'data_type' => 'string',
            'is_indexed' => true,
        ]);

        Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'price',
            'data_type' => 'int',
            'is_indexed' => true,
        ]);

        Entry::create([
            'blueprint_id' => $blueprint->id,
            'data_json' => [
                'author' => ['name' => 'John'],
                'price' => 50,
            ],
        ]);

        $entry2 = Entry::create([
            'blueprint_id' => $blueprint->id,
            'data_json' => [
                'author' => ['name' => 'John'],
                'price' => 150,
            ],
        ]);

        Entry::create([
            'blueprint_id' => $blueprint->id,
            'data_json' => [
                'author' => ['name' => 'Jane'],
                'price' => 150,
            ],
        ]);

        // Комбинированный запрос
        $results = Entry::query()
            ->wherePath('author.name', '=', 'John')
            ->wherePath('price', '>', 100)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($entry2->id, $results->first()->id);
    }
}
```

### 12.3. Интеграционные тесты для материализации

**tests/Feature/BlueprintEmbedTest.php:**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BlueprintEmbedTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function materialized_paths_are_indexed_correctly(): void
    {
        // Создаём компонент SEO
        $seoComponent = Blueprint::factory()->create([
            'code' => 'seo_fields',
            'type' => 'component',
        ]);

        $seoPath = Path::factory()->create([
            'blueprint_id' => $seoComponent->id,
            'full_path' => 'metaTitle',
            'data_type' => 'string',
            'is_indexed' => true,
        ]);

        // Создаём full Blueprint
        $articleBlueprint = Blueprint::factory()->create([
            'code' => 'article',
            'type' => 'full',
        ]);

        // Attach компонента → материализация
        $structureService = app(\App\Services\BlueprintStructureService::class);
        $embed = $structureService->createEmbed(
            $articleBlueprint,
            $seoComponent,
            Path::factory()->create([
                'blueprint_id' => $articleBlueprint->id,
                'full_path' => 'seo',
                'data_type' => 'json',
            ])
        );

        // Проверяем, что материализованный Path создан
        $materializedPath = Path::query()
            ->where('blueprint_id', $articleBlueprint->id)
            ->where('full_path', 'seo.metaTitle')
            ->first();

        $this->assertNotNull($materializedPath);
        $this->assertEquals($seoComponent->id, $materializedPath->source_blueprint_id);
        $this->assertEquals($embed->id, $materializedPath->blueprint_embed_id);

        // Создаём Entry с данными
        $entry = Entry::create([
            'blueprint_id' => $articleBlueprint->id,
            'data_json' => [
                'seo' => [
                    'metaTitle' => 'SEO Title',
                ],
            ],
        ]);

        // Проверяем индексацию материализованного поля
        $this->assertDatabaseHas('doc_values', [
            'entry_id' => $entry->id,
            'path_id' => $materializedPath->id,
            'value_string' => 'SEO Title',
        ]);

        // Проверяем запрос
        $results = Entry::wherePath('seo.metaTitle', '=', 'SEO Title')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($entry->id, $results->first()->id);
    }
}
```

### 12.4. Performance-тесты

**tests/Performance/IndexingBenchmarkTest.php:**

```php
<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Models\Blueprint;
use App\Models\Entry;
use App\Models\Path;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IndexingBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_indexes_100_entries_in_reasonable_time(): void
    {
        $blueprint = Blueprint::factory()->create();

        // Создаём 10 индексируемых полей
        Path::factory()->count(10)->create([
            'blueprint_id' => $blueprint->id,
            'is_indexed' => true,
        ]);

        $start = microtime(true);

        // Создаём 100 Entry
        Entry::factory()->count(100)->create([
            'blueprint_id' => $blueprint->id,
        ]);

        $duration = microtime(true) - $start;

        // Ожидаем < 5 секунд для 100 Entry
        $this->assertLessThan(5, $duration, "Индексация 100 Entry заняла {$duration}s");
    }

    /** @test */
    public function it_queries_indexed_fields_fast(): void
    {
        $blueprint = Blueprint::factory()->create();

        $path = Path::factory()->create([
            'blueprint_id' => $blueprint->id,
            'full_path' => 'author.name',
            'data_type' => 'string',
            'is_indexed' => true,
        ]);

        // Создаём 1000 Entry
        Entry::factory()->count(1000)->create([
            'blueprint_id' => $blueprint->id,
        ]);

        $start = microtime(true);

        // Запрос
        Entry::wherePath('author.name', '=', 'John Doe')->get();

        $duration = microtime(true) - $start;

        // Ожидаем < 100ms для запроса
        $this->assertLessThan(0.1, $duration, "Запрос занял {$duration}s");
    }
}
```

---

## 13. Приоритетный чек-лист реализации

### Последовательность внедрения (от критичного к важному)

#### Приоритет 1: Корректность данных

1. **События и транзитивная рематериализация (раздел 8.0.2)**

    - Реализовать каскадные события `BlueprintStructureChanged` с передачей `$processedBlueprints`.
    - Добавить защиту от зацикливания в listener'е.
    - **Тест:** длинная цепочка `Geo → Address → Company → Department`, изменение `Geo` обновляет `Department`.

2. **PRE-CHECK конфликтов full_path (раздел 8.0.1)**

    - Реализовать `validateNoPathConflictsBeforeMaterialization()` и `collectFuturePathsRecursive()`.
    - Выбрасывать `EmbeddedBlueprintPathConflictException` **до** вставки.
    - **Тест:** встраивание с конфликтом путей выбрасывает доменное исключение, транзакция откатывается.

3. **Защита `full_path` (раздел 8.0.4)**
    - Убрать `full_path` из `$fillable`, добавить в `$guarded`.
    - Централизовать вычисление `full_path` в `BlueprintStructureService`.
    - Не принимать `full_path` из Request/DTO.
    - **Тест:** попытка задать `full_path` через `create()` игнорируется, значение вычисляется автоматически.

#### Приоритет 2: Надёжность БД

4. **Проверка требований к СУБД (раздел 8.0.6)**

    - Зафиксировать в документации: MySQL 8.0.16+, MariaDB 10.2.1+.
    - Если нужна поддержка старых версий: создать триггеры для валидации инвариантов.
    - Продублировать валидацию в доменном слое (`validatePathIntegrity()`).
    - **Тест:** попытка нарушить инвариант «скопированное поле → readonly» выбрасывает исключение.

5. **Защита служебных полей (раздел 8.0.7)**
    - Убедиться, что `source_blueprint_id`, `blueprint_embed_id`, `is_readonly` в `$guarded`.
    - Блокировать редактирование/удаление embedded-полей на уровне Request/Controller.
    - **Тест:** API возвращает 403 при попытке изменить скопированное поле.

#### Приоритет 3: Покрытие edge-cases

6. **Множественное встраивание одного blueprint (тест 9)**

    - Проверить, что `Address` можно встроить дважды в `Company` (`legal_address`, `postal_address`).
    - Убедиться, что `full_path` различаются, `blueprint_embed_id` разные.
    - Удаление одного embed'а не трогает другой.

7. **Встраивание в корень с host_path = NULL (тест 10)**

    - Проверить, что поля попадают в корень с `parent_id = NULL`.
    - Проверить конфликты при нескольких embed'ах в корень.

8. **Транзитивное встраивание (тест 6)**

    - Проверить, что `D → C → A → B` корректно материализуется.
    - Проверить, что изменение `D` обновляет `B` через каскад событий (тест 11).

9. **Конфликты при встраивании (тест 8)**
    - Проверить, что pre-check ловит конфликт `meta.created_by`.
    - Проверить, что транзакция откатывается без частичных копий.

#### Приоритет 4: Оптимизация и документация

10. **Closure Table для зависимостей (раздел 9, опционально)**

    -   Если количество blueprint'ов > 100 и глубина > 3-4, внедрить `blueprint_dependencies`.
    -   Заменить DFS на запросы по closure table для проверки циклов и поиска зависимых.

11. **Документация API**

    -   Обновить Scribe-комментарии для контроллеров.
    -   Добавить примеры транзитивного встраивания в API-доку.

12. **Performance-тесты**
    -   Измерить время материализации для глубоких структур (5+ уровней).
    -   Измерить время реиндексации при изменении базового blueprint'а.

---

## 13.1. Мониторинг и производительность

### 13.1.1. Метрики для отслеживания

**Ключевые показатели:**

1. **Размер индекса:**

    - Количество записей в `doc_values` и `doc_refs`
    - Рост за день/неделю
    - Соотношение к количеству Entry

2. **Производительность индексации:**

    - Время `syncDocumentIndex()` для Entry
    - Среднее время реиндексации Blueprint
    - Количество индексируемых полей на Blueprint

3. **Производительность запросов:**
    - Время выполнения `wherePath()` / `whereRef()`
    - Использование индексов (EXPLAIN запросы)
    - Количество JOIN'ов в запросах

**Пример мониторинга с Laravel Telescope:**

```php
// AppServiceProvider.php
use Illuminate\Support\Facades\Event;
use App\Models\Entry;

public function boot(): void
{
    // Отслеживание времени индексации
    Entry::saved(function ($entry) {
        $start = microtime(true);
        $entry->syncDocumentIndex();
        $duration = microtime(true) - $start;

        if ($duration > 1) {
            \Log::warning("Медленная индексация Entry #{$entry->id}: {$duration}s");
        }
    });
}
```

### 13.1.2. Оптимизация запросов

**Проблема N+1:**

```php
// ❌ ПЛОХО: N+1 запросы
$entries = Entry::all();
foreach ($entries as $entry) {
    $authorName = $entry->getPath('author.name'); // Каждый раз обращение к БД
}

// ✅ ХОРОШО: Eager loading
$entries = Entry::with(['values' => function ($query) {
    $query->whereHas('path', fn($q) => $q->where('full_path', 'author.name'));
}])->get();

foreach ($entries as $entry) {
    $authorName = $entry->getPath('author.name'); // Из загруженных данных
}
```

**Использование индексов БД:**

```sql
-- Проверка использования индексов
EXPLAIN SELECT entries.*
FROM entries
INNER JOIN doc_values ON doc_values.entry_id = entries.id
INNER JOIN paths ON paths.id = doc_values.path_id
WHERE paths.full_path = 'author.name'
  AND doc_values.value_string = 'John Doe';

-- Ожидаемый результат:
-- type: ref
-- key: idx_path_string (используется индекс)
```

**Добавление составных индексов для горячих запросов:**

```sql
-- Если часто фильтруем по blueprint_id + path
ALTER TABLE doc_values
ADD INDEX idx_blueprint_path_string (path_id, value_string(255));

-- Для ref-запросов
ALTER TABLE doc_refs
ADD INDEX idx_path_target (path_id, target_entry_id);
```

### 13.1.3. Кеширование результатов запросов

```php
use Illuminate\Support\Facades\Cache;

class EntryRepository
{
    /**
     * Получить Entry с кешированием.
     */
    public function getBySlug(string $slug): ?Entry
    {
        return Cache::remember(
            "entry:slug:{$slug}",
            3600,
            fn() => Entry::with('values', 'refs')->where('slug', $slug)->first()
        );
    }

    /**
     * Получить Entry по фильтру с кешированием.
     */
    public function findByAuthor(string $authorName): Collection
    {
        return Cache::remember(
            "entries:author:" . md5($authorName),
            1800,
            fn() => Entry::wherePath('author.name', '=', $authorName)->get()
        );
    }

    /**
     * Инвалидация кеша при изменении Entry.
     */
    public function save(Entry $entry): bool
    {
        $saved = $entry->save();

        if ($saved) {
            Cache::forget("entry:slug:{$entry->slug}");
            Cache::forget("entries:author:" . md5($entry->getPath('author.name')));
        }

        return $saved;
    }
}
```

### 13.1.4. Очередь для асинхронной индексации

Для больших Entry или при пакетных обновлениях используйте очередь:

```php
// Job для асинхронной индексации
namespace App\Jobs;

use App\Models\Entry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class IndexEntry implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public int $entryId
    ) {}

    public function handle(): void
    {
        $entry = Entry::find($this->entryId);

        if ($entry) {
            $entry->syncDocumentIndex();
        }
    }
}

// Использование
Entry::saved(function ($entry) {
    // Синхронная индексация только для небольших Entry
    if ($entry->shouldIndexAsync()) {
        dispatch(new IndexEntry($entry->id));
    } else {
        $entry->syncDocumentIndex();
    }
});
```

### 13.1.5. Архивация старых индексов

Периодическая очистка неиспользуемых индексов:

```php
// Command: CleanupOldIndexes
namespace App\Console\Commands;

use App\Models\Entry;
use Illuminate\Console\Command;

class CleanupOldIndexes extends Command
{
    protected $signature = 'indexes:cleanup {--days=90}';
    protected $description = 'Удалить индексы для удалённых Entry';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        // Найти Entry с soft delete старше N дней
        $deletedEntries = Entry::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays($days))
            ->pluck('id');

        if ($deletedEntries->isEmpty()) {
            $this->info('Нет Entry для очистки.');
            return Command::SUCCESS;
        }

        $this->info("Удаление индексов для {$deletedEntries->count()} Entry...");

        // Удаляем индексы (CASCADE через FK)
        Entry::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays($days))
            ->forceDelete();

        $this->info('✓ Готово.');

        return Command::SUCCESS;
    }
}
```

### 13.1.6. Профилирование с Laravel Debugbar

```php
// config/debugbar.php
'collectors' => [
    'db' => true, // SQL запросы
    'time' => true, // Время выполнения
    'memory' => true, // Использование памяти
],

// Использование
use DebugBar\DebugBar;

$entries = Entry::wherePath('author.name', '=', 'John')->get();

// Смотрим в Debugbar:
// - Количество SQL запросов
// - Время выполнения
// - Использование индексов
```

---

## 13.2. REST API и Scribe документация

### 13.2.1. Контроллеры для Entry API

**app/Http/Controllers/Api/EntryController.php:**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEntryRequest;
use App\Http\Requests\UpdateEntryRequest;
use App\Http\Resources\EntryResource;
use App\Models\Entry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * @group Entry Management
 *
 * API для управления Entry (документами).
 */
class EntryController extends Controller
{
    /**
     * Список Entry.
     *
     * Получить список Entry с фильтрацией и пагинацией.
     *
     * @queryParam blueprint_id int Фильтр по Blueprint. Example: 1
     * @queryParam status string Фильтр по статусу. Example: published
     * @queryParam search string Поиск по title. Example: Laravel
     * @queryParam per_page int Записей на страницу. Example: 15
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "blueprint_id": 1,
     *       "title": "How to Build CMS",
     *       "slug": "how-to-build-cms",
     *       "status": "published",
     *       "published_at": "2024-01-15T10:30:00Z",
     *       "data_json": {...},
     *       "created_at": "2024-01-10T12:00:00Z",
     *       "updated_at": "2024-01-15T10:30:00Z"
     *     }
     *   ],
     *   "links": {...},
     *   "meta": {...}
     * }
     */
    public function index(Request $request): ResourceCollection
    {
        $query = Entry::query()
            ->with(['blueprint', 'author']);

        // Фильтры
        if ($request->has('blueprint_id')) {
            $query->where('blueprint_id', $request->input('blueprint_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%");
        }

        // Пагинация
        $perPage = (int) $request->input('per_page', 15);
        $entries = $query->paginate($perPage);

        return EntryResource::collection($entries);
    }

    /**
     * Создать Entry.
     *
     * @bodyParam blueprint_id int required ID Blueprint. Example: 1
     * @bodyParam title string required Заголовок. Example: New Article
     * @bodyParam slug string required URL-идентификатор. Example: new-article
     * @bodyParam status string Статус. Example: draft
     * @bodyParam data_json object required Динамические данные. Example: {"content": "Article content..."}
     *
     * @response 201 {
     *   "data": {
     *     "id": 2,
     *     "blueprint_id": 1,
     *     "title": "New Article",
     *     "slug": "new-article",
     *     "status": "draft",
     *     "data_json": {...},
     *     "created_at": "2024-01-20T14:00:00Z",
     *     "updated_at": "2024-01-20T14:00:00Z"
     *   }
     * }
     */
    public function store(StoreEntryRequest $request): EntryResource
    {
        $entry = Entry::create($request->validated());

        return new EntryResource($entry);
    }

    /**
     * Получить Entry.
     *
     * @urlParam id int required ID Entry. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "blueprint_id": 1,
     *     "title": "How to Build CMS",
     *     "slug": "how-to-build-cms",
     *     "status": "published",
     *     "published_at": "2024-01-15T10:30:00Z",
     *     "data_json": {...},
     *     "created_at": "2024-01-10T12:00:00Z",
     *     "updated_at": "2024-01-15T10:30:00Z"
     *   }
     * }
     */
    public function show(Entry $entry): EntryResource
    {
        $entry->load(['blueprint', 'author', 'values.path', 'refs.target']);

        return new EntryResource($entry);
    }

    /**
     * Обновить Entry.
     *
     * @urlParam id int required ID Entry. Example: 1
     * @bodyParam title string Заголовок. Example: Updated Title
     * @bodyParam status string Статус. Example: published
     * @bodyParam data_json object Динамические данные. Example: {"content": "Updated content..."}
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "title": "Updated Title",
     *     "status": "published",
     *     "data_json": {...},
     *     "updated_at": "2024-01-20T15:00:00Z"
     *   }
     * }
     */
    public function update(UpdateEntryRequest $request, Entry $entry): EntryResource
    {
        $entry->update($request->validated());

        return new EntryResource($entry);
    }

    /**
     * Удалить Entry.
     *
     * @urlParam id int required ID Entry. Example: 1
     *
     * @response 204
     */
    public function destroy(Entry $entry)
    {
        $entry->delete();

        return response()->noContent();
    }

    /**
     * Запросить Entry по динамическим полям.
     *
     * Выполнить запрос с фильтрацией по индексированным полям.
     *
     * @bodyParam blueprint_id int required ID Blueprint. Example: 1
     * @bodyParam filters array required Массив фильтров. Example: [{"path": "author.name", "operator": "=", "value": "John Doe"}]
     * @bodyParam per_page int Записей на страницу. Example: 15
     *
     * @response 200 {
     *   "data": [...],
     *   "links": {...},
     *   "meta": {...}
     * }
     */
    public function query(Request $request): ResourceCollection
    {
        $request->validate([
            'blueprint_id' => 'required|exists:blueprints,id',
            'filters' => 'required|array',
            'filters.*.path' => 'required|string',
            'filters.*.operator' => 'required|string|in:=,!=,>,<,>=,<=,like',
            'filters.*.value' => 'required',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Entry::where('blueprint_id', $request->input('blueprint_id'));

        // Применяем фильтры
        foreach ($request->input('filters') as $filter) {
            $query->wherePath($filter['path'], $filter['operator'], $filter['value']);
        }

        $perPage = (int) $request->input('per_page', 15);
        $entries = $query->paginate($perPage);

        return EntryResource::collection($entries);
    }
}
```

### 13.2.2. Resource для API ответов

**app/Http/Resources/EntryResource.php:**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Entry
 */
class EntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'blueprint_id' => $this->blueprint_id,
            'blueprint' => new BlueprintResource($this->whenLoaded('blueprint')),

            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),

            'author_id' => $this->author_id,
            'author' => new UserResource($this->whenLoaded('author')),

            'data_json' => $this->data_json,

            // Индексированные значения (если загружены)
            'values' => DocValueResource::collection($this->whenLoaded('values')),
            'refs' => DocRefResource::collection($this->whenLoaded('refs')),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
```

### 13.2.3. Генерация документации Scribe

**config/scribe.php:**

```php
return [
    'type' => 'laravel',

    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/*'],
                'domains' => ['*'],
            ],
            'include' => [],
            'exclude' => [],
            'apply' => [
                'headers' => [
                    'Authorization' => 'Bearer {token}',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ],
        ],
    ],

    'static' => [
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        'add_routes' => true,
        'docs_url' => '/docs',
    ],

    'example_languages' => [
        'bash',
        'javascript',
        'php',
        'python',
    ],
];
```

**Команды для генерации:**

```bash
# Сгенерировать документацию
php artisan scribe:generate

# Просмотр документации
# http://localhost/docs

# Обновить после изменений в контроллерах
php artisan scribe:generate --force
```

### 13.2.4. Примеры API запросов

**Создание Entry:**

```bash
curl -X POST http://localhost/api/entries \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "blueprint_id": 1,
    "title": "New Article",
    "slug": "new-article",
    "status": "draft",
    "data_json": {
      "content": "Article content...",
      "author": {
        "name": "John Doe",
        "bio": "Developer"
      },
      "tags": ["cms", "laravel"]
    }
  }'
```

**Запрос с фильтрацией:**

```bash
curl -X POST http://localhost/api/entries/query \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "blueprint_id": 1,
    "filters": [
      {
        "path": "author.name",
        "operator": "=",
        "value": "John Doe"
      },
      {
        "path": "status",
        "operator": "=",
        "value": "published"
      }
    ],
    "per_page": 20
  }'
```

**JavaScript пример (Fetch API):**

```javascript
// Получить список Entry
const response = await fetch(
    "http://localhost/api/entries?blueprint_id=1&status=published",
    {
        headers: {
            Authorization: "Bearer YOUR_TOKEN",
            Accept: "application/json",
        },
    }
);

const data = await response.json();
console.log(data.data); // Массив Entry

// Создать Entry
const newEntry = await fetch("http://localhost/api/entries", {
    method: "POST",
    headers: {
        Authorization: "Bearer YOUR_TOKEN",
        "Content-Type": "application/json",
        Accept: "application/json",
    },
    body: JSON.stringify({
        blueprint_id: 1,
        title: "New Article",
        slug: "new-article",
        status: "draft",
        data_json: {
            content: "Article content...",
            author: {
                name: "John Doe",
            },
        },
    }),
});

const entry = await newEntry.json();
console.log(entry.data);
```

---

## 14. Итог

### Интеграция с stupidCMS ✅

**Ключевое архитектурное решение:**

-   ✅ **Blueprint интегрирован через PostType** — используется связь `PostType.blueprint_id` (nullable)
-   ✅ **Entry наследует blueprint через PostType** — `$entry->postType->blueprint`
-   ✅ **Существующая таблица entries** — минимум изменений, только добавление trait и Observer
-   ✅ **Гибридный режим** — Entry может работать с blueprint или без него (обратная совместимость)
-   ✅ **Постепенная миграция** — можно подключать blueprint по типам контента
-   ✅ **Централизованное управление** — все Entry одного PostType используют единую структуру

**Преимущества интеграции:**

-   Не ломает текущую архитектуру stupidCMS
-   Полная обратная совместимость
-   Минимальные изменения в существующем коде
-   Индексация только для Entry с blueprint
-   Понятная семантика: `$entry->postType->blueprint`

### Критические исправления (обязательны для production)

1. **PRE-CHECK конфликтов:** проверка `full_path` **до** вставки → понятные доменные ошибки вместо SQL-исключений.
2. **Каскадные события:** изменение blueprint'а триггерит цепочку событий → транзитивная рематериализация работает корректно.
3. **`full_path` в guarded:** защита от ручного изменения вычисляемого поля → согласованность дерева гарантирована.
4. **Требования к БД:** MySQL 8.0.16+ или триггеры для старых версий → CHECK constraints работают.
5. **Защита служебных полей:** `source_blueprint_id`, `blueprint_embed_id`, `is_readonly`, `full_path` в `$guarded` → невозможно сломать систему через API.
6. **Миграции:** **5 миграций** в строгой последовательности (добавлена миграция `post_types.blueprint_id`).

### Архитектура

-   **Интеграция через PostType:** Blueprint крепится к PostType, Entry наследует blueprint через связь.
-   Шаблоны больше не разделены на **full** и **component** — любой `Blueprint` может быть и самостоятельным, и встраиваемым.
-   **Множественное встраивание:** один blueprint можно встроить в другой **несколько раз** под разными полями (например, `Address` → `office_address` и `legal_address` в `Company`).
-   **Многоуровневое встраивание:** `host_path` может находиться **на любом уровне вложенности**, не только в корне (например, `ContactInfo` в `Article.author.contacts`).
-   Встраивание реализовано через `blueprint_embeds` и копирование `paths` с признаками:
    -   `blueprint_embed_id` — привязка копии к конкретному встраиванию (позволяет различать несколько встраиваний одного blueprint'а),
    -   `source_blueprint_id` — откуда скопировано,
    -   `is_readonly = 1` — запрет редактирования.
-   Циклы в графе встраиваний запрещены через DFS-проверку на уровне blueprint'ов (или Closure Table для масштабируемости).

### Материализация

-   **Рекурсивная:** встраивание `B → A` разворачивает не только поля `A`, но и все транзитивные embed'ы (`A → C`, `C → D`, ...).
-   Все копии (включая транзитивные) имеют `blueprint_embed_id = embed(B→A).id` → удаляются одной командой `WHERE blueprint_embed_id = ?`.
-   `source_blueprint_id` различает исходный шаблон: поля из `A` → `source = A`, поля из `C` → `source = C`.
-   Оптимизация: без лишних `find()`, вся операция в транзакции.
-   **Критично:** сохранение копий только после вычисления `full_path` (иначе нарушается UNIQUE constraint).
-   Обход в порядке зависимостей: родители сохраняются перед детьми, чтобы `parent_id` и `full_path` всегда были корректными.
-   Валидация конфликтов `full_path` до завершения материализации.

### Обработка изменений

-   Использование доменного события `BlueprintStructureChanged` вместо Observer → дебаунс и батчинг.
-   Рематериализация всех зависимых blueprint'ов транзитивно.
-   Опционально: версионирование структуры (`structure_version`) для отслеживания устаревших Entry.

### БД и производительность

-   **Миграции:** **5 миграций** в строгой последовательности из-за взаимных FK между `paths` и `blueprint_embeds` + интеграция `post_types.blueprint_id`.
-   CHECK-constraints для инвариантов (`source_blueprint_id` ↔ `is_readonly`).
-   Индексы под реальные запросы: `blueprint_id`, `source_blueprint_id`, `(blueprint_id, parent_id, sort_order)`, `embedded_blueprint_id`.
-   **FK на PostType:** `post_types.blueprint_id` (nullable) с ON DELETE RESTRICT.
-   Опциональная Closure Table для больших графов зависимостей.

### Edge-cases

-   Конфликты путей при встраивании → валидация и понятная ошибка.
-   Переименование `host_path` → автоматическая рематериализация.
-   Удаление встроенного blueprint'а → `ON DELETE RESTRICT` + проверка в UI.
-   Каскадное удаление `host_path` → предупреждение пользователя.
-   Изменение типа поля в исходном blueprint'е → реиндексация с потенциальными ошибками конвертации.

### Laravel-слой

-   Модели с удобными связями и скоупами (`own()`, `embedded()`, `readonly()`, `isEmbedded()`, `isOwn()`).
-   **Защита служебных полей:** `source_blueprint_id`, `blueprint_embed_id`, `is_readonly` исключены из "$fillable" и защищены на уровне Request/Controller.
-   Сервисный слой `BlueprintStructureService` для централизации логики.
-   Доменные события для управления жизненным циклом структуры.

### Индексация и запросы

-   **Автоматическая индексация через PostType:** Entry автоматически индексируются в `doc_values` и `doc_refs` при сохранении благодаря трейту `HasDocumentData`, **только если** `postType.blueprint_id` NOT NULL.
-   **Гибридный режим:** Entry без blueprint (legacy) не индексируются, `data_json` остается произвольным.
-   **Эффективные запросы:** скопированные поля (включая транзитивные) в целевом blueprint получают полноценные `full_path` и участвуют в индексе так же, как собственные поля.
-   **Глубокая вложенность:** запросы `wherePath('group_a.group_c.field_c1', ...)` работают для любой глубины благодаря рекурсивной материализации.
-   **Batch insert:** для массивов используется пакетная вставка для оптимизации производительности.
-   **Реиндексация:**
    -   При смене `postType.blueprint_id` → реиндексация всех Entry этого PostType
    -   При изменении структуры Blueprint → реиндексация всех Entry зависимых PostType
    -   Вручную: `php artisan entries:reindex`
-   **Скоупы для запросов:**
    -   `wherePath($path, $operator, $value)` — фильтрация по индексированным полям
    -   `whereRef($path, $targetId)` — фильтрация по ref-полям
    -   `wherePathExists($path)` — проверка наличия значения
    -   `wherePathMissing($path)` — проверка отсутствия значения

### Производительность и безопасность

-   **Рекурсия безопасна:** граф встраиваний проверяется на циклы при создании embed'а (`ensureNoCyclicDependency`), поэтому рекурсивная материализация гарантированно завершится.
-   **Глубина вложенности:** в реальных проектах редко превышает 3-4 уровня. При большей глубине материализация может занимать больше времени, но это ожидаемое поведение (вся структура должна быть развёрнута).
-   **Оптимизация:** при изменении глубоко вложенного шаблона (например, `D` в цепочке `D → C → A → B`) рематериализуются все зависимые blueprint'ы транзитивно через доменное событие.
-   **Batch индексация:** для больших массивов значений используется пакетная вставка в `doc_values` и `doc_refs`.
-   **Кеширование:** индексируемые Paths кешируются на уровне Blueprint для минимизации запросов к БД.
-   **Асинхронная обработка:** реиндексация выполняется через очередь Laravel для больших Entry.

### REST API и документация

-   **CRUD API:** полный набор endpoints для управления Entry через REST API.
-   **Фильтрация:** endpoint `/api/entries/query` для сложных запросов с фильтрацией по динамическим полям.
-   **Scribe документация:** автоматическая генерация API-документации с примерами запросов.
-   **API Resources:** структурированные ответы через Laravel Resources.

### Тестирование и мониторинг

-   **Unit-тесты:** покрытие индексации, материализации, запросов.
-   **Feature-тесты:** интеграционные тесты для полного цикла работы с Entry.
-   **Performance-тесты:** бенчмарки для индексации и запросов.
-   **Мониторинг:** метрики производительности индексации и запросов.
-   **Профилирование:** интеграция с Laravel Telescope и Debugbar.

---

## 11. Команды для реализации

### 11.1. Создание миграций (порядок важен!)

```bash
# 1. Создать таблицу blueprints
php artisan make:migration create_blueprints_table

# 2. Создать таблицу paths БЕЗ FK на blueprint_embed_id
php artisan make:migration create_paths_table

# 3. Создать таблицу blueprint_embeds
php artisan make:migration create_blueprint_embeds_table

# 4. Добавить FK для paths.blueprint_embed_id
php artisan make:migration add_blueprint_embed_id_fk_to_paths_table

# 5. Добавить версионирование структуры (опционально)
php artisan make:migration add_structure_version_to_blueprints_and_entries
```

### 11.2. Создание моделей и сервисов

```bash
# Модели
php artisan make:model Blueprint
php artisan make:model Path
php artisan make:model BlueprintEmbed
php artisan make:model Entry

# Сервис
php artisan make:class Services/BlueprintStructureService

# События и Listeners
php artisan make:event BlueprintStructureChanged
php artisan make:listener RematerializeEmbeds --event=BlueprintStructureChanged

# Исключения
php artisan make:exception EmbeddedBlueprintPathConflictException
```

### 11.3. Создание Request валидации

```bash
php artisan make:request StorePathRequest
php artisan make:request UpdatePathRequest
php artisan make:request StoreBlueprintEmbedRequest
```

### 11.4. Создание фабрик и сидеров

```bash
# Фабрики
php artisan make:factory BlueprintFactory
php artisan make:factory PathFactory
php artisan make:factory EntryFactory
php artisan make:factory DocValueFactory
php artisan make:factory DocRefFactory

# Сидеры
php artisan make:seeder BlueprintSeeder
php artisan make:seeder PathSeeder
php artisan make:seeder EntrySeeder
```

**database/factories/BlueprintFactory.php:**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Blueprint;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlueprintFactory extends Factory
{
    protected $model = Blueprint::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'code' => $this->faker->unique()->slug(2),
            'description' => $this->faker->sentence(),
        ];
    }
}
```

**database/factories/PathFactory.php:**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Path;
use Illuminate\Database\Eloquent\Factories\Factory;

class PathFactory extends Factory
{
    protected $model = Path::class;

    public function definition(): array
    {
        $name = $this->faker->word();

        return [
            'name' => $name,
            'full_path' => $name,
            'data_type' => $this->faker->randomElement(['string', 'int', 'float', 'bool', 'text', 'json']),
            'cardinality' => $this->faker->randomElement(['one', 'many']),
            'is_indexed' => $this->faker->boolean(80), // 80% индексируются
            'is_required' => $this->faker->boolean(30),
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }
}
```

**database/factories/EntryFactory.php:**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Entry;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntryFactory extends Factory
{
    protected $model = Entry::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'slug' => $this->faker->unique()->slug(),
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
            'published_at' => $this->faker->boolean(70) ? $this->faker->dateTimeBetween('-1 year', 'now') : null,
            'data_json' => [
                'content' => $this->faker->paragraphs(3, true),
                'excerpt' => $this->faker->sentence(),
                'author' => [
                    'name' => $this->faker->name(),
                    'bio' => $this->faker->sentence(),
                ],
            ],
        ];
    }

    public function published(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }
}
```

**database/seeders/BlueprintSeeder.php:**

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Blueprint;
use App\Models\Path;
use Illuminate\Database\Seeder;

class BlueprintSeeder extends Seeder
{
    public function run(): void
    {
        // Компонент: SEO fields
        $seoComponent = Blueprint::create([
            'code' => 'seo_fields',
            'name' => 'SEO Fields',
            'description' => 'Standard SEO metadata',
        ]);

        Path::create([
            'blueprint_id' => $seoComponent->id,
            'name' => 'metaTitle',
            'full_path' => 'metaTitle',
            'data_type' => 'string',
            'cardinality' => 'one',
            'is_indexed' => true,
            'is_required' => false,
            'sort_order' => 1,
        ]);

        Path::create([
            'blueprint_id' => $seoComponent->id,
            'name' => 'metaDescription',
            'full_path' => 'metaDescription',
            'data_type' => 'text',
            'cardinality' => 'one',
            'is_indexed' => true,
            'is_required' => false,
            'sort_order' => 2,
        ]);

        Path::create([
            'blueprint_id' => $seoComponent->id,
            'name' => 'keywords',
            'full_path' => 'keywords',
            'data_type' => 'string',
            'cardinality' => 'many',
            'is_indexed' => true,
            'is_required' => false,
            'sort_order' => 3,
        ]);

        // Blueprint: Article
        $articleBlueprint = Blueprint::create([
            'code' => 'article',
            'name' => 'Article',
            'description' => 'Blog article',
        ]);

        Path::create([
            'blueprint_id' => $articleBlueprint->id,
            'name' => 'content',
            'full_path' => 'content',
            'data_type' => 'text',
            'cardinality' => 'one',
            'is_indexed' => false, // Большой текст не индексируем
            'is_required' => true,
            'sort_order' => 1,
        ]);

        Path::create([
            'blueprint_id' => $articleBlueprint->id,
            'name' => 'excerpt',
            'full_path' => 'excerpt',
            'data_type' => 'string',
            'cardinality' => 'one',
            'is_indexed' => true,
            'is_required' => false,
            'sort_order' => 2,
        ]);

        $authorGroupPath = Path::create([
            'blueprint_id' => $articleBlueprint->id,
            'name' => 'author',
            'full_path' => 'author',
            'data_type' => 'json',
            'cardinality' => 'one',
            'is_indexed' => false,
            'is_required' => false,
            'sort_order' => 3,
        ]);

        Path::create([
            'blueprint_id' => $articleBlueprint->id,
            'parent_id' => $authorGroupPath->id,
            'name' => 'name',
            'full_path' => 'author.name',
            'data_type' => 'string',
            'cardinality' => 'one',
            'is_indexed' => true,
            'is_required' => true,
            'sort_order' => 1,
        ]);

        Path::create([
            'blueprint_id' => $articleBlueprint->id,
            'parent_id' => $authorGroupPath->id,
            'name' => 'bio',
            'full_path' => 'author.bio',
            'data_type' => 'text',
            'cardinality' => 'one',
            'is_indexed' => false,
            'is_required' => false,
            'sort_order' => 2,
        ]);

        Path::create([
            'blueprint_id' => $articleBlueprint->id,
            'name' => 'tags',
            'full_path' => 'tags',
            'data_type' => 'string',
            'cardinality' => 'many',
            'is_indexed' => true,
            'is_required' => false,
            'sort_order' => 4,
        ]);

        Path::create([
            'blueprint_id' => $articleBlueprint->id,
            'name' => 'relatedArticles',
            'full_path' => 'relatedArticles',
            'data_type' => 'ref',
            'cardinality' => 'many',
            'is_indexed' => true,
            'is_required' => false,
            'ref_target_type' => 'article',
            'sort_order' => 5,
        ]);

        $this->command->info('✓ Blueprints и Paths созданы');
    }
}
```

### 11.5. Запуск миграций и тестов

```bash
# Применить миграции
php artisan migrate

# Запустить сидеры
php artisan db:seed --class=BlueprintSeeder

# Запустить тесты
php artisan test --filter=BlueprintEmbedTest
php artisan test --filter=PathProtectionTest
php artisan test --filter=HasDocumentDataTest
php artisan test --filter=EntryQueryTest

# Обновить документацию API
composer scribe:gen
php artisan docs:generate
```

---

## 12. Тест-кейсы для множественного и многоуровневого встраивания

### 12.1. Unit-тесты (Pest)

#### Тест 1: Множественное встраивание одного blueprint'а

```php
test('можно встроить один blueprint несколько раз под разными host_path', function () {
    $company = Blueprint::factory()->create(['code' => 'company']);
    $address = Blueprint::factory()->create(['code' => 'address']);

    $officePath = Path::factory()->create([
        'blueprint_id' => $company->id,
        'full_path' => 'office_address',
        'data_type' => 'json',
    ]);

    $legalPath = Path::factory()->create([
        'blueprint_id' => $company->id,
        'full_path' => 'legal_address',
        'data_type' => 'json',
    ]);

    // Первое встраивание
    $embed1 = $structureService->createEmbed($company, $address, $officePath);
    expect($embed1)->toBeInstanceOf(BlueprintEmbed::class);

    // Второе встраивание того же blueprint'а
    $embed2 = $structureService->createEmbed($company, $address, $legalPath);
    expect($embed2)->toBeInstanceOf(BlueprintEmbed::class);

    // Проверяем, что созданы отдельные копии полей для каждого embed'а
    $officeFields = Path::where('blueprint_embed_id', $embed1->id)->count();
    $legalFields = Path::where('blueprint_embed_id', $embed2->id)->count();

    expect($officeFields)->toBeGreaterThan(0);
    expect($legalFields)->toEqual($officeFields);
});
```

#### Тест 2: Нельзя встроить дважды под одним host_path

```php
test('нельзя встроить blueprint дважды под одним host_path', function () {
    $company = Blueprint::factory()->create();
    $address = Blueprint::factory()->create();
    $officePath = Path::factory()->create(['blueprint_id' => $company->id]);

    $structureService->createEmbed($company, $address, $officePath);

    expect(fn() => $structureService->createEmbed($company, $address, $officePath))
        ->toThrow(LogicException::class, 'уже встроен');
});
```

#### Тест 3: Многоуровневое встраивание

```php
test('можно встроить blueprint в глубоко вложенное поле', function () {
    $article = Blueprint::factory()->create(['code' => 'article']);
    $contactInfo = Blueprint::factory()->create(['code' => 'contact_info']);

    // Создаём многоуровневую структуру: article.author.contacts
    $author = Path::factory()->create([
        'blueprint_id' => $article->id,
        'full_path' => 'author',
        'parent_id' => null,
        'data_type' => 'json',
    ]);

    $contacts = Path::factory()->create([
        'blueprint_id' => $article->id,
        'full_path' => 'author.contacts',
        'parent_id' => $author->id,
        'data_type' => 'json',
    ]);

    // Встраиваем ContactInfo в author.contacts
    $embed = $structureService->createEmbed($article, $contactInfo, $contacts);

    // Проверяем, что поля созданы с правильными путями
    $fields = Path::where('blueprint_embed_id', $embed->id)->get();

    expect($fields)->not->toBeEmpty();
    expect($fields->first()->full_path)->toStartWith('author.contacts.');
});
```

#### Тест 4: Удаление одного embed'а не трогает другой

```php
test('удаление одного embed не удаляет поля другого embed того же blueprint', function () {
    $company = Blueprint::factory()->create();
    $address = Blueprint::factory()->create();

    $officePath = Path::factory()->create(['blueprint_id' => $company->id]);
    $legalPath = Path::factory()->create(['blueprint_id' => $company->id]);

    $embed1 = $structureService->createEmbed($company, $address, $officePath);
    $embed2 = $structureService->createEmbed($company, $address, $legalPath);

    $legalFieldsCount = Path::where('blueprint_embed_id', $embed2->id)->count();

    // Удаляем первый embed
    $structureService->deleteEmbed($embed1);

    // Проверяем, что поля второго embed'а остались
    $remainingFields = Path::where('blueprint_embed_id', $embed2->id)->count();
    expect($remainingFields)->toEqual($legalFieldsCount);

    // Проверяем, что поля первого embed'а удалены
    expect(Path::where('blueprint_embed_id', $embed1->id)->count())->toEqual(0);
});
```

#### Тест 5: Рематериализация всех embed'ов при изменении исходного blueprint'а

```php
test('изменение исходного blueprint рематериализует все его embed\'ы', function () {
    $company = Blueprint::factory()->create();
    $address = Blueprint::factory()->create();

    $officePath = Path::factory()->create(['blueprint_id' => $company->id]);
    $legalPath = Path::factory()->create(['blueprint_id' => $company->id]);

    $embed1 = $structureService->createEmbed($company, $address, $officePath);
    $embed2 = $structureService->createEmbed($company, $address, $legalPath);

    // Добавляем новое поле в Address
    $newField = Path::factory()->create([
        'blueprint_id' => $address->id,
        'name' => 'country',
        'full_path' => 'country',
    ]);

    // Запускаем событие изменения структуры
    event(new BlueprintStructureChanged($address));

    // Проверяем, что новое поле появилось в ОБОИХ embed'ах
    $officeCountry = Path::query()
        ->where('blueprint_embed_id', $embed1->id)
        ->where('name', 'country')
        ->exists();

    $legalCountry = Path::query()
        ->where('blueprint_embed_id', $embed2->id)
        ->where('name', 'country')
        ->exists();

    expect($officeCountry)->toBeTrue();
    expect($legalCountry)->toBeTrue();
});
```

#### Тест 6: Транзитивное встраивание (A → C, B → A)

```php
test('транзитивное встраивание: изменения в C попадают в B через A', function () {
    // Создаём иерархию: D → C → A → B
    $blueprintD = Blueprint::factory()->create(['code' => 'd']);
    $blueprintC = Blueprint::factory()->create(['code' => 'c']);
    $blueprintA = Blueprint::factory()->create(['code' => 'a']);
    $blueprintB = Blueprint::factory()->create(['code' => 'b']);

    // Поля D
    Path::factory()->create([
        'blueprint_id' => $blueprintD->id,
        'name' => 'field_d1',
        'full_path' => 'field_d1',
    ]);

    // Поля C + группа для D
    Path::factory()->create([
        'blueprint_id' => $blueprintC->id,
        'name' => 'field_c1',
        'full_path' => 'field_c1',
    ]);

    $groupD = Path::factory()->create([
        'blueprint_id' => $blueprintC->id,
        'name' => 'group_d',
        'full_path' => 'group_d',
        'data_type' => 'json',
    ]);

    // Встраиваем D в C
    $embedCD = $structureService->createEmbed($blueprintC, $blueprintD, $groupD);

    // Поля A + группа для C
    Path::factory()->create([
        'blueprint_id' => $blueprintA->id,
        'name' => 'field_a1',
        'full_path' => 'field_a1',
    ]);

    $groupC = Path::factory()->create([
        'blueprint_id' => $blueprintA->id,
        'name' => 'group_c',
        'full_path' => 'group_c',
        'data_type' => 'json',
    ]);

    // Встраиваем C в A
    $embedAC = $structureService->createEmbed($blueprintA, $blueprintC, $groupC);

    // Поля B + группа для A
    Path::factory()->create([
        'blueprint_id' => $blueprintB->id,
        'name' => 'field_b1',
        'full_path' => 'field_b1',
    ]);

    $groupA = Path::factory()->create([
        'blueprint_id' => $blueprintB->id,
        'name' => 'group_a',
        'full_path' => 'group_a',
        'data_type' => 'json',
    ]);

    // Встраиваем A в B (должно рекурсивно развернуть C и D)
    $embedBA = $structureService->createEmbed($blueprintB, $blueprintA, $groupA);

    // Проверяем, что в B есть транзитивные поля из D
    $transitiveField = Path::query()
        ->where('blueprint_id', $blueprintB->id)
        ->where('full_path', 'group_a.group_c.group_d.field_d1')
        ->first();

    expect($transitiveField)->not->toBeNull();
    expect($transitiveField->source_blueprint_id)->toBe($blueprintD->id);
    expect($transitiveField->blueprint_embed_id)->toBe($embedBA->id); // корневой embed B→A
    expect($transitiveField->is_readonly)->toBeTrue();
});

test('изменение транзитивного blueprint рематериализует всех зависимых', function () {
    // Используем ту же структуру: D → C → A → B
    // (setup как в предыдущем тесте)

    // Добавляем новое поле в D
    $newFieldD = Path::factory()->create([
        'blueprint_id' => $blueprintD->id,
        'name' => 'field_d2',
        'full_path' => 'field_d2',
    ]);

    // Запускаем событие изменения D
    event(new BlueprintStructureChanged($blueprintD));

    // Проверяем, что новое поле появилось в B (через транзитивность)
    $transitiveNewField = Path::query()
        ->where('blueprint_id', $blueprintB->id)
        ->where('full_path', 'group_a.group_c.group_d.field_d2')
        ->exists();

    expect($transitiveNewField)->toBeTrue();
});
```

#### Тест 8: Конфликт full_path при встраивании

```php
test('встраивание с конфликтом full_path выбрасывает доменное исключение', function () {
    $blueprintB = Blueprint::factory()->create(['code' => 'b']);
    $blueprintA = Blueprint::factory()->create(['code' => 'a']);

    // В B уже есть поле meta.created_by
    $metaPath = Path::factory()->create([
        'blueprint_id' => $blueprintB->id,
        'name' => 'meta',
        'full_path' => 'meta',
        'data_type' => 'json',
    ]);

    Path::factory()->create([
        'blueprint_id' => $blueprintB->id,
        'parent_id' => $metaPath->id,
        'name' => 'created_by',
        'full_path' => 'meta.created_by',
    ]);

    // В A есть поле created_by
    Path::factory()->create([
        'blueprint_id' => $blueprintA->id,
        'name' => 'created_by',
        'full_path' => 'created_by',
    ]);

    // Пытаемся встроить A в B под meta → должен быть конфликт meta.created_by
    expect(fn() => $structureService->createEmbed($blueprintB, $blueprintA, $metaPath))
        ->toThrow(EmbeddedBlueprintPathConflictException::class, 'конфликт путей: meta.created_by');

    // Проверяем, что транзакция откатилась
    $copiedFields = Path::query()
        ->where('blueprint_id', $blueprintB->id)
        ->where('source_blueprint_id', $blueprintA->id)
        ->count();

    expect($copiedFields)->toBe(0);
});
```

#### Тест 9: Множественное встраивание одного blueprint в разные host_path

```php
test('один blueprint можно встроить дважды с разными full_path', function () {
    $company = Blueprint::factory()->create(['code' => 'company']);
    $address = Blueprint::factory()->create(['code' => 'address']);

    // Поля Address
    Path::factory()->create([
        'blueprint_id' => $address->id,
        'name' => 'street',
        'full_path' => 'street',
    ]);

    // Две группы в Company
    $legalPath = Path::factory()->create([
        'blueprint_id' => $company->id,
        'name' => 'legal_address',
        'full_path' => 'legal_address',
        'data_type' => 'json',
    ]);

    $postalPath = Path::factory()->create([
        'blueprint_id' => $company->id,
        'name' => 'postal_address',
        'full_path' => 'postal_address',
        'data_type' => 'json',
    ]);

    // Встраиваем Address дважды
    $embed1 = $structureService->createEmbed($company, $address, $legalPath);
    $embed2 = $structureService->createEmbed($company, $address, $postalPath);

    // Проверяем разные full_path
    $legalStreet = Path::query()
        ->where('blueprint_embed_id', $embed1->id)
        ->where('name', 'street')
        ->value('full_path');

    $postalStreet = Path::query()
        ->where('blueprint_embed_id', $embed2->id)
        ->where('name', 'street')
        ->value('full_path');

    expect($legalStreet)->toBe('legal_address.street');
    expect($postalStreet)->toBe('postal_address.street');

    // Удаляем один embed — поля второго остаются
    $structureService->deleteEmbed($embed1);

    expect(Path::where('blueprint_embed_id', $embed1->id)->count())->toBe(0);
    expect(Path::where('blueprint_embed_id', $embed2->id)->count())->toBeGreaterThan(0);
});
```

#### Тест 10: Встраивание в корень (host_path = NULL)

```php
test('можно встроить blueprint в корень без host_path', function () {
    $article = Blueprint::factory()->create(['code' => 'article']);
    $metadata = Blueprint::factory()->create(['code' => 'metadata']);

    Path::factory()->create([
        'blueprint_id' => $article->id,
        'name' => 'title',
        'full_path' => 'title',
    ]);

    Path::factory()->create([
        'blueprint_id' => $metadata->id,
        'name' => 'created_by',
        'full_path' => 'created_by',
    ]);

    // Встраиваем Metadata в корень Article
    $embed = $structureService->createEmbed($article, $metadata, null);

    // Поля Metadata должны быть в корне Article
    $createdBy = Path::query()
        ->where('blueprint_id', $article->id)
        ->where('blueprint_embed_id', $embed->id)
        ->where('full_path', 'created_by')
        ->first();

    expect($createdBy)->not->toBeNull();
    expect($createdBy->parent_id)->toBeNull(); // в корне
});

test('несколько embed\'ов в корень: проверка конфликтов', function () {
    $article = Blueprint::factory()->create();
    $metadataA = Blueprint::factory()->create(['code' => 'metadata_a']);
    $metadataB = Blueprint::factory()->create(['code' => 'metadata_b']);

    // Оба имеют поле created_by
    Path::factory()->create([
        'blueprint_id' => $metadataA->id,
        'name' => 'created_by',
        'full_path' => 'created_by',
    ]);

    Path::factory()->create([
        'blueprint_id' => $metadataB->id,
        'name' => 'created_by',
        'full_path' => 'created_by',
    ]);

    // Встраиваем первый
    $embed1 = $structureService->createEmbed($article, $metadataA, null);

    // Попытка встроить второй должна выбросить конфликт
    expect(fn() => $structureService->createEmbed($article, $metadataB, null))
        ->toThrow(EmbeddedBlueprintPathConflictException::class);
});
```

#### Тест 11: Транзитивная рематериализация через события (длинная цепочка)

```php
test('изменение в нижнем blueprint рематериализует всю цепочку вверх', function () {
    // Создаём цепочку: Geo → Address → Company → Department
    $geo = Blueprint::factory()->create(['code' => 'geo']);
    $address = Blueprint::factory()->create(['code' => 'address']);
    $company = Blueprint::factory()->create(['code' => 'company']);
    $department = Blueprint::factory()->create(['code' => 'department']);

    Path::factory()->create(['blueprint_id' => $geo->id, 'name' => 'lat', 'full_path' => 'lat']);

    $geoGroup = Path::factory()->create([
        'blueprint_id' => $address->id,
        'name' => 'geo',
        'full_path' => 'geo',
        'data_type' => 'json',
    ]);

    $addressGroup = Path::factory()->create([
        'blueprint_id' => $company->id,
        'name' => 'address',
        'full_path' => 'address',
        'data_type' => 'json',
    ]);

    $companyGroup = Path::factory()->create([
        'blueprint_id' => $department->id,
        'name' => 'company',
        'full_path' => 'company',
        'data_type' => 'json',
    ]);

    // Встраиваем цепочкой
    $embedGA = $structureService->createEmbed($address, $geo, $geoGroup);
    $embedAC = $structureService->createEmbed($company, $address, $addressGroup);
    $embedCD = $structureService->createEmbed($department, $company, $companyGroup);

    // Проверяем, что в Department есть транзитивное поле из Geo
    $transitiveField = Path::query()
        ->where('blueprint_id', $department->id)
        ->where('full_path', 'company.address.geo.lat')
        ->first();

    expect($transitiveField)->not->toBeNull();
    expect($transitiveField->source_blueprint_id)->toBe($geo->id);

    // Добавляем новое поле в Geo
    $newField = Path::factory()->create([
        'blueprint_id' => $geo->id,
        'name' => 'lng',
        'full_path' => 'lng',
    ]);

    // Запускаем событие изменения Geo (имитация реального изменения)
    event(new BlueprintStructureChanged($geo));

    // Проверяем, что новое поле появилось в Department (через всю цепочку!)
    $newTransitiveField = Path::query()
        ->where('blueprint_id', $department->id)
        ->where('full_path', 'company.address.geo.lng')
        ->exists();

    expect($newTransitiveField)->toBeTrue();
});
```

#### Тест 12: Защита служебных полей от изменения

```php
test('нельзя изменить служебные поля скопированного path', function () {
    $company = Blueprint::factory()->create();
    $address = Blueprint::factory()->create();
    $officePath = Path::factory()->create(['blueprint_id' => $company->id]);

    $embed = $structureService->createEmbed($company, $address, $officePath);

    // Пытаемся получить скопированное поле
    $copiedPath = Path::where('blueprint_embed_id', $embed->id)->first();

    // Попытка изменить через update() не должна сработать
    $copiedPath->update([
        'source_blueprint_id' => null, // пытаемся "превратить" в собственное поле
    ]);

    // Поле должно остаться нетронутым (guarded)
    expect($copiedPath->fresh()->source_blueprint_id)->not->toBeNull();
});

test('нельзя создать path с явно заданными служебными полями', function () {
    $blueprint = Blueprint::factory()->create();

    $path = Path::create([
        'blueprint_id' => $blueprint->id,
        'name' => 'test_field',
        'full_path' => 'test_field',
        'data_type' => 'string',
        'cardinality' => 'one',

        // Попытка задать служебные поля (должны быть проигнорированы)
        'source_blueprint_id' => 999,
        'blueprint_embed_id' => 999,
        'is_readonly' => true,
    ]);

    // Служебные поля должны быть NULL (guarded)
    expect($path->source_blueprint_id)->toBeNull();
    expect($path->blueprint_embed_id)->toBeNull();
    expect($path->is_readonly)->toBeFalse();
});
```

### 12.2. Feature-тесты (API)

```php
test('API: нельзя редактировать скопированное поле', function () {
    $company = Blueprint::factory()->create();
    $address = Blueprint::factory()->create();
    $officePath = Path::factory()->create(['blueprint_id' => $company->id]);

    $embed = $structureService->createEmbed($company, $address, $officePath);
    $copiedPath = Path::where('blueprint_embed_id', $embed->id)->first();

    $response = $this->putJson("/api/paths/{$copiedPath->id}", [
        'name' => 'new_name',
        'data_type' => 'text',
    ]);

    $response->assertStatus(403);
    $response->assertJsonFragment(['message' => 'Нельзя редактировать поля, скопированные из встроенного blueprint']);
});

test('API: нельзя удалить скопированное поле', function () {
    $company = Blueprint::factory()->create();
    $address = Blueprint::factory()->create();
    $officePath = Path::factory()->create(['blueprint_id' => $company->id]);

    $embed = $structureService->createEmbed($company, $address, $officePath);
    $copiedPath = Path::where('blueprint_embed_id', $embed->id)->first();

    $response = $this->deleteJson("/api/paths/{$copiedPath->id}");

    $response->assertStatus(403);
});

test('API: создание множественного встраивания', function () {
    $company = Blueprint::factory()->create();
    $address = Blueprint::factory()->create();
    $officePath = Path::factory()->create(['blueprint_id' => $company->id]);

    $response = $this->postJson("/api/blueprints/{$company->id}/embeds", [
        'embedded_blueprint_id' => $address->id,
        'host_path_id' => $officePath->id,
    ]);

    $response->assertStatus(201);

    // Создаём второе встраивание
    $legalPath = Path::factory()->create(['blueprint_id' => $company->id]);

    $response2 = $this->postJson("/api/blueprints/{$company->id}/embeds", [
        'embedded_blueprint_id' => $address->id,
        'host_path_id' => $legalPath->id,
    ]);

    $response2->assertStatus(201);
});

test('API: получение списка всех embed\'ов blueprint\'а', function () {
    $company = Blueprint::factory()->create();
    $address = Blueprint::factory()->create();

    BlueprintEmbed::factory()->count(2)->create([
        'blueprint_id' => $company->id,
        'embedded_blueprint_id' => $address->id,
    ]);

    $response = $this->getJson("/api/blueprints/{$company->id}/embeds");

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'data');
});

test('API: транзитивные поля доступны в структуре blueprint\'а', function () {
    // Setup: D → C → A → B
    $d = Blueprint::factory()->create(['code' => 'd']);
    $c = Blueprint::factory()->create(['code' => 'c']);
    $a = Blueprint::factory()->create(['code' => 'a']);
    $b = Blueprint::factory()->create(['code' => 'b']);

    Path::factory()->create(['blueprint_id' => $d->id, 'name' => 'field_d1', 'full_path' => 'field_d1']);

    $groupD = Path::factory()->create([
        'blueprint_id' => $c->id,
        'name' => 'group_d',
        'full_path' => 'group_d',
        'data_type' => 'json'
    ]);

    $this->postJson("/api/blueprints/{$c->id}/embeds", [
        'embedded_blueprint_id' => $d->id,
        'host_path_id' => $groupD->id,
    ]);

    $groupC = Path::factory()->create([
        'blueprint_id' => $a->id,
        'name' => 'group_c',
        'full_path' => 'group_c',
        'data_type' => 'json'
    ]);

    $this->postJson("/api/blueprints/{$a->id}/embeds", [
        'embedded_blueprint_id' => $c->id,
        'host_path_id' => $groupC->id,
    ]);

    $groupA = Path::factory()->create([
        'blueprint_id' => $b->id,
        'name' => 'group_a',
        'full_path' => 'group_a',
        'data_type' => 'json'
    ]);

    $this->postJson("/api/blueprints/{$b->id}/embeds", [
        'embedded_blueprint_id' => $a->id,
        'host_path_id' => $groupA->id,
    ]);

    // Проверяем структуру B
    $response = $this->getJson("/api/blueprints/{$b->id}/paths");

    $response->assertStatus(200);
    $response->assertJsonFragment(['full_path' => 'group_a.group_c.group_d.field_d1']);
});
```

---

Документ совместим по стилю с предыдущими версиями и может рассматриваться как **v3 решения с поддержкой встраиваемых шаблонов** с учётом производственных требований и масштабируемости.
