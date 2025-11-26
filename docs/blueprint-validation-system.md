# Система валидации Blueprint

Подробное описание архитектуры и взаимодействия компонентов системы валидации контента Entry на основе Blueprint.

## Содержание

1. [Обзор системы](#обзор-системы)
2. [Архитектура](#архитектура)
3. [Компоненты системы](#компоненты-системы)
4. [Последовательность работы](#последовательность-работы)
5. [Детальное описание компонентов](#детальное-описание-компонентов)
6. [Примеры использования](#примеры-использования)
7. [Расширение системы](#расширение-системы)

---

## Обзор системы

Система валидации Blueprint предназначена для динамической валидации поля `content_json` модели `Entry` на основе структуры `Path` в связанном `Blueprint`.

### Основные принципы

- **Доменная независимость**: правила валидации представлены в виде доменных объектов, независимых от Laravel
- **Адаптация к Laravel**: доменные правила преобразуются в Laravel правила через адаптер
- **Расширяемость**: новые типы правил можно добавлять через систему handlers
- **Кэширование**: правила валидации кэшируются для оптимизации производительности

### Ключевые сущности

- **Blueprint**: шаблон структуры данных для Entry
- **Path**: поле внутри Blueprint с метаданными (data_type, cardinality, validation_rules)
- **Entry**: запись контента с полем `content_json`, которое валидируется по правилам Blueprint

---

## Архитектура

Система построена по принципу многослойной архитектуры:

```
┌─────────────────────────────────────────────────────────────┐
│                    FormRequest Layer                         │
│  (StoreEntryRequest, UpdateEntryRequest)                    │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│              BlueprintContentValidator                       │
│  (кэширование, координация)                                 │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│            EntryValidationService                            │
│  (построение доменного RuleSet)                             │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│        PathValidationRulesConverter                          │
│  (преобразование validation_rules → Rule объекты)            │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│                  RuleFactory                                │
│  (создание доменных Rule объектов)                         │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│              LaravelValidationAdapter                       │
│  (преобразование RuleSet → Laravel правила)                │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│            RuleHandlerRegistry                              │
│  (реестр handlers для преобразования правил)              │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│              Rule Handlers                                  │
│  (RequiredRuleHandler, MinRuleHandler, ...)                │
└─────────────────────────────────────────────────────────────┘
```

---

## Компоненты системы

### 1. Уровень FormRequest

**Файлы:**
- `app/Http/Requests/Admin/StoreEntryRequest.php`
- `app/Http/Requests/Admin/UpdateEntryRequest.php`

**Ответственность:**
- Получение Blueprint из PostType
- Вызов доменных сервисов для построения правил
- Добавление правил к Laravel валидатору

### 2. Уровень валидатора контента

**Файлы:**
- `app/Domain/Blueprint/Validation/BlueprintContentValidator.php`
- `app/Domain/Blueprint/Validation/BlueprintContentValidatorInterface.php`

**Ответственность:**
- Кэширование правил валидации
- Координация работы EntryValidationService и LaravelValidationAdapter
- Добавление базовых типов данных и правил для массивов

### 3. Уровень доменного сервиса

**Файлы:**
- `app/Domain/Blueprint/Validation/EntryValidationService.php`
- `app/Domain/Blueprint/Validation/EntryValidationServiceInterface.php`

**Ответственность:**
- Построение RuleSet из Path в Blueprint
- Обработка cardinality (one/many)
- Преобразование full_path в точечную нотацию

### 4. Уровень конвертера правил

**Файлы:**
- `app/Domain/Blueprint/Validation/PathValidationRulesConverter.php`
- `app/Domain/Blueprint/Validation/PathValidationRulesConverterInterface.php`

**Ответственность:**
- Преобразование validation_rules из Path в доменные Rule объекты
- Обработка различных форматов правил (min, max, pattern, conditional и т.д.)
- Учет data_type и cardinality

### 5. Уровень фабрики правил

**Файлы:**
- `app/Domain/Blueprint/Validation/Rules/RuleFactory.php`
- `app/Domain/Blueprint/Validation/Rules/RuleFactoryImpl.php`

**Ответственность:**
- Создание доменных Rule объектов
- Инкапсуляция логики создания правил

### 6. Уровень доменных правил

**Файлы:**
- `app/Domain/Blueprint/Validation/Rules/Rule.php` (интерфейс)
- `app/Domain/Blueprint/Validation/Rules/RequiredRule.php`
- `app/Domain/Blueprint/Validation/Rules/MinRule.php`
- `app/Domain/Blueprint/Validation/Rules/MaxRule.php`
- `app/Domain/Blueprint/Validation/Rules/PatternRule.php`
- `app/Domain/Blueprint/Validation/Rules/NullableRule.php`
- `app/Domain/Blueprint/Validation/Rules/ArrayMinItemsRule.php`
- `app/Domain/Blueprint/Validation/Rules/ArrayMaxItemsRule.php`
- `app/Domain/Blueprint/Validation/Rules/ArrayUniqueRule.php`
- `app/Domain/Blueprint/Validation/Rules/ConditionalRule.php`
- `app/Domain/Blueprint/Validation/Rules/UniqueRule.php`
- `app/Domain/Blueprint/Validation/Rules/ExistsRule.php`
- `app/Domain/Blueprint/Validation/Rules/FieldComparisonRule.php`

**Ответственность:**
- Представление правил валидации в виде доменных объектов
- Хранение параметров правил

### 7. Уровень набора правил

**Файлы:**
- `app/Domain/Blueprint/Validation/Rules/RuleSet.php`

**Ответственность:**
- Хранение правил, сгруппированных по путям полей
- Предоставление API для работы с правилами

### 8. Уровень адаптера

**Файлы:**
- `app/Domain/Blueprint/Validation/Adapters/LaravelValidationAdapter.php`
- `app/Domain/Blueprint/Validation/Adapters/LaravelValidationAdapterInterface.php`

**Ответственность:**
- Преобразование доменного RuleSet в массив Laravel правил
- Добавление базовых типов данных (string, integer, numeric и т.д.)

### 9. Уровень реестра handlers

**Файлы:**
- `app/Domain/Blueprint/Validation/Rules/Handlers/RuleHandlerRegistry.php`

**Ответственность:**
- Регистрация и получение handlers для различных типов правил

### 10. Уровень handlers

**Файлы:**
- `app/Domain/Blueprint/Validation/Rules/Handlers/RuleHandlerInterface.php`
- `app/Domain/Blueprint/Validation/Rules/Handlers/RequiredRuleHandler.php`
- `app/Domain/Blueprint/Validation/Rules/Handlers/MinRuleHandler.php`
- `app/Domain/Blueprint/Validation/Rules/Handlers/MaxRuleHandler.php`
- `app/Domain/Blueprint/Validation/Rules/Handlers/PatternRuleHandler.php`
- `app/Domain/Blueprint/Validation/Rules/Handlers/NullableRuleHandler.php`
- `app/Domain/Blueprint/Validation/Rules/Handlers/ArrayMinItemsRuleHandler.php`
- `app/Domain/Blueprint/Validation/Rules/Handlers/ArrayMaxItemsRuleHandler.php`
- `app/Domain/Blueprint/Validation/Rules/Handlers/ArrayUniqueRuleHandler.php`
- `app/Domain/Blueprint/Validation/Rules/Handlers/ConditionalRuleHandler.php`
- `app/Domain/Blueprint/Validation/Rules/Handlers/UniqueRuleHandler.php`
- `app/Domain/Blueprint/Validation/Rules/Handlers/ExistsRuleHandler.php`
- `app/Domain/Blueprint/Validation/Rules/Handlers/FieldComparisonRuleHandler.php`

**Ответственность:**
- Преобразование доменных Rule объектов в строки Laravel правил
- Обработка специфичной логики для каждого типа правила

---

## Последовательность работы

### Шаг 1: Запрос валидации в FormRequest

Когда приходит HTTP-запрос на создание/обновление Entry, Laravel вызывает метод `rules()` в FormRequest (`StoreEntryRequest` или `UpdateEntryRequest`).

**Код:**
```php
// app/Http/Requests/Admin/StoreEntryRequest.php
public function rules(): array
{
    return [
        'post_type' => 'required|string|exists:post_types,slug',
        'title' => 'required|string|max:500',
        'content_json' => ['nullable', 'array'],
        // ...
    ];
}
```

### Шаг 2: Настройка валидатора

Laravel вызывает метод `withValidator()` в FormRequest, где добавляются динамические правила из Blueprint.

**Код:**
```php
// app/Http/Requests/Admin/StoreEntryRequest.php
public function withValidator(Validator $validator): void
{
    $this->addBlueprintValidationRules($validator);
    // ...
}
```

### Шаг 3: Получение Blueprint

FormRequest получает PostType и связанный с ним Blueprint.

**Код:**
```php
// app/Http/Requests/Admin/StoreEntryRequest.php
private function addBlueprintValidationRules(Validator $validator): void
{
    $postType = PostType::query()
        ->with('blueprint')
        ->where('slug', $postTypeSlug)
        ->first();

    if (! $postType || ! $postType->blueprint) {
        return;
    }
    // ...
}
```

### Шаг 4: Построение RuleSet

FormRequest использует `EntryValidationService` для построения доменного RuleSet.

**Код:**
```php
// app/Http/Requests/Admin/StoreEntryRequest.php
$validationService = app(EntryValidationServiceInterface::class);
$ruleSet = $validationService->buildRulesFor($postType->blueprint);
```

**Внутри EntryValidationService:**

1. Загружаются все Path из Blueprint:
```php
// app/Domain/Blueprint/Validation/EntryValidationService.php
$paths = $blueprint->paths()
    ->select(['id', 'name', 'full_path', 'data_type', 'cardinality', 'is_required', 'validation_rules'])
    ->orderByRaw('LENGTH(full_path), full_path')
    ->get();
```

2. Для каждого Path:
   - Преобразуется `full_path` в путь для валидации: `'content_json.' . $fullPath`
   - Если `cardinality === 'many'`:
     - Добавляются правила для самого массива (required/nullable, array_min_items, array_max_items)
     - Добавляются правила для элементов массива (через `PathValidationRulesConverter`)
   - Если `cardinality === 'one'`:
     - Добавляются правила для поля (через `PathValidationRulesConverter`)

3. `PathValidationRulesConverter` преобразует `validation_rules` в доменные Rule объекты:
   - Добавляет `RequiredRule` или `NullableRule` (для cardinality: 'one')
   - Обрабатывает правила: min, max, pattern, array_min_items, array_max_items, array_unique, conditional, unique, exists, field_comparison
   - Создает Rule объекты через `RuleFactory`

### Шаг 5: Преобразование в Laravel правила

FormRequest использует `LaravelValidationAdapter` для преобразования RuleSet в Laravel правила.

**Код:**
```php
// app/Http/Requests/Admin/StoreEntryRequest.php
$adapter = app(LaravelValidationAdapterInterface::class);
$laravelRules = $adapter->adapt($ruleSet, $dataTypes);
```

**Внутри LaravelValidationAdapter:**

1. Для каждого поля в RuleSet:
   - Получаются все Rule объекты для поля
   - Для каждого Rule:
     - Определяется тип правила через `getType()`
     - Получается handler из `RuleHandlerRegistry`
     - Handler преобразует Rule в массив строк Laravel правил
   - Добавляется базовый тип данных (string, integer, numeric и т.д.) на основе dataType

2. Handlers преобразуют Rule объекты:
   - `RequiredRule` → `['required']`
   - `MinRule` → `['min:5']` (для строк) или `['min:10']` (для чисел)
   - `MaxRule` → `['max:500']` (для строк) или `['max:100']` (для чисел)
   - `PatternRule` → `['regex:/pattern/']`
   - `ArrayMinItemsRule` → `['min:2']`
   - `ArrayMaxItemsRule` → `['max:10']`
   - `ArrayUniqueRule` → `['distinct']`
   - `ConditionalRule` → `['required_if:field,value']`
   - `UniqueRule` → `['unique:table,column']`
   - `ExistsRule` → `['exists:table,column']`
   - `FieldComparisonRule` → `[new FieldComparison(...)]`

### Шаг 6: Добавление правил для массивов

FormRequest добавляет правило `'array'` для полей с `cardinality: 'many'`.

**Код:**
```php
// app/Http/Requests/Admin/StoreEntryRequest.php
$this->addArrayRulesForManyFields($laravelRules, $postType->blueprint);
```

### Шаг 7: Применение правил к валидатору

FormRequest добавляет все правила к Laravel валидатору.

**Код:**
```php
// app/Http/Requests/Admin/StoreEntryRequest.php
foreach ($laravelRules as $field => $rules) {
    $validator->addRules([$field => $rules]);
}
```

### Шаг 8: Валидация данных

Laravel выполняет валидацию данных по всем правилам, включая правила из Blueprint.

---

## Детальное описание компонентов

### EntryValidationService

**Назначение:** Построение доменного RuleSet из Blueprint.

**Методы:**

- `buildRulesFor(Blueprint $blueprint): RuleSet` - основной метод построения правил

**Логика работы:**

1. Загружает все Path из Blueprint, отсортированные по длине и значению `full_path`
2. Для каждого Path:
   - Преобразует `full_path` в путь для валидации: `'content_json.' . $fullPath`
   - Если `cardinality === 'many'`:
     - Добавляет `RequiredRule` или `NullableRule` для самого массива
     - Обрабатывает правила для массива (`array_min_items`, `array_max_items`, `array_unique`)
     - Создает правила для элементов массива (путь: `$fieldPath . '.*'`)
   - Если `cardinality === 'one'`:
     - Создает правила для поля через `PathValidationRulesConverter`

**Особенности:**

- Для полей с `cardinality: 'many'` правила разделяются:
  - Правила для массива (required/nullable, array_min_items, array_max_items)
  - Правила для элементов массива (min, max, pattern и т.д.)

### PathValidationRulesConverter

**Назначение:** Преобразование `validation_rules` из Path в доменные Rule объекты.

**Методы:**

- `convert(?array $validationRules, string $dataType, bool $isRequired, string $cardinality): array` - основной метод преобразования

**Поддерживаемые правила:**

1. **required/nullable**: Добавляется на основе `isRequired` (только для `cardinality: 'one'`)
2. **min**: Минимальное значение/длина
3. **max**: Максимальное значение/длина
4. **pattern**: Регулярное выражение
5. **array_min_items**: Минимальное количество элементов массива (только для `cardinality: 'many'`)
6. **array_max_items**: Максимальное количество элементов массива (только для `cardinality: 'many'`)
7. **array_unique**: Уникальность элементов массива (только для `cardinality: 'many'`)
8. **required_if**: Условное правило (поле обязательно, если другое поле имеет значение)
9. **prohibited_unless**: Условное правило (поле запрещено, если другое поле не имеет значения)
10. **required_unless**: Условное правило (поле обязательно, если другое поле не имеет значения)
11. **prohibited_if**: Условное правило (поле запрещено, если другое поле имеет значение)
12. **unique**: Уникальность значения в таблице
13. **exists**: Существование значения в таблице
14. **field_comparison**: Сравнение поля с другим полем или константой

**Форматы правил:**

**min/max:**
```php
'validation_rules' => [
    'min' => 5,
    'max' => 500,
]
```

**pattern:**
```php
'validation_rules' => [
    'pattern' => '/^[a-z]+$/',
]
```

**array_min_items/array_max_items:**
```php
'validation_rules' => [
    'array_min_items' => 2,
    'array_max_items' => 10,
]
```

**array_unique:**
```php
'validation_rules' => [
    'array_unique' => true,
]
```

**conditional (required_if):**
```php
// Простой формат (поле обязательно, если другое поле существует)
'validation_rules' => [
    'required_if' => 'is_published',
]

// Формат с значением (поле обязательно, если другое поле == value)
'validation_rules' => [
    'required_if' => ['field' => 'is_published', 'value' => true],
]

// Старый формат (совместимость)
'validation_rules' => [
    'required_if' => ['is_published' => true],
]

// С оператором
'validation_rules' => [
    'required_if' => ['field' => 'status', 'value' => 'active', 'operator' => '=='],
]
```

**unique:**
```php
// Простой формат
'validation_rules' => [
    'unique' => 'users',
]

// Расширенный формат
'validation_rules' => [
    'unique' => [
        'table' => 'users',
        'column' => 'email',
        'except' => ['column' => 'id', 'value' => 1],
        'where' => ['column' => 'status', 'value' => 'active'],
    ],
]
```

**exists:**
```php
// Простой формат
'validation_rules' => [
    'exists' => 'users',
]

// Расширенный формат
'validation_rules' => [
    'exists' => [
        'table' => 'users',
        'column' => 'id',
        'where' => ['column' => 'status', 'value' => 'active'],
    ],
]
```

**field_comparison:**
```php
// Сравнение с другим полем
'validation_rules' => [
    'field_comparison' => [
        'operator' => '>=',
        'field' => 'content_json.start_date',
    ],
]

// Сравнение с константой
'validation_rules' => [
    'field_comparison' => [
        'operator' => '>=',
        'value' => '2024-01-01',
    ],
]
```

### RuleFactory

**Назначение:** Создание доменных Rule объектов.

**Методы:**

- `createMinRule(mixed $value, string $dataType): MinRule`
- `createMaxRule(mixed $value, string $dataType): MaxRule`
- `createPatternRule(mixed $pattern): PatternRule`
- `createRequiredRule(): RequiredRule`
- `createNullableRule(): NullableRule`
- `createArrayMinItemsRule(int $value): ArrayMinItemsRule`
- `createArrayMaxItemsRule(int $value): ArrayMaxItemsRule`
- `createConditionalRule(string $type, string $field, mixed $value, ?string $operator = null): ConditionalRule`
- `createUniqueRule(...): UniqueRule`
- `createExistsRule(...): ExistsRule`
- `createArrayUniqueRule(): ArrayUniqueRule`
- `createFieldComparisonRule(string $operator, string $otherField, mixed $constantValue = null): FieldComparisonRule`

### RuleSet

**Назначение:** Хранение правил, сгруппированных по путям полей.

**Методы:**

- `addRule(string $fieldPath, Rule $rule): void` - добавить правило для поля
- `getRulesForField(string $fieldPath): array` - получить правила для поля
- `getAllRules(): array` - получить все правила
- `hasRulesForField(string $fieldPath): bool` - проверить наличие правил для поля
- `getFieldPaths(): array` - получить список всех путей полей
- `isEmpty(): bool` - проверить, пуст ли набор правил

**Структура данных:**

```php
[
    'content_json.title' => [
        RequiredRule,
        MinRule,
        MaxRule,
    ],
    'content_json.author.name' => [
        RequiredRule,
        PatternRule,
    ],
    'content_json.tags.*' => [
        MinRule,
        MaxRule,
    ],
]
```

### LaravelValidationAdapter

**Назначение:** Преобразование доменного RuleSet в массив Laravel правил.

**Методы:**

- `adapt(RuleSet $ruleSet, array $dataTypes = []): array` - основной метод преобразования

**Логика работы:**

1. Для каждого поля в RuleSet:
   - Получает все Rule объекты для поля
   - Для каждого Rule:
     - Определяет тип правила через `getType()`
     - Получает handler из `RuleHandlerRegistry`
     - Handler преобразует Rule в массив строк Laravel правил
   - Добавляет базовый тип данных (string, integer, numeric и т.д.) на основе `dataTypes`

2. Маппинг data_type → базовый тип Laravel:
   - `string`, `text` → `'string'`
   - `int` → `'integer'`
   - `float` → `'numeric'`
   - `bool` → `'boolean'`
   - `date`, `datetime` → `'date'`
   - `json` → `'array'`
   - `ref` → `'integer'`

**Порядок правил:**

Базовый тип вставляется после `required`/`nullable`, но перед остальными правилами:

```php
['required', 'string', 'min:5', 'max:500']
```

### RuleHandlerRegistry

**Назначение:** Регистрация и получение handlers для различных типов правил.

**Методы:**

- `register(string $ruleType, RuleHandlerInterface $handler): void` - зарегистрировать handler
- `getHandler(string $ruleType): ?RuleHandlerInterface` - получить handler для типа правила
- `hasHandler(string $ruleType): bool` - проверить наличие handler
- `getRegisteredTypes(): array` - получить список зарегистрированных типов

**Регистрация handlers:**

Handlers регистрируются в `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.php
$registry->register('required', new RequiredRuleHandler());
$registry->register('nullable', new NullableRuleHandler());
$registry->register('min', new MinRuleHandler());
// ...
```

### Rule Handlers

**Назначение:** Преобразование доменных Rule объектов в строки Laravel правил.

**Интерфейс:**

```php
interface RuleHandlerInterface
{
    public function supports(string $ruleType): bool;
    public function handle(Rule $rule, string $dataType): array;
}
```

**Handlers:**

1. **RequiredRuleHandler**: `RequiredRule` → `['required']`
2. **NullableRuleHandler**: `NullableRule` → `['nullable']`
3. **MinRuleHandler**: `MinRule` → `['min:5']` (для строк) или `['min:10']` (для чисел)
4. **MaxRuleHandler**: `MaxRule` → `['max:500']` (для строк) или `['max:100']` (для чисел)
5. **PatternRuleHandler**: `PatternRule` → `['regex:/pattern/']`
6. **ArrayMinItemsRuleHandler**: `ArrayMinItemsRule` → `['min:2']`
7. **ArrayMaxItemsRuleHandler**: `ArrayMaxItemsRule` → `['max:10']`
8. **ArrayUniqueRuleHandler**: `ArrayUniqueRule` → `['distinct']`
9. **ConditionalRuleHandler**: `ConditionalRule` → `['required_if:field,value']`
10. **UniqueRuleHandler**: `UniqueRule` → `['unique:table,column']`
11. **ExistsRuleHandler**: `ExistsRule` → `['exists:table,column']`
12. **FieldComparisonRuleHandler**: `FieldComparisonRule` → `[new FieldComparison(...)]`

**Особенности handlers:**

- **MinRuleHandler/MaxRuleHandler**: Учитывают `dataType` для определения формата значения (int/float для чисел, int для строк)
- **PatternRuleHandler**: Обрабатывает паттерны с ограничителями и без, экранирует слэши
- **ConditionalRuleHandler**: Поддерживает различные форматы значений (bool, null, array)
- **FieldComparisonRuleHandler**: Возвращает объект Laravel custom rule вместо строки

---

## Примеры использования

### Пример 1: Простое поле с валидацией

**Blueprint:**
- Path: `name = 'title'`, `full_path = 'title'`, `data_type = 'string'`, `cardinality = 'one'`, `is_required = true`
- `validation_rules = ['min' => 5, 'max' => 500]`

**Результат:**
```php
[
    'content_json.title' => ['required', 'string', 'min:5', 'max:500'],
]
```

### Пример 2: Поле с регулярным выражением

**Blueprint:**
- Path: `name = 'email'`, `full_path = 'email'`, `data_type = 'string'`, `cardinality = 'one'`, `is_required = true`
- `validation_rules = ['pattern' => '/^[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,}$/i']`

**Результат:**
```php
[
    'content_json.email' => ['required', 'string', 'regex:/^[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,}$/i'],
]
```

### Пример 3: Массив с валидацией элементов

**Blueprint:**
- Path: `name = 'tags'`, `full_path = 'tags'`, `data_type = 'string'`, `cardinality = 'many'`, `is_required = false`
- `validation_rules = ['array_min_items' => 2, 'array_max_items' => 10, 'min' => 3, 'max' => 50]`

**Результат:**
```php
[
    'content_json.tags' => ['nullable', 'array', 'min:2', 'max:10'],
    'content_json.tags.*' => ['string', 'min:3', 'max:50'],
]
```

### Пример 4: Условное правило

**Blueprint:**
- Path: `name = 'published_at'`, `full_path = 'published_at'`, `data_type = 'date'`, `cardinality = 'one'`, `is_required = false`
- `validation_rules = ['required_if' => 'is_published']`

**Результат:**
```php
[
    'content_json.published_at' => ['nullable', 'date', 'required_if:content_json.is_published,true'],
]
```

### Пример 5: Вложенное поле

**Blueprint:**
- Path: `name = 'author'`, `full_path = 'author'`, `data_type = 'json'`, `cardinality = 'one'`, `is_required = true`
- Path: `name = 'name'`, `full_path = 'author.name'`, `data_type = 'string'`, `cardinality = 'one'`, `is_required = true`
- Path: `name = 'email'`, `full_path = 'author.email'`, `data_type = 'string'`, `cardinality = 'one'`, `is_required = true`

**Результат:**
```php
[
    'content_json.author' => ['required', 'array'],
    'content_json.author.name' => ['required', 'string'],
    'content_json.author.email' => ['required', 'string'],
]
```

### Пример 6: Правило уникальности

**Blueprint:**
- Path: `name = 'slug'`, `full_path = 'slug'`, `data_type = 'string'`, `cardinality = 'one'`, `is_required = true`
- `validation_rules = ['unique' => ['table' => 'entries', 'column' => 'slug']]`

**Результат:**
```php
[
    'content_json.slug' => ['required', 'string', 'unique:entries,slug'],
]
```

### Пример 7: Правило существования

**Blueprint:**
- Path: `name = 'category_id'`, `full_path = 'category_id'`, `data_type = 'ref'`, `cardinality = 'one'`, `is_required = true`
- `validation_rules = ['exists' => ['table' => 'categories', 'column' => 'id']]`

**Результат:**
```php
[
    'content_json.category_id' => ['required', 'integer', 'exists:categories,id'],
]
```

### Пример 8: Сравнение полей

**Blueprint:**
- Path: `name = 'start_date'`, `full_path = 'start_date'`, `data_type = 'date'`, `cardinality = 'one'`, `is_required = true`
- Path: `name = 'end_date'`, `full_path = 'end_date'`, `data_type = 'date'`, `cardinality = 'one'`, `is_required = true`
- `validation_rules = ['field_comparison' => ['operator' => '>=', 'field' => 'content_json.start_date']]`

**Результат:**
```php
[
    'content_json.start_date' => ['required', 'date'],
    'content_json.end_date' => ['required', 'date', new FieldComparison('>=', 'content_json.start_date', null)],
]
```

---

## Расширение системы

### Добавление нового типа правила

1. **Создать доменное правило:**

```php
// app/Domain/Blueprint/Validation/Rules/CustomRule.php
final class CustomRule implements Rule
{
    public function getType(): string
    {
        return 'custom';
    }

    public function getParams(): array
    {
        return ['param' => $this->param];
    }
}
```

2. **Добавить метод в RuleFactory:**

```php
// app/Domain/Blueprint/Validation/Rules/RuleFactory.php
public function createCustomRule(mixed $param): CustomRule;
```

3. **Реализовать метод в RuleFactoryImpl:**

```php
// app/Domain/Blueprint/Validation/Rules/RuleFactoryImpl.php
public function createCustomRule(mixed $param): CustomRule
{
    return new CustomRule($param);
}
```

4. **Обработать правило в PathValidationRulesConverter:**

```php
// app/Domain/Blueprint/Validation/PathValidationRulesConverter.php
foreach ($validationRules as $key => $value) {
    match ($key) {
        'custom' => $this->handleCustomRule($rules, $value),
        // ...
    };
}
```

5. **Создать handler:**

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

        $params = $rule->getParams();
        return ["custom:{$params['param']}"];
    }
}
```

6. **Зарегистрировать handler в AppServiceProvider:**

```php
// app/Providers/AppServiceProvider.php
$registry->register('custom', new CustomRuleHandler());
```

### Добавление нового типа данных

1. **Добавить маппинг в LaravelValidationAdapter:**

```php
// app/Domain/Blueprint/Validation/Adapters/LaravelValidationAdapter.php
private function getBaseTypeForDataType(string $dataType): ?string
{
    return match ($dataType) {
        'custom_type' => 'string', // или другой базовый тип
        // ...
    };
}
```

2. **Добавить маппинг в BlueprintContentValidator (если используется):**

```php
// app/Domain/Blueprint/Validation/BlueprintContentValidator.php
private function getBaseTypeForDataType(string $dataType): ?string
{
    return match ($dataType) {
        'custom_type' => 'string',
        // ...
    };
}
```

---

## Кэширование

Система использует кэширование для оптимизации производительности.

**BlueprintContentValidator** кэширует правила валидации:

```php
// app/Domain/Blueprint/Validation/BlueprintContentValidator.php
$cacheKey = "blueprint:validation_rules:{$blueprint->id}";

return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($blueprint): array {
    // Построение правил
});
```

**TTL кэша:** 3600 секунд (1 час)

**Инвалидация кэша:**

Кэш инвалидируется при изменении структуры Path в Blueprint через метод `invalidateCache()`:

```php
$validator->invalidateCache($blueprint);
```

---

## Зависимости и регистрация

### Регистрация сервисов

Все сервисы регистрируются в `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.php

// PathValidationRulesConverter
$this->app->singleton(
    PathValidationRulesConverterInterface::class,
    PathValidationRulesConverter::class
);

// RuleFactory
$this->app->singleton(
    RuleFactory::class,
    RuleFactoryImpl::class
);

// EntryValidationService
$this->app->singleton(
    EntryValidationServiceInterface::class,
    EntryValidationService::class
);

// RuleHandlerRegistry
$this->app->singleton(RuleHandlerRegistry::class, function () {
    $registry = new RuleHandlerRegistry();
    // Регистрация handlers
    return $registry;
});

// LaravelValidationAdapter
$this->app->singleton(
    LaravelValidationAdapterInterface::class,
    LaravelValidationAdapter::class
);

// BlueprintContentValidator
$this->app->singleton(
    BlueprintContentValidatorInterface::class,
    BlueprintContentValidator::class
);
```

---

## Заключение

Система валидации Blueprint представляет собой многослойную архитектуру, которая:

1. **Разделяет ответственность**: каждый компонент отвечает за свою область
2. **Обеспечивает расширяемость**: новые типы правил можно легко добавлять
3. **Сохраняет независимость**: доменные правила не зависят от Laravel
4. **Оптимизирует производительность**: использует кэширование

Система позволяет динамически валидировать контент Entry на основе структуры Blueprint, обеспечивая гибкость и удобство использования.

