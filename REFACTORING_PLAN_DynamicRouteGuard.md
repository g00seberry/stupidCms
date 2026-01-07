# План рефакторинга DynamicRouteGuard

## Цель
Упростить `DynamicRouteGuard` путем удаления проверок middleware и контроллеров, оставив только проверку префиксов и конфликтов. Улучшить проверку конфликтов для работы с паттернами маршрутов.

## Текущее состояние
- `DynamicRouteGuard` проверяет: middleware, контроллеры, префиксы, конфликты
- Проверка конфликтов работает по точному совпадению строк URI (не учитывает паттерны)
- Проверки middleware/контроллеров используются в:
  - `RouteRouteRegistrar::applyRouteSettings()` (строка 162)
  - `RouteGroupRegistrar::buildGroupAttributes()` (строка 69)
  - `ControllerActionResolver::validateControllerAllowed()` (строка 174)

## Задачи

### Задача 1: Создать утилиту для нормализации паттернов маршрутов
**Файл:** `app/Services/DynamicRoutes/RoutePatternNormalizer.php` (новый)

**Описание:**
Создать класс для нормализации и сравнения паттернов маршрутов. Класс должен:
- Преобразовывать URI с параметрами в нормализованный паттерн
- Сравнивать паттерны на конфликтность
- Учитывать порядок сегментов и типы параметров

**Методы:**
```php
public function normalize(string $uri): string
// Преобразует /products/{id} → /products/{param}
// Преобразует /{slug} → /{param}

public function patternsConflict(string $pattern1, string $pattern2): bool
// Проверяет, могут ли два паттерна конфликтовать
// /products/{id} и /products/{slug} → true (конфликт)
// /products/{id} и /pages/{id} → false (нет конфликта)
// /{slug} и /{id} → true (конфликт)

public function extractSegments(string $uri): array
// Разбивает URI на сегменты: /products/{id} → ['products', '{param}']
```

**Критерии готовности:**
- Класс создан с полной реализацией
- Написаны unit-тесты для всех методов
- Тесты покрывают edge cases (пустые URI, множественные параметры, вложенные пути)

---

### Задача 2: Улучшить метод `checkConflict()` в DynamicRouteGuard
**Файл:** `app/Services/DynamicRoutes/DynamicRouteGuard.php`

**Описание:**
Переработать логику проверки конфликтов для использования паттернов вместо точного сравнения строк.

**Изменения:**
1. Добавить зависимость от `RoutePatternNormalizer`
2. Обновить `findConflictInCollection()` для сравнения паттернов
3. Использовать `RoutePatternNormalizer::patternsConflict()` вместо `===`

**До:**
```php
if ($nodeUri === $normalizedUri) {
    // проверка конфликта
}
```

**После:**
```php
$normalizer = new RoutePatternNormalizer();
$pattern1 = $normalizer->normalize($nodeUri);
$pattern2 = $normalizer->normalize($normalizedUri);

if ($normalizer->patternsConflict($pattern1, $pattern2)) {
    // проверка конфликта
}
```

**Критерии готовности:**
- Метод использует нормализацию паттернов
- Тесты подтверждают корректную работу с паттернами
- Маршруты `/{slug}` и `/{id}` определяются как конфликтующие
- Маршруты `/products/{id}` и `/pages/{id}` НЕ конфликтуют

---

### Задача 3: Удалить метод `isMiddlewareAllowed()` из DynamicRouteGuard
**Файл:** `app/Services/DynamicRoutes/DynamicRouteGuard.php`

**Описание:**
Полностью удалить метод `isMiddlewareAllowed()` и все связанные проверки.

**Действия:**
1. Удалить метод `isMiddlewareAllowed()` (строки 42-77)
2. Удалить метод `sanitizeMiddleware()` (строки 149-158)
3. Удалить использование `config('dynamic-routes.allowed_middleware')`

**Критерии готовности:**
- Методы удалены из класса
- Нет ссылок на удаленные методы в коде
- Код компилируется без ошибок

---

### Задача 4: Удалить метод `isControllerAllowed()` из DynamicRouteGuard
**Файл:** `app/Services/DynamicRoutes/DynamicRouteGuard.php`

**Описание:**
Полностью удалить метод `isControllerAllowed()` и все связанные проверки.

**Действия:**
1. Удалить метод `isControllerAllowed()` (строки 88-114)
2. Удалить использование `config('dynamic-routes.allowed_controllers')`

**Критерии готовности:**
- Метод удален из класса
- Нет ссылок на удаленный метод в коде
- Код компилируется без ошибок

---

### Задача 5: Обновить RouteRouteRegistrar для удаления sanitizeMiddleware
**Файл:** `app/Services/DynamicRoutes/Registrars/RouteRouteRegistrar.php`

**Описание:**
Убрать вызов `sanitizeMiddleware()` и применять middleware напрямую.

**Изменения в методе `applyRouteSettings()`:**

**До:**
```php
if ($node->middleware) {
    $sanitized = $this->guard->sanitizeMiddleware($node->middleware);
    if (!empty($sanitized)) {
        $route->middleware($sanitized);
    }
}
```

**После:**
```php
if ($node->middleware && !empty($node->middleware)) {
    $route->middleware($node->middleware);
}
```

**Критерии готовности:**
- Метод `applyRouteSettings()` не использует `sanitizeMiddleware()`
- Middleware применяются напрямую из `$node->middleware`
- Тесты проходят успешно

---

### Задача 6: Обновить RouteGroupRegistrar для удаления sanitizeMiddleware
**Файл:** `app/Services/DynamicRoutes/Registrars/RouteGroupRegistrar.php`

**Описание:**
Убрать вызов `sanitizeMiddleware()` в методе `buildGroupAttributes()`.

**Изменения:**

**До:**
```php
if ($node->middleware) {
    $sanitized = $this->guard->sanitizeMiddleware($node->middleware);
    if (!empty($sanitized)) {
        $attributes['middleware'] = $sanitized;
    }
}
```

**После:**
```php
if ($node->middleware && !empty($node->middleware)) {
    $attributes['middleware'] = $node->middleware;
}
```

**Критерии готовности:**
- Метод `buildGroupAttributes()` не использует `sanitizeMiddleware()`
- Middleware применяются напрямую
- Тесты проходят успешно

---

### Задача 7: Обновить ControllerActionResolver для удаления проверки контроллеров
**Файл:** `app/Services/DynamicRoutes/ActionResolvers/ControllerActionResolver.php`

**Описание:**
Удалить проверку `validateControllerAllowed()` и вызов `guard->isControllerAllowed()`.

**Изменения в методе `validateAndResolveController()`:**

**До:**
```php
private function validateAndResolveController(string $controller, int $routeNodeId, ?string $method = null): bool
{
    if (!$this->validateControllerAllowed($controller, $routeNodeId)) {
        return false;
    }
    // ... остальные проверки
}
```

**После:**
```php
private function validateAndResolveController(string $controller, int $routeNodeId, ?string $method = null): bool
{
    // Удалена проверка validateControllerAllowed
    if (!$this->validateController($controller)) {
        return false;
    }
    // ... остальные проверки
}
```

**Дополнительно:**
- Удалить метод `validateControllerAllowed()` (строки 172-183)
- Удалить зависимость от `DynamicRouteGuard` в конструкторе (если больше не используется)

**Критерии готовности:**
- Метод `validateControllerAllowed()` удален
- Проверка существования контроллера/метода сохранена
- Тесты проходят успешно

---

### Задача 8: Обновить конфигурацию dynamic-routes.php
**Файл:** `config/dynamic-routes.php`

**Описание:**
Удалить секции `allowed_middleware` и `allowed_controllers` из конфигурации.

**Изменения:**
1. Удалить секцию `allowed_middleware` (строки 16-55)
2. Удалить секцию `allowed_controllers` (строки 57-75)
3. Обновить комментарии в начале файла

**Критерии готовности:**
- Секции удалены из конфига
- Комментарии обновлены
- Нет ссылок на удаленные конфиги в коде

---

### Задача 9: Обновить тесты
**Файлы:**
- `tests/Unit/Services/DynamicRoutes/DynamicRouteGuardTest.php`
- `tests/Unit/Config/DynamicRoutesConfigTest.php`
- `tests/Feature/DynamicRoutes/SecurityTest.php` (если существует)

**Описание:**
Удалить тесты для проверок middleware/контроллеров, добавить тесты для проверки конфликтов по паттернам.

**Действия:**
1. Удалить тесты для `isMiddlewareAllowed()`
2. Удалить тесты для `isControllerAllowed()`
3. Удалить тесты для `sanitizeMiddleware()`
4. Добавить тесты для `RoutePatternNormalizer`
5. Обновить тесты для `checkConflict()` с использованием паттернов
6. Обновить тесты конфигурации (удалить проверки `allowed_middleware` и `allowed_controllers`)

**Примеры новых тестов:**
```php
test('patternsConflict определяет конфликт для одинаковых паттернов', function () {
    $normalizer = new RoutePatternNormalizer();
    expect($normalizer->patternsConflict('/{slug}', '/{id}'))->toBeTrue();
});

test('patternsConflict не определяет конфликт для разных префиксов', function () {
    $normalizer = new RoutePatternNormalizer();
    expect($normalizer->patternsConflict('/products/{id}', '/pages/{id}'))->toBeFalse();
});
```

**Критерии готовности:**
- Все старые тесты удалены или обновлены
- Новые тесты добавлены и проходят
- Покрытие тестами не уменьшилось

---

### Задача 10: Обновить документацию и комментарии
**Файлы:**
- `app/Services/DynamicRoutes/DynamicRouteGuard.php`
- `app/Services/DynamicRoutes/Registrars/RouteRouteRegistrar.php`
- `app/Services/DynamicRoutes/Registrars/RouteGroupRegistrar.php`
- `app/Services/DynamicRoutes/ActionResolvers/ControllerActionResolver.php`

**Описание:**
Обновить PHPDoc комментарии во всех измененных классах.

**Действия:**
1. Обновить описание класса `DynamicRouteGuard` (убрать упоминания middleware/контроллеров)
2. Обновить комментарии методов
3. Добавить документацию для `RoutePatternNormalizer`
4. Обновить комментарии в регистраторах (убрать упоминания sanitizeMiddleware)

**Критерии готовности:**
- Все комментарии актуальны
- PHPDoc соответствует текущей реализации
- Нет устаревших упоминаний удаленных методов

---

## Порядок выполнения

Рекомендуемый порядок выполнения задач:

1. **Задача 1** - Создать `RoutePatternNormalizer` (основа для улучшения проверки конфликтов)
2. **Задача 2** - Улучшить `checkConflict()` (использует результат задачи 1)
3. **Задача 3-4** - Удалить проверки middleware/контроллеров из `DynamicRouteGuard`
4. **Задача 5-6** - Обновить регистраторы (зависит от задачи 3)
5. **Задача 7** - Обновить `ControllerActionResolver` (зависит от задачи 4)
6. **Задача 8** - Обновить конфигурацию
7. **Задача 9** - Обновить тесты (зависит от всех предыдущих)
8. **Задача 10** - Обновить документацию (финальная задача)

## Критерии завершения рефакторинга

- ✅ Все методы проверки middleware/контроллеров удалены
- ✅ Проверка конфликтов работает по паттернам маршрутов
- ✅ Все тесты проходят
- ✅ Нет использования удаленных методов в коде
- ✅ Конфигурация обновлена
- ✅ Документация актуальна
- ✅ Код компилируется без ошибок и предупреждений

## Риски и митигация

**Риск 1:** Удаление проверок middleware может привести к использованию небезопасных middleware
- **Митигация:** Проверки можно добавить на уровне валидации форм (Request classes)

**Риск 2:** Удаление проверок контроллеров может привести к использованию несуществующих контроллеров
- **Митигация:** Проверка существования контроллера/метода сохранена в `ControllerActionResolver::validateController()`

**Риск 3:** Новая логика проверки конфликтов может иметь баги
- **Митигация:** Тщательное тестирование всех edge cases, постепенное внедрение

## Дополнительные улучшения (опционально)

После завершения основных задач можно рассмотреть:
- Кэширование результатов проверки конфликтов
- Оптимизация производительности `checkConflict()` для больших объемов маршрутов
- Добавление метрик и логирования для мониторинга

