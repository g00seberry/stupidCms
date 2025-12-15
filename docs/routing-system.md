# Система роутинга

## Оглавление

1. [Обзор](#обзор)
2. [Архитектура](#архитектура)
3. [Компоненты системы](#компоненты-системы)
4. [Типы маршрутов](#типы-маршрутов)
5. [Процесс регистрации маршрутов](#процесс-регистрации-маршрутов)
6. [Безопасность](#безопасность)
7. [Кэширование](#кэширование)
8. [API для управления маршрутами](#api-для-управления-маршрутами)
9. [Примеры использования](#примеры-использования)
10. [Структура базы данных](#структура-базы-данных)

---

## Обзор

Система роутинга представляет собой гибридную систему, объединяющую **декларативные маршруты** (из файлов `routes/*.php`) и **динамические маршруты** (из базы данных `route_nodes`). Все маршруты организованы в иерархическое дерево с поддержкой групп и вложенности.

### Ключевые особенности

-   **Гибридная система**: декларативные маршруты из файлов + динамические из БД
-   **Иерархическая структура**: поддержка групп маршрутов с наследованием настроек
-   **Приоритет декларативных маршрутов**: они регистрируются первыми и имеют приоритет
-   **Два типа узлов**: группы (`GROUP`) и маршруты (`ROUTE`)
-   **Два типа действий**: контроллеры (`CONTROLLER`) и Entry (`ENTRY`)
-   **Безопасность**: проверка разрешённых middleware и контроллеров
-   **Кэширование**: автоматическое кэширование дерева маршрутов
-   **Защита от изменений**: декларативные маршруты помечены как `readonly`

---

## Архитектура

### Порядок регистрации маршрутов

Маршруты регистрируются в следующем детерминированном порядке:

1. **Core маршруты** (`web_core.php`, `sort_order = -1000`)

    - Системные веб-маршруты
    - Middleware: `web`
    - Пример: главная страница `/`

2. **Public API** (`api.php`, `sort_order = -999`)

    - Публичные API endpoints
    - Префикс: `api/v1`
    - Middleware: `api`
    - Примеры: `/api/v1/auth/login`, `/api/v1/media/{id}`

3. **Admin API** (`api_admin.php`, `sort_order = -998`)

    - Админские API endpoints
    - Префикс: `api/v1/admin`
    - Middleware: `api`, `jwt.auth`, `throttle:api`
    - Примеры: `/api/v1/admin/entries`, `/api/v1/admin/route-nodes`

4. **Content маршруты** (`web_content.php`, `sort_order = -997`)

    - Контентные веб-маршруты
    - Middleware: `web`
    - Обычно пустая группа, контент управляется через БД

5. **Динамические маршруты из БД** (`sort_order >= 0`)

    - Маршруты, созданные через админ-панель
    - Загружаются из таблицы `route_nodes`
    - Сортируются по `sort_order`, затем по `id`

6. **Fallback маршрут** (строго последним)
    - Обрабатывает все несовпавшие запросы (404)
    - Регистрируется для всех HTTP методов

### Схема загрузки

```
RouteServiceProvider::boot()
    └─> registerAllRoutes()
        └─> DynamicRouteRegistrar::register()
            └─> RouteNodeRepository::getEnabledTree()
                ├─> DeclarativeRouteLoader::loadAll()  (декларативные)
                └─> RouteNode::query()->where('enabled', true)  (из БД)
            └─> DynamicRouteRegistrar::registerNode()  (для каждого корневого узла)
                ├─> registerGroup()  (если kind=GROUP)
                └─> registerRoute()  (если kind=ROUTE)
```

---

## Компоненты системы

### 1. RouteNode (Модель)

**Файл**: `app/Models/RouteNode.php`

Eloquent модель для узлов маршрутов. Хранит всю информацию о маршруте или группе.

**Основные поля**:

-   `id` - ID узла
-   `parent_id` - ID родительского узла (NULL для корневых)
-   `sort_order` - Порядок сортировки
-   `enabled` - Включён ли маршрут
-   `readonly` - Защита от изменения (true для декларативных)
-   `kind` - Тип узла: `GROUP` или `ROUTE`
-   `name` - Имя маршрута (для `Route::name()`)
-   `domain` - Домен для маршрута
-   `prefix` - Префикс URI для группы
-   `namespace` - Namespace контроллеров для группы
-   `methods` - HTTP методы (только для `ROUTE`)
-   `uri` - URI паттерн (только для `ROUTE`)
-   `action_type` - Тип действия: `CONTROLLER` или `ENTRY`
-   `action` - Действие (Controller@method, view:..., redirect:...)
-   `entry_id` - ID связанной Entry (для `action_type=ENTRY`)
-   `middleware` - Массив middleware
-   `where` - Ограничения параметров маршрута
-   `defaults` - Значения по умолчанию
-   `options` - Дополнительные опции

**Связи**:

-   `parent()` - Родительский узел
-   `children()` - Дочерние узлы (отсортированные по `sort_order`, затем по `id`)
-   `entry()` - Связанная Entry (для `action_type=ENTRY`)

**Скоупы**:

-   `scopeEnabled()` - Только включённые узлы
-   `scopeOfKind()` - Узлы определённого типа
-   `scopeRoots()` - Только корневые узлы

### 2. RouteNodeRepository

**Файл**: `app/Repositories/RouteNodeRepository.php`

Репозиторий для загрузки дерева маршрутов с оптимизацией запросов.

**Основные методы**:

-   `getTree()` - Получить полное дерево маршрутов (с кэшированием)
-   `getEnabledTree()` - Получить дерево только включённых маршрутов (с кэшированием)
-   `getNodeWithAncestors(int $id)` - Получить узел со всеми предками

**Логика работы**:

1. Загружает декларативные маршруты через `DeclarativeRouteLoader`
2. Загружает маршруты из БД одним запросом
3. Собирает дерево в памяти (избегая N+1 проблем)
4. Объединяет декларативные и БД маршруты
5. Сортирует по `sort_order` (декларативные с отрицательными значениями идут первыми)

### 3. DeclarativeRouteLoader

**Файл**: `app/Services/DynamicRoutes/DeclarativeRouteLoader.php`

Сервис для загрузки декларативных маршрутов из файлов `routes/*.php`.

**Основные методы**:

-   `loadAll()` - Загрузить все декларативные маршруты
-   `loadFromFile(string $file)` - Загрузить маршруты из файла
-   `convertToRouteNodes(array $config, string $source)` - Преобразовать массив в RouteNode
-   `createFromArray(array $data, ?RouteNode $parent, string $source)` - Создать RouteNode из массива

**Особенности**:

-   Декларативные маршруты получают отрицательные ID (начинаются с -1)
-   Все декларативные маршруты помечаются как `readonly = true`
-   Поддерживается фильтрация по environment через `options.environments`
-   Обрабатывает иерархию (группы и вложенные маршруты)

### 4. DynamicRouteRegistrar

**Файл**: `app/Services/DynamicRoutes/DynamicRouteRegistrar.php`

Сервис для регистрации маршрутов в Laravel Router.

**Основные методы**:

-   `register()` - Зарегистрировать все маршруты
-   `registerCollection(Collection $nodes)` - Зарегистрировать коллекцию маршрутов
-   `registerNode(RouteNode $node)` - Зарегистрировать узел (группу или маршрут)
-   `registerGroup(RouteNode $node)` - Зарегистрировать группу
-   `registerRoute(RouteNode $node)` - Зарегистрировать маршрут
-   `resolveAction(RouteNode $node)` - Разрешить действие для маршрута

**Поддерживаемые форматы action**:

-   `Controller@method`: `App\Http\Controllers\BlogController@show`
-   `Invokable controller`: `App\Http\Controllers\HomeController`
-   `view:pages.about` - Возвращает view
-   `redirect:/new-page:301` - Редирект с кодом статуса
-   `redirect:/new-page` - Редирект (по умолчанию 302)

**Для `action_type=ENTRY`**:

-   Автоматически использует `EntryPageController@show`
-   Передаёт `route_node_id` через `defaults`

### 5. DynamicRouteGuard

**Файл**: `app/Services/DynamicRoutes/DynamicRouteGuard.php`

Сервис для проверки безопасности динамических маршрутов.

**Основные методы**:

-   `isMiddlewareAllowed(string $middleware)` - Проверить разрешённость middleware
-   `isControllerAllowed(string $controller)` - Проверить разрешённость контроллера
-   `isPrefixReserved(string $prefix)` - Проверить зарезервированность префикса
-   `sanitizeMiddleware(array $middleware)` - Отфильтровать неразрешённые middleware
-   `checkConflict(string $uri, array $methods, ?int $excludeId)` - Проверить конфликт маршрута
-   `canCreateRoute(string $uri, array $methods, ?int $excludeId)` - Проверить возможность создания маршрута

**Поддерживаемые паттерны**:

-   Параметризованные middleware: `can:*`, `throttle:*`
-   Wildcard для классов: `App\Http\Middleware\*`, `App\Http\Controllers\*`

### 6. DynamicRouteCache

**Файл**: `app/Services/DynamicRoutes/DynamicRouteCache.php`

Сервис для кэширования дерева маршрутов.

**Основные методы**:

-   `rememberTree(callable $callback)` - Получить дерево из кэша или выполнить callback
-   `forgetTree()` - Очистить кэш

**Настройки** (в `config/dynamic-routes.php`):

-   `cache_ttl` - Время жизни кэша (по умолчанию 3600 секунд)
-   `cache_key_prefix` - Префикс ключа кэша (по умолчанию `dynamic_routes`)

**Формат ключа**: `{prefix}:tree:v{version}` (например, `dynamic_routes:tree:v1`)

### 7. RouteNodeObserver

**Файл**: `app/Observers/RouteNodeObserver.php`

Observer для автоматической инвалидации кэша при изменениях RouteNode.

**Обрабатываемые события**:

-   `saved` - При создании или обновлении узла
-   `deleted` - При удалении узла (включая soft delete)
-   `restored` - При восстановлении узла из soft delete

### 8. RouteNodeDeletionService

**Файл**: `app/Services/DynamicRoutes/RouteNodeDeletionService.php`

Сервис для каскадного удаления узлов маршрутов.

**Основные методы**:

-   `deleteWithChildren(RouteNode $node)` - Рекурсивно удалить узел и всех потомков
-   `canDelete(RouteNode $node)` - Проверить возможность удаления

**Особенности**:

-   Выполняется в транзакции для атомарности
-   Использует soft delete для всех узлов
-   Возвращает количество удалённых узлов

---

## Типы маршрутов

### RouteNodeKind (Enum)

**Файл**: `app/Enums/RouteNodeKind.php`

Определяет два типа узлов:

1. **GROUP** (`'group'`)

    - Группа маршрутов для организации иерархии
    - Применяет общие настройки к дочерним узлам:
        - `prefix` - Префикс URI
        - `domain` - Домен
        - `namespace` - Namespace контроллеров
        - `middleware` - Middleware
        - `where` - Ограничения параметров

2. **ROUTE** (`'route'`)
    - Конкретный HTTP endpoint
    - Требует: `uri`, `methods`, `action_type`
    - Может иметь собственные настройки, переопределяющие групповые

### RouteNodeActionType (Enum)

**Файл**: `app/Enums/RouteNodeActionType.php`

Определяет два типа действий:

1. **CONTROLLER** (`'controller'`)

    - Универсальный тип для контроллеров, view и redirect
    - Поддерживаемые форматы в поле `action`:
        - `Controller@method`: `App\Http\Controllers\BlogController@show`
        - `Invokable controller`: `App\Http\Controllers\HomeController`
        - `view:pages.about` - Возвращает view
        - `redirect:/new-page:301` - Редирект с кодом статуса
        - `redirect:/new-page` - Редирект (по умолчанию 302)

2. **ENTRY** (`'entry'`)
    - Жёсткое назначение конкретной Entry на URL
    - Требует `entry_id` в узле
    - Автоматически использует `EntryPageController@show`
    - Передаёт `route_node_id` через `defaults` маршрута
    - Использование: статические страницы контента (О компании, Политика конфиденциальности, лендинги)

---

## Процесс регистрации маршрутов

### 1. Загрузка дерева

```
RouteNodeRepository::getEnabledTree()
    ├─> DynamicRouteCache::rememberTree()  (проверка кэша)
    └─> loadEnabledTree()
        ├─> DeclarativeRouteLoader::loadAll()  (декларативные)
        │   ├─> loadFromFile('web_core.php')
        │   ├─> loadFromFile('api.php')
        │   ├─> loadFromFile('api_admin.php')
        │   └─> loadFromFile('web_content.php')
        └─> RouteNode::query()->where('enabled', true)  (из БД)
            └─> Сборка дерева в памяти
```

### 2. Регистрация в Laravel Router

```
DynamicRouteRegistrar::register()
    └─> Для каждого корневого узла:
        └─> registerNode()
            ├─> Если kind=GROUP:
            │   └─> registerGroup()
            │       ├─> buildGroupAttributes()
            │       └─> Route::group($attributes, function() {
            │           └─> Рекурсивно для каждого дочернего узла
            └─> Если kind=ROUTE:
                └─> registerRoute()
                    ├─> resolveAction()
                    ├─> Route::match($methods, $uri, $action)
                    └─> Применение настроек (name, domain, middleware, where, defaults)
```

### 3. Разрешение действия

```
resolveAction(RouteNode $node)
    ├─> Если action_type=ENTRY:
    │   └─> return [EntryPageController::class, 'show']
    └─> Если action_type=CONTROLLER:
        ├─> Если action начинается с 'view:':
        │   └─> return fn() => view($viewName)
        ├─> Если action начинается с 'redirect:':
        │   └─> return fn() => redirect($url, $status)
        └─> Если action содержит '@':
            ├─> Controller@method
            └─> Проверка через DynamicRouteGuard::isControllerAllowed()
        └─> Иначе:
            └─> Invokable controller
            └─> Проверка через DynamicRouteGuard::isControllerAllowed()
```

---

## Безопасность

### Конфигурация безопасности

**Файл**: `config/dynamic-routes.php`

#### Разрешённые middleware (`allowed_middleware`)

Массив разрешённых middleware-алиасов, которые могут быть назначены динамическим маршрутам.

**Поддерживаемые паттерны**:

-   Точное совпадение: `'web'`, `'api'`, `'auth'`
-   Параметризованные: `'can:*'`, `'throttle:*'`
-   Wildcard для классов: `'App\Http\Middleware\*'`

**Примеры**:

```php
'allowed_middleware' => [
    'web',
    'api',
    'auth',
    'jwt.auth',
    'can:*',  // Разрешает can:view,Entry, can:edit,Post и т.д.
    'throttle:*',  // Разрешает throttle:60,1, throttle:120,1 и т.д.
    'App\Http\Middleware\*',  // Разрешает все middleware из этого namespace
],
```

#### Разрешённые контроллеры (`allowed_controllers`)

Массив разрешённых контроллеров (полные namespace + класс).

**Поддерживаемые паттерны**:

-   Точное совпадение: `'App\Http\Controllers\BlogController'`
-   Wildcard: `'App\Http\Controllers\*'`

**Примеры**:

```php
'allowed_controllers' => [
    'App\Http\Controllers\*',  // Разрешает все контроллеры из стандартного namespace
],
```

#### Зарезервированные префиксы (`reserved_prefixes`)

Массив запрещённых префиксов URI, которые нельзя использовать в динамических маршрутах.

**Примеры**:

```php
'reserved_prefixes' => [
    'api',
    'admin',
    'sanctum',
    '_ignition',
    'horizon',
    'telescope',
],
```

### Проверки при создании маршрута

1. **Проверка зарезервированных префиксов**

    - Первый сегмент URI проверяется на наличие в `reserved_prefixes`
    - Запрещено создавать маршруты с префиксами `api`, `admin` и т.д.

2. **Проверка конфликтов**

    - Проверяется совпадение URI и пересечение HTTP методов
    - Проверяются как декларативные маршруты, так и маршруты из БД
    - При обновлении маршрута исключается сам маршрут из проверки

3. **Проверка middleware**

    - При регистрации маршрута неразрешённые middleware фильтруются
    - Логируется предупреждение о неразрешённых middleware

4. **Проверка контроллеров**
    - При регистрации маршрута проверяется разрешённость контроллера
    - Неразрешённые контроллеры заменяются на `abort(404)`
    - Логируется ошибка о неразрешённых контроллерах

### Защита декларативных маршрутов

-   Все декларативные маршруты помечены как `readonly = true`
-   При попытке обновления или удаления декларативного маршрута возвращается ошибка 403
-   Поле `readonly` запрещено устанавливать через API (в `StoreRouteNodeRequest`)

---

## Кэширование

### Механизм кэширования

1. **Кэширование дерева маршрутов**

    - Дерево маршрутов кэшируется через `DynamicRouteCache::rememberTree()`
    - TTL настраивается в `config/dynamic-routes.php` (по умолчанию 3600 секунд)
    - Ключ кэша: `{prefix}:tree:v{version}` (например, `dynamic_routes:tree:v1`)

2. **Автоматическая инвалидация**

    - При создании, обновлении или удалении RouteNode кэш автоматически очищается
    - Реализовано через `RouteNodeObserver`

3. **Ручная очистка кэша**
    - Команда: `php artisan dynamic-routes:clear`
    - Или через `DynamicRouteCache::forgetTree()`

### Оптимизация загрузки

-   Все узлы загружаются одним запросом (избегание N+1 проблем)
-   Дерево собирается в памяти
-   Используется составной индекс `(parent_id, sort_order)` для эффективной сортировки

---

## API для управления маршрутами

### Endpoints

Все endpoints находятся под префиксом `/api/v1/admin/route-nodes` и требуют аутентификации через JWT.

#### 1. Список всех маршрутов

```
GET /api/v1/admin/route-nodes
```

Возвращает объединённый плоский список всех маршрутов (декларативные + из БД) с меткой источника.

**Ответ**:

```json
{
    "data": [
        {
            "id": -1,
            "uri": "/",
            "methods": ["GET"],
            "name": "home",
            "source": "web_core.php",
            "readonly": true
        },
        {
            "id": 1,
            "uri": "/about",
            "methods": ["GET"],
            "name": "about",
            "source": "database",
            "readonly": false
        }
    ]
}
```

#### 2. Создание узла маршрута

```
POST /api/v1/admin/route-nodes
```

**Тело запроса**:

```json
{
    "kind": "route",
    "parent_id": null,
    "sort_order": 0,
    "enabled": true,
    "name": "about",
    "uri": "/about",
    "methods": ["GET"],
    "action_type": "controller",
    "action": "App\\Http\\Controllers\\AboutController@show",
    "middleware": ["web"]
}
```

**Валидация**:

-   `kind` - обязательный, значения: `group`, `route`
-   `action_type` - обязательный, значения: `controller`, `entry`
-   `uri` - проверка на зарезервированные префиксы и конфликты
-   `action` - проверка формата (для `action_type=controller`)
-   `entry_id` - обязательный для `action_type=entry`
-   `readonly` - запрещено устанавливать через API

#### 3. Получение узла маршрута

```
GET /api/v1/admin/route-nodes/{id}
```

Возвращает полную информацию об узле, включая связанные сущности (entry, parent, children).

#### 4. Обновление узла маршрута

```
PUT /api/v1/admin/route-nodes/{id}
```

**Ограничения**:

-   Декларативные маршруты (`readonly=true`) нельзя обновлять (403)

#### 5. Удаление узла маршрута

```
DELETE /api/v1/admin/route-nodes/{id}
```

**Особенности**:

-   Выполняется каскадное удаление (удаляются все дочерние узлы)
-   Декларативные маршруты (`readonly=true`) нельзя удалять (403)
-   Используется soft delete

#### 6. Переупорядочивание узлов

```
POST /api/v1/admin/route-nodes/reorder
```

**Тело запроса**:

```json
{
    "nodes": [
        { "id": 1, "parent_id": null, "sort_order": 0 },
        { "id": 2, "parent_id": 1, "sort_order": 0 }
    ]
}
```

Массовое изменение `parent_id` и `sort_order` для множества узлов. Выполняется в транзакции.

---

## Примеры использования

### Пример 1: Создание простого маршрута

```php
// Через API
POST /api/v1/admin/route-nodes
{
  "kind": "route",
  "uri": "/blog",
  "methods": ["GET"],
  "action_type": "controller",
  "action": "App\\Http\\Controllers\\BlogController@index",
  "name": "blog.index",
  "middleware": ["web"]
}
```

### Пример 2: Создание группы маршрутов

```php
// Создаём группу
POST /api/v1/admin/route-nodes
{
  "kind": "group",
  "prefix": "blog",
  "middleware": ["web", "auth"],
  "sort_order": 0
}

// Создаём маршруты внутри группы
POST /api/v1/admin/route-nodes
{
  "kind": "route",
  "parent_id": 1,  // ID созданной группы
  "uri": "/posts",
  "methods": ["GET"],
  "action_type": "controller",
  "action": "App\\Http\\Controllers\\BlogController@index",
  "name": "blog.posts.index"
}
```

Результат: маршрут будет доступен по адресу `/blog/posts` с middleware `web` и `auth`.

### Пример 3: Создание маршрута для Entry

```php
POST /api/v1/admin/route-nodes
{
  "kind": "route",
  "uri": "/about",
  "methods": ["GET"],
  "action_type": "entry",
  "entry_id": 5,  // ID Entry
  "name": "about.page"
}
```

Результат: маршрут будет обрабатываться через `EntryPageController@show`, который вернёт JSON с данными Entry.

### Пример 4: Декларативный маршрут (в файле)

```php
// routes/web_content.php
return [
    [
        'kind' => RouteNodeKind::GROUP,
        'sort_order' => -997,
        'middleware' => ['web'],
        'children' => [
            [
                'kind' => RouteNodeKind::ROUTE,
                'uri' => '/custom-page',
                'methods' => ['GET'],
                'action_type' => RouteNodeActionType::CONTROLLER,
                'action' => 'App\Http\Controllers\CustomPageController@show',
                'name' => 'custom.page',
            ],
        ],
    ],
];
```

### Пример 5: Использование view и redirect

```php
// View
{
  "kind": "route",
  "uri": "/static-page",
  "methods": ["GET"],
  "action_type": "controller",
  "action": "view:pages.static"
}

// Redirect
{
  "kind": "route",
  "uri": "/old-page",
  "methods": ["GET"],
  "action_type": "controller",
  "action": "redirect:/new-page:301"
}
```

### Пример 6: Условная загрузка маршрута

```php
// routes/web_core.php
[
    'kind' => RouteNodeKind::ROUTE,
    'uri' => '/admin/ping',
    'methods' => ['GET'],
    'action_type' => RouteNodeActionType::CONTROLLER,
    'action' => 'App\Http\Controllers\AdminPingController@ping',
    'options' => [
        'environments' => ['testing'],  // Загружается только в testing окружении
    ],
]
```

---

## Структура базы данных

### Таблица `route_nodes`

```sql
CREATE TABLE route_nodes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    -- Self-relation для иерархии
    parent_id BIGINT UNSIGNED NULL,
    FOREIGN KEY (parent_id) REFERENCES route_nodes(id) ON DELETE RESTRICT,

    -- Сортировка и состояние
    sort_order INT UNSIGNED DEFAULT 0,
    enabled BOOLEAN DEFAULT TRUE,
    readonly BOOLEAN DEFAULT FALSE,

    -- Тип узла
    kind ENUM('group', 'route') NOT NULL,

    -- Настройки маршрута/группы
    name VARCHAR(255) NULL,
    domain VARCHAR(255) NULL,
    prefix VARCHAR(255) NULL,
    namespace VARCHAR(255) NULL,

    -- HTTP методы и URI (только для kind='route')
    methods JSON NULL,
    uri VARCHAR(255) NULL,

    -- Тип действия и само действие
    action_type ENUM('controller', 'entry') NOT NULL,
    action VARCHAR(255) NULL,

    -- Связь с Entry (для action_type='entry')
    entry_id BIGINT UNSIGNED NULL,
    FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE SET NULL,

    -- Дополнительные настройки
    middleware JSON NULL,
    where JSON NULL,
    defaults JSON NULL,
    options JSON NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    -- Индексы
    INDEX idx_enabled (enabled),
    INDEX idx_readonly (readonly),
    INDEX idx_kind (kind),
    INDEX idx_action_type (action_type),
    INDEX idx_entry_id (entry_id),
    INDEX route_nodes_parent_sort_idx (parent_id, sort_order)
);
```

### Индексы

-   `idx_enabled` - Для фильтрации включённых маршрутов
-   `idx_readonly` - Для фильтрации декларативных маршрутов
-   `idx_kind` - Для фильтрации по типу узла
-   `idx_action_type` - Для фильтрации по типу действия
-   `idx_entry_id` - Для связи с Entry
-   `route_nodes_parent_sort_idx` - Составной индекс для эффективной загрузки дерева с сортировкой

---

## Дополнительные замечания

### Обработка ошибок

-   При ошибке регистрации маршрута логируется предупреждение, но загрузка приложения не прерывается
-   Несуществующие контроллеры заменяются на `abort(404)`
-   Неразрешённые middleware фильтруются, но маршрут всё равно регистрируется

### Производительность

-   Дерево маршрутов кэшируется для избежания повторных запросов к БД
-   Все узлы загружаются одним запросом (избегание N+1 проблем)
-   Используются составные индексы для эффективной сортировки

### Тестирование

-   Система полностью покрыта тестами (см. `tests/Feature/DynamicRoutes/`, `tests/Unit/Services/DynamicRoutes/`)
-   В тестах таблица `route_nodes` может не существовать - система корректно обрабатывает это

### Миграции

-   Миграция создания таблицы: `database/migrations/2025_12_10_152453_create_route_nodes_table.php`

---

## Заключение

Система роутинга предоставляет гибкий и мощный механизм для управления маршрутами в headless CMS. Она объединяет статические декларативные маршруты с динамическими маршрутами из базы данных, обеспечивая при этом безопасность, производительность и удобство использования.
