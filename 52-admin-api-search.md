# 52. Admin API: Search
---
owner: "@backend-team"
review_cycle_days: 90
last_reviewed: 2025-11-08
system_of_record: "code+generated"
related_code:
  - "routes/api.php"
  - "routes/api_admin.php"
  - "app/Http/Controllers/SearchController.php"
  - "app/Http/Controllers/Admin/SearchAdminController.php"
  - "app/Http/Requests/Public/Search/QuerySearchRequest.php"
  - "app/Http/Resources/SearchHitResource.php"
  - "app/Domain/Search/SearchService.php"
  - "app/Domain/Search/IndexManager.php"
  - "app/Domain/Search/Jobs/ReindexSearchJob.php"
  - "app/Domain/Search/Transformers/EntryToSearchDoc.php"
  - "app/Domain/Search/Commands/SearchReindexCommand.php"
  - "config/search.php"
---

## Контекст и цель
Реализовать публичный поиск контента и админский ребилд индекса. Требования:
- Публичный `GET /api/v1/search` доступен без авторизации, безопасен, с честным 200 даже при **пустом индексе**.
- Админ-ребилд `POST /api/v1/admin/search/reindex` запускает асинхронную переиндексацию (очередь/команда) и возвращает 202.

## Объём задачи (Scope)
- Публичный endpoint поиска по `entries` (только опубликованные/активные).
- Фильтры: `post_type`, `term`(taxonomy), диапазоны дат, `status=only_published` (по умолчанию).
- Подсветка (highlight) и пагинация.
- Админский endpoint для запуска ребилда и, опционально, `GET /status`.
- Генерация OpenAPI/Routes/Permissions в `/docs/_generated/*`.

### Вне объёма
- Сложные агрегаты/фасеты (можно добавить позже как `aggregations`).
- Частичный ребилд по диапазону времён (в этой задаче — full reindex).

---

## Индекс и маппинги (ES/OpenSearch)
- Индекс: алиасы `entries_read` / `entries_write`.
- Документ (минимум):
  - `id`, `post_type`, `slug`, `title`, `excerpt`, `body_plain`, `terms` (array of `{taxonomy,slug}`),
  - `published_at`, `boost` (optional).
- Анализатор: RU/EN (см. `/docs/_generated/search-mappings.md` и `config/search.php`).
- Источник истины: `Entry` + трансформер `EntryToSearchDoc`.

> Если маппинги/алиасы ещё не сгенерированы — обновить `docs:gen` и добавить шаблоны.

---

## Эндпоинты

### 1) Публичный поиск
`GET /api/v1/search` (без авторизации)  
Query:
- `q` — строка запроса (min 2 символа; пустая — разрешена → вернёт пустой набор с 200)
- `post_type` — строка или список через запятую (`page,post,...`)
- `term` — фильтр по термину (`taxonomy:slug`), повторяемый
- `from` / `to` — ISO8601 даты по `published_at`
- `page` (≥1), `per_page` (1..100, по умолчанию 20)

Ответ `200`:
```json
{
  "data": [
    {
      "id": "01HF...",
      "post_type": "page",
      "slug": "about",
      "title": "About Us",
      "excerpt": "…",
      "score": 3.21,
      "highlight": { "body_plain": ["<em>about</em> …"] }
    }
  ],
  "meta": { "total": 0, "page": 1, "per_page": 20, "took_ms": 3 }
}
```
Особые случаи:
- **Пустой индекс** → `200` и `meta.total = 0`, `data: []`.
- Пустой `q` → `200` + `data: []` (без «списка всего»).

Производительность/кэш:
- Rate-limit: 120 rpm per IP.
- Ответы публичные: `Cache-Control: public, max-age=30` + `ETag` (Vary: `Accept-Encoding`).
- Никаких cookie/персонализации.

### 2) Ребилд индекса (админ)
`POST /api/v1/admin/search/reindex` → `202 Accepted`  
Поведение:
- Запускает `SearchReindexCommand` или `ReindexSearchJob` (очередь).
- Алгоритм:
  1. Создать новый индекс с маппингами.
  2. Наполнить документами батчами (stream из БД).
  3. Переключить алиасы `entries_read/write` атомарно.
  4. Удалить старый индекс (опционально, через grace-period).
- Ответ содержит `job_id`/`batch_size`/`estimated_total` (если доступно).

(Опционально) `GET /api/v1/admin/search/reindex/status?job_id=...` → прогресс.

Безопасность:
- Abilities: `search.reindex` (policy), группа `/api/v1/admin/*`.
- Rate-limit: 5 rpm (защита от повторного запуска).

> Все ошибки — RFC7807.

---

## Реализация

### Контроллеры
- `SearchController@index`
- `SearchAdminController@reindex` (+ `status` опционально)

### Валидация
`QuerySearchRequest`:
- `q` — `nullable|string|min:2` (пусто → фенс: вернуть пустой результат),
- `post_type` — `array<string>` (или comma-separated → explode),
- `term` — `array<string>` вида `taxonomy:slug` (проверить формат),
- `from/to` — `date`.

### Сервис
`SearchService`:
- Строит DSL запрос к ES (multi_match + фильтры по полям).
- Преобразует ES hits → `SearchHitResource`.
- Порог подсветки, поля для `_source` и `highlight`.

`IndexManager`:
- Создание индекса, алиасы, bulk-заливка (батчи, retry).
- Команда/Job `SearchReindexCommand`/`ReindexSearchJob` инкапсулируют процесс.

---

## Конфиг
`config/search.php` (пример):
```php
return [
  'driver' => 'elasticsearch',
  'client' => [
    'hosts' => explode(',', env('ES_HOSTS', 'http://localhost:9200')),
  ],
  'indexes' => [
    'entries' => [
      'read_alias' => 'entries_read',
      'write_alias' => 'entries_write',
      'mappings' => base_path('resources/search/entries_mappings.json'),
      'settings' => base_path('resources/search/entries_settings.json'),
    ],
  ],
  'batch' => ['size' => 500],
];
```

---

## Документация и артефакты
- Аннотации для Scribe/Swagger → `composer docs:gen` генерит OpenAPI и Routes.
- Обновить `/docs/10-concepts/search.md` и `/docs/_generated/search-mappings.md` при изменении маппингов.
- Пометка к PR: **requires: docs:gen**.

---

## Ошибки (RFC7807) — примеры
- `422 INVALID_TERM_FORMAT` — неверный формат `term`.
- `403 FORBIDDEN` — нет права на ребилд.
- `500 REINDEX_FAILED` — ошибка в процессе переиндексации.

---

## Тесты (Pest)

### Feature — публичный поиск
- `it_returns_200_and_empty_result_when_index_is_empty`
- `it_searches_by_query_and_respects_filters_post_type_and_term`
- `it_supports_pagination_and_returns_meta`
- `it_accepts_empty_q_and_returns_empty_result`

### Feature — админ ребилд
- `it_starts_reindex_and_returns_202_with_job_id`
- `it_switches_aliases_after_reindex` (интеграционный: эмулировать ES-ответы/фейковый клиент)
- `it_requires_permission_for_reindex` → `403`

### Unit
- `SearchService_builds_correct_dsl`
- `IndexManager_creates_index_and_switches_aliases_atomically`

---

## Приёмка (Acceptance)
- **Пустой индекс**: `GET /api/v1/search` → `200` и пустой список.
- **Ребилд**: `POST /api/v1/admin/search/reindex` запускает процесс (возвращает `202` + `job_id`).
- Публичный поиск возвращает корректную структуру, фильтры и пагинацию.
- Сгенерированы `/docs/_generated/*` (OpenAPI/Routes/Permissions).

---

## Примеры запросов

**Публичный поиск**
```bash
curl "https://cms.local/api/v1/search?q=about&post_type=page&term=category:news&page=1&per_page=10"
```

**Ребилд**
```bash
curl -X POST "https://cms.local/api/v1/admin/search/reindex" -H "Cookie: jwt=..."
```

**(Опц.) Статус**
```bash
curl "https://cms.local/api/v1/admin/search/reindex/status?job_id=01J..." -H "Cookie: jwt=..."
```
