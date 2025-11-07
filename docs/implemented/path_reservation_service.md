# Сервис резервирования путей

## Резюме
Реализован сервис для динамического резервирования URL-путей, позволяющий плагинам и системным модулям защищать свои маршруты от конфликтов с контентом CMS. Система обеспечивает уникальность путей, нормализацию, интеграцию со статическим конфигом и полный API для управления резервированиями.

**Дата реализации:** 2025-11-07

---

## Структура системы

### 1. База данных
**Таблица:** `route_reservations`

- `id` BIGINT PK
- `path` VARCHAR(255) UNIQUE — канонический путь в нижнем регистре
- `source` VARCHAR(100) — источник резервирования (system:name, plugin:name, module:name)
- `reason` VARCHAR(255) NULL — необязательное описание причины
- `created_at`, `updated_at`
- Индекс на `source` для быстрого освобождения

### 2. Модель
**Файл:** `app/Models/RouteReservation.php`

Простая Eloquent модель с `$fillable` для массового присвоения.

### 3. Нормализация путей
**Файл:** `app/Domain/Routing/PathNormalizer.php`

Класс `PathNormalizer` выполняет нормализацию путей:
- Убирает query и fragment (`?foo=bar#section`)
- Trim пробелов
- Гарантирует ведущий `/`
- Убирает trailing `/` (кроме корня `/`)
- Приводит к нижнему регистру (`mb_strtolower`)
- Unicode NFC нормализация (если доступно расширение `intl`)

**Ошибочные значения** (`''`, `'#'`, `'?'`) → `InvalidPathException`.

### 4. Исключения
**Директория:** `app/Domain/Routing/Exceptions/`

- `InvalidPathException` — невалидный путь
- `PathAlreadyReservedException` — путь уже зарезервирован (включает `path`, `owner`)
- `ForbiddenReservationRelease` — попытка освободить чужую бронь

### 5. Хранилище
**Интерфейс:** `app/Domain/Routing/PathReservationStore.php`  
**Реализация:** `app/Domain/Routing/PathReservationStoreImpl.php`

Интерфейс для работы с БД:
- `insert(string $path, string $source, ?string $reason): void`
- `delete(string $path): void`
- `deleteBySource(string $source): int`
- `exists(string $path): bool`
- `ownerOf(string $path): ?string`
- `isUniqueViolation(Throwable $e): bool` — определение нарушения уникальности для разных СУБД

### 6. Сервис
**Интерфейс:** `app/Domain/Routing/PathReservationService.php`  
**Реализация:** `app/Domain/Routing/PathReservationServiceImpl.php`

Основной API сервиса:
- `reservePath(string $path, string $source, ?string $reason = null): void` — резервирование пути
- `releasePath(string $path, string $source): void` — освобождение пути
- `releaseBySource(string $source): int` — освобождение всех путей источника
- `isReserved(string $path): bool` — проверка резервирования (с учётом статики)
- `ownerOf(string $path): ?string` — получение владельца

**Особенности:**
- Интеграция со статическими путями из `config/stupidcms.php` (секция `reserved_routes.paths`)
- Автоматическая нормализация путей
- Обработка конфликтов через уникальный индекс БД

### 7. Service Provider
**Файл:** `app/Providers/PathReservationServiceProvider.php`

Регистрирует сервисы в DI контейнере:
- `PathReservationStore` → `PathReservationStoreImpl` (singleton)
- `PathReservationService` → `PathReservationServiceImpl` (singleton с загрузкой статических путей из конфига)

### 8. CLI команды
**Директория:** `app/Console/Commands/`

- `routes:reserve {path} {source} {reason?}` — резервирование пути
- `routes:release {path} {source}` — освобождение пути
- `routes:list-reservations` — список всех резервирований

### 9. HTTP API
**Контроллер:** `app/Http/Controllers/Admin/PathReservationController.php`

**Маршруты:**
- `GET /api/v1/admin/reservations` — список резервирований
- `POST /api/v1/admin/reservations` — создание резервирования
- `DELETE /api/v1/admin/reservations/{path}` — удаление резервирования

**Защита:** middleware `auth:sanctum` + политики (`can:viewAny/create/delete,RouteReservation`)

**Формат ошибок:** RFC 7807 (problem+json)
- `409 Conflict` для `PathAlreadyReservedException`
- `422 Unprocessable Entity` для `InvalidPathException`
- `403 Forbidden` для `ForbiddenReservationRelease`

### 10. Политика авторизации
**Файл:** `app/Policies/RouteReservationPolicy.php`

Политика для `RouteReservation` с методами:
- `viewAny`, `view`, `create`, `update`, `delete`

Все методы возвращают `false` по умолчанию (доступ только через `Gate::before()` для администраторов).

---

## Использование

### CLI
```bash
# Резервирование пути
php artisan routes:reserve /feed.xml system:feeds "RSS feed"

# Освобождение пути
php artisan routes:release /feed.xml system:feeds

# Список резервирований
php artisan routes:list-reservations
```

### В коде
```php
use App\Domain\Routing\PathReservationService;

$service = app(PathReservationService::class);

// Резервирование
try {
    $service->reservePath('/shop', 'plugin:shop', 'E-commerce plugin');
} catch (PathAlreadyReservedException $e) {
    // Путь уже занят
}

// Проверка
if ($service->isReserved('/shop')) {
    // Путь зарезервирован
}

// Освобождение
$service->releaseBySource('plugin:shop');
```

### HTTP API
```bash
# Создание резервирования
curl -X POST http://localhost/api/v1/admin/reservations \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "path": "/feed.xml",
    "source": "system:feeds",
    "reason": "RSS feed"
  }'

# Список резервирований
curl -X GET http://localhost/api/v1/admin/reservations \
  -H "Authorization: Bearer {token}"

# Удаление резервирования
curl -X DELETE "http://localhost/api/v1/admin/reservations/feed.xml?source=system:feeds" \
  -H "Authorization: Bearer {token}"
```

---

## Интеграция со статическим конфигом

Сервис автоматически загружает статические пути из `config/stupidcms.php`:
```php
'reserved_routes' => [
    'paths' => [
        'admin', // будет нормализован к '/admin'
    ],
],
```

Эти пути:
- Нельзя резервировать через API/CLI (бросит `PathAlreadyReservedException`)
- Учитываются в `isReserved()` и `ownerOf()` (возвращают `'static:config'`)

---

## Нормализация путей

Все пути нормализуются одинаково:
- `'/Admin/'` → `'/admin'`
- `'/test?foo=bar#section'` → `'/test'`
- `'admin'` → `'/admin'`
- `'/'` → `'/'` (корень сохраняется)

**Важно:** Пути сравниваются case-insensitive, поэтому `/Admin` и `/admin` считаются одним путём.

---

## Тесты

**Файл:** `tests/Feature/PathReservationServiceTest.php`

Набор тестов покрывает:
1. ✅ Успешное резервирование пути
2. ✅ Повторное резервирование → `PathAlreadyReservedException` (критерий приёмки)
3. ✅ Нормализация путей (case-insensitive)
4. ✅ Блокировка статических путей из конфига
5. ✅ Освобождение пути
6. ✅ Освобождение чужого пути → `ForbiddenReservationRelease`
7. ✅ Освобождение по источнику
8. ✅ Проверка `isReserved()` и `ownerOf()`
9. ✅ Обработка невалидных путей

Все тесты проходят успешно:
- **PathReservationServiceTest**: 22 passed, 30 assertions
- **PathReservationApiTest**: 13 passed, 44 assertions
- **Итого**: 35 passed, 73 assertions

---

## Критерии приёмки

✅ Таблица `route_reservations` и репозиторий/сервис реализованы  
✅ API `reservePath($path,$source)` бросает ошибку при повторном резерве  
✅ Интеграция со статическим конфигом  
✅ Освобождение срабатывает при выключении плагина (через `releaseBySource`)  
✅ Тесты зелёные (35 passed, 73 assertions)

## Исправления после ревью

После первоначального ревью были внесены следующие исправления:

1. **DELETE-роут с многосегментными путями**:
   - Добавлен wildcard `->where('path', '.*')` для поддержки путей с несколькими сегментами
   - Теперь `/api/v1/admin/reservations/blog/rss` корректно обрабатывается

2. **Политика `deleteAny`**:
   - Добавлен метод `deleteAny(User $user)` в `RouteReservationPolicy`
   - Middleware изменён с `can:delete` на `can:deleteAny` для операций над коллекцией

3. **Сравнение кода ошибки**:
   - В `isUniqueViolation()` код ошибки приводится к строке: `(string)$e->getCode()`
   - Обеспечивает корректную работу с разными типами СУБД

4. **Мутатор в модели**:
   - Добавлен `setPathAttribute()` в `RouteReservation` для автоматической нормализации
   - Защищает от прямого создания модели без нормализации

5. **Улучшенная нормализация**:
   - Добавлена защита от относительных путей (`./`, `../`)
   - Удаление дублирующих слэшей (`//` → `/`)

6. **Атомарная операция release**:
   - Метод `releasePath()` использует `deleteIfOwnedBy()` для атомарной проверки и удаления
   - Устранена гонка (TOCTOU) между проверкой владельца и удалением

7. **RFC 7807 type**:
   - Изменён `type` с `https://tools.ietf.org/html/rfc7231#section-6.5` на `about:blank`

8. **Док-комментарии**:
   - Исправлены пути в комментариях контроллера: `/api/admin/...` → `/api/v1/admin/...`

9. **Контракт статики vs reserved_routes**:
   - Добавлен комментарий в `PathReservationServiceProvider` о разделении ответственности
   - `PathReservationService` — для динамических резервирований плагинов
   - `ReservedRouteRegistry` (задача 23) — для валидации слугов и fallback-роутера

10. **HTTP тесты**:
    - Добавлен `PathReservationApiTest` с 13 тестами
    - Покрытие: создание (201/409), удаление (200/403), список, авторизация

## Дополнительные улучшения (nice-to-have)

После основного ревью были внесены дополнительные улучшения:

1. **Переиспользование нормализатора в провайдере**:
   - Статические пути из конфига теперь нормализуются через `PathNormalizer::normalize()`
   - Исключает расхождения при изменении правил нормализации
   - Невалидные пути из конфига логируются, но не прерывают загрузку

2. **FormRequest классы**:
   - Созданы `StorePathReservationRequest` и `DestroyPathReservationRequest`
   - Валидация вынесена из контроллера в отдельные классы
   - Улучшена читаемость и расширяемость кода
   - Кастомные сообщения об ошибках валидации

3. **DELETE с path в body**:
   - Поддержка передачи `path` как в URL параметре, так и в JSON body
   - Полезно для экзотических URL-encode кейсов
   - Метод `getPath()` в `DestroyPathReservationRequest` выбирает источник автоматически

4. **Аудит логирование**:
   - Все операции `reserve` и `release` логируются в таблицу `audits`
   - Сохраняется: пользователь, действие, путь, источник, IP, User-Agent
   - Ошибки логирования не прерывают выполнение операции
   - Помогает разбирать конфликты источников

5. **Аутентификация**:
   - Оставлен `auth` middleware (sanctum не установлен в проекте)
   - При необходимости можно легко переключить на `auth:sanctum` или `auth:passport`  

---

## Расширение (out of scope)

- Резервирование префиксов (`/admin/*`) — возможна отдельная таблица
- Срок действия резерва (TTL) — добавление `expires_at`
- Валидация по HTTP-методу (GET-only vs POST-only)

---

## Файлы изменений

### Новые файлы:
- `database/migrations/2025_11_07_053847_create_route_reservations_table.php`
- `app/Models/RouteReservation.php`
- `app/Domain/Routing/PathNormalizer.php`
- `app/Domain/Routing/Exceptions/InvalidPathException.php`
- `app/Domain/Routing/Exceptions/PathAlreadyReservedException.php`
- `app/Domain/Routing/Exceptions/ForbiddenReservationRelease.php`
- `app/Domain/Routing/PathReservationStore.php`
- `app/Domain/Routing/PathReservationStoreImpl.php`
- `app/Domain/Routing/PathReservationService.php`
- `app/Domain/Routing/PathReservationServiceImpl.php`
- `app/Providers/PathReservationServiceProvider.php`
- `app/Console/Commands/RoutesReserveCommand.php`
- `app/Console/Commands/RoutesReleaseCommand.php`
- `app/Console/Commands/RoutesListReservationsCommand.php`
- `app/Http/Controllers/Admin/PathReservationController.php`
- `app/Http/Requests/StorePathReservationRequest.php`
- `app/Http/Requests/DestroyPathReservationRequest.php`
- `app/Policies/RouteReservationPolicy.php`
- `tests/Feature/PathReservationServiceTest.php`
- `tests/Feature/PathReservationApiTest.php`
- `docs/implemented/path_reservation_service.md`

### Изменённые файлы:
- `bootstrap/providers.php` — добавлен `PathReservationServiceProvider`
- `routes/web.php` — добавлены маршруты для API резервирований
- `app/Providers/AuthServiceProvider.php` — добавлена политика для `RouteReservation`

---

## Примечания

- Система полностью изолирована от существующей системы `ReservedRouteRegistry` (задача 23)
- Пути нормализуются автоматически, что предотвращает дубликаты
- Статические пути из конфига имеют приоритет над динамическими резервированиями
- Все операции защищены авторизацией через политики

