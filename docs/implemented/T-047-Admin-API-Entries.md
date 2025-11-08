# T-047 — Admin API: Entries

## Краткое описание функционала

Реализован полнофункциональный Admin API для управления записями (Entries) с поддержкой:
- Полный CRUD (Create, Read, Update, Delete)
- Мягкое удаление и восстановление (soft-delete/restore)
- Фильтрация и поиск с пагинацией
- Валидация slug (уникальность, зарезервированные пути)
- Валидация публикации (требования к slug при публикации)
- Автоматическая генерация slug из заголовка
- RFC7807 Problem Details для ошибок
- Ability-based авторизация (`manage.entries`)

## Структура файлов

### Модели и фабрики
- `app/Models/Entry.php` — модель Entry с HasFactory и SoftDeletes
- `database/factories/EntryFactory.php` — фабрика для тестирования

### Правила валидации
- `app/Rules/UniqueEntrySlug.php` — проверка уникальности slug в рамках post_type
- `app/Rules/ReservedSlug.php` — проверка конфликтов с зарезервированными путями
- `app/Rules/Publishable.php` — проверка обязательности slug при публикации

### Form Requests
- `app/Http/Requests/Admin/IndexEntriesRequest.php` — валидация параметров фильтрации
- `app/Http/Requests/Admin/StoreEntryRequest.php` — валидация создания записи
- `app/Http/Requests/Admin/UpdateEntryRequest.php` — валидация обновления записи

### API Resources
- `app/Http/Resources/Admin/EntryResource.php` — трансформация Entry в JSON
- `app/Http/Resources/Admin/EntryCollection.php` — трансформация коллекции с пагинацией

### Контроллеры
- `app/Http/Controllers/Admin/V1/EntryController.php` — CRUD операции и restore

### Роуты
- `routes/api_admin.php` — маршруты `/api/v1/admin/entries/*`

### Политики и Authorization
- `app/Policies/EntryPolicy.php` — обновлено для `manage.entries` ability
- `app/Providers/AuthServiceProvider.php` — Gate для `manage.entries`

### Тесты
- `tests/Feature/Admin/Entries/IndexEntriesTest.php` — тесты списка и фильтров
- `tests/Feature/Admin/Entries/CrudEntriesTest.php` — тесты CRUD операций
- `tests/Feature/Admin/Entries/SlugPublishValidationTest.php` — тесты валидации

### Дополнительно
- `tests/TestCase.php` — добавлен метод `putJsonAsAdmin()`

## Эндпоинты

### GET /api/v1/admin/entries
Список записей с фильтрацией и пагинацией.

**Query параметры:**
- `post_type` — фильтр по типу записи
- `status` — all|draft|published|scheduled|trashed
- `q` — поиск по title/slug
- `author_id` — фильтр по автору
- `term` — массив ID терминов
- `date_from`, `date_to` — диапазон дат
- `date_field` — updated|published
- `sort` — updated_at.desc|published_at.desc|title.asc|title.desc и др.
- `per_page` — 10-100

**Middleware:** `admin.auth`, `throttle:api`, `can:viewAny,Entry`

### POST /api/v1/admin/entries
Создание новой записи.

**Payload:**
```json
{
  "post_type": "page",
  "title": "About Us",
  "slug": "about-us",  // optional, auto-generated if empty
  "content_json": {},
  "meta_json": {},
  "is_published": false,
  "published_at": "2025-11-08T12:00:00Z",
  "template_override": null,
  "term_ids": [1, 2, 3]
}
```

**Middleware:** `admin.auth`, `throttle:api`, `can:create,Entry`

### GET /api/v1/admin/entries/{id}
Получение одной записи (включая soft-deleted).

**Middleware:** `admin.auth`, `throttle:api`  
**Authorization:** проверка в контроллере через `$this->authorize('view', $entry)`

### PUT /api/v1/admin/entries/{id}
Обновление записи.

**Payload:** все поля опциональны (partial update)

**Middleware:** `admin.auth`, `throttle:api`  
**Authorization:** проверка в контроллере через `$this->authorize('update', $entry)`

### DELETE /api/v1/admin/entries/{id}
Мягкое удаление записи.

**Middleware:** `admin.auth`, `throttle:api`  
**Authorization:** проверка в контроллере через `$this->authorize('delete', $entry)`

### POST /api/v1/admin/entries/{id}/restore
Восстановление soft-deleted записи.

**Middleware:** `admin.auth`, `throttle:api`  
**Authorization:** проверка в контроллере через `$this->authorize('restore', $entry)`

## Валидация

### Slug
- Формат: `^[a-z0-9]+(?:-[a-z0-9]+)*$`
- Уникальность в рамках `post_type` (включая soft-deleted)
- Не может совпадать с зарезервированными путями
- Автогенерация из `title` если не указан
- При обновлении: разрешено не менять slug

### Публикация
- При `is_published=true`:
  - Для создания: slug может быть пустым (автогенерация)
  - Для обновления: slug должен быть заполнен
  - `published_at` устанавливается в `now()` если не указан
  - Запись со статусом `published` и `published_at` в будущем считается `scheduled`

### Reserved Routes
- Path kind: точное совпадение (case-insensitive)
- Prefix kind: slug равен префиксу или начинается с `prefix/`
- SQL-запрос совместим с SQLite (использует `||` вместо `CONCAT`)

## Особенности реализации

### Authorization
- `Gate::define('manage.entries')` проверяет `hasAdminPermission('manage.entries')`
- `EntryPolicy` использует `hasAdminPermission` для всех abilities
- Проверки авторизации выполняются на уровне контроллера через `$this->authorize()`
- `is_admin` users имеют доступ ко всему (через `Gate::before` в AuthServiceProvider)

### Empty JSON Objects
- `EntryResource::transformJson()` рекурсивно преобразует пустые массивы в `stdClass`
- Это гарантирует, что пустые JSON объекты возвращаются как `{}` а не `[]`

### Автогенерация Slug
- Использует `Slugifier` и `UniqueSlugService` из T-021
- При конфликте добавляет суффикс `-2`, `-3` и т.д.
- Fallback на `'entry'` если slug из title пустой

### Статус и фильтрация
- `draft` — status='draft', deleted_at=null
- `published` — scopePublished() (status='published', published_at<=now, deleted_at=null)
- `scheduled` — status='published', published_at>now, deleted_at=null
- `trashed` — deleted_at IS NOT NULL
- `all` — без фильтра

### Cache Headers
- Все ответы: `Cache-Control: no-store, private`, `Vary: Cookie`
- 401: + `WWW-Authenticate: Bearer realm="admin"`, `Pragma: no-cache`
- 404/422: RFC7807 `application/problem+json`

## Известные проблемы

### Текущие ошибки тестов (8 failed, 368 passed):

1. **CSRF для неавторизованных запросов** — POST без auth возвращает 419 вместо 401  
   *Причина:* CSRF middleware выполняется до AdminAuth  
   *Решение:* тесты должны включать CSRF токен даже для неавторизованных запросов

2. **`per_page` возвращается как string** — `EntryCollection` не кастует в integer  
   *Решение:* добавить `(int)` к `per_page` в meta

3. **`manage.entries` permission не работает** — non-admin users с permission получают 403  
   *Причина:* требуется дополнительная отладка Policy/Gate  
   *Решение:* проверить, что `grantAdminPermissions()` сохраняет данные

4. **Reserved slug validation** — ReservedSlug Rule не распознает резервации  
   *Причина:* ReservedRoute не создаются в тестах  
   *Решение:* в тестах вызвать `ReservedRoute::create()` до POST

5. **Publishable Rule для create** — не различает create и update  
   *Причина:* `request()->isMethod('PUT')` не работает в validation rule context  
   *Решение:* использовать отдельные Rules для Store и Update

6. **Slug с символом `/`** — regex отклоняет `api/docs`  
   *Причина:* regex допускает только `[a-z0-9-]`  
   *Решение:* либо разрешить `/` в regex, либо тест неверный (slugs не должны содержать `/`)

## Тесты

### IndexEntriesTest (12 passed, 2 failed)
✅ Возврат пагинированного списка  
✅ Фильтрация по post_type, status (draft/published/scheduled/trashed), author, terms  
✅ Поиск по title  
✅ Сортировка  
❌ per_page возвращается как string  
✅ 401 без auth  
✅ 403 для non-admin  
❌ Permission `manage.entries` для non-admin

### CrudEntriesTest (15 passed, 2 failed)
✅ Show, store, update, delete, restore  
✅ Store с автогенерацией slug  
✅ Store с кастомным slug  
✅ Store с публикацией  
✅ Update изменяет поля  
✅ Update публикует/снимает публикацию  
✅ 404 для несуществующих записей  
❌ 401 без auth (получаем 419)  
✅ 403 для non-admin  
❌ Permission `manage.entries` для non-admin

### SlugPublishValidationTest (10 passed, 4 failed)
✅ Валидация формата slug  
✅ Уникальность slug в post_type  
✅ Уникальность включает soft-deleted  
✅ Slug переиспользуется между post_types  
✅ Update валидирует уникальность  
✅ Update разрешает оставить тот же slug  
❌ Reserved path validation  
❌ Reserved prefix validation (regex)  
✅ Case-insensitive reserved check  
❌ Publishing требует slug (для create)  
✅ Auto-generated slug при публикации  
✅ Автозаполнение published_at  
✅ Кастомный published_at  
✅ Draft разрешает пустой slug  
❌ Update to publish требует slug

**Итого:** 37/46 тестов Entry API проходят (80%)

## Связанные задачи

- T-020 — Entry model и миграции  
- T-021 — Slug Service (Slugifier, UniqueSlugService)  
- T-022 — Reserved Routes  
- T-046 — Admin API для PostTypes (использован как референс для архитектуры)

## Следующие шаги

1. Исправить оставшиеся 8 failing тестов
2. Добавить force-delete endpoint (опционально)
3. Добавить bulk operations (опционально)
4. Документировать API в OpenAPI/Swagger (опционально)
5. Добавить rate limiting per-user (опционально)

## Дата реализации

2025-11-08

