# Система истории slug'ов для записей

## Обзор

Система автоматически ведёт историю изменений `slug` для записей (`entries`) в таблице `entry_slugs`. Гарантирует, что для каждой записи **ровно один** slug имеет флаг `is_current = true`. История используется для аналитики и интеграции с плагином Redirects (автоматические 301-редиректы со старых адресов).

## Структура

```
app/
├── Support/EntrySlug/
│   ├── EntrySlugService.php              # Интерфейс сервиса
│   └── DefaultEntrySlugService.php       # Реализация сервиса
├── Events/
│   └── EntrySlugChanged.php              # Событие смены slug
├── Observers/
│   └── EntryObserver.php                 # Интеграция с жизненным циклом Entry
├── Console/Commands/
│   └── BackfillEntrySlugsCommand.php     # Команда backfill для существующих записей
└── Providers/
    └── EntrySlugServiceProvider.php      # Регистрация в DI

database/migrations/
└── 2025_11_06_000021_create_entry_slugs_table.php
```

## Схема данных

Таблица `entry_slugs`:

-   `entry_id` (FK → `entries.id`, `on delete cascade`)
-   `slug` (varchar)
-   `is_current` (tinyint bool)
-   `created_at` (timestamp)

Индексы:

-   PK `(entry_id, slug)` — предотвращает дублирование одной и той же пары
-   IDX `(entry_id, is_current)` — быстрый поиск текущего slug для записи

## Принцип работы

### 1. EntrySlugService

Сервис управления историей slug'ов с тремя основными методами:

**`onCreated(Entry $entry): void`**

-   Вызывается после создания записи
-   Блокирует строки истории через `lockForUpdate()` (защита от гонок)
-   Создаёт запись через `firstOrCreate()` (сохраняет историческую дату `created_at`)
-   Обновляет `is_current = true` только если запись уже существовала
-   Снимает флаг `is_current` у всех остальных записей
-   Гарантирует единственность текущего slug

**`onUpdated(Entry $entry, string $oldSlug, bool $dispatchEvent = true): bool`**

-   Вызывается при изменении slug
-   Блокирует строки истории через `lockForUpdate()` (защита от гонок)
-   Создаёт запись для нового slug через `firstOrCreate()` (сохраняет историческую дату)
-   Выполняет массовый UPDATE с `CASE WHEN` для атомарного переключения `is_current`
-   Диспатчит событие `EntrySlugChanged` (если `$dispatchEvent = true`)
-   Возвращает `true`, если slug действительно изменился
-   Параметр `$dispatchEvent` позволяет отключить событие (например, при backfill)

**`currentSlug(int $entryId): ?string`**

-   Возвращает текущий slug для записи
-   Использует индекс `(entry_id, is_current)` для быстрого поиска

### 2. EntryObserver

Интеграция с жизненным циклом модели `Entry`:

-   **`created()`** → вызывает `EntrySlugService::onCreated()`
-   **`updated()`** → если `slug` изменился, вызывает `EntrySlugService::onUpdated()`
-   **`updating()`** → читает оригинальный slug через `getOriginal('slug')` **до** вызова `ensureSlug()` и сохраняет во временном хранилище

**Важно:**

-   Старое значение slug читается **до** вызова `ensureSlug()`, так как `ensureSlug()` может изменить slug
-   Используется статическое хранилище `$oldSlugs` для передачи старого slug из `updating()` в `updated()`
-   Проверка уникальности выполняется по `post_type_id` (прямой запрос, без `whereHas`)
-   Проверка зарезервированных путей кэшируется (TTL 300 сек) и выполняется в PHP для производительности
-   Генерация slug из `title` использует явные опции `SlugOptions(toLower: true, asciiOnly: true)`

### 3. Алгоритм работы

#### При создании записи

```
Entry::create(['slug' => 'about'])
  ↓
EntryObserver::creating() → ensureSlug()
  ↓
EntryObserver::created() → EntrySlugService::onCreated()
  ↓
DB::transaction {
  SELECT * FROM entry_slugs WHERE entry_id = E.id FOR UPDATE;  // Блокировка
  firstOrCreate(entry_id, slug) → создаёт с is_current=true, created_at=now()
    ИЛИ находит существующую (created_at сохраняется)
  UPDATE entry_slugs SET is_current = CASE WHEN slug = 'about' THEN 1 ELSE 0 END
    WHERE entry_id = E.id;  // Атомарное переключение
}
```

#### При изменении slug

```
Entry::update(['slug' => 'about-us'])
  ↓
EntryObserver::updating() → oldSlug = getOriginal('slug') ДО ensureSlug(), ensureSlug()
  ↓
EntryObserver::updated() → EntrySlugService::onUpdated(E, 'about')
  ↓
DB::transaction {
  SELECT * FROM entry_slugs WHERE entry_id = E.id FOR UPDATE;  // Блокировка
  firstOrCreate(entry_id, slug='about-us') → создаёт с is_current=false, created_at=now()
    ИЛИ находит существующую (created_at сохраняется)
  UPDATE entry_slugs SET is_current = CASE WHEN slug = 'about-us' THEN 1 ELSE 0 END
    WHERE entry_id = E.id;  // Атомарное переключение
}
  ↓
EntrySlugChanged::dispatch(E.id, 'about', 'about-us')  // Если $dispatchEvent = true
```

#### Возврат к предыдущему slug

Если запись возвращается к slug, который уже был в истории:

-   `firstOrCreate()` находит существующую запись (не создаёт дубликат)
-   Массовый UPDATE устанавливает `is_current = 1` для этого slug
-   **`created_at` остаётся прежним** (историческая дата сохраняется благодаря `firstOrCreate` + `update`)

### 4. Инварианты

1. Для каждой записи существует **не более одного** ряда с `is_current = 1`
2. При создании записи первый slug фиксируется с `is_current = 1`
3. При изменении slug предыдущая запись помечается `is_current = 0`, новая — `is_current = 1`
4. Возврат к прошлому slug не создаёт дубликат (обновляется существующая строка)

## События

**`EntrySlugChanged`** — диспатчится при фактической смене slug:

```php
EntrySlugChanged::dispatch(
    int $entryId,
    string $old,
    string $new
);
```

Используется плагином Redirects для автоматического создания 301-редиректов.

## Команда backfill

CLI-команда `cms:slugs:backfill` для заполнения истории существующих записей:

```bash
php artisan cms:slugs:backfill
php artisan cms:slugs:backfill --chunk=200
```

**Функционал:**

-   Проходит по всем `entries` батчами
-   Для каждой записи проверяет наличие текущей записи в истории
-   Создаёт недостающие записи через `onCreated()`
-   Исправляет рассинхрон через `onUpdated($entry, $currentSlug, false)` (без диспатча события)
-   Исправляет множественные `is_current = 1` через массовый UPDATE с блокировкой

**Примечание:** Команда использует транзакции только внутри сервиса (не создаёт внешние транзакции). При исправлении рассинхрона событие `EntrySlugChanged` **не диспатчится** (параметр `$dispatchEvent = false`), чтобы не создавать редиректы при backfill.

## Транзакции и конкурентность

### Защита от гонок

Система защищена от race conditions при параллельных обновлениях:

1. **Блокировка строк** — в начале транзакции выполняется `SELECT ... FOR UPDATE` для всех строк истории записи
2. **Атомарный массовый UPDATE** — используется `UPDATE ... SET is_current = CASE WHEN slug = ? THEN 1 ELSE 0 END`, который атомарно переключает флаги
3. **Транзакции** — все операции обёрнуты в `DB::transaction()` для атомарности

**Результат:** Даже при параллельных обновлениях одной записи гарантируется, что итогом будет **ровно один** `is_current = 1`.

### Сохранение исторических дат

-   Используется `firstOrCreate()` вместо `updateOrCreate()` для сохранения `created_at`
-   При возврате к предыдущему slug историческая дата создания записи **не перезаписывается**
-   Обновляется только `is_current`, остальные поля (включая `created_at`) сохраняются

## Обработка спец-случаев

-   **Восстановление soft-deleted Entry**: история не меняется; текущий slug остаётся прежним
-   **Повторное присвоение старого slug**: не создаётся новая строка, `firstOrCreate()` находит существующую, массовый UPDATE устанавливает `is_current = 1`, `created_at` сохраняется
-   **Пустой slug**: `onCreated()` и `onUpdated()` игнорируют записи с пустым slug
-   **Параллельные обновления**: блокировка `FOR UPDATE` и атомарный массовый UPDATE гарантируют единственность `is_current = 1`

## Тестирование

Unit-тесты: `tests/Unit/EntrySlugServiceTest.php`

Покрытие:

-   Создание записи в истории при создании Entry
-   Обновление истории при смене slug
-   Возврат к предыдущему slug (без дубликатов, сохранение `created_at`)
-   Гарантия единственности `is_current = 1` (включая параллельные обновления)
-   Получение текущего slug
-   Обработка пустых slug'ов
-   **Сохранение `created_at` при возврате к предыдущему slug**
-   **Защита от гонок при параллельных обновлениях**
-   **Исправление множественных `is_current = 1` в backfill**

## Производительность

### Кэширование reserved routes

Проверка зарезервированных путей оптимизирована для производительности:

-   Префиксы и пути загружаются из БД один раз и кэшируются на 300 секунд
-   Проверка выполняется в PHP (в памяти), а не через SQL-запросы
-   Особенно полезно при множественных вызовах `ensureUnique()` (дедупликация -2, -3)

### Оптимизация запросов

-   Проверка уникальности использует прямой запрос по `post_type_id` вместо `whereHas('postType')`
-   Это избегает лишних JOIN'ов и улучшает производительность

## Использование

Система работает автоматически через `EntryObserver`. Ручной вызов не требуется, но возможен:

```php
use App\Support\EntrySlug\EntrySlugService;

$service = app(EntrySlugService::class);

// Получить текущий slug
$current = $service->currentSlug($entryId);

// Создать запись в истории (обычно вызывается автоматически)
$service->onCreated($entry);

// Обновить историю (обычно вызывается автоматически)
$service->onUpdated($entry, $oldSlug);

// Обновить историю без диспатча события (например, при backfill)
$service->onUpdated($entry, $oldSlug, false);
```

## Защита на уровне БД

Миграция `entries` содержит триггеры (MySQL/MariaDB), которые проверяют:

-   Уникальность slug для активных страниц (post_type.slug = 'page')
-   Конфликты с зарезервированными путями:
    -   Точное совпадение с `reserved_routes.path` (case-insensitive)
    -   Начало slug с `reserved_routes.prefix` (case-insensitive)

Триггеры выполняют проверку **до** вставки/обновления и выбрасывают ошибку при конфликте.
