# Система валидации Blueprint данных

## Содержание

1. [Обзор](#обзор)
2. [Архитектура системы](#архитектура-системы)
3. [Компоненты системы](#компоненты-системы)
4. [Типы правил валидации](#типы-правил-валидации)
5. [Процесс валидации](#процесс-валидации)
6. [Структура данных](#структура-данных)
7. [Примеры использования](#примеры-использования)
8. [Расширение системы](#расширение-системы)

---

## Обзор

Система валидации Blueprint данных предназначена для автоматической валидации содержимого записей (Entry) на основе структуры Blueprint. Система преобразует правила валидации, определённые в Path (полях Blueprint), в правила валидации Laravel и применяет их к полю `content_json` при создании и обновлении записей.

### Основные принципы

- **Доменная независимость**: Правила валидации представлены в виде доменных объектов, независимых от Laravel
- **Адаптация к Laravel**: Доменные правила преобразуются в правила Laravel через систему адаптеров
- **Гибкость**: Поддержка различных типов правил (required, min, max, pattern, conditional и т.д.)
- **Расширяемость**: Легко добавлять новые типы правил через систему handlers

---

## Архитектура системы

Система валидации состоит из нескольких слоёв:

```
┌─────────────────────────────────────────────────────────────┐
│                    HTTP Request Layer                        │
│  (StoreEntryRequest, UpdateEntryRequest)                    │
│                    BlueprintValidationTrait                  │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│              EntryValidationService                          │
│  (построение RuleSet из Blueprint)                          │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│         PathValidationRulesConverter                        │
│  (преобразование validation_rules в Rule объекты)           │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                    RuleSet (доменные правила)                │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│              LaravelValidationAdapter                        │
│  (преобразование RuleSet в Laravel правила)                  │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│              RuleHandlerRegistry                             │
│  (обработка правил через handlers)                          │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│              Laravel Validator                                │
│  (финальная валидация данных)                                │
└─────────────────────────────────────────────────────────────┘
```

---

## Компоненты системы

### 1. EntryValidationService

**Путь:** `app/Domain/Blueprint/Validation/EntryValidationService.php`

**Назначение:** Основной сервис для построения набора правил валидации из Blueprint.

**Основные методы:**

- `buildRulesFor(Blueprint $blueprint): RuleSet` — строит RuleSet для всех Path в Blueprint

**Процесс работы:**

1. Загружает все Path из Blueprint (включая скопированные из embedded blueprint'ов)
2. Сортирует Path по длине `full_path` для корректной обработки вложенных путей
3. Для каждого Path:
   - Преобразует `full_path` в путь для валидации через `FieldPathBuilder` (с учётом cardinality)
   - Преобразует `validation_rules` в доменные Rule объекты через `PathValidationRulesConverter`
   - Добавляет правила в RuleSet

**Пример использования:**

```php
$validationService = app(EntryValidationServiceInterface::class);
$ruleSet = $validationService->buildRulesFor($blueprint);
```

### 2. PathValidationRulesConverter

**Путь:** `app/Domain/Blueprint/Validation/PathValidationRulesConverter.php`

**Назначение:** Преобразует массив `validation_rules` из Path в доменные Rule объекты.

**Поддерживаемые правила:**

- `required` (bool) → `RequiredRule` или `NullableRule`
- `min` (mixed) → `MinRule`
- `max` (mixed) → `MaxRule`
- `pattern` (string) → `PatternRule`
- `distinct` (bool) → `DistinctRule`
- `required_if`, `prohibited_unless`, `required_unless`, `prohibited_if` → `ConditionalRule`
- `field_comparison` → `FieldComparisonRule`

**Формат validation_rules:**

```php
[
    'required' => true,
    'min' => 1,
    'max' => 500,
    'pattern' => '/^[a-z]+$/',
    'required_if' => [
        'field' => 'is_published',
        'value' => true,
        'operator' => '=='
    ],
    'field_comparison' => [
        'operator' => '>=',
        'field' => 'content_json.start_date',
        'value' => null
    ]
]
```

### 3. FieldPathBuilder

**Путь:** `app/Domain/Blueprint/Validation/FieldPathBuilder.php`

**Назначение:** Преобразует `full_path` из Path в путь для валидации с учётом cardinality.

**Логика преобразования:**

- Если родительский путь имеет `cardinality: 'many'`, соответствующий сегмент заменяется на `*` (wildcard)
- Префикс `content_json.` добавляется ко всем путям

**Примеры:**

- `full_path: 'author.name'`, `cardinality: 'one'` → `'content_json.author.name'`
- `full_path: 'author.contacts.phone'`, где `'author.contacts'` имеет `cardinality: 'many'` → `'content_json.author.*.contacts.*.phone'`

### 4. RuleSet

**Путь:** `app/Domain/Blueprint/Validation/Rules/RuleSet.php`

**Назначение:** Хранит правила валидации, сгруппированные по путям полей.

**Структура:**

```php
[
    'content_json.title' => [RequiredRule, MinRule(1), MaxRule(500)],
    'content_json.author.name' => [RequiredRule, PatternRule('/^[A-Z]/')],
    'content_json.*.contacts.*.phone' => [RequiredRule, PatternRule('/^\+?[0-9]+$/')]
]
```

### 5. LaravelValidationAdapter

**Путь:** `app/Domain/Blueprint/Validation/Adapters/LaravelValidationAdapter.php`

**Назначение:** Преобразует доменный RuleSet в массив правил валидации Laravel.

**Процесс:**

1. Для каждого поля в RuleSet получает все правила
2. Для каждого правила находит соответствующий handler через `RuleHandlerRegistry`
3. Преобразует правило в массив строк Laravel правил
4. Объединяет все правила для поля

**Результат:**

```php
[
    'content_json.title' => ['required', 'min:1', 'max:500'],
    'content_json.author.name' => ['required', 'regex:/^[A-Z]/'],
    'content_json.*.contacts.*.phone' => ['required', 'regex:/^\+?[0-9]+$/']
]
```

### 6. RuleHandlerRegistry

**Путь:** `app/Domain/Blueprint/Validation/Rules/Handlers/RuleHandlerRegistry.php`

**Назначение:** Реестр обработчиков правил валидации.

**Зарегистрированные handlers:**

- `RequiredRuleHandler` — для `required`
- `NullableRuleHandler` — для `nullable`
- `MinRuleHandler` — для `min`
- `MaxRuleHandler` — для `max`
- `PatternRuleHandler` — для `pattern`
- `DistinctRuleHandler` — для `distinct`
- `ConditionalRuleHandler` — для `required_if`, `prohibited_unless`, `required_unless`, `prohibited_if`
- `FieldComparisonRuleHandler` — для `field_comparison`

**Регистрация handlers:**

Handlers регистрируются в `AppServiceProvider::register()`:

```php
$registry->register('required', new RequiredRuleHandler());
$registry->register('min', new MinRuleHandler());
// ... и т.д.
```

### 7. BlueprintValidationTrait

**Путь:** `app/Http/Requests/Admin/Concerns/BlueprintValidationTrait.php`

**Назначение:** Trait для добавления валидации Blueprint в Request классы.

**Метод:**

- `addBlueprintValidationRules(Validator $validator, ?PostType $postType = null): void` — добавляет правила валидации для `content_json` из Blueprint

**Использование:**

```php
class StoreEntryRequest extends FormRequest
{
    use BlueprintValidationTrait;

    public function withValidator(Validator $validator): void
    {
        $this->addBlueprintValidationRules($validator);
    }
}
```

---

## Типы правил валидации

### 1. RequiredRule / NullableRule

**Классы:** 
- `app/Domain/Blueprint/Validation/Rules/RequiredRule.php`
- `app/Domain/Blueprint/Validation/Rules/NullableRule.php`

**Handler:** `RequiredRuleHandler`, `NullableRuleHandler`

**Описание:** Определяет обязательность поля.

**В validation_rules:**

```php
'required' => true  // RequiredRule
'required' => false // NullableRule
```

**Laravel правило:** `'required'` или `'nullable'`

### 2. MinRule

**Класс:** `app/Domain/Blueprint/Validation/Rules/MinRule.php`

**Handler:** `MinRuleHandler`

**Описание:** Минимальное значение/длина.

**В validation_rules:**

```php
'min' => 1  // для строк — минимальная длина, для чисел — минимальное значение
```

**Laravel правило:** `'min:1'`

### 3. MaxRule

**Класс:** `app/Domain/Blueprint/Validation/Rules/MaxRule.php`

**Handler:** `MaxRuleHandler`

**Описание:** Максимальное значение/длина.

**В validation_rules:**

```php
'max' => 500  // для строк — максимальная длина, для чисел — максимальное значение
```

**Laravel правило:** `'max:500'`

### 4. PatternRule

**Класс:** `app/Domain/Blueprint/Validation/Rules/PatternRule.php`

**Handler:** `PatternRuleHandler`

**Описание:** Регулярное выражение для валидации строк.

**В validation_rules:**

```php
'pattern' => '/^[a-z]+$/'  // может быть с ограничителями или без
```

**Laravel правило:** `'regex:/^[a-z]+$/'`

**Особенности:**

- Если паттерн уже в формате `/pattern/flags`, используется как есть
- Иначе паттерн экранируется и оборачивается в `/pattern/`

### 5. DistinctRule

**Класс:** `app/Domain/Blueprint/Validation/Rules/DistinctRule.php`

**Handler:** `DistinctRuleHandler`

**Описание:** Уникальность элементов массива.

**В validation_rules:**

```php
'distinct' => true
```

**Laravel правило:** `'distinct'`

### 6. ConditionalRule

**Класс:** `app/Domain/Blueprint/Validation/Rules/ConditionalRule.php`

**Handler:** `ConditionalRuleHandler`

**Описание:** Условное правило валидации в зависимости от значения другого поля.

**Типы:**

- `required_if` — поле обязательно, если другое поле равно значению
- `prohibited_unless` — поле запрещено, если другое поле не равно значению
- `required_unless` — поле обязательно, если другое поле не равно значению
- `prohibited_if` — поле запрещено, если другое поле равно значению

**В validation_rules:**

```php
'required_if' => [
    'field' => 'is_published',
    'value' => true,
    'operator' => '=='  // опционально, по умолчанию '=='
]
```

**Laravel правило:** `'required_if:is_published,true'`

**Поддерживаемые операторы:**

- `==` (по умолчанию)
- `!=`, `>`, `<`, `>=`, `<=` (в будущих версиях)

### 7. FieldComparisonRule

**Класс:** `app/Domain/Blueprint/Validation/Rules/FieldComparisonRule.php`

**Handler:** `FieldComparisonRuleHandler`

**Описание:** Сравнение значения поля с другим полем или константой.

**В validation_rules:**

```php
'field_comparison' => [
    'operator' => '>=',
    'field' => 'content_json.start_date',  // сравнение с другим полем
    'value' => null
]

// или

'field_comparison' => [
    'operator' => '>=',
    'value' => '2024-01-01'  // сравнение с константой
]
```

**Поддерживаемые операторы:**

- `>=`, `<=`, `>`, `<`, `==`, `!=`

**Laravel правило:** Преобразуется в custom rule или `sometimes` с условием

---

## Процесс валидации

### Шаг 1: HTTP Request

При создании или обновлении Entry через API:

```php
POST /api/v1/admin/entries
{
    "post_type": "article",
    "title": "My Article",
    "content_json": {
        "title": "Article Title",
        "author": {
            "name": "John Doe"
        }
    }
}
```

### Шаг 2: FormRequest с BlueprintValidationTrait

В методе `withValidator()` вызывается `addBlueprintValidationRules()`:

```php
public function withValidator(Validator $validator): void
{
    $this->addBlueprintValidationRules($validator);
}
```

### Шаг 3: Построение RuleSet

`EntryValidationService::buildRulesFor()`:

1. Загружает Blueprint из PostType
2. Загружает все Path из Blueprint
3. Для каждого Path:
   - Преобразует `full_path` в путь валидации через `FieldPathBuilder`
   - Преобразует `validation_rules` в Rule объекты через `PathValidationRulesConverter`
   - Добавляет правила в RuleSet

### Шаг 4: Адаптация к Laravel

`LaravelValidationAdapter::adapt()`:

1. Для каждого поля в RuleSet получает все правила
2. Для каждого правила находит handler через `RuleHandlerRegistry`
3. Преобразует правило в строки Laravel правил
4. Объединяет все правила для поля

### Шаг 5: Применение правил

Правила добавляются в Laravel Validator:

```php
$validator->addRules([
    'content_json.title' => ['required', 'min:1', 'max:500'],
    'content_json.author.name' => ['required', 'regex:/^[A-Z]/']
]);
```

### Шаг 6: Валидация данных

Laravel Validator проверяет данные и возвращает ошибки, если валидация не прошла.

---

## Структура данных

### Path (модель)

**Таблица:** `paths`

**Основные поля:**

- `id` — ID пути
- `blueprint_id` — ID Blueprint
- `name` — локальное имя поля
- `full_path` — материализованный путь (например, `'author.contacts.phone'`)
- `data_type` — тип данных (`string`, `text`, `int`, `float`, `bool`, `datetime`, `json`, `ref`, `array`)
- `cardinality` — кардинальность (`one` или `many`)
- `validation_rules` — JSON массив правил валидации

**Пример validation_rules:**

```json
{
    "required": true,
    "min": 1,
    "max": 500,
    "pattern": "/^[a-z]+$/",
    "required_if": {
        "field": "is_published",
        "value": true,
        "operator": "=="
    }
}
```

### Blueprint (модель)

**Таблица:** `blueprints`

**Связь с Path:**

```php
$blueprint->paths() // HasMany
```

### Entry (модель)

**Таблица:** `entries`

**Поле для валидации:**

- `content_json` — JSON массив с данными, структура которых определяется Blueprint

**Пример content_json:**

```json
{
    "title": "Article Title",
    "author": {
        "name": "John Doe",
        "contacts": [
            {
                "phone": "+1234567890"
            }
        ]
    }
}
```

---

## Примеры использования

### Пример 1: Простая валидация строки

**Blueprint Path:**

```php
Path::create([
    'name' => 'title',
    'full_path' => 'title',
    'data_type' => 'string',
    'cardinality' => 'one',
    'validation_rules' => [
        'required' => true,
        'min' => 1,
        'max' => 500
    ]
]);
```

**Результат валидации:**

```php
[
    'content_json.title' => ['required', 'min:1', 'max:500']
]
```

### Пример 2: Вложенные поля

**Blueprint Paths:**

```php
// Родительский путь
Path::create([
    'name' => 'author',
    'full_path' => 'author',
    'data_type' => 'json',
    'cardinality' => 'one',
    'validation_rules' => ['required' => true]
]);

// Дочерний путь
Path::create([
    'name' => 'name',
    'full_path' => 'author.name',
    'data_type' => 'string',
    'cardinality' => 'one',
    'validation_rules' => [
        'required' => true,
        'pattern' => '/^[A-Z][a-z]+ [A-Z][a-z]+$/'
    ]
]);
```

**Результат валидации:**

```php
[
    'content_json.author' => ['required', 'array'],
    'content_json.author.name' => ['required', 'regex:/^[A-Z][a-z]+ [A-Z][a-z]+$/']
]
```

### Пример 3: Массивы (cardinality: many)

**Blueprint Paths:**

```php
// Родительский путь с cardinality: many
Path::create([
    'name' => 'contacts',
    'full_path' => 'author.contacts',
    'data_type' => 'array',
    'cardinality' => 'many',
    'validation_rules' => ['required' => true]
]);

// Дочерний путь
Path::create([
    'name' => 'phone',
    'full_path' => 'author.contacts.phone',
    'data_type' => 'string',
    'cardinality' => 'one',
    'validation_rules' => [
        'required' => true,
        'pattern' => '/^\+?[0-9]+$/',
        'distinct' => true
    ]
]);
```

**Результат валидации:**

```php
[
    'content_json.author.contacts' => ['required', 'array'],
    'content_json.author.*.contacts.*.phone' => ['required', 'regex:/^\+?[0-9]+$/', 'distinct']
]
```

**Пример данных:**

```json
{
    "author": {
        "contacts": [
            {"phone": "+1234567890"},
            {"phone": "+0987654321"}
        ]
    }
}
```

### Пример 4: Условная валидация

**Blueprint Path:**

```php
Path::create([
    'name' => 'published_at',
    'full_path' => 'published_at',
    'data_type' => 'datetime',
    'cardinality' => 'one',
    'validation_rules' => [
        'required_if' => [
            'field' => 'is_published',
            'value' => true,
            'operator' => '=='
        ]
    ]
]);
```

**Результат валидации:**

```php
[
    'content_json.published_at' => ['required_if:is_published,true']
]
```

### Пример 5: Сравнение полей

**Blueprint Paths:**

```php
Path::create([
    'name' => 'start_date',
    'full_path' => 'start_date',
    'data_type' => 'datetime',
    'cardinality' => 'one',
    'validation_rules' => ['required' => true]
]);

Path::create([
    'name' => 'end_date',
    'full_path' => 'end_date',
    'data_type' => 'datetime',
    'cardinality' => 'one',
    'validation_rules' => [
        'required' => true,
        'field_comparison' => [
            'operator' => '>=',
            'field' => 'content_json.start_date'
        ]
    ]
]);
```

**Результат валидации:**

```php
[
    'content_json.start_date' => ['required'],
    'content_json.end_date' => ['required', /* custom rule для сравнения */]
]
```

---

## Расширение системы

### Добавление нового типа правила

#### Шаг 1: Создать Rule класс

```php
// app/Domain/Blueprint/Validation/Rules/CustomRule.php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

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

#### Шаг 2: Добавить в RuleFactory

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

#### Шаг 3: Добавить обработку в PathValidationRulesConverter

```php
// app/Domain/Blueprint/Validation/PathValidationRulesConverter.php

foreach ($validationRules as $key => $value) {
    match ($key) {
        // ... существующие правила
        'custom' => $rules[] = $this->ruleFactory->createCustomRule($value),
        default => throw new \InvalidArgumentException("Неизвестное правило валидации: {$key}"),
    };
}
```

#### Шаг 4: Создать Handler

```php
// app/Domain/Blueprint/Validation/Rules/Handlers/CustomRuleHandler.php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\CustomRule;
use App\Domain\Blueprint\Validation\Rules\Rule;

final class CustomRuleHandler implements RuleHandlerInterface
{
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'custom';
    }

    public function handle(Rule $rule): array
    {
        if (! $rule instanceof CustomRule) {
            throw new \InvalidArgumentException('Expected CustomRule instance');
        }

        $value = $rule->getParams()['value'];
        
        return ["custom:{$value}"];
    }
}
```

#### Шаг 5: Зарегистрировать Handler

```php
// app/Providers/AppServiceProvider.php

$this->app->singleton(RuleHandlerRegistry::class, function () {
    $registry = new RuleHandlerRegistry();
    
    // ... существующие handlers
    $registry->register('custom', new CustomRuleHandler());
    
    return $registry;
});
```

### Добавление нового типа данных

Если нужно добавить поддержку нового типа данных (например, `email`, `url`), необходимо:

1. Добавить константу в `ValidationConstants`:

```php
public const DATA_TYPE_EMAIL = 'email';
```

2. Добавить маппинг в `DataTypeMapper`:

```php
public function toLaravelRule(string $dataType): ?string
{
    return match ($dataType) {
        // ... существующие типы
        ValidationConstants::DATA_TYPE_EMAIL => 'email',
        default => null,
    };
}
```

3. Обновить документацию и тесты

---

## Константы и утилиты

### ValidationConstants

**Путь:** `app/Domain/Blueprint/Validation/ValidationConstants.php`

**Основные константы:**

- `RULE_REQUIRED` — `'required'`
- `RULE_NULLABLE` — `'nullable'`
- `RULE_ARRAY` — `'array'`
- `CARDINALITY_ONE` — `'one'`
- `CARDINALITY_MANY` — `'many'`
- `CONTENT_JSON_PREFIX` — `'content_json.'`
- `ARRAY_ELEMENT_WILDCARD` — `'.*'`

**Типы данных:**

- `DATA_TYPE_STRING` — `'string'`
- `DATA_TYPE_TEXT` — `'text'`
- `DATA_TYPE_INT` — `'int'`
- `DATA_TYPE_FLOAT` — `'float'`
- `DATA_TYPE_BOOL` — `'bool'`
- `DATA_TYPE_DATETIME` — `'datetime'`
- `DATA_TYPE_JSON` — `'json'`
- `DATA_TYPE_REF` — `'ref'`
- `DATA_TYPE_ARRAY` — `'array'`

### DataTypeMapper

**Путь:** `app/Domain/Blueprint/Validation/DataTypeMapper.php`

**Назначение:** Преобразует типы данных Path в правила валидации Laravel.

**Методы:**

- `toLaravelRule(string $dataType): ?string` — преобразует тип данных в Laravel правило
- `isArrayType(string $dataType): bool` — проверяет, является ли тип массивом
- `isJsonType(string $dataType): bool` — проверяет, является ли тип JSON

**Маппинг:**

- `string`, `text` → `'string'`
- `int` → `'integer'`
- `float` → `'numeric'`
- `bool` → `'boolean'`
- `datetime` → `'date'`
- `json`, `array` → `'array'`
- `ref` → `'integer'`

### RuleArrayManipulator

**Путь:** `app/Domain/Blueprint/Validation/RuleArrayManipulator.php`

**Назначение:** Утилиты для манипуляции массивами правил валидации.

**Методы:**

- `insertAfterRequired(array &$rules, string|object $ruleToInsert): void` — вставляет правило после required/nullable
- `ensureArrayRule(array &$rules): void` — добавляет правило `'array'`, если его нет
- `mergeRules(array $existing, array $new): array` — объединяет два массива правил

---

## Обработка ошибок

### ValidationError

**Путь:** `app/Domain/Blueprint/Validation/ValidationError.php`

**Назначение:** Структурированная информация об ошибке валидации.

**Поля:**

- `field` — путь поля в точечной нотации
- `code` — код ошибки (например, `'BLUEPRINT_REQUIRED'`)
- `params` — параметры ошибки (например, `['min' => 1, 'max' => 500]`)
- `message` — текстовое сообщение (опционально)
- `pathId` — ID Path из Blueprint (опционально)

### ValidationResult

**Путь:** `app/Domain/Blueprint/Validation/ValidationResult.php`

**Назначение:** Результат валидации с ошибками, сгруппированными по полям.

**Методы:**

- `addError(string $field, ValidationError $error): void` — добавляет ошибку для поля
- `hasErrors(): bool` — проверяет, есть ли ошибки
- `getErrors(): array` — получает все ошибки
- `getErrorsForField(string $field): array` — получает ошибки для конкретного поля
- `getFieldsWithErrors(): array` — получает список полей с ошибками

---

## Тестирование

### Примеры тестов

Тесты для системы валидации находятся в `tests/`:

- `tests/Unit/Rules/` — тесты для правил валидации
- `tests/Feature/` — тесты для интеграции с HTTP запросами

### Запуск тестов

```bash
php artisan test
```

---

## Заключение

Система валидации Blueprint данных предоставляет гибкий и расширяемый механизм для автоматической валидации содержимого записей на основе структуры Blueprint. Система разделена на доменный слой (независимый от Laravel) и слой адаптации к Laravel, что обеспечивает чистую архитектуру и возможность переиспользования компонентов.

---

**Дата создания:** 2025-12-01  
**Версия:** 1.0

