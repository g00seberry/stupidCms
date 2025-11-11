---
owner: "@backend-team"
system_of_record: "handwritten"
review_cycle_days: 30
last_reviewed: 2025-11-08
---

# Admin API — Plugins

Управление подключаемыми модулями CMS: синхронизация manifest-файлов, включение/отключение и просмотр статуса.

## Связанные артефакты

-   Конфигурация: `config/plugins.php`
-   Миграция: `database/migrations/2025_11_09_000060_create_plugins_table.php`
-   Модель: `app/Models/Plugin.php`
-   Сервис синхронизации: `app/Domain/Plugins/Services/PluginsSynchronizer.php`

## Требования безопасности

| Операция       | Middleware                    | Специальное право |
| -------------- | ----------------------------- | ----------------- |
| Список         | `admin.auth`, `throttle:60,1` | `plugins.read`    |
| Синхронизация  | `admin.auth`, `throttle:10,1` | `plugins.sync`    |
| Enable/Disable | `admin.auth`, `throttle:10,1` | `plugins.toggle`  |

Права назначаются через `admin_permissions` пользователя или глобальное `is_admin=true`.

## Формат manifest

При синхронизации читается `plugin.json` (или `composer.json` с блоком `extra.stupidcms-plugin`). Минимальный набор полей:

```json
{
    "slug": "example",
    "name": "Example Plugin",
    "version": "1.0.0",
    "provider": "Plugins\\Example\\ExamplePluginServiceProvider",
    "routes": ["routes/plugin.php"]
}
```

> Маршруты и провайдер должны существовать в `plugins/<slug>/src`. Папка плагина перечисляется в `config/plugins.php`.

## Эндпоинты

### GET `/api/v1/admin/plugins`

Возвращает пагинированный список плагинов.

Параметры запроса:

| Параметр   | Тип                                           | Значение по умолчанию | Описание                 |
| ---------- | --------------------------------------------- | --------------------- | ------------------------ |
| `q`        | string                                        | —                     | Поиск по `slug` / `name` |
| `enabled`  | `true` \| `false` \| `any`                    | `any`                 | Фильтр по статусу        |
| `sort`     | `name` \| `slug` \| `version` \| `updated_at` | `name`                | Поле сортировки          |
| `order`    | `asc` \| `desc`                               | `asc`                 | Направление сортировки   |
| `per_page` | int (1..100)                                  | `25`                  | Размер страницы          |

Структура элемента:

| Поле             | Описание                                               |
| ---------------- | ------------------------------------------------------ |
| `slug`           | системный идентификатор                                |
| `name`           | отображаемое имя                                       |
| `version`        | версия из manifest                                     |
| `enabled`        | флаг активности                                        |
| `provider`       | FQCN сервиса                                           |
| `routes_active`  | `true`, если провайдер зарегистрирован в контейнере    |
| `last_synced_at` | ISO8601 timestamp последней синхронизации (или `null`) |

### POST `/api/v1/admin/plugins/sync`

Перечитывает файловую систему, актуализирует таблицу `plugins` и перестраивает кеш маршрутов (если включено `plugins.auto_route_cache`).

Ответ `202 Accepted`:

```json
{
    "status": "accepted",
    "summary": {
        "added": 1,
        "updated": 0,
        "removed": 0,
        "providers": ["Plugins\\Example\\ExamplePluginServiceProvider"]
    }
}
```

> Все ошибки возвращаются в формате [`ErrorPayload`](../errors.md) (RFC7807 + `code`, `meta`, `trace_id`). Ниже — типовые ответы.

```json
{
    "type": "https://stupidcms.dev/problems/invalid-plugin-manifest",
    "title": "Invalid plugin manifest",
    "status": 422,
    "code": "INVALID_PLUGIN_MANIFEST",
    "detail": "Plugin manifest is invalid.",
    "meta": {
        "request_id": "b4865eb5-1fa6-46a5-9d25-73e9d55876a0",
        "manifest": "plugins/example/plugin.json"
    },
    "trace_id": "00-b4865eb51fa646a59d2573e9d55876a0-b4865eb51fa646a5-01"
}
```

```json
{
    "type": "https://stupidcms.dev/problems/routes-reload-failed",
    "title": "Failed to reload plugin routes",
    "status": 500,
    "code": "ROUTES_RELOAD_FAILED",
    "detail": "Failed to reload plugin routes.",
    "meta": {
        "request_id": "b2f5c3f7-4edb-40dd-a0c8-7c0a5db91f61",
        "providers": ["Plugins\\Example\\ExamplePluginServiceProvider"]
    },
    "trace_id": "00-b2f5c3f74edb40dda0c87c0a5db91f61-b2f5c3f74edb40dd-01"
}
```

### POST `/api/v1/admin/plugins/{slug}/enable`

Активирует плагин, регистрирует провайдер, обновляет кеш маршрутов. Идемпотентность обеспечивается: повторное включение отдаёт `409`.

Ответ `200 OK` — объект `Plugin` в текущем состоянии.

### POST `/api/v1/admin/plugins/{slug}/disable`

Деактивирует плагин и пересобирает кеш маршрутов. Повторное отключение → `409`.

---

## Типовой lifecycle

1. Разработчик кладёт плагин в `plugins/<slug>` и добавляет manifest.
2. Администратор выполняет `POST /plugins/sync`, чтобы зарегистрировать плагин в БД.
3. В UI или через API включает (`POST /plugins/{slug}/enable`) при необходимости.
4. Отключение (`POST /plugins/{slug}/disable`) снимает маршруты, но не удаляет запись. Полное удаление — вручную (удаление каталога + повторный sync).
