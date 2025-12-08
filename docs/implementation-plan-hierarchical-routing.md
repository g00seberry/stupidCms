# План реализации иерархической системы роутинга

План разбит на 10 блоков задач с тестами. Каждый блок выполняется последовательно: реализация → тесты → проверка.

## Общие принципы выполнения

1. **Выполняем блок** — реализуем функционал блока
2. **Пишем тесты** — создаём тесты для нового функционала
3. **Выполняем тесты** — запускаем `php artisan test` и убеждаемся, что всё работает

**Важно:** После каждого блока необходимо убедиться, что все тесты проходят, включая существующие.

---

## Блок 1: База данных и миграции

### Задачи

1. Создать миграцию для таблицы `route_nodes`:
   - `id` (PK, ULID)
   - `parent_id` (nullable, FK на route_nodes.id)
   - `type` (enum: 'folder', 'entry')
   - `slug` (string, один сегмент, без `/`)
   - `title` (string, человеческое имя)
   - `entry_id` (nullable, FK на entries.id)
   - `is_published` (boolean, default: true)
   - `sort` (integer, default: 0)
   - `path_cache` (nullable, string) — материализованный путь для оптимизации
   - `created_at`, `updated_at`
   - Уникальный индекс `(parent_id, slug)` — уникальность в пределах родителя
   - Уникальный индекс `entry_id` (где `type = 'entry'`) — одна entry = один node
   - Индекс на `parent_id` для быстрого поиска детей
   - Индекс на `type` для фильтрации
   - Индекс на `is_published` для публичных запросов

2. Создать миграцию для таблицы `route_redirects`:
   - `id` (PK, ULID)
   - `old_path` (string, уникальный) — старый путь
   - `new_node_id` (nullable, FK на route_nodes.id)
   - `new_path` (nullable, string) — новый путь (если node_id не указан)
   - `http_code` (integer, default: 301)
   - `created_at`
   - Уникальный индекс на `old_path`
   - Индекс на `new_node_id`

3. Добавить связь `route_node_id` в таблицу `entries` (nullable):
   - FK на route_nodes.id
   - Индекс на `route_node_id`

### Тесты

**Файл:** `tests/Feature/Database/RouteNodesMigrationTest.php`

```php
✓ таблица route_nodes создана с правильной структурой
✓ таблица route_redirects создана с правильной структурой
✓ поле route_node_id добавлено в entries
✓ индексы созданы корректно
✓ внешние ключи настроены правильно
✓ уникальные ограничения работают (parent_id, slug)
✓ уникальное ограничение entry_id работает для type='entry'
```

**Файл:** `tests/Unit/Models/RouteNodeTest.php`

```php
✓ модель RouteNode может быть создана
✓ связь parent работает
✓ связь children работает
✓ связь entry работает (для type='entry')
✓ касты типов работают (type, is_published)
✓ массовое присвоение настроено правильно
```

---

## Блок 2: Модели и связи

### Задачи

1. Создать модель `RouteNode`:
   - Определить связи: `parent()`, `children()`, `entry()`
   - Определить касты: `type` (enum), `is_published` (boolean)
   - Добавить скоупы: `folders()`, `entryNodes()`, `published()`, `root()` (где parent_id is null)
   - Методы: `isFolder()`, `isEntry()`, `isRoot()`

2. Обновить модель `Entry`:
   - Добавить связь `routeNode()` (hasOne)
   - Обновить метод `url()` для использования routeNode (если есть)

3. Создать Factory для `RouteNode`:
   - Поддержка создания folder и entry nodes
   - Поддержка создания с parent

4. Создать Policy для `RouteNode`:
   - `viewAny`, `view`, `create`, `update`, `delete`

### Тесты

**Файл:** `tests/Unit/Models/RouteNodeTest.php`

```php
✓ модель RouteNode создаётся с правильными атрибутами
✓ связь parent возвращает родительский узел
✓ связь children возвращает дочерние узлы
✓ связь entry работает для entry-узлов
✓ скоуп folders фильтрует только folder-узлы
✓ скоуп entryNodes фильтрует только entry-узлы
✓ скоуп published фильтрует опубликованные узлы
✓ скоуп root фильтрует корневые узлы
✓ метод isFolder() возвращает true для folder
✓ метод isEntry() возвращает true для entry
✓ метод isRoot() возвращает true для корневых узлов
✓ касты типов работают корректно
```

**Файл:** `tests/Unit/Models/EntryRouteNodeTest.php`

```php
✓ связь routeNode работает
✓ метод url() использует routeNode если он есть
✓ метод url() возвращает плоский slug если routeNode отсутствует
```

**Файл:** `tests/Feature/Policies/RouteNodePolicyTest.php`

```php
✓ admin может просматривать любые route nodes
✓ admin может создавать route nodes
✓ admin может обновлять route nodes
✓ admin может удалять route nodes
✓ обычный пользователь не может управлять route nodes
```

---

## Блок 3: RoutePathBuilder — построение путей

### Задачи

1. Создать интерфейс `RoutePathBuilderInterface`:
   - `buildPath(RouteNode $node): string` — построить полный путь
   - `buildSegments(RouteNode $node): array` — получить массив сегментов
   - `getFirstSegment(RouteNode $node): ?string` — получить первый сегмент
   - `getFullPath(RouteNode $node): string` — получить полный путь

2. Создать реализацию `RoutePathBuilder`:
   - Вычисление пути "на лету" по цепочке parent
   - Кэширование результатов (опционально, через cache)
   - Обработка root-узлов (пустой путь или специальный маркер)
   - Поддержка материализованного `path_cache` (если есть)

3. Добавить метод в модель `RouteNode`:
   - `getFullPath(): string` — использует RoutePathBuilder
   - `getSegments(): array`
   - `getFirstSegment(): ?string`

### Тесты

**Файл:** `tests/Unit/Domain/Routing/RoutePathBuilderTest.php`

```php
✓ buildPath для root-узла возвращает пустую строку или "/"
✓ buildPath для folder на первом уровне возвращает /folder
✓ buildPath для вложенного folder возвращает /parent/child
✓ buildPath для entry-node возвращает полный путь включая slug entry
✓ buildSegments возвращает массив сегментов
✓ getFirstSegment возвращает первый сегмент или null для root
✓ кэширование работает корректно
✓ инвалидация кэша при изменении parent
✓ корректная работа при глубине > 3 уровней
✓ корректная работа с материализованным path_cache
```

**Файл:** `tests/Unit/Models/RouteNodePathTest.php`

```php
✓ метод getFullPath() использует RoutePathBuilder
✓ метод getSegments() возвращает массив сегментов
✓ метод getFirstSegment() возвращает первый сегмент
```

---

## Блок 4: RouteResolver — резолвинг путей

### Задачи

1. Создать интерфейс `RouteResolverInterface`:
   - `resolveByPath(string $path): ?RouteNode` — найти узел по пути
   - `resolveFolder(string $path): ?RouteNode` — найти folder по пути
   - `resolveEntry(string $path): ?Entry` — найти entry по пути (через entry-node)

2. Создать реализацию `RouteResolver`:
   - Разбиение пути на сегменты
   - Поиск по дереву: сначала folders, затем entry-node
   - Проверка `is_published` для всех узлов в цепочке
   - Кэширование результатов резолвинга
   - Обработка пустого пути (root)

3. Интеграция с кэшем:
   - Ключ: `route:resolve:{path}`
   - Инвалидация при изменении узлов в поддереве

### Тесты

**Файл:** `tests/Unit/Domain/Routing/RouteResolverTest.php`

```php
✓ resolveByPath находит folder на первом уровне
✓ resolveByPath находит вложенный folder
✓ resolveByPath находит entry-node по полному пути
✓ resolveByPath возвращает null для несуществующего пути
✓ resolveByPath проверяет is_published для всех узлов в цепочке
✓ resolveByPath возвращает null если родитель не опубликован
✓ resolveFolder находит только folder-узлы
✓ resolveEntry находит entry через entry-node
✓ resolveEntry возвращает null если entry-node не опубликован
✓ кэширование результатов работает
✓ инвалидация кэша при изменении узлов
✓ обработка пустого пути (root)
✓ обработка путей с одинаковыми slug в разных ветках
```

**Файл:** `tests/Feature/Routing/RouteResolverIntegrationTest.php`

```php
✓ резолвинг работает с реальной БД
✓ резолвинг корректно обрабатывает транзакции
✓ резолвинг работает с большим деревом (>100 узлов)
```

---

## Блок 5: RouteTreeService — операции с деревом

### Задачи

1. Создать интерфейс `RouteTreeServiceInterface`:
   - `createFolder(string $slug, string $title, ?int $parentId = null, bool $isPublished = true): RouteNode`
   - `renameNode(int $nodeId, string $newSlug, ?string $newTitle = null): RouteNode`
   - `moveNode(int $nodeId, ?int $newParentId, int $newSort = 0): RouteNode`
   - `reorderNodes(array $nodeIds): void` — массовый reorder
   - `publishBranch(int $nodeId, bool $isPublished): void` — массовая публикация/скрытие
   - `createEntryNode(int $entryId, string $slug, ?int $parentId = null, bool $isPublished = true): RouteNode`
   - `moveEntryNode(int $entryId, ?int $newParentId): RouteNode`
   - `deleteNode(int $nodeId, bool $force = false): void` — запрет удаления если есть children

2. Создать реализацию `RouteTreeService`:
   - Все операции в транзакциях
   - Валидация уникальности slug в пределах parent
   - Каскадное обновление `path_cache` для всех descendants
   - Инвалидация кэша резолвинга
   - Проверка циклов при перемещении
   - Автоматическое обновление sort при создании/перемещении

3. Интеграция с RouteRedirectService (если включён):
   - Создание редиректов при переименовании/перемещении

### Тесты

**Файл:** `tests/Unit/Domain/Routing/RouteTreeServiceTest.php`

```php
✓ createFolder создаёт folder-узел
✓ createFolder проверяет уникальность slug в пределах parent
✓ createFolder запрещает reserved slug на первом уровне
✓ renameNode переименовывает узел
✓ renameNode обновляет path_cache для всех descendants
✓ renameNode создаёт редирект (если включено)
✓ moveNode перемещает узел
✓ moveNode запрещает создание циклов
✓ moveNode обновляет path_cache для всех descendants
✓ reorderNodes переупорядочивает узлы
✓ publishBranch публикует/скрывает всю ветку
✓ createEntryNode создаёт entry-node
✓ createEntryNode проверяет уникальность entry_id
✓ moveEntryNode перемещает entry-node
✓ deleteNode удаляет узел
✓ deleteNode запрещает удаление если есть children
✓ все операции выполняются в транзакциях
✓ кэш резолвинга инвалидируется при изменениях
```

**Файл:** `tests/Feature/Routing/RouteTreeServiceIntegrationTest.php`

```php
✓ массовое переименование folder обновляет все descendants
✓ перемещение folder обновляет пути всех children
✓ создание узлов с одинаковыми slug в разных ветках работает
✓ операции с большим деревом (>100 узлов) работают корректно
```

---

## Блок 6: RouteRedirectService — управление редиректами

### Задачи

1. Создать интерфейс `RouteRedirectServiceInterface`:
   - `createRedirect(string $oldPath, int|string $newTarget, int $httpCode = 301): RouteRedirect`
   - `findRedirect(string $path): ?RouteRedirect` — найти редирект по старому пути
   - `deleteRedirectsForNode(int $nodeId): void` — удалить редиректы для узла
   - `shouldCreateRedirect(RouteNode $node): bool` — проверка политики создания

2. Создать реализацию `RouteRedirectService`:
   - Поддержка политик:
     - Создавать всегда
     - Создавать только для опубликованных узлов
     - Отключено флагом
   - Контроль уникальности `old_path`
   - Автоматическое создание при изменениях через `RouteTreeService`

3. Обновить модель `Redirect` (если используется существующая):
   - Добавить связь `routeNode()` если нужно

4. Middleware для обработки редиректов:
   - Проверка редиректов ДО резолвинга пути
   - Выполнение 301/302 редиректа

### Тесты

**Файл:** `tests/Unit/Domain/Routing/RouteRedirectServiceTest.php`

```php
✓ createRedirect создаёт редирект
✓ createRedirect проверяет уникальность old_path
✓ findRedirect находит редирект по старому пути
✓ deleteRedirectsForNode удаляет редиректы для узла
✓ shouldCreateRedirect проверяет политику (всегда)
✓ shouldCreateRedirect проверяет политику (только published)
✓ shouldCreateRedirect проверяет политику (отключено)
✓ автоматическое создание при переименовании узла
✓ автоматическое создание при перемещении узла
```

**Файл:** `tests/Feature/Middleware/RouteRedirectMiddlewareTest.php`

```php
✓ middleware выполняет 301 редирект для старого пути
✓ middleware не влияет на новые пути
✓ middleware корректно обрабатывает query string
✓ middleware работает только для GET/HEAD запросов
```

---

## Блок 7: Расширение системы резервирования путей для multi-level

### Задачи

1. Расширить `PathReservationService`:
   - Метод `isFirstSegmentReserved(string $segment): bool` — проверка первого сегмента
   - Метод `getReservedFirstSegments(): array` — список зарезервированных первых сегментов
   - Метод `isPathReserved(string $path): bool` — проверка полного пути (для будущего)

2. Обновить `ReservedPattern`:
   - Метод `firstSegmentRegex(): string` — regex для первого сегмента с исключением reserved
   - Обновить `slugRegex()` для поддержки multi-level (если нужно)

3. Создать валидатор `ReservedFirstSegment`:
   - Проверка первого сегмента при создании root-level folder
   - Интеграция с существующим `ReservedSlug`

4. Обновить `RejectReservedIfMatched` middleware:
   - Извлечение первого сегмента из `{path}` (multi-level)
   - Проверка через `PathReservationService`

### Тесты

**Файл:** `tests/Unit/Domain/Routing/PathReservationServiceMultiLevelTest.php`

```php
✓ isFirstSegmentReserved проверяет первый сегмент
✓ getReservedFirstSegments возвращает список reserved сегментов
✓ isPathReserved проверяет полный путь (для будущего)
✓ интеграция с конфигом и БД работает
```

**Файл:** `tests/Unit/Domain/Routing/ReservedPatternMultiLevelTest.php`

```php
✓ firstSegmentRegex исключает reserved сегменты
✓ firstSegmentRegex работает с динамическими резервациями
✓ slugRegex поддерживает multi-level пути
```

**Файл:** `tests/Unit/Rules/ReservedFirstSegmentTest.php`

```php
✓ валидатор запрещает reserved первый сегмент
✓ валидатор разрешает не-reserved первый сегмент
✓ валидатор работает с root-level folders
```

**Файл:** `tests/Feature/Middleware/RejectReservedIfMatchedMultiLevelTest.php`

```php
✓ middleware извлекает первый сегмент из multi-level пути
✓ middleware отклоняет reserved первый сегмент
✓ middleware пропускает не-reserved пути
✓ middleware работает корректно с пустым путём
```

---

## Блок 8: Обновление CanonicalUrl для multi-level

### Задачи

1. Обновить `CanonicalUrl` middleware:
   - Нормализация многоуровневых путей: `/A/B/Entry/` → `/a/b/entry`
   - Приведение всех сегментов к lowercase
   - Удаление trailing slash
   - Сохранение query string
   - Исключение системных путей (admin, api, plugins)

2. Обновить логику `isSystemPath()`:
   - Проверка первого сегмента для multi-level путей
   - Поддержка plugin namespaces

### Тесты

**Файл:** `tests/Unit/Http/Middleware/CanonicalUrlMultiLevelTest.php`

```php
✓ нормализует многоуровневые пути: /A/B/Entry/ → /a/b/entry
✓ приводит все сегменты к lowercase
✓ удаляет trailing slash
✓ сохраняет query string
✓ не нормализует системные пути (admin, api)
✓ не нормализует plugin namespaces
✓ работает с пустым путём
✓ работает с одним сегментом
✓ работает с глубокой вложенностью (>3 уровней)
```

**Файл:** `tests/Feature/Middleware/CanonicalUrlIntegrationTest.php`

```php
✓ middleware выполняется ДО роутинга
✓ редирект 301 работает корректно
✓ middleware не влияет на API запросы
✓ middleware не влияет на admin запросы
```

---

## Блок 9: Обновление публичного роутинга

### Задачи

1. Обновить `routes/web_content.php`:
   - Удалить старый роут `GET /{slug}`
   - Добавить новый роут `GET /{path}` с regex для multi-level
   - Regex должен исключать reserved первый сегмент
   - Применить middleware: `web`, `RejectReservedIfMatched`

2. Обновить `PageController`:
   - Изменить метод `show()` для работы с `{path}` вместо `{slug}`
   - Использовать `RouteResolver` для резолвинга пути
   - Обработка entry-node → отображение entry
   - Обработка folder-node → опционально страница раздела (пока 404)
   - Обработка 404 если путь не найден

3. Обновить `PageShowRequest`:
   - Валидация формата пути (multi-level)

4. Обновить модель `Entry`:
   - Метод `url()` должен использовать `routeNode` если он есть

### Тесты

**Файл:** `tests/Feature/Web/PageControllerMultiLevelTest.php`

```php
✓ GET /folder/entry возвращает entry по полному пути
✓ GET /folder/subfolder/entry работает с вложенностью
✓ GET /entry работает для entry на первом уровне
✓ GET /non-existent возвращает 404
✓ GET /folder (без entry) возвращает 404 (пока)
✓ запрос на /api/... не попадает в PageController
✓ запрос на /admin/... не попадает в PageController
✓ запрос на reserved первый сегмент возвращает 404
✓ метод url() использует routeNode если он есть
✓ метод url() возвращает полный путь через routeNode
```

**Файл:** `tests/Feature/Routing/WebContentRoutesTest.php`

```php
✓ роут /{path} регистрируется корректно
✓ regex исключает reserved первый сегмент
✓ middleware применяется корректно
✓ старый роут /{slug} удалён
```

**Файл:** `tests/Unit/Http/Requests/PageShowRequestTest.php`

```php
✓ валидация принимает корректные multi-level пути
✓ валидация отклоняет некорректные пути
✓ валидация проверяет формат сегментов
```

---

## Блок 10: Admin API для управления деревом

### Задачи

1. Создать контроллер `RouteNodeController`:
   - `GET /api/v1/admin/route-nodes/tree` — получить дерево
   - `POST /api/v1/admin/route-nodes/folder` — создать folder
   - `GET /api/v1/admin/route-nodes/{id}` — получить узел
   - `PATCH /api/v1/admin/route-nodes/{id}` — обновить узел (title, slug, is_published)
   - `POST /api/v1/admin/route-nodes/{id}/move` — переместить узел
   - `POST /api/v1/admin/route-nodes/{id}/reorder` — переупорядочить узлы
   - `DELETE /api/v1/admin/route-nodes/{id}` — удалить узел

2. Создать контроллер `EntryRouteNodeController`:
   - `POST /api/v1/admin/entries/{entryId}/route-node` — создать entry-node
   - `PATCH /api/v1/admin/entries/{entryId}/route-node` — обновить entry-node
   - `GET /api/v1/admin/entries/{entryId}/route` — получить текущий URL entry

3. Создать Request классы:
   - `StoreFolderRequest` — валидация создания folder
   - `UpdateRouteNodeRequest` — валидация обновления узла
   - `MoveRouteNodeRequest` — валидация перемещения
   - `CreateEntryNodeRequest` — валидация создания entry-node

4. Добавить роуты в `routes/api_admin.php`

5. Создать Resources для API ответов:
   - `RouteNodeResource` — форматирование узла
   - `RouteTreeResource` — форматирование дерева

### Тесты

**Файл:** `tests/Feature/Api/Admin/RouteNodes/RouteNodeControllerTest.php`

```php
✓ GET /route-nodes/tree возвращает дерево
✓ POST /route-nodes/folder создаёт folder
✓ POST /route-nodes/folder валидирует slug
✓ POST /route-nodes/folder запрещает reserved slug на первом уровне
✓ GET /route-nodes/{id} возвращает узел
✓ PATCH /route-nodes/{id} обновляет узел
✓ POST /route-nodes/{id}/move перемещает узел
✓ POST /route-nodes/{id}/move запрещает циклы
✓ POST /route-nodes/{id}/reorder переупорядочивает узлы
✓ DELETE /route-nodes/{id} удаляет узел
✓ DELETE /route-nodes/{id} запрещает удаление если есть children
✓ все эндпоинты требуют аутентификации
✓ все эндпоинты требуют прав администратора
```

**Файл:** `tests/Feature/Api/Admin/Entries/EntryRouteNodeControllerTest.php`

```php
✓ POST /entries/{id}/route-node создаёт entry-node
✓ POST /entries/{id}/route-node валидирует slug
✓ PATCH /entries/{id}/route-node обновляет entry-node
✓ GET /entries/{id}/route возвращает текущий URL
✓ все эндпоинты требуют аутентификации
```

**Файл:** `tests/Feature/Api/Admin/RouteNodes/RouteNodeResourcesTest.php`

```php
✓ RouteNodeResource форматирует узел корректно
✓ RouteTreeResource форматирует дерево корректно
✓ ресурсы включают все необходимые поля
```

---

## Дополнительные задачи (после основных блоков)

### Оптимизация производительности

1. Добавить кэш резолвинга по path:
   - Ключ: `route:resolve:{path}`
   - Инвалидация при изменениях в поддереве

2. Материализация `path_cache`:
   - Автоматическое обновление при изменениях
   - Каскадное обновление для descendants

3. Индексы для производительности:
   - Составной индекс на `(parent_id, is_published, sort)`
   - Индекс на `path_cache` для быстрого поиска

### Миграция данных

1. Создать команду `route-nodes:migrate`:
   - Создание root-узла
   - Создание entry-nodes для всех существующих entries
   - Опционально: создание folder-узлов из категорий

2. Тесты миграции:
   - Проверка корректности создания узлов
   - Проверка сохранения связей
   - Проверка целостности данных

---

## Чек-лист выполнения

После каждого блока проверяем:

- [ ] Код написан и соответствует стандартам проекта
- [ ] PHPDoc комментарии добавлены/обновлены
- [ ] Тесты написаны и проходят
- [ ] Существующие тесты не сломаны
- [ ] Линтер не выдаёт ошибок
- [ ] Документация обновлена (если нужно)

### Команды после каждого блока

```bash
# Запуск тестов
php artisan test

# Проверка линтера
composer lint  # если настроен

# Генерация документации
php artisan docs:generate
```

---

## Порядок выполнения блоков

**Критически важно:** Блоки должны выполняться строго в указанном порядке, так как каждый блок зависит от предыдущих.

1. **Блок 1** — База данных (фундамент)
2. **Блок 2** — Модели (работа с данными)
3. **Блок 3** — RoutePathBuilder (построение путей)
4. **Блок 4** — RouteResolver (резолвинг)
5. **Блок 5** — RouteTreeService (операции с деревом)
6. **Блок 6** — RouteRedirectService (редиректы)
7. **Блок 7** — Расширение reserved paths
8. **Блок 8** — CanonicalUrl для multi-level
9. **Блок 9** — Публичный роутинг
10. **Блок 10** — Admin API

---

## Примечания

- **Обратная совместимость:** Плоская маршрутизация `/{slug}` будет удалена. Все существующие entries должны получить entry-nodes через миграцию данных.

- **Производительность:** На старте используем вычисление путей "на лету" + кэш. При необходимости переходим к материализации `path_cache`.

- **Публикация:** Используем "явный режим" — ветка доступна только если все родители опубликованы.

- **Безопасность:** Все проверки reserved paths должны работать на трёх уровнях: regex в роуте, middleware, валидация в админке.

---

**Дата создания:** 2025-12-05  
**Версия:** 1.0  
**Статус:** Готов к реализации

