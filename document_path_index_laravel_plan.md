# Документная система с path-индексацией и ссылками в Laravel 12 + MySQL

Подробный поэтапный план реализации.

---

## Стадия 1. Концепция и цели

- Хранить произвольные JSON-документы в MySQL.
- Давать динамическую схему без миграций (поля описываются в БД, а не в структурах таблиц).
- Иметь индекс по путям (`path`) внутри JSON для быстрых запросов.
- Поддерживать ссылки между документами (ref-поля).
- Сохранить удобство Eloquent (модели, связи, скоупы).

Ключевые идеи:

1. Таблица `documents` с полем `raw_json`.
2. Таблица `paths` — реестр полей (full_path, тип, кардинальность, ref-target, is_indexed).
3. Таблица `doc_values` — индекс скалярных значений.
4. Таблица `doc_refs` — индекс ссылок «документ → документ».
5. Laravel-модели и трейты, которые скрывают всю механику за удобным API.

---

## Стадия 2. Схема БД (MySQL)

### 2.1. Таблица `documents`

- Поля:
  - `id` (PK)
  - `type` (строка — логический тип: article, banner, product…)
  - `raw_json` (JSON)
  - timestamps
- Индекс по `type`.

### 2.2. Таблица `paths`

- Поля:
  - `id`
  - `parent_id` (nullable, FK → paths.id)
  - `name` (короткое имя: name, bug, weight)
  - `full_path` (уникальный путь: `name`, `bug.weight`, `article`, `relatedArticles`)
  - `data_type` (enum: string, int, bool, json, ref)
  - `cardinality` (enum: one, many)
  - `is_indexed` (bool)
  - `ref_target_type` (nullable string для ref-полей: article, user…)
  - timestamps
- `full_path` уникальное.

### 2.3. Таблица `doc_values`

- Поля:
  - `document_id` (FK → documents.id)
  - `path_id` (FK → paths.id)
  - `idx` (nullable, индекс массива; NULL для одиночных значений)
  - `value_string` (nullable)
  - `value_int` (nullable)
  - `value_bool` (nullable)
  - `value_json` (nullable JSON)
- PK: `(document_id, path_id, idx)`.
- Индексы:
  - `(path_id, value_string)`
  - `(path_id, value_int)`
  - `(path_id, value_bool)`.

### 2.4. Таблица `doc_refs`

- Поля:
  - `document_id` (FK → documents.id) — документ-владелец.
  - `path_id` (FK → paths.id) — какое поле (article / relatedArticles).
  - `idx` (nullable, индекс в массиве ссылок).
  - `target_document_id` (FK → documents.id) — целевой документ.
- PK: `(document_id, path_id, idx)`.
- Индекс: `(path_id, target_document_id)`.

---

## Стадия 3. Базовые модели Eloquent

### 3.1. `Path`

- `fillable`: `parent_id`, `name`, `full_path`, `data_type`, `cardinality`, `is_indexed`, `ref_target_type`.
- Связи:
  - `parent()`
  - `children()`.

### 3.2. `DocValue`

- `timestamps = false`.
- `fillable`: `document_id`, `path_id`, `idx`, `value_string`, `value_int`, `value_bool`, `value_json`.
- Связи:
  - `document()`
  - `path()`.

### 3.3. `DocRef`

- `timestamps = false`.
- `fillable`: `document_id`, `path_id`, `idx`, `target_document_id`.
- Связи:
  - `owner()` (belongsTo Document по document_id)
  - `target()` (belongsTo Document по target_document_id)
  - `path()`.

### 3.4. `Document`

- `fillable`: `type`, `raw_json`.
- `casts`: `raw_json` → `array`.
- `appends`: `data` (геттер/сеттер поверх raw_json).
- Связи:
  - `values()` → hasMany DocValue.
  - `refs()` → hasMany DocRef.
- Подключить трейт `HasDocumentData`.

### 3.5. Типизированные документы

Например:

- `ArticleDocument extends Document`
- `BannerDocument extends Document`

В каждом:

- `protected $attributes = ['type' => 'article'];`
- `booted()` → глобальный скоуп, фильтрующий по `type`.

---

## Стадия 4. Трейт HasDocumentData

Задачи:

1. Автоматически синхронизировать индексы при `saved`:
   - скаляры → `doc_values`;
   - ссылки → `doc_refs`.
2. Предоставить:
   - `getPath($path, $default)` / `setPath($path, $value)`;
   - `scopeWherePath($query, $path, $op, $value)`.

Ключевые шаги в трейте:

- В `bootHasDocumentData()` повесить `static::saved(...)`.
- В `syncDocumentIndex()`:
  - получить `$data = $this->raw_json ?? []`;
  - выбрать все `Path::where('is_indexed', true)` (затем кешировать);
  - удалить старые записи в `values()` и `refs()`;
  - для каждого пути:
    - `data_get($data, $path->full_path)`;
    - если `data_type === 'ref'` → `syncRefPath`;
    - иначе → `syncScalarPath`.

Для скаляров:

- Если `cardinality === 'one'` → одна запись в `doc_values`.
- Если `many` и `$value` — массив → одна запись на элемент (`idx` = позиция).

Для ref:

- Аналогично, но записи идут в `doc_refs` с `target_document_id`.

`scopeWherePath`:

- Находит `Path` по `full_path`.
- Подключает `whereHas('values', ...)`:
  - фильтрует по `path_id`;
  - выбирает нужный `value_*` в зависимости от `data_type`.

---

## Стадия 5. Ссылки (ref) и связи Eloquent

### 5.1. Представление ссылок в JSON

Простой формат:

```json
{
  "title": "Main banner",
  "article": 42,
  "relatedArticles": [42, 77, 91]
}
```

- `article` и `relatedArticles` описаны в `paths` как `data_type = ref`.
- В индексе `doc_refs` появятся строки:
  - (banner_id, path(article), idx = null, target_document_id = 42)
  - (banner_id, path(relatedArticles), idx = 0/1/2, target_document_id = 42/77/91).

### 5.2. Универсальные связи на Document

Метод `belongsToDocument($path, $targetClass)`:

- Находит `Path` по `full_path`.
- Строит `hasOneThrough` через `DocRef` к целевой модели (например, ArticleDocument).
- Фильтрует по `doc_refs.path_id`.

Метод `hasManyDocuments($path, $targetClass)`:

- Аналогично, но `hasManyThrough`.
- Сортирует по `doc_refs.idx`.

### 5.3. Типизированные связи

В `BannerDocument`:

- `article()` → `return $this->belongsToDocument('article', ArticleDocument::class);`
- `relatedArticles()` → `return $this->hasManyDocuments('relatedArticles', ArticleDocument::class);`

Примеры:

```php
$banner = BannerDocument::with('article', 'relatedArticles')->first();
$article = $banner->article;
$related = $banner->relatedArticles;
```

Фильтрация по полям связанной статьи:

```php
$banners = BannerDocument::whereHas('article', function ($q) {
    $q->wherePath('seo.slug', '=', 'how-to-fly');
})->get();
```

---

## Стадия 6. Управление схемой (paths) и админка

Функционал:

1. Список путей (`paths`):
   - `full_path`, `data_type`, `cardinality`, `is_indexed`, `ref_target_type`.
2. Создание/редактирование пути:
   - указать `full_path`;
   - выбрать тип и кардинальность;
   - отметить `is_indexed`;
   - для `ref` указать тип целевого документа.
3. Кнопка/действие «Реиндексировать»:
   - глобально;
   - по типу документа;
   - потенциально по конкретному пути.

---

## Стадия 7. Команда реиндексации

Пример простой команды:

- `php artisan docs:reindex {--type=}`.

В реализации:

- Фильтруем `Document::query()` по `type` (если указан).
- `chunkById(100, fn($docs) => foreach $doc->syncDocumentIndex())`.

В будущем можно улучшить:

- Опция `--path=full_path` для частичной реиндексации только одного пути.
- Асинхронная постановка задач в очередь.

---

## Стадия 8. Оптимизации

- **Кеширование `paths`**:
  - статический кеш в трейте;
  - опционально — Redis/апп-кеш.
- **Пакетные вставки**:
  - собирать массивы `doc_values` / `doc_refs` и делать один `insert`.
- **Ограничение количества индексируемых путей**:
  - выставлять `is_indexed` только там, где реально нужны запросы.
- **Специальные индексы** под горячие запросы:
  - например, `INDEX(path_id, value_string)` для конкретного `path_id` (seo.slug).

---

## Стадия 9. Тестирование и внедрение

### 9.1. Тесты индексации

- Подготовить записи в `paths`.
- Создать `Document` с разным JSON.
- Проверить содержимое `doc_values` и `doc_refs` после `save()`.

### 9.2. Тесты wherePath / whereHas

- Проверить, что `wherePath('bug.weight', '>', 10)` возвращает правильные документы.
- Проверить связки:
  - `BannerDocument::whereHas('article', fn($q) => $q->wherePath('seo.slug', 'x'))`.

### 9.3. Миграция старых данных

- Написать конвертер, который:
  - проходит по старым таблицам/моделям;
  - на каждый объект создаёт `Document` с `raw_json`;
  - вызывает `syncDocumentIndex()` (или запускает `docs:reindex`).

---

Этот план можно использовать как основу для реальной реализации или оформить в виде Laravel-пакета (с миграциями, моделями, трейтами и artisan-командами).
