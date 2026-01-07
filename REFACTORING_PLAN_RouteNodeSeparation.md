# План рефакторинга: Разделение декларативных и динамических маршрутов

## Цель
Разделить декларативные маршруты (из файлов) и динамические маршруты (из БД), предоставить отдельный API endpoint для системных маршрутов, и улучшить проверку конфликтов с учетом полной иерархии маршрута (включая префиксы родительских групп).

## Текущее состояние
- Декларативные и динамические маршруты объединяются в `RouteNodeRepository::getEnabledTree()`
- Проверка конфликтов в `DynamicRouteGuard::checkConflict()` не учитывает префиксы родительских групп
- Все маршруты возвращаются через единый endpoint `/api/v1/admin/routes` (index)

## Целевое состояние
- Декларативные маршруты доступны отдельно через `/api/v1/admin/routes/system`
- Динамические маршруты доступны через `/api/v1/admin/routes` (только из БД)
- Проверка конфликтов учитывает полный путь: `prefix1/prefix2/uri` (с учетом всех родительских групп)
- При регистрации маршрутов декларативные и динамические объединяются (без изменений)

---

## План реализации (12 пунктов)

### 1. Создать сервис для построения полного пути маршрута

**Файл:** `app/Services/DynamicRoutes/RoutePathBuilder.php`

**Задачи:**
- Создать класс `RoutePathBuilder` с методом `buildFullPath(RouteNode $routeNode): string`
- Метод должен рекурсивно собирать префиксы всех родительских групп от корня до маршрута
- Формат результата: `prefix1/prefix2/uri` (без ведущего слэша)
- Обработать случаи:
  - Маршрут без родителя: возвращать только `uri`
  - Группа без префикса: пропускать в пути
  - Пустой префикс: не добавлять в путь
- Добавить метод `buildFullPathForNodeWithAncestors(RouteNode $node, Collection $ancestors): string` для оптимизации (когда предки уже загружены)

**Пример:**
```php
// Группа: prefix = "api/v1", parent_id = null
// Группа: prefix = "admin", parent_id = 1
// Маршрут: uri = "users", parent_id = 2
// Результат: "api/v1/admin/users"
```

---

### 2. Обновить RouteNodeRepository для разделения методов получения маршрутов

**Файл:** `app/Repositories/RouteNodeRepository.php`

**Задачи:**
- Переименовать `getEnabledTree()` → `getEnabledTree()` (оставить для регистрации маршрутов)
- Добавить метод `getDynamicTree(): Collection` — возвращает только маршруты из БД (enabled = true)
- Добавить метод `getDeclarativeTree(): Collection` — возвращает только декларативные маршруты
- Обновить `loadEnabledTree()` — оставить для регистрации (объединяет оба источника)
- Добавить `loadDynamicTree(): Collection` — загружает только из БД
- Добавить `loadDeclarativeTree(): Collection` — загружает только декларативные (уже есть, сделать публичным)

**Изменения:**
- `loadDeclarativeTree()` сделать публичным методом (сейчас private)
- Добавить кэширование для `getDynamicTree()` и `getDeclarativeTree()`

---

### 3. Обновить DynamicRouteGuard для проверки конфликтов с учетом иерархии

**Файл:** `app/Services/DynamicRoutes/DynamicRouteGuard.php`

**Задачи:**
- Добавить зависимость от `RoutePathBuilder` в конструктор
- Обновить метод `checkConflict()`:
  - При проверке декларативных маршрутов: строить полный путь для каждого маршрута с учетом родительских групп
  - При проверке динамических маршрутов: строить полный путь с учетом родительских групп
  - Сравнивать полные пути, а не только URI маршрута
- Обновить метод `findConflictInCollection()`:
  - При обходе дерева накапливать префиксы родительских групп
  - Для каждого маршрута строить полный путь: `accumulatedPrefix/uri`
  - Сравнивать полные пути с нормализованным URI из запроса
- Обновить метод `canCreateRoute()`:
  - Принимать `parentId` для построения полного пути нового маршрута
  - Строить полный путь нового маршрута перед проверкой конфликтов

**Логика построения пути:**
```php
// Для маршрута в запросе:
// 1. Если parent_id указан → загрузить всех предков
// 2. Построить полный путь: prefix1/prefix2/uri
// 3. Проверить конфликт с полным путем
```

---

### 4. Обновить RouteConflictRule для передачи parent_id в проверку конфликтов

**Файл:** `app/Rules/RouteConflictRule.php`

**Задачи:**
- Добавить получение `parent_id` из запроса
- Передать `parent_id` в `DynamicRouteGuard::canCreateRoute()`
- Обновить сигнатуру `canCreateRoute()`: добавить параметр `?int $parentId = null`
- Обновить сообщение об ошибке: показывать полный путь конфликтующего маршрута

**Изменения:**
```php
// Было:
$result = $guard->canCreateRoute($uri, $methods, $this->excludeId);

// Станет:
$parentId = $request->input('parent_id');
$result = $guard->canCreateRoute($uri, $methods, $this->excludeId, $parentId);
```

---

### 5. Создать новый метод в RouteNodeController для системных маршрутов

**Файл:** `app/Http/Controllers/Admin/V1/RouteNodeController.php`

**Задачи:**
- Добавить метод `system(): JsonResponse` — возвращает только декларативные маршруты
- Использовать `RouteNodeRepository::getDeclarativeTree()`
- Вернуть через `RouteNodeResource::collection()`
- Добавить документацию в PHPDoc (аналогично `index()`)
- Метод должен быть доступен по маршруту `/api/v1/admin/routes/system`

**Пример:**
```php
/**
 * Список системных (декларативных) маршрутов.
 *
 * Возвращает только маршруты из файлов routes/*.php.
 * Эти маршруты нельзя изменять через UI (readonly).
 *
 * @group Admin ▸ Routes
 * @name List system routes
 * @authenticated
 */
public function system(Request $request): JsonResponse
{
    $this->authorize('viewAny', RouteNode::class);
    
    $cache = app(DynamicRouteCache::class);
    $loader = new DeclarativeRouteLoader();
    $repository = new RouteNodeRepository($cache, $loader);
    
    $systemNodes = $repository->getDeclarativeTree();
    
    return RouteNodeResource::collection($systemNodes)->response();
}
```

---

### 6. Обновить метод index() в RouteNodeController для возврата только динамических маршрутов

**Файл:** `app/Http/Controllers/Admin/V1/RouteNodeController.php`

**Задачи:**
- Изменить `index()` для использования `RouteNodeRepository::getDynamicTree()`
- Обновить PHPDoc: указать, что возвращаются только маршруты из БД
- Убрать упоминание декларативных маршрутов из документации

**Изменения:**
```php
// Было:
$allNodes = $repository->getEnabledTree();

// Станет:
$allNodes = $repository->getDynamicTree();
```

---

### 7. Добавить маршрут для системных маршрутов в api_admin.php

**Файл:** `routes/api_admin.php`

**Задачи:**
- Найти секцию с маршрутами RouteNodeController
- Добавить новый маршрут перед существующим `/routes`:
  ```php
  [
      'kind' => RouteNodeKind::ROUTE,
      'uri' => '/routes/system',
      'methods' => ['GET'],
      'action_type' => RouteNodeActionType::CONTROLLER,
      'action' => 'App\Http\Controllers\Admin\V1\RouteNodeController@system',
      'name' => 'admin.v1.routes.system',
  ],
  ```
- Убедиться, что маршрут находится в правильной группе (внутри `api/v1/admin`)

---

### 8. Обновить RouteNodeRepository для публичного метода loadDeclarativeTree()

**Файл:** `app/Repositories/RouteNodeRepository.php`

**Задачи:**
- Изменить видимость `loadDeclarativeTree()` с `private` на `public`
- Добавить кэширование через `DynamicRouteCache` для декларативных маршрутов
- Создать метод `getDeclarativeTree(): Collection` с кэшированием
- Убедиться, что кэш инвалидируется при изменении файлов маршрутов (опционально, для будущего)

**Изменения:**
```php
// Было:
private function loadDeclarativeTree(): Collection

// Станет:
public function getDeclarativeTree(): Collection
{
    return $this->cache->rememberDeclarativeTree(function () {
        return $this->loadDeclarativeTree();
    });
}

private function loadDeclarativeTree(): Collection
```

---

### 9. Обновить DynamicRouteCache для поддержки кэширования декларативных маршрутов

**Файл:** `app/Services/DynamicRoutes/DynamicRouteCache.php`

**Задачи:**
- Добавить метод `rememberDeclarativeTree(callable $callback): Collection`
- Использовать отдельный ключ кэша: `dynamic_routes:declarative_tree`
- Добавить метод `rememberDynamicTree(callable $callback): Collection` с ключом `dynamic_routes:dynamic_tree`
- Обновить метод инвалидации кэша: очищать оба ключа при изменении маршрутов

**Пример:**
```php
public function rememberDeclarativeTree(callable $callback): Collection
{
    return Cache::remember(
        'dynamic_routes:declarative_tree',
        $this->getCacheTtl(),
        $callback
    );
}

public function rememberDynamicTree(callable $callback): Collection
{
    return Cache::remember(
        'dynamic_routes:dynamic_tree',
        $this->getCacheTtl(),
        $callback
    );
}
```

---

### 10. Обновить тесты для проверки новой функциональности

**Файлы:**
- `tests/Unit/Services/DynamicRoutes/RoutePathBuilderTest.php` (создать)
- `tests/Unit/Services/DynamicRoutes/DynamicRouteGuardTest.php` (обновить)
- `tests/Unit/Repositories/RouteNodeRepositoryTest.php` (обновить)
- `tests/Feature/Http/Controllers/Admin/V1/RouteNodeControllerTest.php` (обновить)

**Задачи:**
- Создать тесты для `RoutePathBuilder`:
  - Тест построения пути для маршрута без родителя
  - Тест построения пути с одной родительской группой
  - Тест построения пути с несколькими уровнями вложенности
  - Тест обработки пустых префиксов
- Обновить тесты `DynamicRouteGuard`:
  - Тест проверки конфликта с учетом префиксов родительских групп
  - Тест проверки конфликта для маршрута в группе
- Обновить тесты `RouteNodeRepository`:
  - Тест `getDeclarativeTree()` возвращает только декларативные
  - Тест `getDynamicTree()` возвращает только из БД
- Обновить тесты контроллера:
  - Тест `system()` возвращает декларативные маршруты
  - Тест `index()` возвращает только динамические маршруты

---

### 11. Обновить документацию и комментарии в коде

**Файлы:**
- Все измененные файлы (обновить PHPDoc)
- `README.md` или документация API (если есть)

**Задачи:**
- Обновить PHPDoc в `RouteNodeRepository`:
  - Описать разделение декларативных и динамических маршрутов
  - Указать назначение каждого метода
- Обновить PHPDoc в `DynamicRouteGuard`:
  - Описать проверку конфликтов с учетом иерархии
  - Указать, что проверяется полный путь маршрута
- Обновить PHPDoc в `RouteNodeController`:
  - Описать разницу между `index()` и `system()`
- Добавить примеры использования в комментариях

---

### 12. Обновить RouteKindValidationBuilder для передачи parent_id в RouteConflictRule

**Файл:** `app/Http/Requests/Admin/RouteNode/Kinds/RouteKindValidationBuilder.php`

**Задачи:**
- Обновить создание `RouteConflictRule` в `buildRulesForStore()`:
  - Передавать `parent_id` из запроса в правило (через замыкание)
- Обновить создание `RouteConflictRule` в `buildRulesForUpdate()`:
  - Передавать `parent_id` из запроса или из модели (если не указан в запросе)
- Убедиться, что `RouteConflictRule` может получить `parent_id` из запроса

**Изменения:**
```php
// В buildRulesForStore():
'uri' => [
    'required',
    'string',
    'max:255',
    new ReservedPrefixRule(),
    new RouteConflictRule(null, $this->getParentIdFromRequest()), // Передаем parent_id
],

// Добавить метод:
private function getParentIdFromRequest(): ?int
{
    $parentId = request()->input('parent_id');
    return $parentId !== null ? (int) $parentId : null;
}
```

**Обновить RouteConflictRule:**
```php
public function __construct(
    private ?int $excludeId = null,
    private ?int $parentId = null, // Добавить параметр
) {}
```

---

## Последовательность выполнения

1. **Пункт 1** — Создать `RoutePathBuilder` (базовая функциональность)
2. **Пункт 2** — Обновить `RouteNodeRepository` (разделение методов)
3. **Пункт 3** — Обновить `DynamicRouteGuard` (проверка с иерархией)
4. **Пункт 4** — Обновить `RouteConflictRule` (передача parent_id)
5. **Пункт 12** — Обновить `RouteKindValidationBuilder` (интеграция)
6. **Пункт 9** — Обновить `DynamicRouteCache` (кэширование)
7. **Пункт 8** — Обновить `RouteNodeRepository` (публичные методы)
8. **Пункт 5** — Создать метод `system()` в контроллере
9. **Пункт 6** — Обновить метод `index()` в контроллере
10. **Пункт 7** — Добавить маршрут в `api_admin.php`
11. **Пункт 10** — Написать/обновить тесты
12. **Пункт 11** — Обновить документацию

---

## Важные замечания

### Обратная совместимость
- Метод `getEnabledTree()` должен продолжать работать для регистрации маршрутов
- API endpoint `/api/v1/admin/routes` изменит поведение (только динамические), но это ожидаемо

### Производительность
- Кэширование декларативных и динамических маршрутов отдельно улучшит производительность
- Построение полного пути должно быть оптимизировано (кэширование предков)

### Безопасность
- Endpoint `/api/v1/admin/routes/system` должен требовать авторизацию (как и `/routes`)
- Декларативные маршруты должны быть помечены как `readonly` (уже реализовано)

---

## Примеры использования после рефакторинга

### Получить системные маршруты
```http
GET /api/v1/admin/routes/system
Authorization: Bearer {token}
```

### Получить динамические маршруты
```http
GET /api/v1/admin/routes
Authorization: Bearer {token}
```

### Создать маршрут в группе (с проверкой полного пути)
```http
POST /api/v1/admin/routes
Authorization: Bearer {token}
Content-Type: application/json

{
  "kind": "route",
  "parent_id": 5,  // ID группы "api/v1/admin"
  "uri": "users",
  "methods": ["GET"],
  "action_type": "controller",
  "action": "App\\Http\\Controllers\\Admin\\V1\\UserController@index"
}
```

**Проверка конфликта:**
- Если группа с `parent_id = 5` имеет `prefix = "admin"`
- И родитель группы имеет `prefix = "api/v1"`
- То полный путь будет: `api/v1/admin/users`
- Конфликт проверится с этим полным путем

---

## Критерии готовности

- [ ] Декларативные маршруты доступны через `/api/v1/admin/routes/system`
- [ ] Динамические маршруты доступны через `/api/v1/admin/routes` (только из БД)
- [ ] Проверка конфликтов учитывает префиксы родительских групп
- [ ] При создании маршрута в группе проверяется полный путь: `prefix1/prefix2/uri`
- [ ] Все тесты проходят
- [ ] Документация обновлена
- [ ] Кэширование работает корректно для обоих типов маршрутов

