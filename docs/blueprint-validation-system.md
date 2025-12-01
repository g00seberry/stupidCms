# Система валидации Blueprint

## Оглавление

1. [Обзор](#обзор)
2. [Архитектура](#архитектура)
3. [Компоненты системы](#компоненты-системы)
4. [Типы правил валидации](#типы-правил-валидации)
5. [Процесс валидации](#процесс-валидации)
6. [Структура данных](#структура-данных)
7. [Примеры использования](#примеры-использования)
8. [Кэширование](#кэширование)
9. [Расширение системы](#расширение-системы)

---

## Обзор

Система валидации Blueprint — это доменно-ориентированная система валидации контента записей (Entry) на основе структуры Blueprint. Она преобразует правила валидации, определённые в Path модели, в правила валидации Laravel для поля `content_json`.

### Основные возможности

-   **Доменная модель**: Правила валидации независимы от Laravel и могут быть переиспользованы
-   **Динамическая валидация**: Правила строятся на основе структуры Blueprint в runtime
-   **Поддержка вложенных структур**: Валидация полей внутри массивов и объектов
-   **Кэширование**: Правила кэшируются для оптимизации производительности
-   **Расширяемость**: Легко добавлять новые типы правил через систему handlers

### Ключевые концепции

-   **Blueprint**: Шаблон структуры данных для Entry
-   **Path**: Поле внутри Blueprint с метаданными (тип, кардинальность, правила валидации)
-   **Rule**: Доменное правило валидации (независимо от Laravel)
-   **RuleSet**: Набор правил для всех полей Blueprint
-   **Handler**: Преобразователь доменного правила в Laravel правило

---

## Архитектура

Система построена по принципу **Domain-Driven Design** с разделением на доменный слой и адаптеры:

```
┌─────────────────────────────────────────────────────────────┐
│                    HTTP Request Layer                        │
│  (StoreEntryRequest, UpdateEntryRequest)                      │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              BlueprintContentValidator                        │
│  (Кэширование, координация)                                 │
└──────────────┬───────────────────────┬──────────────────────┘
               │                       │
               ▼                       ▼
┌──────────────────────────┐  ┌──────────────────────────────┐
│ EntryValidationService    │  │ LaravelValidationAdapter      │
│ (Построение RuleSet)      │  │ (Преобразование в Laravel)    │
└───────────┬──────────────┘  └──────────────┬─────────────────┘
            │                                 │
            ▼                                 ▼
┌──────────────────────────┐  ┌──────────────────────────────┐
│ PathValidationRules       │  │ RuleHandlerRegistry           │
│ Converter                 │  │ (Handlers для каждого типа)   │
└───────────┬──────────────┘  └──────────────┬─────────────────┘
            │                                 │
            ▼                                 ▼
┌──────────────────────────┐  ┌──────────────────────────────┐
│ RuleFactory              │  │ Rule Handlers                 │
│ (Создание Rule объектов) │  │ (MinRuleHandler, etc.)        │
└──────────────────────────┘  └──────────────────────────────┘
```

### Поток данных

1. **HTTP Request** → `StoreEntryRequest::withValidator()`
2. **BlueprintContentValidator** → получает Blueprint из PostType
3. **EntryValidationService** → строит `RuleSet` из Path'ов Blueprint
4. **PathValidationRulesConverter** → преобразует `validation_rules` в Rule объекты
5. **LaravelValidationAdapter** → преобразует RuleSet в Laravel правила
6. **RuleHandlerRegistry** → использует handlers для преобразования каждого правила
7. **Laravel Validator** → применяет правила к `content_json`

---

## Компоненты системы

### 1. BlueprintContentValidator

**Путь**: `app/Domain/Blueprint/Validation/BlueprintContentValidator.php`

Главный координатор системы валидации. Отвечает за:

-   Кэширование правил валидации
-   Координацию между `EntryValidationService` и `LaravelValidationAdapter`
-   Добавление базовых типов данных (string, integer, array и т.д.)

**Методы**:

-   `buildRules(Blueprint $blueprint): array` — построить правила валидации для Blueprint
-   `invalidateCache(Blueprint $blueprint): void` — инвалидировать кэш

**Кэширование**: TTL = 3600 секунд (1 час), ключ: `blueprint:validation_rules:{blueprint_id}`

### 2. EntryValidationService

**Путь**: `app/Domain/Blueprint/Validation/EntryValidationService.php`

Доменный сервис для построения `RuleSet` из структуры Blueprint. Анализирует все Path'ы и преобразует их в доменные правила.

**Ключевая логика**:

-   Преобразует `full_path` в точечную нотацию (`content_json.field.path`)
-   Обрабатывает `cardinality: 'many'` → заменяет сегменты на `*` для массивов
-   Разделяет правила для массивов и их элементов

**Пример преобразования путей**:

-   `'title'` → `'content_json.title'`
-   `'author.name'` (где `author` имеет `cardinality: 'many'`) → `'content_json.author.*.name'`

### 3. PathValidationRulesConverter

**Путь**: `app/Domain/Blueprint/Validation/PathValidationRulesConverter.php`

Преобразует `validation_rules` из модели Path в доменные Rule объекты.

**Поддерживаемые правила**:

-   `min`, `max` — минимальное/максимальное значение/длина
-   `pattern` — регулярное выражение
-   `array_min_items`, `array_max_items` — ограничения для массивов
-   `array_unique` — уникальность элементов массива
-   `required_if`, `prohibited_unless`, `required_unless`, `prohibited_if` — условные правила
-   `unique` — уникальность в таблице
-   `exists` — существование в таблице
-   `field_comparison` — сравнение с другим полем

### 4. LaravelValidationAdapter

**Путь**: `app/Domain/Blueprint/Validation/Adapters/LaravelValidationAdapter.php`

Преобразует доменный `RuleSet` в массив Laravel правил валидации.

**Особенности**:

-   Преобразует доменные Rule объекты в Laravel правила валидации через систему handlers
-   Не добавляет базовые типы данных автоматически (пользователь сам указывает все необходимые правила)

### 5. RuleFactory

**Путь**: `app/Domain/Blueprint/Validation/Rules/RuleFactory.php`

Фабрика для создания доменных Rule объектов. Инкапсулирует логику создания правил.

**Реализация**: `RuleFactoryImpl`

### 6. RuleHandlerRegistry

**Путь**: `app/Domain/Blueprint/Validation/Rules/Handlers/RuleHandlerRegistry.php`

Реестр обработчиков правил. Каждый handler преобразует доменное правило в Laravel правило.

**Регистрация handlers** (в `AppServiceProvider`):

-   `required` → `RequiredRuleHandler`
-   `nullable` → `NullableRuleHandler`
-   `min` → `MinRuleHandler`
-   `max` → `MaxRuleHandler`
-   `pattern` → `PatternRuleHandler`
-   `array_min_items` → `ArrayMinItemsRuleHandler`
-   `array_max_items` → `ArrayMaxItemsRuleHandler`
-   `array_unique` → `ArrayUniqueRuleHandler`
-   `required_if`, `prohibited_unless`, `required_unless`, `prohibited_if` → `ConditionalRuleHandler`
-   `field_comparison` → `FieldComparisonRuleHandler`

---

## Типы правил валидации

### Базовые правила

#### RequiredRule / NullableRule

**Тип**: `required` / `nullable`

**Описание**: Поле обязательно или опционально.

**Создание**:

```php
$ruleFactory->createRequiredRule();
$ruleFactory->createNullableRule();
```

**Laravel правило**: `'required'` / `'nullable'`

**Применение**: Только для `cardinality: 'one'`. Для массивов (`cardinality: 'many'`) правило применяется к самому массиву, а не к элементам.

---

#### MinRule / MaxRule

**Тип**: `min` / `max`

**Описание**: Минимальное/максимальное значение или длина.

**Параметры**:

-   `value` — значение минимума/максимума
-   `data_type` — тип данных (определяет семантику: для строк — длина, для чисел — значение)

**Создание**:

```php
$ruleFactory->createMinRule(1, 'string'); // Минимум 1 символ
$ruleFactory->createMaxRule(500, 'string'); // Максимум 500 символов
$ruleFactory->createMinRule(0, 'int'); // Минимум 0
$ruleFactory->createMaxRule(100, 'int'); // Максимум 100
```

**Laravel правило**: `'min:1'` / `'max:500'`

**Пример в Path**:

```json
{
    "validation_rules": {
        "min": 1,
        "max": 500
    }
}
```

---

#### PatternRule

**Тип**: `pattern`

**Описание**: Регулярное выражение для валидации строки.

**Параметры**:

-   `pattern` — регулярное выражение (без ограничителей)

**Создание**:

```php
$ruleFactory->createPatternRule('^\\+?[1-9]\\d{1,14}$');
```

**Laravel правило**: `'regex:/^\\+?[1-9]\\d{1,14}$/'`

**Пример в Path**:

```json
{
    "validation_rules": {
        "pattern": "^\\+?[1-9]\\d{1,14}$"
    }
}
```

---

### Правила для массивов

#### ArrayMinItemsRule / ArrayMaxItemsRule

**Тип**: `array_min_items` / `array_max_items`

**Описание**: Минимальное/максимальное количество элементов в массиве.

**Параметры**:

-   `value` — количество элементов

**Создание**:

```php
$ruleFactory->createArrayMinItemsRule(1);
$ruleFactory->createArrayMaxItemsRule(10);
```

**Laravel правило**: `'min:1'` / `'max:10'` (применяется к массиву)

**Применение**: Только для `cardinality: 'many'`. Правило применяется к самому массиву.

**Пример в Path**:

```json
{
    "validation_rules": {
        "array_min_items": 1,
        "array_max_items": 10
    }
}
```

---

#### ArrayUniqueRule

**Тип**: `array_unique`

**Описание**: Все элементы массива должны быть уникальными.

**Создание**:

```php
$ruleFactory->createArrayUniqueRule();
```

**Laravel правило**: `'distinct'`

**Применение**: Только для `cardinality: 'many'`. Правило применяется к элементам массива (`field.*`).

**Пример в Path**:

```json
{
    "validation_rules": {
        "array_unique": true
    }
}
```

---

### Условные правила

#### ConditionalRule

**Тип**: `required_if`, `prohibited_unless`, `required_unless`, `prohibited_if`

**Описание**: Правило применяется в зависимости от значения другого поля.

**Параметры**:

-   `type` — тип правила
-   `field` — путь к полю условия
-   `value` — значение для условия
-   `operator` — оператор сравнения (по умолчанию `'=='`)

**Создание**:

```php
// С оператором
$ruleFactory->createConditionalRule('required_if', 'status', 'active', '==');
```

**Laravel правило**: `'required_if:is_published,true'`

**Формат в Path** (только расширенный):

```json
{
    "validation_rules": {
        "required_if": {
            "field": "is_published",
            "value": true,
            "operator": "=="
        }
    }
}
```

**Примечание**: Поле `operator` опционально, по умолчанию используется `'=='`. Поддерживаемые операторы: `'=='`, `'!='`, `'>'`, `'<'`, `'>='`, `'<='`.

---

### Правила сравнения

#### FieldComparisonRule

**Тип**: `field_comparison`

**Описание**: Сравнение поля с другим полем или константой.

**Параметры**:

-   `operator` — оператор сравнения (`'>=', '<=', '>', '<', '==', '!='`)
-   `otherField` — путь к другому полю (например, `'content_json.start_date'`)
-   `constantValue` — константное значение (если указано, используется вместо `otherField`)

**Создание**:

```php
// Сравнение с другим полем
$ruleFactory->createFieldComparisonRule('>=', 'content_json.start_date', null);

// Сравнение с константой
$ruleFactory->createFieldComparisonRule('>=', '', '2024-01-01');
```

**Laravel правило**: Используется кастомное правило через `ValidationRule` интерфейс.

**Форматы в Path**:

```json
// Сравнение с другим полем
{
  "validation_rules": {
    "field_comparison": {
      "operator": ">=",
      "field": "content_json.start_date"
    }
  }
}

// Сравнение с константой
{
  "validation_rules": {
    "field_comparison": {
      "operator": ">=",
      "value": "2024-01-01"
    }
  }
}
```

---

## Процесс валидации

### Шаг 1: HTTP Request

В `StoreEntryRequest::withValidator()` вызывается `addBlueprintValidationRules()`:

```php
private function addBlueprintValidationRules(Validator $validator): void
{
    $postType = PostType::query()
        ->with('blueprint')
        ->where('slug', $postTypeSlug)
        ->first();

    if (! $postType || ! $postType->blueprint) {
        return;
    }

    // Построение RuleSet
    $validationService = app(EntryValidationServiceInterface::class);
    $ruleSet = $validationService->buildRulesFor($postType->blueprint);

    // Преобразование в Laravel правила
    $adapter = app(LaravelValidationAdapterInterface::class);
    $laravelRules = $adapter->adapt($ruleSet, $dataTypes);

    // Добавление правил в валидатор
    foreach ($laravelRules as $field => $rules) {
        $validator->addRules([$field => $rules]);
    }
}
```

### Шаг 2: Построение RuleSet

`EntryValidationService::buildRulesFor()`:

1. Загружает все Path'ы из Blueprint
2. Для каждого Path:
    - Преобразует `full_path` в точечную нотацию
    - Обрабатывает `cardinality: 'many'` → заменяет сегменты на `*`
    - Вызывает `PathValidationRulesConverter::convert()`
    - Добавляет правила в `RuleSet`

**Пример**:

```php
// Path: full_path = 'author.name', cardinality = 'many'
// Результат: 'content_json.author.*.name'

// Path: full_path = 'title', cardinality = 'one'
// Результат: 'content_json.title'
```

### Шаг 3: Преобразование validation_rules

`PathValidationRulesConverter::convert()`:

1. Добавляет `RequiredRule` или `NullableRule` (только для `cardinality: 'one'`)
2. Обрабатывает каждое правило из `validation_rules`:
    - `min` / `max` → `MinRule` / `MaxRule`
    - `pattern` → `PatternRule`
    - `array_min_items` / `array_max_items` → `ArrayMinItemsRule` / `ArrayMaxItemsRule`
    - `array_unique` → `ArrayUniqueRule`
    - `required_if` и т.д. → `ConditionalRule`
    - `field_comparison` → `FieldComparisonRule`

### Шаг 4: Преобразование в Laravel правила

`LaravelValidationAdapter::adapt()`:

1. Для каждого поля в `RuleSet`:
    - Получает handler для типа правила из `RuleHandlerRegistry`
    - Вызывает `handler->handle($rule, $dataType)`
    - Добавляет базовый тип данных (string, integer, array и т.д.)
2. Специальная обработка для массивов:
    - Добавляет правило `'array'` для полей с `cardinality: 'many'`
    - Для элементов массивов (`.*`) также добавляет `'array'`, если `data_type: 'json'`

### Шаг 5: Применение правил

Laravel Validator применяет правила к `content_json`:

```php
$validator->validate([
    'content_json' => [
        'title' => 'Test',
        'author' => [
            ['name' => 'John'],
            ['name' => 'Jane'],
        ],
    ],
]);
```

---

## Структура данных

### Blueprint

**Модель**: `app/Models/Blueprint.php`

**Поля**:

-   `id` — ID
-   `name` — название
-   `code` — уникальный код
-   `description` — описание

**Связи**:

-   `paths()` — все Path'ы Blueprint
-   `postTypes()` — PostType'ы, использующие этот Blueprint

### Path

**Модель**: `app/Models/Path.php`

**Поля**:

-   `id` — ID
-   `blueprint_id` — владелец
-   `name` — локальное имя поля
-   `full_path` — материализованный путь (например, `'author.contacts.phone'`)
-   `data_type` — тип данных: `string`, `text`, `int`, `float`, `bool`, `date`, `datetime`, `json`, `ref`
-   `cardinality` — кардинальность: `'one'` или `'many'`
-   `is_indexed` — индексируется ли поле
-   `validation_rules` — JSON правила валидации (массив), включая `required` для обязательности поля

**Пример `validation_rules`**:

```json
{
    "min": 1,
    "max": 500,
    "pattern": "^[a-z]+$",
    "array_min_items": 1,
    "array_max_items": 10,
    "array_unique": true,
    "required_if": {
        "field": "is_published",
        "value": true,
        "operator": "=="
    },
    "unique": {
        "table": "users",
        "column": "email"
    },
    "exists": {
        "table": "categories",
        "column": "id"
    },
    "field_comparison": {
        "operator": ">=",
        "field": "content_json.start_date"
    }
}
```

---

## Примеры использования

### Пример 1: Простая валидация строки

**Blueprint**:

```php
Path::create([
    'blueprint_id' => $blueprint->id,
    'name' => 'title',
    'full_path' => 'title',
    'data_type' => 'string',
    'cardinality' => 'one',
    'validation_rules' => [
        'required' => true,
        'min' => 1,
        'max' => 500,
    ],
]);
```

**Результат**:

```php
[
    'content_json.title' => ['required', 'string', 'min:1', 'max:500'],
]
```

---

### Пример 2: Валидация массива объектов

**Blueprint**:

```php
// Массив авторов
Path::create([
    'blueprint_id' => $blueprint->id,
    'name' => 'authors',
    'full_path' => 'authors',
    'data_type' => 'json',
    'cardinality' => 'many',
    'validation_rules' => [
        'required' => false,
        'array_min_items' => 1,
        'array_max_items' => 10,
    ],
]);

// Имя автора (внутри массива)
Path::create([
    'blueprint_id' => $blueprint->id,
    'name' => 'name',
    'full_path' => 'authors.name',
    'data_type' => 'string',
    'cardinality' => 'one',
    'validation_rules' => [
        'required' => true,
        'min' => 1,
        'max' => 100,
    ],
]);
```

**Результат**:

```php
[
    'content_json.authors' => ['nullable', 'array', 'min:1', 'max:10'],
    'content_json.authors.*' => ['array'], // Для элементов массива (объектов)
    'content_json.authors.*.name' => ['required', 'string', 'min:1', 'max:100'],
]
```

---

### Пример 3: Условная валидация

**Blueprint**:

```php
Path::create([
    'blueprint_id' => $blueprint->id,
    'name' => 'published_at',
    'full_path' => 'published_at',
    'data_type' => 'datetime',
    'cardinality' => 'one',
    'validation_rules' => [
        'required' => false,
        'required_if' => [
            'field' => 'is_published',
            'value' => true,
            'operator' => '==',
        ],
    ],
]);
```

**Результат**:

```php
[
    'content_json.published_at' => ['nullable', 'date', 'required_if:is_published,true'],
]
```

---

### Пример 4: Уникальность в БД

**Blueprint**:

```php
Path::create([
    'blueprint_id' => $blueprint->id,
    'name' => 'email',
    'full_path' => 'email',
    'data_type' => 'string',
    'cardinality' => 'one',
    'validation_rules' => [
        'required' => true,
        'unique' => [
            'table' => 'users',
            'column' => 'email',
        ],
    ],
]);
```

**Результат**:

```php
[
    'content_json.email' => ['required', 'string', 'unique:users,email'],
]
```

---

## Кэширование

### Механизм кэширования

Правила валидации кэшируются в `BlueprintContentValidator::buildRules()`:

```php
$cacheKey = "blueprint:validation_rules:{$blueprint->id}";

return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($blueprint): array {
    // Построение правил
});
```

**TTL**: 3600 секунд (1 час)

### Инвалидация кэша

Кэш инвалидируется автоматически при изменении структуры Blueprint через событие `BlueprintStructureChanged` и listener `InvalidateValidationCache`:

```php
// app/Listeners/Blueprint/InvalidateValidationCache.php
public function handle(BlueprintStructureChanged $event): void
{
    $this->validator->invalidateCache($event->blueprint);
}
```

**Ручная инвалидация**:

```php
$validator = app(BlueprintContentValidatorInterface::class);
$validator->invalidateCache($blueprint);
```

---

## Расширение системы

### Добавление нового типа правила

#### Шаг 1: Создать Rule класс

```php
// app/Domain/Blueprint/Validation/Rules/CustomRule.php
final class CustomRule implements Rule
{
    public function __construct(
        private readonly mixed $value
    ) {}

    public function getType(): string
    {
        return 'custom';
    }

    public function getParams(): array
    {
        return ['value' => $this->value];
    }
}
```

#### Шаг 2: Добавить метод в RuleFactory

```php
// app/Domain/Blueprint/Validation/Rules/RuleFactory.php
public function createCustomRule(mixed $value): CustomRule;
```

```php
// app/Domain/Blueprint/Validation/Rules/RuleFactoryImpl.php
public function createCustomRule(mixed $value): CustomRule
{
    return new CustomRule($value);
}
```

#### Шаг 3: Создать Handler

```php
// app/Domain/Blueprint/Validation/Rules/Handlers/CustomRuleHandler.php
final class CustomRuleHandler implements RuleHandlerInterface
{
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'custom';
    }

    public function handle(Rule $rule, string $dataType): array
    {
        if (! $rule instanceof CustomRule) {
            throw new \InvalidArgumentException('Expected CustomRule instance');
        }

        $value = $rule->getParams()['value'];
        return ["custom:{$value}"];
    }
}
```

#### Шаг 4: Обработать в PathValidationRulesConverter

```php
// app/Domain/Blueprint/Validation/PathValidationRulesConverter.php
foreach ($validationRules as $key => $value) {
    match ($key) {
        // ... существующие правила
        'custom' => $rules[] = $this->ruleFactory->createCustomRule($value),
        default => null,
    };
}
```

#### Шаг 5: Зарегистрировать Handler

```php
// app/Providers/AppServiceProvider.php
$registry->register('custom', new CustomRuleHandler());
```

---

## Типы данных и базовые правила

### Маппинг data_type → Laravel правило

| data_type        | Laravel правило          | Описание                       |
| ---------------- | ------------------------ | ------------------------------ |
| `string`, `text` | `'string'`               | Строка                         |
| `int`            | `'integer'`              | Целое число                    |
| `float`          | `'numeric'`              | Число с плавающей точкой       |
| `bool`           | `'boolean'`              | Булево значение                |
| `date`           | `'date'`                 | Дата                           |
| `datetime`       | `'date'`                 | Дата и время                   |
| `json`           | `'array'`                | JSON объект (массив в Laravel) |
| `ref`            | `'integer'`              | Ссылка (ID)                    |

**Особенности**:

-   Для `data_type: 'json'` с `cardinality: 'many'` элементы массива имеют тип `'array'` (объекты внутри массива)

---

## Обработка массивов

### Cardinality: 'many'

Для полей с `cardinality: 'many'` система создаёт правила на двух уровнях:

1. **Для самого массива**:

    - `required` / `nullable`
    - `array`
    - `array_min_items` / `array_max_items` (преобразуются в `min` / `max`)

2. **Для элементов массива** (`field.*`):
    - Базовый тип данных
    - Правила валидации из `validation_rules` (кроме `array_*`)

**Пример**:

```php
// Path: name='tags', cardinality='many', data_type='string'
// Результат:
[
    'content_json.tags' => ['nullable', 'array'],
    'content_json.tags.*' => ['string', 'min:1', 'max:50'], // Правила для элементов
]
```

### Вложенные массивы

Для вложенных массивов (массивы внутри массивов объектов) система правильно обрабатывает пути:

```php
// Path: full_path='articles.tags', cardinality='many'
// Где 'articles' также имеет cardinality='many'
// Результат:
[
    'content_json.articles.*.tags' => ['nullable', 'array'],
    'content_json.articles.*.tags.*' => ['string'],
]
```

---

## Обработка ошибок

### Валидация min/max

Если `min > max`, оба правила игнорируются (валидация не пройдёт):

```php
// PathValidationRulesConverter::convert()
if ($minNumeric !== null && $maxNumeric !== null && $minNumeric > $maxNumeric) {
    return $rules; // Игнорируем оба правила
}
```

### Неизвестные правила

Неизвестные ключи в `validation_rules` игнорируются:

```php
match ($key) {
    // ... известные правила
    default => null, // Игнорируем неизвестные ключи
};
```

### Отсутствие handler'а

Если handler не найден, выбрасывается исключение:

```php
$handler = $this->registry->getHandler($ruleType);
if ($handler === null) {
    throw new \InvalidArgumentException("No handler found for rule type: {$ruleType}");
}
```

---

## Тестирование

### Unit тесты

-   `tests/Unit/Domain/Blueprint/Validation/BlueprintContentValidatorTest.php`
-   `tests/Unit/Domain/Blueprint/Validation/EntryValidationServiceTest.php`
-   `tests/Unit/Domain/Blueprint/Validation/PathValidationRulesConverterTest.php`
-   `tests/Unit/Domain/Blueprint/Validation/Adapters/LaravelValidationAdapterTest.php`
-   `tests/Unit/Domain/Blueprint/Validation/Rules/*Test.php`

### Feature тесты

-   `tests/Feature/Api/Entries/EntryValidationTest.php`
-   `tests/Feature/Api/Entries/EntryValidationAdditionalTest.php`
-   `tests/Feature/Api/Entries/EntryValidationAdvancedTest.php`

---

## Заключение

Система валидации Blueprint предоставляет гибкий и расширяемый механизм валидации контента записей на основе структуры Blueprint. Она разделяет доменную логику от инфраструктуры Laravel, что позволяет легко тестировать и расширять систему.

### Ключевые преимущества

1. **Доменная модель**: Правила независимы от Laravel
2. **Динамическая валидация**: Правила строятся на основе структуры Blueprint
3. **Кэширование**: Оптимизация производительности
4. **Расширяемость**: Легко добавлять новые типы правил
5. **Поддержка сложных структур**: Вложенные массивы и объекты

### Рекомендации

1. Всегда инвалидируйте кэш при изменении структуры Blueprint
2. Используйте правильные типы данных для оптимальной валидации
3. Тестируйте новые правила перед добавлением в production
4. Документируйте кастомные правила для команды

---

**Версия документа**: 1.0  
**Дата обновления**: 2024  
**Автор**: Система валидации Blueprint
