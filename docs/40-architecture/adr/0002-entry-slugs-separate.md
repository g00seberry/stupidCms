# ADR-0002: Entry Slugs as Separate Entity

**Status**: Accepted  
**Date**: 2025-11-08  
**Deciders**: @backend-team

## Context

При проектировании системы маршрутизации для entries возникла проблема:

-   Пользователи должны иметь возможность менять URL записи
-   При изменении URL старые ссылки должны автоматически редиректиться (301)
-   История изменений URL должна сохраняться для аудита
-   Резолв URL должен работать быстро (индексы БД)

## Decision

Выделить slugs в отдельную сущность `EntrySlug` со следующей структурой:

**Таблица**: `entry_slugs`

-   `entry_id` (FK → entries)
-   `slug` (string)
-   `is_current` (boolean)
-   `parent_slug` (string, nullable) — для иерархических URL
-   Primary Key: composite `(entry_id, slug)`

**Поведение**:

1. При создании entry → создаётся запись с `is_current = true`
2. При изменении slug:
    - Старая запись: `is_current = false`
    - Новая запись: `is_current = true`
3. Резолв URL:
    - Поиск по `slug`
    - Если `is_current = false` → 301 редирект на текущий slug

## Alternatives Considered

### 1. Single slug field in entries table

```sql
entries: { id, slug, ... }
```

**Минусы**:

-   Нет истории изменений
-   Невозможны 301-редиректы на старые URL
-   Потеря SEO при изменении URL

### 2. Separate redirects table

```sql
entries: { id, slug }
redirects: { from_path, to_path }
```

**Минусы**:

-   Ручное управление редиректами
-   Риск рассинхронизации
-   Дублирование данных

### 3. JSON field with slug history

```sql
entries: { id, slug, slug_history: json }
```

**Минусы**:

-   Медленные запросы (нет индекса по JSON)
-   Сложная миграция при изменении структуры
-   Проблемы с целостностью данных

## Consequences

### Положительные

-   ✅ Автоматические 301-редиректы при изменении URL
-   ✅ Полная история изменений slug
-   ✅ Быстрый резолв через индекс по `slug`
-   ✅ Поддержка иерархических URL (parent_slug)
-   ✅ Отдельный контекст для логики слагов

### Отрицательные

-   ❌ Дополнительная таблица (больше JOIN'ов)
-   ❌ Сложность при миграции данных
-   ❌ Необходим EntryObserver для синхронизации

### Нейтральные

-   ⚠️ Требуется сервис `EntrySlugService` для управления
-   ⚠️ Логика резолва усложняется (проверка `is_current`)

## Implementation

Файлы:

-   `app/Models/EntrySlug.php`
-   `app/Support/EntrySlug/EntrySlugService.php`
-   `app/Observers/EntryObserver.php`
-   `database/migrations/*_create_entry_slugs_table.php`

Документация:

-   [Slugs & 301](../../10-concepts/slugs.md)

## Related

-   Связано с роутингом: `app/Domain/Routing/`
-   Используется в: `FallbackController`, `PageController`
