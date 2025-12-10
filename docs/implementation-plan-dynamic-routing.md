# План реализации DB-driven системы роутинга с интеграцией Entry

Исчерпывающий план реализации динамической системы роутинга на основе документов:

-   `docs/plan-dynamic-routing-laravel.md`
-   `docs/entry-routing-integration.md`

**Правило:** После выполнения каждого блока **обязательно** выполнить тесты блока и убедиться, что все работает перед переходом к следующему.

---

## Блок 1: Анализ требований и проектирование модели данных

### Задачи

1.1. Изучить текущую структуру Entry:

-   Статусы (`draft`, `published`)
-   `published_at` и `scopePublished()`
-   `data_json`, `template_override`
-   Индексация через `DocValue/DocRef`
-   Trait `HasDocumentData` и метод `wherePath()`

1.2. Определить структуру таблицы `route_nodes`:

-   Поля: `id`, `parent_id`, `sort_order`, `enabled`
-   `kind`: `group`, `route`, `redirect`
-   `name`, `domain`, `prefix`, `namespace`
-   `methods` (JSON), `uri`
-   `action_type`, `action`, `view`
-   `entry_id` (nullable FK на `entries`)
-   `middleware` (JSON), `where` (JSON), `defaults` (JSON), `options` (JSON)
-   `timestamps`, `soft_deletes`

1.3. Определить enum-значения:

-   `RouteNodeKind`: `GROUP`, `ROUTE`, `REDIRECT`
-   `RouteNodeActionType`: `CONTROLLER`, `ENTRY`

**Назначение каждого `action_type`:**

-   **`CONTROLLER`** — универсальный тип для всех контроллеров, view и redirect.

    -   **Controller@method**: `App\Http\Controllers\BlogController@show`

        -   Использование: кастомная логика, API endpoints, сложная обработка запросов
        -   Пример: `GET /blog/{slug}` → `BlogController@show`

    -   **Invokable controller**: `App\Http\Controllers\HomeController` (без `@method`)

        -   Использование: простые контроллеры с одной точкой входа
        -   Пример: `GET /` → `HomeController` (автоматически вызывает `__invoke`)

    -   **View**: специальный формат `view:pages.about` в поле `action`

        -   Использование: статические страницы без логики, простые лендинги
        -   Пример: `action='view:pages.about'` → `view('pages.about')`

    -   **Redirect**: специальный формат `redirect:/new-page:301` в поле `action`
        -   Использование: редиректы старых URL, временные/постоянные перенаправления
        -   Пример: `action='redirect:/new-page:301'` → `redirect('/new-page', 301)`
        -   Формат: `redirect:{url}:{status}` (status опционален, по умолчанию 302)

-   **`ENTRY`** — жёсткое назначение конкретной Entry на URL.

    -   Требует `entry_id` в узле
    -   Использование: статические страницы контента (О компании, Политика конфиденциальности, лендинги)
    -   Пример: `GET /about` → отображение Entry с `id=123` (страница "О нас")
    -   Контроллер: `EntryPageController@show` (автоматически назначается регистратором)

    **Примечание:** Динамическое разрешение Entry по slug (например, для блогов) реализуется через `CONTROLLER` с кастомным контроллером, использующим `Entry::wherePath()`.

1.4. Создать ER-диаграмму связей:

-   `RouteNode` self-relation (`parent_id`)
-   `RouteNode` belongsTo `Entry` (nullable)

### Результаты

-   Спецификация таблицы `route_nodes` в формате миграции
-   Enum-классы для `kind` и `action_type`
-   Документация связей

### Тесты после блока 1

**Документальные/архитектурные:**

-   [ ] Спецификация покрывает все кейсы: group + nested group, route with entry, route with controller, redirect
-   [ ] Enum-значения зафиксированы в коде как константы
-   [ ] ER-диаграмма соответствует требованиям

---

## Блок 2: Создание миграции route_nodes

### Задачи

2.1. Создать миграцию `create_route_nodes_table`:

-   Все поля из спецификации
-   Индексы: `(parent_id, sort_order)`, `entry_id`, `enabled`
-   Foreign key на `entries` с `nullOnDelete()`

2.2. Добавить индексы для производительности:

-   `enabled` для фильтрации
-   `kind` для группировки
-   `action_type` для фильтрации по типу действия

2.3. Подготовить rollback-логику в `down()`

### Результаты

-   Рабочая миграция `database/migrations/YYYY_MM_DD_HHMMSS_create_route_nodes_table.php`
-   Миграция проходит без ошибок

### Тесты после блока 2

**Feature:**

-   [ ] `php artisan migrate` выполняется успешно
-   [ ] `php artisan migrate:rollback` откатывает таблицу
-   [ ] Все индексы созданы корректно
-   [ ] Foreign key на `entries` работает с `nullOnDelete()`

---

## Блок 3: Создание Enum-классов

### Задачи

3.1. Создать `app/Enums/RouteNodeKind.php`:

-   `GROUP`, `ROUTE`, `REDIRECT`
-   Метод `values(): array`
-   Метод `isGroup(): bool`, `isRoute(): bool`, `isRedirect(): bool`

3.2. Создать `app/Enums/RouteNodeActionType.php`:

-   `CONTROLLER`, `ENTRY`
-   Метод `values(): array`
-   Метод `requiresEntry(): bool` (для `ENTRY`)

3.3. Добавить PHPDoc с описанием каждого значения

### Результаты

-   Enum-классы с полной документацией
-   Типобезопасность при работе с `kind` и `action_type`

### Тесты после блока 3

**Unit:**

-   [ ] `RouteNodeKind::values()` возвращает все значения
-   [ ] `RouteNodeKind::isGroup()` корректно определяет тип
-   [ ] `RouteNodeActionType::requiresEntry()` возвращает `true` для `ENTRY`
-   [ ] Enum можно использовать в type hints

---

## Блок 4: Создание модели RouteNode

### Задачи

4.1. Создать `app/Models/RouteNode.php`:

-   `$fillable` со всеми полями
-   `$casts` для JSON-полей: `methods`, `middleware`, `where`, `defaults`, `options`
-   `$casts` для enum: `kind` → `RouteNodeKind`, `action_type` → `RouteNodeActionType`
-   `SoftDeletes` trait

4.2. Реализовать отношения:

-   `parent()` → `belongsTo(RouteNode::class, 'parent_id')`
-   `children()` → `hasMany(RouteNode::class, 'parent_id')->orderBy('sort_order')->orderBy('id')`
-   `entry()` → `belongsTo(Entry::class, 'entry_id')->nullOnDelete()`

4.3. Добавить scope:

-   `scopeEnabled(Builder $query): Builder`
-   `scopeOfKind(Builder $query, RouteNodeKind $kind): Builder`
-   `scopeRoots(Builder $query): Builder` (где `parent_id IS NULL`)

4.4. Добавить PHPDoc для всех свойств и методов

### Результаты

-   Модель `RouteNode` с полной документацией
-   Рабочие отношения и scopes

### Тесты после блока 4

**Unit/Feature:**

-   [ ] `RouteNode::create()` сохраняет JSON-поля корректно
-   [ ] `$node->parent` возвращает родителя или `null`
-   [ ] `$node->children` возвращает отсортированных потомков
-   [ ] `$node->entry` возвращает Entry или `null`
-   [ ] `RouteNode::enabled()->get()` фильтрует только включённые
-   [ ] `RouteNode::roots()->get()` возвращает только корневые узлы
-   [ ] При удалении Entry, `entry_id` становится `null` (не каскадное удаление)

---

## Блок 5: Создание фабрики и сидера RouteNode

### Задачи

5.1. Создать `database/factories/RouteNodeFactory.php`:

-   Методы состояния: `group()`, `route()`, `redirect()`
-   Методы: `withParent(RouteNode $parent)`, `withEntry(Entry $entry)`
-   Методы: `enabled()`, `disabled()`

5.2. Создать `database/seeders/RouteNodeSeeder.php`:

-   Пример дерева: корневая группа `blog` с дочерним маршрутом `{slug}`
-   Пример статического маршрута `about` с `action_type=entry`
-   Пример redirect-узла

5.3. Добавить вызов сидера в `DatabaseSeeder` (опционально, для dev)

### Результаты

-   Фабрика для тестов
-   Сидер для локальной разработки

### Тесты после блока 5

**Unit:**

-   [ ] `RouteNodeFactory::new()->group()->create()` создаёт узел с `kind=GROUP`
-   [ ] `RouteNodeFactory::new()->withParent($parent)->create()` устанавливает `parent_id`
-   [ ] `RouteNodeFactory::new()->withEntry($entry)->create()` связывает Entry
-   [ ] Сидер создаёт корректное дерево без ошибок

---

## Блок 6: Создание RouteNodeRepository

### Задачи

6.1. Создать `app/Repositories/RouteNodeRepository.php`:

-   Метод `getTree(): Collection` — возвращает корневые узлы с eager loading детей
-   Метод `getEnabledTree(): Collection` — только включённые узлы
-   Метод `getNodeWithAncestors(int $id): ?RouteNode` — узел с предками

6.2. Реализовать оптимизацию запросов:

-   Избежать N+1 через рекурсивный eager loading
-   Альтернатива: загрузка всех узлов одним запросом + сборка дерева в памяти

6.3. Контракт сортировки:

-   Сначала `sort_order`, затем `id` как стабильный tie-breaker

6.4. Добавить кэширование (пока заглушка, будет реализовано в блоке 15)

### Результаты

-   Репозиторий с детерминированным деревом
-   Отсутствие N+1 проблем

### Тесты после блока 6

**Unit:**

-   [ ] `getTree()` возвращает структуру корректной вложенности
-   [ ] Сортировка детей идёт по `sort_order`, затем по `id`
-   [ ] `getEnabledTree()` исключает `enabled=false` узлы
-   [ ] При одинаковом `sort_order` порядок стабилен

**Performance smoke:**

-   [ ] В тесте с 200-500 узлами число запросов не растёт линейно с глубиной (проверка через `DB::getQueryLog()`)

---

## Блок 7: Создание конфигурации dynamic-routes

### Задачи

7.1. Создать `config/dynamic-routes.php`:

-   `allowed_middleware`: массив разрешённых middleware-алиасов
-   `allowed_controllers`: массив разрешённых контроллеров (namespace + класс)
-   `reserved_prefixes`: массив запрещённых префиксов (`api`, `admin`, `sanctum`)
-   `cache_ttl`: время жизни кэша дерева (по умолчанию 3600)
-   `cache_key_prefix`: префикс для ключей кэша

7.2. Добавить параметризованные middleware:

-   `can:*` — разрешён
-   `throttle:*` — разрешён
-   Другие по политике проекта

7.3. Документировать конфигурацию в PHPDoc

### Результаты

-   Централизованная конфигурация безопасности

### Тесты после блока 7

**Unit:**

-   [ ] `config('dynamic-routes.allowed_middleware')` возвращает массив
-   [ ] `config('dynamic-routes.reserved_prefixes')` содержит системные префиксы
-   [ ] Конфиг корректно подхватывается из файла

---

## Блок 8: Создание DynamicRouteGuard

### Задачи

8.1. Создать `app/Services/DynamicRoutes/DynamicRouteGuard.php`:

-   Метод `isMiddlewareAllowed(string $middleware): bool`
-   Метод `isControllerAllowed(string $controller): bool`
-   Метод `isPrefixReserved(string $prefix): bool`
-   Метод `sanitizeMiddleware(array $middleware): array` — фильтрует неразрешённые

8.2. Реализовать логику проверки:

-   Параметризованные middleware (`can:view,Entry`, `throttle:60,1`)
-   Проверка контроллера по полному namespace
-   Проверка префиксов URI

8.3. Логирование нарушений:

-   При неразрешённом middleware/controller — запись в лог

8.4. Добавить PHPDoc

### Результаты

-   Централизованная политика безопасности
-   Защита от произвольных middleware и контроллеров

### Тесты после блока 8

**Unit:**

-   [ ] `isMiddlewareAllowed('web')` возвращает `true` для разрешённого
-   [ ] `isMiddlewareAllowed('unknown')` возвращает `false` для неразрешённого
-   [ ] `isMiddlewareAllowed('can:view,Entry')` возвращает `true` (параметризованный)
-   [ ] `isControllerAllowed('App\\Http\\Controllers\\TestController')` проверяет по конфигу
-   [ ] `sanitizeMiddleware(['web', 'unknown'])` возвращает только `['web']`
-   [ ] `isPrefixReserved('api')` возвращает `true`

---

## Блок 9: Создание DynamicRouteRegistrar с поддержкой CONTROLLER

### Задачи

9.1. Создать `app/Services/DynamicRoutes/DynamicRouteRegistrar.php`:

-   Зависимости: `RouteNodeRepository`, `DynamicRouteGuard`
-   Метод `register(): void` — основная точка входа

9.2. Реализовать регистрацию `group` узлов:

-   `Route::group([...], fn() => children)`
-   Поддержка: `prefix`, `domain`, `namespace`, `middleware`, `where`

9.3. Реализовать регистрацию `route` узлов:

-   `Route::match($methods, $uri, $action)`
-   Поддержка: `name`, `domain`, `middleware`, `where`, `defaults`

9.4. Обработка `action_type=CONTROLLER`:

-   Парсинг `action` с поддержкой форматов:
    -   **Controller@method**: `App\Http\Controllers\BlogController@show`
        -   Регистрация: `Route::match(..., [Controller::class, 'method'])`
    -   **Invokable**: `App\Http\Controllers\HomeController` (без `@`)
        -   Регистрация: `Route::match(..., Controller::class)`
    -   **View**: `view:pages.about`
        -   Регистрация: `Route::match(..., fn() => view('pages.about'))`
    -   **Redirect**: `redirect:/new-page:301` или `redirect:/new-page` (по умолчанию 302)
        -   Регистрация: `Route::match(..., fn() => redirect($url, $status))`
-   Проверка через `DynamicRouteGuard` для контроллеров (не для view/redirect)
-   Обработка ошибок: неразрешённый контроллер → safe action `fn() => abort(404)`

9.5. Обработка `enabled=false`:

-   Узел не регистрируется

9.6. Структурное логирование ошибок регистрации

9.7. Добавить PHPDoc

### Результаты

-   Регистратор маршрутов с поддержкой всех вариантов CONTROLLER

### Тесты после блока 9

**Feature (router integration):**

-   [ ] Локальная регистрация тестового дерева:
    -   Группа с `prefix='blog'` + дочерний `GET /blog/{slug}` → корректный ответ 200
-   [ ] Проверка `where`:
    -   Валидный параметр проходит
    -   Невалидный даёт 404
-   [ ] Проверка middleware:
    -   Тестовый middleware срабатывает при наличии в белом списке
-   [ ] Узел `enabled=false` не создаёт маршрут
-   [ ] Ошибки регистрации логируются
-   [ ] `action_type=CONTROLLER` с `action='App\\Http\\Controllers\\TestController@show'` регистрирует маршрут
-   [ ] `action_type=CONTROLLER` с `action='App\\Http\\Controllers\\TestController'` (invokable) регистрирует маршрут
-   [ ] `action_type=CONTROLLER` с `action='view:pages.about'` возвращает Blade-шаблон
-   [ ] `action_type=CONTROLLER` с `action='redirect:/old:301'` делает редирект
-   [ ] Неразрешённый контроллер заменяется на safe action
-   [ ] Некорректный формат `action` логируется и не ломает регистрацию

---

## Блок 10: Интеграция Entry (режим A — жёсткое назначение)

### Задачи

10.1. Создать `app/Http/Controllers/EntryPageController.php`:

-   Метод `show(Request $request): Response`
-   Получение `route_node_id` из `$request->route()->defaults['route_node_id']`
-   Загрузка `RouteNode` с `entry` и `entry.postType.blueprint`
-   Проверка `entry_id` (404 если отсутствует)

10.2. Логика публикации:

-   По умолчанию только `Entry::published()`
-   Опционально `options->require_published=false` для preview (защищённого)

10.3. Рендеринг:

-   Возврат JSON с данными Entry (headless режим)
-   Структура: `entry`, `post_type`, `blueprint`, `route`

10.4. В `DynamicRouteRegistrar`:

-   При `action_type=ENTRY` задавать action на `EntryPageController@show`
-   Добавлять default: `route_node_id = $node->id`

10.5. Добавить PHPDoc

### Результаты

-   Конструктор может назначать Entry на конкретный URL

### Тесты после блока 10

**Feature:**

-   [ ] Роут `action_type=ENTRY` с `entry_id` возвращает опубликованную Entry
-   [ ] Если `entry_id` отсутствует → 404
-   [ ] Если Entry `status=draft` → 404 (публичный доступ)
-   [ ] Если `published_at > now()` → 404
-   [ ] JSON-ответ содержит `entry`, `post_type`, `blueprint`, `route`
-   [ ] Узел `enabled=false` → маршрут недоступен

---

## Блок 11: Создание DynamicRouteCache

### Задачи

11.1. Создать `app/Services/DynamicRoutes/DynamicRouteCache.php`:

-   Метод `rememberTree(callable $callback): Collection`
-   Метод `forgetTree(): void`
-   Использование `Cache::remember()` с ключом из конфига

11.2. Интеграция в `RouteNodeRepository`:

-   Обернуть `getTree()` в кэш через `DynamicRouteCache`

11.3. Версионирование ключа:

-   Ключ: `{prefix}:tree:v1` (версия для инвалидации при изменении схемы)

11.4. Добавить PHPDoc

### Результаты

-   Кэширование дерева маршрутов

### Тесты после блока 11

**Unit/Feature:**

-   [ ] `rememberTree()` не вызывает builder повторно при наличии кэша
-   [ ] `forgetTree()` очищает кэш
-   [ ] Кэш использует правильный ключ из конфига
-   [ ] TTL кэша соответствует конфигу

---

## Блок 12: Создание RouteNodeObserver для инвалидации кэша

### Задачи

12.1. Создать `app/Observers/RouteNodeObserver.php`:

-   Метод `saved(RouteNode $node): void` → `DynamicRouteCache::forgetTree()`
-   Метод `deleted(RouteNode $node): void` → `DynamicRouteCache::forgetTree()`
-   Метод `restored(RouteNode $node): void` → `DynamicRouteCache::forgetTree()`

12.2. Зарегистрировать Observer в `AppServiceProvider`:

-   `RouteNode::observe(RouteNodeObserver::class)`

12.3. Добавить PHPDoc

### Результаты

-   Автоматическая инвалидация кэша при изменениях

### Тесты после блока 12

**Feature:**

-   [ ] При `RouteNode::create()` кэш сбрасывается
-   [ ] При `RouteNode::update()` кэш сбрасывается
-   [ ] При `RouteNode::delete()` кэш сбрасывается
-   [ ] При `RouteNode::restore()` кэш сбрасывается

---

## Блок 13: Создание Artisan-команд для управления кэшем

### Задачи

13.1. Создать `app/Console/Commands/DynamicRoutesCacheCommand.php`:

-   Команда `routes:dynamic-cache` — прогрев кэша
-   Вызов `RouteNodeRepository::getTree()` для заполнения кэша

13.2. Создать `app/Console/Commands/DynamicRoutesClearCommand.php`:

-   Команда `routes:dynamic-clear` — сброс кэша
-   Вызов `DynamicRouteCache::forgetTree()`

13.3. Зарегистрировать команды в `app/Console/Kernel.php` или через атрибуты

13.4. Добавить PHPDoc и описания команд

### Результаты

-   Команды для ручного управления кэшем

### Тесты после блока 13

**Feature:**

-   [ ] `php artisan routes:dynamic-cache` заполняет кэш
-   [ ] `php artisan routes:dynamic-clear` очищает кэш
-   [ ] Команды выводят информационные сообщения

---

## Блок 14: Интеграция в RouteServiceProvider

### Задачи

14.1. Модифицировать `app/Providers/RouteServiceProvider.php`:

-   Добавить вызов `DynamicRouteRegistrar::register()` в правильном месте
-   Порядок: после `web_content.php`, до fallback
-   Обернуть в проверку существования таблицы (для миграций)

14.2. Обработка ошибок:

-   При ошибке регистрации — логирование, но не падение приложения

14.3. Зафиксировать порядок в комментариях:

-   1.  Core → 2) Public API → 3) Admin API → 4) Content → **5) Dynamic Routes** → 6) Fallback

14.4. Добавить PHPDoc

### Результаты

-   Динамические маршруты интегрированы в систему роутинга

### Тесты после блока 14

**Feature:**

-   [ ] Маршрут из БД доступен после boot приложения
-   [ ] Существующие системные маршруты имеют приоритет
-   [ ] Fallback не перехватывает корректные dynamic routes
-   [ ] При отсутствии таблицы приложение не падает (graceful degradation)

---

## Блок 15: Создание Admin API — CRUD для RouteNode

### Задачи

15.1. Создать `app/Http/Controllers/Admin/V1/RouteNodeController.php`:

-   `index()` — список узлов (дерево или плоский список)
-   `store(StoreRouteNodeRequest $request)` — создание узла
-   `show(RouteNode $routeNode)` — детали узла
-   `update(UpdateRouteNodeRequest $request, RouteNode $routeNode)` — обновление
-   `destroy(RouteNode $routeNode)` — удаление

15.2. Создать `app/Http/Resources/Admin/RouteNodeResource.php`:

-   Форматирование для ответа API
-   Включение связанных сущностей (`entry`, `parent`, `children`) при загрузке

15.3. Авторизация:

-   Middleware: `jwt.auth`
-   Policy: `can:manage-routes` (или ваш аналог)

15.4. Добавить PHPDoc

### Результаты

-   Полный CRUD для конструктора

### Тесты после блока 15

**Feature:**

-   [ ] `GET /api/v1/admin/routes` требует авторизации
-   [ ] `POST /api/v1/admin/routes` создаёт узел с корректными данными → 201
-   [ ] `GET /api/v1/admin/routes/{id}` возвращает детали узла
-   [ ] `PATCH /api/v1/admin/routes/{id}` обновляет узел → 200
-   [ ] `DELETE /api/v1/admin/routes/{id}` удаляет узел → 204
-   [ ] Неавторизованный запрос → 401

---

## Блок 16: Создание FormRequest для валидации RouteNode

### Задачи

16.1. Создать `app/Http/Requests/Admin/StoreRouteNodeRequest.php`:

-   Валидация `kind` (enum)
-   Валидация `action_type` (enum, опционально)
-   Валидация `methods` (массив, допустимые HTTP-методы)
-   Валидация `uri`/`prefix` (строка, не в `reserved_prefixes`)
-   Валидация `middleware` (массив строк)
-   Валидация `entry_id` (существует, если указан)
-   Валидация `parent_id` (существует или null)

16.2. Создать `app/Http/Requests/Admin/UpdateRouteNodeRequest.php`:

-   Аналогично `StoreRouteNodeRequest`, но все поля опциональны

16.3. Кастомные правила валидации:

-   Проверка `uri` на запрещённые префиксы через `DynamicRouteGuard`
-   Проверка `action` на формат для `CONTROLLER` (Controller@method, Invokable, view:, redirect:)

16.4. Добавить PHPDoc

### Результаты

-   Валидация входных данных для конструктора

### Тесты после блока 16

**Feature:**

-   [ ] `POST /api/v1/admin/routes` с запрещённым префиксом `api` → 422
-   [ ] `POST /api/v1/admin/routes` с невалидным `kind` → 422
-   [ ] `POST /api/v1/admin/routes` с несуществующим `entry_id` → 422
-   [ ] `POST /api/v1/admin/routes` с корректными данными → 201
-   [ ] `PATCH /api/v1/admin/routes/{id}` с частичными данными → 200

---

## Блок 17: Создание Admin API — Reorder для дерева

### Задачи

17.1. Расширить `RouteNodeController`:

-   Метод `reorder(ReorderRouteNodesRequest $request)` — массовое изменение `parent_id` и `sort_order`

17.2. Создать `app/Http/Requests/Admin/ReorderRouteNodesRequest.php`:

-   Валидация массива `nodes` с `id`, `parent_id`, `sort_order`
-   Проверка существования всех `id`

17.3. Реализовать транзакционное обновление:

-   `DB::transaction()` для атомарности
-   Массовое обновление через `RouteNode::whereIn()->update()`

17.4. Обработка ошибок:

-   При ошибке — rollback и 422

17.5. Добавить PHPDoc

### Результаты

-   Возможность переупорядочивания дерева

### Тесты после блока 17

**Feature:**

-   [ ] `POST /api/v1/admin/routes/reorder` меняет `parent_id` и `sort_order` атомарно
-   [ ] При ошибке выполняется rollback
-   [ ] Невалидные `id` в запросе → 422
-   [ ] После reorder дерево остаётся консистентным

---

## Блок 18: Политика удаления и целостность дерева

### Задачи

18.1. Определить политику удаления:

-   Вариант: каскадное удаление дочерних узлов (рекомендуется)
-   Альтернатива: запрет удаления родителя с детьми
-   Альтернатива: перенос детей на корень

18.2. Реализовать выбранную политику:

-   В `RouteNodeController@destroy` или отдельном сервисе
-   Рекурсивное удаление детей

18.3. Добавить транзакции:

-   `DB::transaction()` для атомарности

18.4. Добавить PHPDoc

### Результаты

-   Предсказуемое поведение при удалении

### Тесты после блока 18

**Feature/Unit:**

-   [ ] Удаление родителя ведёт к ожидаемому результату по политике
-   [ ] Удаление выполняется в транзакции
-   [ ] При ошибке выполняется rollback

---

## Блок 19: Создание Policy для RouteNode

### Задачи

19.1. Создать `app/Policies/RouteNodePolicy.php`:

-   Метод `manage(User $user): bool` — проверка права управления маршрутами
-   Метод `view(User $user, RouteNode $routeNode): bool`
-   Метод `create(User $user): bool`
-   Метод `update(User $user, RouteNode $routeNode): bool`
-   Метод `delete(User $user, RouteNode $routeNode): bool`

19.2. Зарегистрировать Policy в `AuthServiceProvider`:

-   `RouteNode::class => RouteNodePolicy::class`

19.3. Использовать в контроллере:

-   `$this->authorize('manage', RouteNode::class)` или `$this->authorize('update', $routeNode)`

19.4. Добавить PHPDoc

### Результаты

-   Централизованная авторизация

### Тесты после блока 19

**Feature:**

-   [ ] Пользователь без права `manage` не может создавать узлы → 403
-   [ ] Пользователь с правом `manage` может выполнять CRUD
-   [ ] Policy корректно проверяет права

---

## Блок 20: Preview режим для Entry (защищённый endpoint)

### Задачи

20.1. Создать `app/Http/Controllers/Admin/V1/EntryPreviewController.php`:

-   Метод `show(Entry $entry)` — предпросмотр Entry (включая draft)
-   Авторизация: `can:view,Entry`
-   Возврат данных аналогично `EntryPageController`, но без проверки `published()`

20.2. Добавить маршрут в `routes/api_admin.php`:

-   `GET /api/v1/admin/entries/{entry}/preview`

20.3. Опционально: поддержка query-флага на публичном роуте:

-   `?preview=true` только для авторизованных пользователей с правом `view`

20.4. Добавить PHPDoc

### Результаты

-   Возможность предпросмотра черновиков

### Тесты после блока 20

**Feature:**

-   [ ] `GET /api/v1/admin/entries/{entry}/preview` требует авторизации
-   [ ] Возвращает Entry даже если `status=draft`
-   [ ] Неавторизованный запрос → 401
-   [ ] Пользователь без права `view` → 403

---

## Блок 21: Endpoint entry-picker для конструктора

### Задачи

21.1. Расширить `EntryController` или создать отдельный endpoint:

-   `GET /api/v1/admin/routes/entry-picker`
-   Параметры: `post_type_id`, `q` (поиск), `status`
-   Возврат минимального формата: `id`, `title`, `post_type_id`, `status`, `published_at`

21.2. Использовать `EntryResource` или создать `EntryPickerResource`:

-   Только необходимые поля для выбора в конструкторе

21.3. Пагинация:

-   По умолчанию 20 записей на страницу

21.4. Добавить PHPDoc

### Результаты

-   Удобный endpoint для выбора Entry в конструкторе

### Тесты после блока 21

**Feature:**

-   [ ] `GET /api/v1/admin/routes/entry-picker` возвращает список Entry
-   [ ] Фильтр по `post_type_id` работает
-   [ ] Поиск по `q` работает
-   [ ] Пагинация работает
-   [ ] Формат ответа содержит только необходимые поля

---

## Блок 23: Тесты безопасности и валидации

### Задачи

23.1. Создать `tests/Feature/DynamicRoutes/SecurityTest.php`:

-   Тест: нельзя назначить неразрешённый `action_type`
-   Тест: нельзя назначить `entry_id`, если пользователь не имеет права
-   Тест: публичный endpoint не отдаёт удалённые/черновые записи
-   Тест: нельзя создать маршрут с запрещённым префиксом

23.2. Создать `tests/Feature/DynamicRoutes/ValidationTest.php`:

-   Тест: валидация всех полей `RouteNode`
-   Тест: валидация формата `action` для разных `action_type`
-   Тест: валидация `middleware` массива

23.3. Добавить тесты на SQL injection и XSS (если применимо)

### Результаты

-   Покрытие безопасности тестами

### Тесты после блока 23

**Feature:**

-   [ ] Все тесты безопасности проходят
-   [ ] Все тесты валидации проходят
-   [ ] Нет уязвимостей в обработке входных данных

---

## Блок 24: Создание команды routes:dynamic-lint

### Задачи

24.1. Создать `app/Console/Commands/DynamicRoutesLintCommand.php`:

-   Команда `routes:dynamic-lint`
-   Проверки:
    -   Неизвестные middleware
    -   Запрещённые action
    -   Конфликтные или пустые URI
    -   Отсутствующие `entry_id` при `action_type=ENTRY`

24.2. Вывод предупреждений:

-   Цветной вывод (info/warning/error)
-   Exit code: 0 если всё ОК, 1 если есть ошибки

24.3. Опционально: JSON-формат вывода для CI

24.4. Добавить PHPDoc

### Результаты

-   Команда для диагностики дерева маршрутов

### Тесты после блока 24

**Feature:**

-   [ ] `php artisan routes:dynamic-lint` находит неизвестные middleware
-   [ ] Находит запрещённые action
-   [ ] Находит конфликтные URI
-   [ ] Exit code корректный
-   [ ] JSON-формат работает (если реализован)

---

## Блок 25: Логирование изменений RouteNode

### Задачи

25.1. Расширить `RouteNodeObserver`:

-   Логирование при создании/обновлении/удалении
-   Использование `Log::info()` с контекстом

25.2. Логирование проблем компиляции:

-   В `DynamicRouteRegistrar` при ошибках регистрации
-   Неразрешённые middleware/action
-   Конфликт имён маршрутов

25.3. Структурированное логирование:

-   Контекст: `route_node_id`, `kind`, `action_type`, `uri`

25.4. Добавить PHPDoc

### Результаты

-   Наблюдаемость системы

### Тесты после блока 25

**Unit/Feature:**

-   [ ] При создании `RouteNode` пишется лог
-   [ ] При ошибке регистрации пишется лог с контекстом
-   [ ] Логи содержат необходимую информацию для диагностики

---

## Блок 26: E2E тесты интеграции

### Задачи

26.1. Создать `tests/Feature/DynamicRoutes/IntegrationTest.php`:

-   Тест: core/public/admin routes живут параллельно с dynamic
-   Тест: dynamic content не ломает fallback
-   Тест: CSRF/guard/авторизация не ломаются
-   Тест: порядок регистрации соблюдается

26.2. Создать `tests/Feature/DynamicRoutes/EntryIntegrationTest.php`:

-   Тест: `action_type=ENTRY` (жёсткое назначение) работает end-to-end
-   Тест: `action_type=CONTROLLER` с Entry-резолвингом работает end-to-end
-   Тест: `template_override` влияет на рендеринг (если SSR)

26.3. Тесты производительности:

-   Регистрация 500 узлов не замедляет boot приложения критично

### Результаты

-   Зафиксированный контракт интеграции

### Тесты после блока 26

**E2E/Feature:**

-   [ ] Все интеграционные тесты проходят
-   [ ] Производительность в пределах нормы
-   [ ] Нет регрессий в существующих маршрутах

---

## Блок 27: Обновление документации

### Задачи

27.1. Обновить `docs/generated/README.md` (через `php artisan docs:generate`):

-   Добавить описание новых классов и endpoints

27.2. Создать/обновить `docs/routing-system.md`:

-   Описание DB-driven роутинга
-   Примеры использования
-   API endpoints для конструктора

27.3. Обновить `docs/entry-routing-integration.md`:

-   Отметить реализованные части
-   Добавить примеры использования

27.4. Обновить PHPDoc во всех новых классах:

-   Проверить соответствие сигнатур и документации

### Результаты

-   Актуальная документация

### Тесты после блока 27

**Документационные:**

-   [ ] `php artisan docs:generate` выполняется без ошибок
-   [ ] Документация соответствует коду
-   [ ] Все PHPDoc актуальны

---

## Блок 28: Финальная проверка и чеклист

### Задачи

28.1. Выполнить полный набор тестов:

-   `php artisan test`
-   Проверить покрытие критических путей

28.2. Проверить соответствие чеклисту:

-   [ ] Миграции и модель `RouteNode` готовы
-   [ ] Репозиторий дерева без N+1
-   [ ] Guard с белыми списками
-   [ ] Регистратор поддерживает все `action_type`
-   [ ] Кэш + observer + команды
-   [ ] Интеграция в `RouteServiceProvider` до fallback
-   [ ] Admin API с валидацией и авторизацией
-   [ ] Тесты по каждому блоку проходят
-   [ ] Lint-команда работает
-   [ ] Документация актуальна

28.3. Проверить производительность:

-   Boot приложения с 500 узлами
-   Время регистрации маршрутов

28.4. Проверить безопасность:

-   Нет SQL injection
-   Нет XSS (если применимо)
-   Middleware и контроллеры защищены

### Результаты

-   Готовая к использованию система

### Тесты после блока 28

**Полный регрессионный набор:**

-   [ ] Все тесты проходят
-   [ ] Производительность в норме
-   [ ] Безопасность проверена
-   [ ] Документация актуальна

---

## Рекомендуемый порядок выполнения

1. **Блоки 1-5**: Фундамент (модель данных, миграции, модель, фабрика)
2. **Блоки 6-8**: Репозиторий, конфигурация, Guard
3. **Блок 9**: Регистратор с поддержкой CONTROLLER (включая invokable, view, redirect)
4. **Блок 10**: Интеграция Entry (жёсткое назначение)
5. **Блоки 11-14**: Кэширование и интеграция в RouteServiceProvider
6. **Блоки 15-17**: Admin API и валидация
7. **Блоки 18-19**: Политика удаления и авторизация
8. **Блоки 20-21**: Дополнительные возможности (preview, entry-picker)
9. **Блоки 22-25**: Тесты и качество
10. **Блоки 26-27**: Документация и финальная проверка

---

## Команды для выполнения после каждого блока

```bash
# После каждого блока выполнять:
php artisan test --filter=<BlockName>
php artisan migrate:fresh --seed  # если были изменения в миграциях
php artisan routes:dynamic-clear  # если реализован кэш
php artisan test                  # полный набор тестов
```

---

## Критические моменты

1. **Порядок регистрации маршрутов**: Dynamic routes должны быть после Content, но до Fallback
2. **Безопасность**: Всегда проверять middleware и контроллеры через Guard
3. **Кэширование**: Инвалидировать кэш при любых изменениях RouteNode
4. **Тесты**: Не переходить к следующему блоку, пока не проходят все тесты текущего
5. **Документация**: Обновлять PHPDoc сразу после создания/изменения кода

---

**Итого: 27 блоков реализации**

Каждый блок содержит задачи, результаты и тесты. После выполнения блока обязательно выполнить тесты и убедиться, что всё работает перед переходом к следующему.
