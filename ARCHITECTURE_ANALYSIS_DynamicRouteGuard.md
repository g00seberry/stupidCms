# Архитектурный анализ: DynamicRouteGuard

## 📋 Обзор

`DynamicRouteGuard` — сервис безопасности для динамических маршрутов, выполняющий проверки middleware, контроллеров, префиксов URI и конфликтов маршрутов.

---

## ✅ Сильные стороны

### 1. **Чёткая ответственность**
- Класс имеет понятное назначение: проверка безопасности динамических маршрутов
- Хорошая документация методов

### 2. **Гибкость паттернов**
- Поддержка wildcard паттернов (`can:*`, `App\Http\Middleware\*`)
- Параметризованные middleware

### 3. **Защита от конфликтов**
- Проверка конфликтов с декларативными и динамическими маршрутами
- Рекурсивная проверка вложенных групп

---

## ⚠️ Архитектурные проблемы

### 1. **Нарушение Single Responsibility Principle (SRP)**

**Проблема:** Класс выполняет слишком много различных обязанностей:

```php
// 5 различных ответственностей в одном классе:
- Проверка middleware (isMiddlewareAllowed, sanitizeMiddleware)
- Проверка контроллеров (isControllerAllowed)
- Проверка префиксов (isPrefixReserved)
- Проверка конфликтов маршрутов (checkConflict, canCreateRoute)
- Логирование (встроено в каждый метод)
```

**Последствия:**
- Сложно тестировать отдельные части
- Сложно расширять функциональность
- Высокая связанность

**Решение:** Разделить на отдельные классы:
- `MiddlewareValidator`
- `ControllerValidator`
- `PrefixValidator`
- `RouteConflictChecker`

---

### 2. **Проблемы с зависимостями**

#### 2.1. Опциональные зависимости (nullable)

```php
public function __construct(
    private ?RouteNodeRepository $repository = null,
    private ?DeclarativeRouteLoader $declarativeLoader = null,
) {}
```

**Проблемы:**
- ❌ Нарушение принципа явных зависимостей
- ❌ Усложняет тестирование (нужно проверять null-кейсы)
- ❌ Непонятно, когда зависимости нужны, а когда нет
- ❌ Множество проверок `if ($this->repository)` по коду

**Примеры использования:**
```php
// В тестах - без зависимостей
$guard = new DynamicRouteGuard();

// В Rules - создаётся новый экземпляр каждый раз
$guard = new DynamicRouteGuard($repository, $loader);

// В RouteServiceProvider - с зависимостями
$guard = new DynamicRouteGuard($repository, $loader);
```

**Решение:** Использовать интерфейсы и обязательные зависимости:
```php
interface RouteConflictCheckerInterface {
    public function checkConflict(...): ?RouteNode;
}

class RouteConflictChecker implements RouteConflictCheckerInterface {
    public function __construct(
        private RouteNodeRepository $repository,
        private DeclarativeRouteLoader $declarativeLoader,
    ) {}
}
```

#### 2.2. Прямой вызов глобальных функций

```php
$allowed = config('dynamic-routes.allowed_middleware', []);
Log::warning('Dynamic route: неразрешённый middleware', [...]);
```

**Проблемы:**
- ❌ Сложно тестировать (зависимость от глобального состояния)
- ❌ Невозможно мокировать без фасадов
- ❌ Нарушение Dependency Inversion Principle

**Решение:** Внедрять через конструктор:
```php
class MiddlewareValidator {
    public function __construct(
        private array $allowedMiddleware,
        private LoggerInterface $logger,
    ) {}
}
```

---

### 3. **Проблемы производительности**

#### 3.1. Отсутствие кэширования

```php
public function isMiddlewareAllowed(string $middleware): bool
{
    $allowed = config('dynamic-routes.allowed_middleware', []); // Чтение конфига каждый раз
    // ...
}
```

**Проблема:** Конфигурация читается при каждом вызове метода.

**Решение:** Кэшировать конфигурацию в конструкторе или использовать lazy loading.

#### 3.2. Загрузка всех маршрутов при проверке конфликтов

```php
public function checkConflict(string $uri, array $methods, ?int $excludeId = null): ?RouteNode
{
    // Загружает ВСЕ маршруты из БД при каждой проверке!
    $dbNodes = $this->repository->getEnabledTree();
    // ...
}
```

**Проблемы:**
- ❌ При валидации формы может вызываться многократно
- ❌ Загружает все маршруты даже для простой проверки
- ❌ Нет индексации по URI для быстрого поиска

**Пример из `RouteConflictRule`:**
```php
public function passes($attribute, $value): bool
{
    // Создаётся новый guard каждый раз
    $guard = new DynamicRouteGuard($repository, $loader);
    $result = $guard->canCreateRoute($uri, $methods, $this->excludeId);
    // ...
}

public function message(): string
{
    // И снова создаётся новый guard и загружаются все маршруты!
    $guard = new DynamicRouteGuard($repository, $loader);
    $result = $guard->canCreateRoute($uri, $methods, $this->excludeId);
    // ...
}
```

**Решение:**
1. Кэшировать результаты проверки конфликтов
2. Использовать индексы БД для быстрого поиска
3. Оптимизировать запросы (проверять только нужные маршруты)

---

### 4. **Дублирование кода**

#### 4.1. Логика паттернов повторяется

```php
// В isMiddlewareAllowed
if (str_ends_with($pattern, ':*')) {
    $prefix = substr($pattern, 0, -2);
    if (str_starts_with($middleware, $prefix . ':')) {
        return true;
    }
}

// В isControllerAllowed
if (str_ends_with($pattern, '*')) {
    $prefix = substr($pattern, 0, -1);
    if (str_starts_with($controller, $prefix)) {
        return true;
    }
}
```

**Решение:** Вынести в отдельный класс `PatternMatcher`:
```php
class PatternMatcher {
    public function matches(string $value, string $pattern): bool {
        // Общая логика для всех паттернов
    }
}
```

#### 4.2. Нормализация URI дублируется

```php
// В checkConflict
$normalizedUri = ltrim($uri, '/');

// В canCreateRoute
$normalizedUri = ltrim($uri, '/');

// В findConflictInCollection
$nodeUri = ltrim($node->uri, '/');
```

**Решение:** Вынести в утилитный метод или класс `UriNormalizer`.

---

### 5. **Проблемы с тестируемостью**

#### 5.1. Создание экземпляров в Rules

```php
// app/Rules/ReservedPrefixRule.php
public function validate(...): void
{
    $guard = new DynamicRouteGuard(); // ❌ Создание в методе
    // ...
}

// app/Rules/RouteConflictRule.php
public function passes(...): bool
{
    $guard = new DynamicRouteGuard($repository, $loader); // ❌ Создание в методе
    // ...
}
```

**Проблемы:**
- ❌ Невозможно мокировать в тестах
- ❌ Нарушение Dependency Injection
- ❌ Сложно тестировать изоляцию

**Решение:** Внедрять через конструктор:
```php
class ReservedPrefixRule implements ValidationRule {
    public function __construct(
        private PrefixValidator $validator,
    ) {}
}
```

#### 5.2. Зависимость от глобального состояния

```php
// Тесты вынуждены мокировать фасады
Log::spy();
Log::shouldHaveReceived('warning');
```

**Решение:** Использовать интерфейсы вместо фасадов.

---

### 6. **Нарушение Open/Closed Principle**

**Проблема:** Для добавления нового типа проверки нужно модифицировать класс.

**Пример:** Если нужно добавить проверку доменов, придётся:
1. Добавить новый метод в `DynamicRouteGuard`
2. Изменить конструктор (если нужны зависимости)
3. Обновить все места использования

**Решение:** Использовать Strategy pattern:
```php
interface RouteValidatorInterface {
    public function validate(RouteNode $node): ValidationResult;
}

class MiddlewareValidator implements RouteValidatorInterface { }
class ControllerValidator implements RouteValidatorInterface { }
class PrefixValidator implements RouteValidatorInterface { }

class DynamicRouteGuard {
    public function __construct(
        private array $validators,
    ) {}
}
```

---

### 7. **Смешение уровней абстракции**

**Проблема:** Класс смешивает:
- Бизнес-логику (проверки)
- Инфраструктурные детали (чтение конфига, логирование)
- Доступ к данным (загрузка маршрутов)

**Пример:**
```php
public function checkConflict(...): ?RouteNode
{
    // Инфраструктура: загрузка данных
    $declarativeNodes = $this->declarativeLoader->loadAll();
    $dbNodes = $this->repository->getEnabledTree();
    
    // Бизнес-логика: поиск конфликтов
    $conflict = $this->findConflictInCollection(...);
    
    // Инфраструктура: возврат модели
    return $conflict;
}
```

**Решение:** Разделить на слои:
- **Domain Layer:** Бизнес-логика проверок
- **Infrastructure Layer:** Доступ к данным, конфигурации
- **Application Layer:** Оркестрация проверок

---

## 🔧 Рекомендации по улучшению

### 1. **Разделение ответственности**

```php
// Валидаторы
class MiddlewareValidator {
    public function __construct(
        private array $allowedMiddleware,
        private PatternMatcher $patternMatcher,
        private LoggerInterface $logger,
    ) {}
    
    public function isAllowed(string $middleware): bool { }
    public function sanitize(array $middleware): array { }
}

class ControllerValidator {
    public function __construct(
        private array $allowedControllers,
        private PatternMatcher $patternMatcher,
        private LoggerInterface $logger,
    ) {}
    
    public function isAllowed(string $controller): bool { }
}

class PrefixValidator {
    public function __construct(
        private array $reservedPrefixes,
    ) {}
    
    public function isReserved(string $prefix): bool { }
}

// Проверка конфликтов
class RouteConflictChecker {
    public function __construct(
        private RouteNodeRepository $repository,
        private DeclarativeRouteLoader $declarativeLoader,
        private UriNormalizer $uriNormalizer,
    ) {}
    
    public function checkConflict(string $uri, array $methods, ?int $excludeId = null): ?RouteNode { }
}

// Фасад (опционально)
class DynamicRouteGuard {
    public function __construct(
        private MiddlewareValidator $middlewareValidator,
        private ControllerValidator $controllerValidator,
        private PrefixValidator $prefixValidator,
        private RouteConflictChecker $conflictChecker,
    ) {}
    
    // Делегирует вызовы валидаторам
    public function isMiddlewareAllowed(string $middleware): bool {
        return $this->middlewareValidator->isAllowed($middleware);
    }
}
```

### 2. **Улучшение производительности**

```php
class RouteConflictChecker {
    private ?Collection $cachedRoutes = null;
    
    public function checkConflict(string $uri, array $methods, ?int $excludeId = null): ?RouteNode
    {
        // Кэшируем загруженные маршруты
        if ($this->cachedRoutes === null) {
            $this->cachedRoutes = $this->loadAllRoutes();
        }
        
        // Используем индексированный поиск
        return $this->findConflictInIndexedCollection(
            $this->cachedRoutes,
            $uri,
            $methods,
            $excludeId
        );
    }
    
    private function loadAllRoutes(): Collection {
        // Загружаем один раз
    }
}
```

### 3. **Dependency Injection в Rules**

```php
// В ServiceProvider
$this->app->singleton(MiddlewareValidator::class, function ($app) {
    return new MiddlewareValidator(
        config('dynamic-routes.allowed_middleware', []),
        $app->make(PatternMatcher::class),
        $app->make(LoggerInterface::class),
    );
});

// В Rule
class ReservedPrefixRule implements ValidationRule {
    public function __construct(
        private PrefixValidator $validator,
    ) {}
    
    public function validate(...): void {
        if ($this->validator->isReserved($value)) {
            $fail("...");
        }
    }
}
```

### 4. **Кэширование конфигурации**

```php
class MiddlewareValidator {
    private array $allowedMiddleware;
    private array $exactMatches;
    private array $patternMatches;
    
    public function __construct(
        array $allowedMiddleware,
        private PatternMatcher $patternMatcher,
        private LoggerInterface $logger,
    ) {
        // Предобработка конфигурации
        $this->allowedMiddleware = $allowedMiddleware;
        $this->exactMatches = array_filter($allowedMiddleware, fn($p) => !str_contains($p, '*') && !str_contains($p, ':'));
        $this->patternMatches = array_filter($allowedMiddleware, fn($p) => str_contains($p, '*') || str_ends_with($p, ':*'));
    }
    
    public function isAllowed(string $middleware): bool {
        // Быстрая проверка точных совпадений
        if (in_array($middleware, $this->exactMatches, true)) {
            return true;
        }
        
        // Проверка паттернов только если нужно
        return $this->checkPatterns($middleware);
    }
}
```

---

## 📊 Метрики сложности

| Метрика | Текущее значение | Рекомендуемое |
|---------|------------------|---------------|
| Cyclomatic Complexity | ~25 | < 10 на класс |
| Количество методов | 7 публичных | 3-5 на класс |
| Зависимости | 2 (nullable) | 3-5 (обязательные) |
| Строк кода | 304 | < 200 на класс |
| Уровень вложенности | 3-4 | < 3 |

---

## 🎯 Приоритеты рефакторинга

### Высокий приоритет:
1. ✅ Разделение на отдельные валидаторы (SRP)
2. ✅ Устранение опциональных зависимостей
3. ✅ Кэширование результатов проверки конфликтов

### Средний приоритет:
4. ⚠️ Внедрение Dependency Injection в Rules
5. ⚠️ Вынос логики паттернов в отдельный класс
6. ⚠️ Кэширование конфигурации

### Низкий приоритет:
7. 📝 Рефакторинг для соответствия Strategy pattern
8. 📝 Улучшение документации

---

## 📝 Выводы

`DynamicRouteGuard` выполняет свою функцию, но имеет серьёзные архитектурные проблемы:

1. **Нарушение SRP** — слишком много ответственностей
2. **Проблемы с зависимостями** — опциональные зависимости, создание в методах
3. **Производительность** — отсутствие кэширования, загрузка всех маршрутов
4. **Тестируемость** — зависимость от глобального состояния

**Рекомендация:** Провести рефакторинг с разделением на отдельные классы-валидаторы и улучшением производительности.

