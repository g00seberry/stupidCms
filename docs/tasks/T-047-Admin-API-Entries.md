# T-047 — Admin API: Entries (список с фильтрами; CRUD; soft-delete; валидация slug/публикации)

```yaml
id: T-047
title: Реализовать Admin API для Entries: список/фильтры, CRUD, soft-delete/restore, валидация slug и публикации
area: [backend, laravel, api, admin, db]
priority: P0
size: M
depends_on: [T-020, T-021, T-022]
blocks: []
labels: [stupidCms, mvp, admin-api, entries]
```

## 1) Контекст

Админка должна управлять записями (`Entry`) всех типов (`post_type`, включая `page`). Нужен список с фильтрами, CRUD-операции, мягкое удаление и восстановление.  
Система использует cookie-based JWT (`cms_at/cms_rt`), ошибки — RFC7807; админ-ответы не кешируются (private, no-store).  
Ключевые доменные правила — уникальность `slug` в рамках `post_type`, зарезервированные пути, а также корректная логика публикации.

## 2) Требуемый результат (Deliverables)

- **Код:**
  - Контроллеры: `app/Http/Controllers/Admin/V1/EntryController.php` (`index, show, store, update, destroy, restore`) и `EntryForceDeleteController` (опционально).
  - Роуты: `routes/api.php` — `/api/v1/admin/entries[...]`.
  - Реквесты: `StoreEntryRequest.php`, `UpdateEntryRequest.php`, `IndexEntriesRequest.php` (валидация фильтров).
  - Ресурсы: `EntryResource.php`, `EntryCollection.php` (пагинация, мета).
  - Правила валидации: `Rules/UniqueEntrySlug.php`, `Rules/ReservedSlug.php`, `Rules/Publishable.php`.
  - Сервис: `app/Support/Slugify.php` (зависимость от T-021) — использовать для автогенерации, дедупа и нормализации.
  - Политики/права: `Gate::authorize('manage.entries')` или `EntryPolicy`.
  - RFC7807 helper `problem(...)` (если нет).
- **Тесты (Feature):**
  - `tests/Feature/Admin/Entries/IndexEntriesTest.php`
  - `tests/Feature/Admin/Entries/CrudEntriesTest.php`
  - `tests/Feature/Admin/Entries/SlugPublishValidationTest.php`
- **Документация:**
  - `docs/admin-api/entries.md` — спецификация, фильтры, примеры `curl`.
- **Команды проверки:**
  - `phpunit --testsuite Feature --filter 'Entries'`
  - `curl` сценарии (ниже).

## 3) Функциональные требования

### Эндпоинты
- `GET    /api/v1/admin/entries` — список с фильтрами и пагинацией.
- `POST   /api/v1/admin/entries` — создание.
- `GET    /api/v1/admin/entries/{id}` — чтение.
- `PUT    /api/v1/admin/entries/{id}` — обновление.
- `DELETE /api/v1/admin/entries/{id}` — soft-delete.
- `POST   /api/v1/admin/entries/{id}/restore` — восстановление.
- _(опц.)_ `DELETE /api/v1/admin/entries/{id}/force` — пермаделит (защищённое право).

Общие middleware: `auth:api`, `throttle:api`, `can:manage.entries`; заголовки `Cache-Control: private, no-store`, `Vary: Cookie`.

### Список/фильтры/сортировка/пагинация
Параметры `GET`:
- `post_type`, `status` (`all|draft|published|scheduled|trashed`), `q`, `author_id`, `term` (ids[]),  
  `date_from`, `date_to`, `date_field=updated|published`, `sort=updated_at.desc|published_at.desc|title.asc|title.desc`, `per_page` (10..100).

### CRUD и правила
- `post_type` обязателен при создании; неизменяем при обновлении.
- `slug` опционален; если пуст — авто-генерация `Slugify` из `title` (+dedupe).
- Уникальность `slug` внутри `post_type` (включая soft-deleted) — `UniqueEntrySlug`.
- Зарезервированные слуги — `ReservedSlug`.
- Публикация: при `is_published=true`:
  - `slug` не пуст и валиден;
  - `published_at` если не задан — `now()`; если в будущем — запись считается `scheduled`.
- Soft-delete и `restore`; пермаделит — опционально.

### Ответы
- `EntryResource`: поля `id, post_type, title, slug, content_json, meta_json, is_published, published_at, author{id,name}, terms[], created_at, updated_at, deleted_at`.
- Заголовки как выше; ошибки — RFC7807.

## 4) План реализации (для ИИ)

1. Проверить модель `Entry` (`SoftDeletes`, связи, casts).
2. Написать Rules: `UniqueEntrySlug`, `ReservedSlug`, `Publishable`.
3. Написать Requests: `StoreEntryRequest`, `UpdateEntryRequest`, `IndexEntriesRequest`.
4. Написать Resources: `EntryResource`, `EntryCollection`.
5. Реализовать `EntryController` (CRUD, фильтры, транзакции, автослаг, sync term_ids).
6. Добавить роуты с middleware и заголовками no-store.
7. Написать интеграционные тесты (все сценарии).
8. Документация и `curl` примеры.

## 5) Acceptance Criteria

- [ ] Полный CRUD и список с фильтрами работают и покрыты интеграционными тестами.
- [ ] Soft-delete и восстановление работают; пермаделит (если включён) требует особого права.
- [ ] Валидация slug (уникальность + резерв) и правил публикации работает (422 для нарушений).
- [ ] Ответы админки не кешируются и содержат `Vary: Cookie`.
- [ ] Ошибки — RFC7807.

## 6) Примеры `curl`

Список:
```bash
curl -i --cookie 'cms_at=token' \
  'https://api.example.com/api/v1/admin/entries?post_type=page&status=published&q=home&per_page=10'
```

Создание:
```bash
curl -i -X POST \
  -H 'Content-Type: application/json' \
  --cookie 'cms_at=token' \
  -d '{
    "post_type":"page",
    "title":"About",
    "is_published": true,
    "content_json": {"type":"doc","content":[]}
  }' \
  https://api.example.com/api/v1/admin/entries
```

Обновление:
```bash
curl -i -X PUT \
  -H 'Content-Type: application/json' \
  --cookie 'cms_at=token' \
  -d '{"title":"About us","is_published": true}' \
  https://api.example.com/api/v1/admin/entries/e_123
```
