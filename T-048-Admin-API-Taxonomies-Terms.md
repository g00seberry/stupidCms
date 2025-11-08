# T-048 — Admin API: Taxonomies/Terms (CRUD + привязка к Entry)

```yaml
id: T-048
title: Реализовать Admin API для таксономий и терминов: CRUD + привязка к Entry (pivot)
area: [backend, laravel, api, admin, db]
priority: P0
size: M
depends_on: [T-020, T-047]
blocks: []
labels: [stupidCms, mvp, admin-api, taxonomies, terms]
```

## 1) Контекст

Админка должна управлять таксономиями и терминами, а также привязывать термины к записям (`Entry`).  
Основные сценарии: CRUD для `Taxonomy` и `Term`, список терминов по таксономии, поиск, и операции над pivot `entry_term`: attach/detach/sync.  
Все админ-ответы — `Cache-Control: private, no-store` + `Vary: Cookie`; ошибки — RFC7807.

**Критерии приёмки:** `POST` создания термина может сразу **связать** его с `Entry` и в ответе отдать **актуальные pivot** для этой записи.

## 2) Требуемый результат (Deliverables)

- **Код:**
  - Модели (проверить/актуализировать): `app/Models/Taxonomy.php`, `app/Models/Term.php` (связи, casts).
  - Контроллеры:
    - `app/Http/Controllers/Admin/V1/TaxonomyController.php` — `index, store, show, update, destroy`.
    - `app/Http/Controllers/Admin/V1/TermController.php` — `indexByTaxonomy, store, show, update, destroy`.
    - `app/Http/Controllers/Admin/V1/EntryTermsController.php` — `index (термины записи), attach, detach, sync`.
  - Реквесты:
    - `StoreTaxonomyRequest.php`, `UpdateTaxonomyRequest.php`.
    - `StoreTermRequest.php`, `UpdateTermRequest.php`.
    - `AttachTermsRequest.php` (валидирует `term_ids[]`).
  - Ресурсы: `TaxonomyResource.php`, `TermResource.php`, `TermCollection.php`.
  - Правила: `Rules/UniqueTermSlug.php` (uni within taxonomy), `Rules/ReservedSlug.php` (при необходимости).
  - Сервис: использовать `app/Support/Slugify.php` (T-021) для автогенерации slug у терминов.
  - Роуты: `routes/api.php`.
- **Тесты (Feature, интеграционные):**
  - `tests/Feature/Admin/Taxonomies/CrudTaxonomiesTest.php`
  - `tests/Feature/Admin/Terms/CrudTermsTest.php`
  - `tests/Feature/Admin/Terms/AttachPivotTest.php` — создание термина с одновременным attach к Entry; отдача актуального pivot.
  - `tests/Feature/Admin/Terms/AttachDetachSyncTest.php` — attach/detach/sync на существующих терминах.
- **Документация:**
  - `docs/admin-api/taxonomies-terms.md` — схемы, фильтры, примеры `curl`.
- **Команды проверки:**
  - `phpunit --testsuite Feature --filter '(Taxonomies|Terms)'`
  - `curl` примеры ниже.

## 3) Функциональные требования

### 3.1 Схема данных (предположения)

- `taxonomies` — `id`, `slug` (UNIQUE), `label` (nullable), `options_json` (JSON), timestamps.
- `terms` — `id`, `taxonomy_id` (FK), `name`, `slug` (UNIQUE **в рамках taxonomy_id**), `meta_json` (JSON), timestamps.
- `entry_term` (pivot) — `entry_id` (FK), `term_id` (FK), timestamps.  
  Индексы: (`taxonomy_id`,`slug`) в `terms`, (`entry_id`,`term_id`) в pivot.

### 3.2 Эндпоинты (админ)

#### Таксономии
- `GET    /api/v1/admin/taxonomies` — список (пагинация, поиск по `q=slug|label`).
- `POST   /api/v1/admin/taxonomies` — создать (`slug` опц., автогенерация из `label`).
- `GET    /api/v1/admin/taxonomies/{slug}` — посмотреть.
- `PUT    /api/v1/admin/taxonomies/{slug}` — обновить (`label`, `options_json`).
- `DELETE /api/v1/admin/taxonomies/{slug}` — удалить (жёстко; запрет если есть термины — 409).

#### Термины
- `GET    /api/v1/admin/taxonomies/{slug}/terms` — список терминов этой таксономии; фильтры: `q=name|slug`, `per_page`, сорт `name.asc|name.desc|created_at.desc`.
- `POST   /api/v1/admin/taxonomies/{slug}/terms` — создать термин. Поля: `name` (required), `slug` (optional), `meta_json` (optional).  
  **Особенность:** опциональный `attach_entry_id` (number/uuid). Если передан — сразу привязать созданный термин к `Entry` и в ответе вернуть **актуальные pivot** по этой записи (см. 5.3).
- `GET    /api/v1/admin/terms/{id}` — показать термин.
- `PUT    /api/v1/admin/terms/{id}` — обновить термин (`name`, `slug`, `meta_json`).
- `DELETE /api/v1/admin/terms/{id}` — удалить термин. Если термин привязан к записям — 409, либо опциональный флаг `?forceDetach=1` (тогда detach+delete).

#### Pivot (термины записи)
- `GET  /api/v1/admin/entries/{entry}/terms` — текущие термины записи, сгруппированные по таксономиям.
- `POST /api/v1/admin/entries/{entry}/terms/attach` — добавить `term_ids[]`.
- `POST /api/v1/admin/entries/{entry}/terms/detach` — убрать `term_ids[]`.
- `PUT  /api/v1/admin/entries/{entry}/terms/sync` — заменить целиком `term_ids[]` (без удаления неизвестных id валидировать).

Общие middleware: `auth:api`, `throttle:api`, `can:manage.terms` (и/или `manage.taxonomies`), заголовки no-store+Vary.

### 3.3 Правила/валидация

- Таксономии:
  - `slug`: `required|string|max:64|regex:/^[a-z0-9_-]+$/|unique:taxonomies,slug,{$ignoreId}`. При `POST` можно не указывать — сгенерировать из `label`.
  - `options_json`: `array` (объект) по необходимости.
- Термины:
  - `name`: `required|string|max:255`.
  - `slug`: `nullable|string|max:255|regex:/^[a-z0-9][a-z0-9_-]*$/|unique_term_slug:taxonomy_id,{ignoreTermId}` (кастомное правило `UniqueTermSlug` с областью `taxonomy_id`).
  - `meta_json`: `nullable|array`.
  - При создании — если `slug` пуст, сгенерировать из `name` → `Slugify::make()` + dedupe в рамках таксономии.
- Pivot:
  - `term_ids`: `required|array|min:1`; `term_ids.*`: `integer|exists:terms,id`.
  - При `attach_entry_id` в POST термина — валидировать существование `Entry`.

### 3.4 Поведение/ошибки

- При удалении таксономии с терминами — `409 Conflict` (или флаг `?force=1` с каскадным удалением терминов и отвязкой pivot — по умолчанию **запрещено**).
- При удалении термина, если он привязан к записям и без `forceDetach` — `409 Conflict`.
- Все ошибки формата RFC7807 (`application/problem+json`).

## 4) Нефункциональные требования

- Laravel 12.x, PHP 8.2+; без сторонних пакетов.
- Транзакции при модификациях (создание/обновление/attach/detach/sync).
- Индексы: `taxonomies.slug`, `terms.taxonomy_id+slug`, `entry_term.entry_id+term_id`.
- Безопасность: права `manage.taxonomies` и `manage.terms`. Все ответы админки — private, no-store.
- Наблюдаемость: логирование изменений на уровне info.

## 5) Контракты API

### 5.1 Примеры: таксономии
`POST /api/v1/admin/taxonomies`
```json
{ "label": "Категории", "slug": "category", "options_json": {} }
```
Ответ `201`:
```json
{ "data": { "slug": "category", "label": "Категории", "options_json": {}, "created_at": "...", "updated_at": "..." } }
```

### 5.2 Примеры: термины
`POST /api/v1/admin/taxonomies/category/terms`
```json
{ "name": "Новости", "slug": "news", "meta_json": { "color": "#ff0" } }
```
Ответ `201`:
```json
{ "data": { "id": 10, "taxonomy": "category", "name": "Новости", "slug": "news", "meta_json": { "color": "#ff0" } } }
```

### 5.3 Особый кейс: создать термин и сразу привязать к Entry
`POST /api/v1/admin/taxonomies/category/terms`
```json
{ "name": "Аналитика", "attach_entry_id": 123 }
```
Ответ `201` (термин создан **и** привязан), дополнительно возвращаем **актуальные термины записи**:
```json
{
  "data": { "id": 11, "taxonomy": "category", "name": "Аналитика", "slug": "analitika" },
  "entry_terms": {
    "entry_id": 123,
    "terms": [
      { "id": 11, "taxonomy": "category", "name": "Аналитика", "slug": "analitika" },
      { "id": 7, "taxonomy": "tag", "name": "longread", "slug": "longread" }
    ]
  }
}
```

### 5.4 Pivot операции
`POST /api/v1/admin/entries/123/terms/attach`
```json
{ "term_ids": [7, 11] }
```
Ответ `200`:
```json
{ "data": { "entry_id": 123, "terms": [ { "id":7, "taxonomy":"tag", "name":"longread" }, { "id":11, "taxonomy":"category", "name":"Аналитика" } ] } }
```

`PUT /api/v1/admin/entries/123/terms/sync`
```json
{ "term_ids": [11] }
```
Ответ содержит новый полный список.

## 6) План реализации (для ИИ)

1. Добавить/актуализировать модели `Taxonomy`, `Term` (связи: `Taxonomy->terms()`, `Term->taxonomy()`, `Entry->terms()` many-to-many, timestamps on pivot).
2. Создать правила `UniqueTermSlug` (scope по `taxonomy_id`) и при необходимости `ReservedSlug`.
3. Реализовать FormRequest’ы: `Store/UpdateTaxonomyRequest`, `Store/UpdateTermRequest`, `AttachTermsRequest`.
4. Реализовать контроллеры: `TaxonomyController`, `TermController`, `EntryTermsController` с транзакциями, RFC7807 и заголовками no-store+Vary.
5. Роутинг в `routes/api.php` под префиксом `api/v1/admin`, middleware: `auth:api`, `throttle:api`, `can:*`.
6. Подключить `Slugify` при создании термина; дедуп слуг внутри таксономии.
7. Написать интеграционные тесты: CRUD таксономий/терминов, создание термина с attach к Entry (ответ содержит актуальные pivot), attach/detach/sync.
8. Обновить документацию `docs/admin-api/taxonomies-terms.md` + `curl` примеры.

## 7) Acceptance Criteria

- [ ] CRUD таксономий и терминов работает, валидации соблюдены (уникальность slug в области таксономии).
- [ ] `GET /taxonomies/{slug}/terms` фильтрует и пагинирует.
- [ ] `POST /taxonomies/{slug}/terms` при наличии `attach_entry_id` — создаёт термин, **привязывает** его к `Entry` и возвращает актуальные pivot (список терминов записи).
- [ ] `attach/detach/sync` обновляют связь записи с терминами; ответ отражает текущее состояние.
- [ ] Все админ-ответы — `Cache-Control: private, no-store` + `Vary: Cookie`; ошибки — RFC7807.
- [ ] Все операции покрыты интеграционными тестами и зелёные.

## 8) Роутинг (эскиз)

```php
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\V1\TaxonomyController;
use App\Http\Controllers\Admin\V1\TermController;
use App\Http\Controllers\Admin\V1\EntryTermsController;

Route::prefix('api/v1/admin')->middleware(['auth:api','throttle:api'])->group(function() {
    Route::middleware('can:manage.taxonomies')->group(function () {
        Route::get('taxonomies', [TaxonomyController::class, 'index']);
        Route::post('taxonomies', [TaxonomyController::class, 'store']);
        Route::get('taxonomies/{slug}', [TaxonomyController::class, 'show']);
        Route::put('taxonomies/{slug}', [TaxonomyController::class, 'update']);
        Route::delete('taxonomies/{slug}', [TaxonomyController::class, 'destroy']);
    });

    Route::middleware('can:manage.terms')->group(function () {
        Route::get('taxonomies/{slug}/terms', [TermController::class, 'indexByTaxonomy']);
        Route::post('taxonomies/{slug}/terms', [TermController::class, 'store']);
        Route::get('terms/{id}', [TermController::class, 'show']);
        Route::put('terms/{id}', [TermController::class, 'update']);
        Route::delete('terms/{id}', [TermController::class, 'destroy']);

        Route::get('entries/{entry}/terms', [EntryTermsController::class, 'index']);
        Route::post('entries/{entry}/terms/attach', [EntryTermsController::class, 'attach']);
        Route::post('entries/{entry}/terms/detach', [EntryTermsController::class, 'detach']);
        Route::put('entries/{entry}/terms/sync', [EntryTermsController::class, 'sync']);
    });
});
```

## 9) Примеры `curl`

Создать таксономию:
```bash
curl -i -X POST --cookie 'cms_at=token' \
  -H 'Content-Type: application/json' \
  -d '{"label":"Категории","slug":"category"}' \
  https://api.example.com/api/v1/admin/taxonomies
```

Создать термин и сразу привязать к записи `123`:
```bash
curl -i -X POST --cookie 'cms_at=token' \
  -H 'Content-Type: application/json' \
  -d '{"name":"Аналитика","attach_entry_id":123}' \
  https://api.example.com/api/v1/admin/taxonomies/category/terms
```

Привязать существующие термины:
```bash
curl -i -X POST --cookie 'cms_at=token' \
  -H 'Content-Type: application/json' \
  -d '{"term_ids":[7,11]}' \
  https://api.example.com/api/v1/admin/entries/123/terms/attach
```

Синхронизировать термины:
```bash
curl -i -X PUT --cookie 'cms_at=token' \
  -H 'Content-Type: application/json' \
  -d '{"term_ids":[11]}' \
  https://api.example.com/api/v1/admin/entries/123/terms/sync
```

## 10) Формат ответа от нейросети (для реализации)

Вернуть: **Plan / Files / Patchset / Tests / Checks / Notes** — как в общем шаблоне проекта.
