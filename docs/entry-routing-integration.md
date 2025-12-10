# Интеграция Entry в DB-driven систему роутинга (Laravel 12)

Документ описывает, как встроить существующую систему контента **Entry** в динамический (DB-driven) роутинг, управляемый через визуальный конструктор на фронтенде.

Основано на текущей архитектуре Entry: статусы, публикация по расписанию, `data_json` + Blueprint, индексация `DocValue/DocRef`, `scopePublished()`, `template_override`, политики доступа и админ API. fileciteturn2file0

---

## 1. Цели интеграции

1. Дать возможность **назначать Entry на конкретный URL** через дерево `route_nodes`.
2. Поддержать публичный вывод только **опубликованных** записей с учётом `published_at`.
3. Разрешить гибкую маршрутизацию для разных типов контента:
    - статические страницы (about, contacts),
    - статьи/блог,
    - спец-лендинги с `template_override`.
4. Сохранить безопасность и предсказуемость порядка роутов.

---

## 2. Ключевые возможности системы Entry (контракт)

Для интеграции критичны следующие аспекты:

-   Entry имеет статусы `draft/published`. fileciteturn2file0L45-L58
-   Публикация зависит от:
    -   `status = published`
    -   `published_at != null`
    -   `published_at <= now()`  
        и уже оформлена через `scopePublished()`. fileciteturn2file0L132-L151
-   Содержимое хранится в `data_json` и валидируется Blueprint-правилами. fileciteturn2file0L19-L37
-   Есть `template_override` для кастомного Blade-шаблона. fileciteturn2file0L68-L80
-   Индексация через `EntryIndexer` формирует `doc_values/doc_refs` по индексируемым Path. fileciteturn2file0L268-L357
-   Trait `HasDocumentData` даёт мощные scopes для фильтрации по индексам. fileciteturn2file0L526-L609

---

## 3. Модель интеграции: два режима

### 3.1. Режим A — “жёсткое назначение Entry на URL” (рекомендуется для MVP)

Визуальный конструктор создаёт `route_node` с:

-   `kind = route`
-   `methods = ["GET"]`
-   `uri = "about"` (или любой статичный путь)
-   `action_type = "entry"`
-   `entry_id = <id>`
-   опционально:
    -   `middleware`
    -   `name`
    -   `defaults/where`

**Плюсы**

-   Самый простой и надёжный UX конструктора.
-   Никаких неоднозначностей.
-   Идеально для страниц типа “О компании”, “Политика конфиденциальности”, “Лендинги”.

**Минусы**

-   Для больших коллекций статей может быть неудобно создавать маршрут на каждую запись.

---

### 3.2. Режим B — “динамическое разрешение Entry по параметрам” (для блогов/каталогов)

Используется один маршрут шаблоном:

-   `uri = "blog/{slug}"`
-   `action_type = "entry_resolver"`
-   `options`:
    -   `post_type_id`
    -   `slug_path` (например, `"slug"` или `"seo.slug"`)
    -   `template_fallback` (опционально)

Контроллер ищет Entry по индексам:

1. В Blueprint соответствующего PostType Path `slug` должен быть `is_indexed=true`.
2. Тогда поиск реализуется так:

```php
Entry::query()
    ->ofType($postTypeId)
    ->published()
    ->wherePath($slugPath, '=', $slug)
    ->firstOrFail();
```

Это использует ваш индексный слой `DocValue` и не требует отдельной колонки `slug`. fileciteturn2file0L268-L357 fileciteturn2file0L526-L609

**Плюсы**

-   Один маршрут обслуживает тысячи записей.
-   Совместимо с текущей архитектурой Entry.

**Минусы**

-   Требует дисциплины в Blueprint и индексации.
-   Нужно определить контракт уникальности slug на уровне доменной логики.

---

## 4. Расширение схемы route_nodes

Для поддержки Entry рекомендуется зафиксировать следующие значения:

### kind

-   `group`
-   `route`
-   `redirect`
-   `resource` (опционально)

### action_type (минимум для Entry)

-   `entry` — режим A.
-   `entry_resolver` — режим B.

### Рекомендуемые поля

-   `entry_id` — nullable.
-   `options->post_type_id` — для resolver.
-   `options->slug_path` — строка пути Blueprint.
-   `options->require_published` — bool (по умолчанию true).

---

## 5. Публичный контроллер отображения Entry

### 5.1. EntryPageController (режим A)

Контракт:

-   Для публичного фронтенда возвращает **только опубликованные** записи.
-   Для админ-предпросмотра может поддерживать режим “preview” через отдельный защищённый endpoint.

Пример логики:

```php
public function show(Request $request)
{
    $nodeId = $request->route()->defaults['route_node_id'] ?? null;
    abort_if(!$nodeId, 404);

    $node = RouteNode::query()
        ->with('entry.postType.blueprint')
        ->findOrFail($nodeId);

    abort_if(!$node->entry_id, 404);

    $query = Entry::query()->whereKey($node->entry_id);

    // По умолчанию только опубликованное
    $requirePublished = $node->options['require_published'] ?? true;
    if ($requirePublished) {
        $query->published();
    }

    $entry = $query->firstOrFail();

    return $this->renderEntry($entry);
}
```

### 5.2. EntryResolverController (режим B)

```php
public function show(Request $request, string $slug)
{
    $nodeId = $request->route()->defaults['route_node_id'] ?? null;
    abort_if(!$nodeId, 404);

    $node = RouteNode::findOrFail($nodeId);

    $postTypeId = (int)($node->options['post_type_id'] ?? 0);
    $slugPath = (string)($node->options['slug_path'] ?? 'slug');

    abort_if($postTypeId <= 0, 404);

    $entry = Entry::query()
        ->ofType($postTypeId)
        ->published()
        ->wherePath($slugPath, '=', $slug)
        ->firstOrFail();

    return $this->renderEntry($entry);
}
```

---

## 6. Рендеринг Entry: стратегия ответа

В вашей системе уже предусмотрены данные для гибкого рендера:

-   `data_json` — структурированное содержимое.
-   `seo_json` — SEO-мета.
-   `template_override` — явный шаблон. fileciteturn2file0L68-L80

Рекомендуемый подход:

### 6.1. Headless JSON (универсально)

Возвращать:

```json
{
  "entry": {...},
  "post_type": {...},
  "blueprint": {...},
  "route": {...}
}
```

### 6.2. Hybrid SSR (если есть web-слой)

Если `template_override` задан:

-   использовать его,
    иначе:
-   указывать шаблон по умолчанию для PostType.

---

## 7. Интеграция в DynamicRouteRegistrar

При регистрации узла с `action_type`:

-   `entry` -> `[EntryPageController::class, 'show']`
-   `entry_resolver` -> `[EntryResolverController::class, 'show']`

Обязательно добавить `defaults`:

-   `route_node_id = $node->id`
-   опционально:
    -   `post_type_id`, `slug_path` (если удобнее, чем читать options)

---

## 8. Админский UX конструктора

### 8.1. Сценарий “назначить Entry на маршрут”

Фронтенду нужны:

-   список Entry с фильтрами:
    -   по `post_type_id`
    -   по статусам
    -   поиск по названию  
        (это уже есть в админ API Entry). fileciteturn2file0L186-L266

Рекомендуется добавить **узкий** endpoint для конструктора:

-   `GET /api/v1/admin/routes/entry-picker`
    -   проксирует или использует `EntryResource` минимального формата:
        -   `id, title, post_type_id, status, published_at`.

### 8.2. Валидация на стороне API маршрутов

При `action_type=entry`:

-   `entry_id` обязателен.
-   entry должен существовать.

При `action_type=entry_resolver`:

-   `options.post_type_id` обязателен.
-   `options.slug_path` должен быть строкой.
-   желательно проверить, что Path существует в Blueprint данного PostType (мягкая валидация с warning).

---

## 9. Политика доступа и preview

### 9.1. Публичный доступ

-   Рендер только `Entry::published()`. fileciteturn2file0L132-L151

### 9.2. Предпросмотр черновиков

Рекомендуемый отдельный защищённый маршрут:

-   `GET /api/v1/admin/entries/{entry}/preview`
    -   `can:view,Entry`
    -   отдаёт payload как публичный рендер.

Или режим query-флага на публичном роуте:

-   **только если** запрос аутентифицирован и авторизован.

---

## 10. Кэширование и инвалидация

### 10.1. Кэш дерева маршрутов

Сброс по событиям `RouteNode`.

### 10.2. Кэш страниц Entry (опционально)

Если вы делаете SSR или тяжёлые сборки JSON:

-   кэшировать ответ по ключу:
    -   `entry:{id}:v{version}`  
        (у Entry уже есть `version`). fileciteturn2file0L68-L84

### 10.3. Инвалидировать при:

-   `EntryObserver@saved`  
    (там уже происходит индексация). fileciteturn2file0L359-L414

---

## 11. Конфликты и правила приоритета

Рекомендуемые меры:

1. Запретить в конструкторе создавать URI с системными префиксами:
    - `api`, `admin`, `sanctum` и ваши internal-пути.
2. Явно фиксировать порядок регистрации:
    - core -> public api -> admin api -> content static -> **dynamic** -> fallback.
3. Линтер дерева:
    - предупреждать о дублирующих `uri` на одном уровне.

---

## 12. Тестовый набор интеграции

### 12.1. Feature-тесты режима A

1. Создаётся `route_node` с `action_type=entry`.
2. Запрос на URI:
    - возвращает 200 для опубликованной записи.
    - возвращает 404 для draft.
3. `template_override` влияет на выбор шаблона (если используете SSR).

### 12.2. Feature-тесты режима B

1. Blueprint Path `slug` отмечен как `is_indexed=true`.
2. Entry сохраняется -> индексы созданы. fileciteturn2file0L268-L357
3. `GET /blog/{slug}`:
    - находит опубликованную запись через `wherePath`.
    - даёт 404 при отсутствии значения или при draft.

### 12.3. Тесты безопасности

1. Нельзя назначить неразрешённый `action_type`.
2. Нельзя назначить `entry_id`, если пользователь не имеет `manage.routes` (или вашей роли).
3. Публичный endpoint не отдаёт удалённые/черновые записи.

---

## 13. Рекомендуемый путь внедрения

1. Запустить режим A для статических страниц.
2. Добавить `entry-picker` для конструктора.
3. Ввести режим B для блогов:
    - определить slug-path для PostType,
    - включить `is_indexed=true`.
4. Добавить lint-команду для проверки корректности путей и коллизий.

---

## 14. Краткий итог

Интеграция Entry в DB-driven роутинг строится вокруг двух простых контрактов:

-   **Назначение конкретной записи** на конкретный URL (максимально простое и удобное для конструктора).
-   **Динамическое разрешение** записи по индексируемому `slug` в `data_json` через `HasDocumentData` и индекс `DocValue`.

Оба подхода полностью соответствуют текущей архитектуре Entry: статусы, расписание публикаций, Blueprint-валидация, индексный слой и `template_override`. fileciteturn2file0
