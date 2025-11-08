---
owner: "@backend-team"
system_of_record: "generated"
review_cycle_days: 30
last_reviewed: 2025-11-08
related_code:
  - "database/migrations/*.php"
---

# Database Schema (ERD)

> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:erd` to update.

## Entity-Relationship Diagram

![ERD](../_generated/erd.svg)

> 📊 **Mermaid source**: [erd.mmd](../_generated/erd.mmd)  
> 📄 **PlantUML source**: [erd.puml](../_generated/erd.puml)  
> 📋 **JSON schema**: [erd.json](../_generated/erd.json)

## Generation

```bash
# Generate ERD files
php artisan docs:erd

# Convert PlantUML to SVG (requires PlantUML)
plantuml docs/_generated/erd.puml

# Or use online tool
# https://www.plantuml.com/plantuml/uml/
```

## Table Overview

### Content Management

- **post_types** — Типы контента (article, page, event)
- **entries** — Записи контента
- **entry_slugs** — История URL с 301-редиректами

### Taxonomy

- **taxonomies** — Группы терминов (categories, tags)
- **terms** — Термины/категории
- **term_tree** — Иерархия терминов (closure table)
- **entry_term** — Связь entries ↔ terms (pivot)

### Media

- **media** — Медиафайлы
- **media_variants** — Варианты изображений (thumbnails)
- **entry_media** — Связь entries ↔ media (pivot)

### Routing

- **redirects** — Ручные 301-редиректы
- **reserved_routes** — Защищённые системные URL
- **route_reservations** — Временные резервации путей

### System

- **users** — Пользователи
- **refresh_tokens** — JWT refresh токены
- **options** — Настройки сайта (key-value)
- **audits** — Лог изменений (audit trail)
- **outbox** — Transactional outbox для событий

### Plugins (future)

- **plugins** — Установленные плагины
- **plugin_migrations** — Миграции плагинов
- **plugin_reserved** — Зарезервированные слаги плагинов

## Migrations

Полный список миграций находится в `database/migrations/`.

Порядок выполнения:
1. Core tables (users, post_types, taxonomies)
2. Content tables (entries, terms, media)
3. Pivot tables (entry_term, entry_media)
4. System tables (options, audits, outbox)

## Indexes

Ключевые индексы для производительности:

| Table | Columns | Type | Purpose |
|-------|---------|------|---------|
| entries | post_type_id | index | Фильтр по типу |
| entries | slug | index | Поиск по URL |
| entries | author_id | index | Записи автора |
| entries | published_at | index | Сортировка |
| entry_slugs | slug | index | Резолв URL |
| entry_slugs | is_current | index | Текущий slug |
| terms | taxonomy_id | index | Термины таксономии |
| terms | slug | index | Поиск термина |
| media | uploader_id | index | Медиа пользователя |
| audits | auditable_type, auditable_id | index | История изменений |

## Foreign Keys

Все foreign keys имеют `ON DELETE CASCADE` или `ON DELETE RESTRICT`:

- **CASCADE**: при удалении родителя удаляется связанная запись (например, `entry_slugs`)
- **RESTRICT**: нельзя удалить родителя, если есть связанные записи (например, `post_type_id`)

## Soft Deletes

Модели с `deleted_at`:
- `entries` — можно восстановить
- `media` — можно восстановить

## JSON Columns

| Table | Column | Schema |
|-------|--------|--------|
| entries | data_json | Кастомные поля по PostType |
| entries | seo_json | SEO метаданные |
| post_types | options_json | Настройки типа |
| media | meta_json | Метаданные файла (EXIF, alt, title) |
| options | value | Любое JSON значение |

## Size Estimates

Примерные размеры для production:

| Table | Est. Rows | Est. Size |
|-------|-----------|-----------|
| entries | 10K - 1M | 100MB - 10GB |
| entry_slugs | 20K - 2M | 50MB - 5GB |
| terms | 100 - 10K | <100MB |
| media | 5K - 500K | (metadata only) |
| audits | 100K - 10M | 1GB - 100GB |

> 📝 **Note**: Media файлы хранятся в S3/filesystem, не в БД.

## Related Pages

- [Domain Model](../10-concepts/domain-model.md) — подробная схема сущностей
- [Migrations](../../database/migrations/) — исходники миграций

---

> 💡 **Актуальность**: ERD автоматически генерируется из миграций командой `php artisan docs:erd`.

