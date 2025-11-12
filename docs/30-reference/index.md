---
owner: "@backend-team"
system_of_record: "generated"
review_cycle_days: 30
last_reviewed: 2025-11-08
---

# Reference

Справочная информация по stupidCms — API, схемы БД, конфигурация, события.

> ⚠️ **Большинство страниц в этом разделе автогенерируются** из кода. Для обновления запустите:
>
> ```bash
> composer docs:gen
> ```

## Содержание

### API Reference (Scribe)

Интерактивная документация для всех public/admin endpoints доступна в `docs/_generated/api-docs/index.html`.

**Источник**: `php artisan scribe:generate --force --no-interaction`.

#### Подразделы

-   [Admin API — Plugins](admin-api/plugins.md) — управление manifest-плагинами, синхронизация, enable/disable.
-   [Admin API — Taxonomies & Terms](admin-api/taxonomies-terms.md) — ручное описание CRUD и pivot операций для таксономий/терминов.
-   [Admin API — Utils](admin-api/utils.md) — утилиты: генерация slug, получение списка шаблонов.

---

---

### [Database Schema (ERD)](erd.md)

Схема базы данных с таблицами, полями, связями и индексами.

**Источник**: Миграции (`database/migrations/*`)

---

### [Routes](routes.md)

Полный список маршрутов приложения с middleware и контроллерами.

**Источник**: `routes/*` файлы

---

### [Permissions & Abilities](permissions.md)

Справочник прав доступа из Policies.

**Источник**: `app/Policies/*`

---

### [Configuration](config.md)

Все конфигурационные ключи и их значения по умолчанию.

**Источник**: `config/*`

---

### [Events](events.md)

События приложения, где триггерятся и какие Listeners.

**Источник**: `app/Events/*`, `app/Listeners/*`

---

### [Errors (RFC7807)](errors.md)

Стандартные коды ошибок API в формате RFC7807 Problem Details.

**Источник**: Определения ошибок в приложении

---

### [Media Pipeline](media-pipeline.md)

Процесс обработки медиафайлов: загрузка, генерация вариантов, оптимизация.

**Источник**: `config/filesystems.php`, Jobs, Events

---

### [Search Mappings](search-mappings.md)

Elasticsearch маппинги и настройки индексов.

**Источник**: `config/search.php` (если есть), дефолтные маппинги

---

## Как обновить

После изменения кода (контроллеры, модели, конфиги, миграции):

```bash
# Сгенерировать всю документацию
composer docs:gen

# Или отдельные части
php artisan docs:routes      # Маршруты
php artisan docs:abilities   # Права
php artisan docs:erd          # ERD
php artisan docs:errors       # Ошибки
php artisan docs:config       # Конфигурация
php artisan docs:search       # Elasticsearch
php artisan docs:media        # Media pipeline
```

## CI интеграция

В CI pipeline проверяется актуальность генерируемых файлов. Если PR меняет код, но не обновляет `_generated/*`, проверка фейлится.

См. [CI/CD документация](../50-operations/ci-cd.md).
