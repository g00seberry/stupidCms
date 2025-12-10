# План тестирования системы валидации Blueprint

## Структура тестов

```
tests/
├── Unit/
│   └── Domain/
│       └── Blueprint/
│           └── Validation/
│               ├── EntryValidationServiceTest.php
│               ├── FieldPathBuilderTest.php
│               ├── PathValidationRulesConverterTest.php
│               ├── RuleSetTest.php
│               ├── RuleFactoryImplTest.php
│               ├── Adapters/
│               │   └── LaravelValidationAdapterTest.php
│               └── Rules/
│                   ├── RequiredRuleTest.php
│                   ├── NullableRuleTest.php
│                   ├── MinRuleTest.php
│                   ├── MaxRuleTest.php
│                   ├── PatternRuleTest.php
│                   ├── DistinctRuleTest.php
│                   ├── ConditionalRuleTest.php
│                   ├── FieldComparisonRuleTest.php
│                   └── Handlers/
│                       ├── RequiredRuleHandlerTest.php
│                       ├── NullableRuleHandlerTest.php
│                       ├── MinRuleHandlerTest.php
│                       ├── MaxRuleHandlerTest.php
│                       ├── PatternRuleHandlerTest.php
│                       ├── DistinctRuleHandlerTest.php
│                       ├── ConditionalRuleHandlerTest.php
│                       ├── FieldComparisonRuleHandlerTest.php
│                       └── RuleHandlerRegistryTest.php
└── Feature/
    └── Api/
        └── Admin/
            └── Entries/
                ├── EntryBlueprintValidationTest.php (расширение существующих тестов)
```

---

## 1. Unit-тесты: EntryValidationService

**Файл:** `tests/Unit/Domain/Blueprint/Validation/EntryValidationServiceTest.php`

### 1.1. Базовые сценарии

-   ✅ `buildRulesFor()` возвращает пустой RuleSet для blueprint без paths
-   ✅ `buildRulesFor()` обрабатывает blueprint с одним простым path
-   ✅ `buildRulesFor()` обрабатывает blueprint с несколькими paths
-   ✅ `buildRulesFor()` загружает paths в правильном порядке (по длине full_path)

### 1.2. Преобразование путей

-   ✅ Правильно преобразует простой путь `title` → `data_json.title`
-   ✅ Правильно преобразует вложенный путь `author.name` → `data_json.author.name`
-   ✅ Правильно обрабатывает cardinality='many' для родительского пути
    -   `author.contacts.phone` с `author.cardinality='many'` → `data_json.*.contacts.phone`
-   ✅ Правильно обрабатывает множественные уровни массивов
    -   `items.tags.name` с `items.cardinality='many'` и `tags.cardinality='many'` → `data_json.*.*.name`
-   ✅ Правильно обрабатывает смешанные структуры (массивы и объекты)

### 1.3. Обработка validation_rules

-   ✅ Правильно преобразует validation_rules в Rule объекты
-   ✅ Правильно обрабатывает null validation_rules
-   ✅ Правильно обрабатывает пустой массив validation_rules
-   ✅ Правильно обрабатывает несколько правил для одного path

### 1.4. Интеграция с зависимостями

-   ✅ Использует FieldPathBuilder для построения путей
-   ✅ Использует PathValidationRulesConverter для преобразования правил
-   ✅ Правильно передаёт pathCardinalities в FieldPathBuilder

---

## 2. Unit-тесты: FieldPathBuilder

**Файл:** `tests/Unit/Domain/Blueprint/Validation/FieldPathBuilderTest.php`

### 2.1. Простые пути

-   ✅ `buildFieldPath()` добавляет префикс `data_json.` к простому пути
-   ✅ `buildFieldPath()` обрабатывает путь без префикса (пустая строка)

### 2.2. Вложенные пути

-   ✅ Правильно обрабатывает одноуровневую вложенность: `author.name` → `data_json.author.name`
-   ✅ Правильно обрабатывает многоуровневую вложенность: `author.contacts.phone` → `data_json.author.contacts.phone`

### 2.3. Обработка cardinality

-   ✅ Заменяет сегмент на `*` если родительский путь имеет `cardinality='many'`
    -   `author.contacts` с `author.cardinality='many'` → `data_json.*.contacts`
-   ✅ Правильно обрабатывает множественные уровни массивов
    -   `items.tags.name` с `items.cardinality='many'` и `tags.cardinality='many'` → `data_json.*.*.name`
-   ✅ Не заменяет сегмент если родительский путь имеет `cardinality='one'`
-   ✅ Правильно обрабатывает первый сегмент (нет родителя)

### 2.4. Граничные случаи

-   ✅ Обрабатывает путь с одним сегментом
-   ✅ Обрабатывает путь с максимальной вложенностью
-   ✅ Правильно работает с кастомным префиксом

---

## 3. Unit-тесты: PathValidationRulesConverter

**Файл:** `tests/Unit/Domain/Blueprint/Validation/PathValidationRulesConverterTest.php`

### 3.1. Базовые правила

-   ✅ `convert()` возвращает пустой массив для null validation_rules
-   ✅ `convert()` возвращает пустой массив для пустого массива
-   ✅ `convert()` создаёт RequiredRule для `required: true`
-   ✅ `convert()` создаёт NullableRule для `required: false`
-   ✅ `convert()` создаёт MinRule для `min: 5`
-   ✅ `convert()` создаёт MaxRule для `max: 100`
-   ✅ `convert()` создаёт PatternRule для `pattern: '/^test$/'`
-   ✅ `convert()` создаёт DistinctRule для `distinct: true`

### 3.2. Условные правила

-   ✅ Создаёт ConditionalRule для `required_if` с правильным форматом
-   ✅ Создаёт ConditionalRule для `prohibited_unless` с правильным форматом
-   ✅ Создаёт ConditionalRule для `required_unless` с правильным форматом
-   ✅ Создаёт ConditionalRule для `prohibited_if` с правильным форматом
-   ✅ Выбрасывает исключение для условного правила без поля 'field'
-   ✅ Выбрасывает исключение для условного правила с неверным форматом (не массив)
-   ✅ Правильно обрабатывает оператор сравнения (по умолчанию '==')

### 3.3. Правило field_comparison

-   ✅ Создаёт FieldComparisonRule для сравнения с полем
-   ✅ Создаёт FieldComparisonRule для сравнения с константой
-   ✅ Правильно обрабатывает приоритет поля над константой
-   ✅ Игнорирует неверный формат (не массив)

### 3.4. Комбинации правил

-   ✅ Правильно обрабатывает несколько правил одновременно
-   ✅ Правильно обрабатывает все типы правил вместе

### 3.5. Ошибки

-   ✅ Выбрасывает InvalidValidationRuleException для неизвестного правила
-   ✅ Выбрасывает InvalidValidationRuleException с правильным сообщением

---

## 4. Unit-тесты: RuleSet

**Файл:** `tests/Unit/Domain/Blueprint/Validation/RuleSetTest.php`

### 4.1. Добавление правил

-   ✅ `addRule()` добавляет правило для нового поля
-   ✅ `addRule()` добавляет несколько правил для одного поля
-   ✅ `addRule()` добавляет правила для разных полей

### 4.2. Получение правил

-   ✅ `getRulesForField()` возвращает правила для существующего поля
-   ✅ `getRulesForField()` возвращает пустой массив для несуществующего поля
-   ✅ `getAllRules()` возвращает все правила
-   ✅ `hasRulesForField()` возвращает true для поля с правилами
-   ✅ `hasRulesForField()` возвращает false для поля без правил

### 4.3. Утилиты

-   ✅ `getFieldPaths()` возвращает список всех путей полей
-   ✅ `isEmpty()` возвращает true для пустого RuleSet
-   ✅ `isEmpty()` возвращает false для RuleSet с правилами

---

## 5. Unit-тесты: RuleFactoryImpl

**Файл:** `tests/Unit/Domain/Blueprint/Validation/RuleFactoryImplTest.php`

### 5.1. Создание правил

-   ✅ `createMinRule()` создаёт MinRule с правильным значением
-   ✅ `createMaxRule()` создаёт MaxRule с правильным значением
-   ✅ `createPatternRule()` создаёт PatternRule с правильным паттерном
-   ✅ `createPatternRule()` обрабатывает пустую строку (возвращает '.\*')
-   ✅ `createPatternRule()` обрабатывает не-строку (возвращает '.\*')
-   ✅ `createRequiredRule()` создаёт RequiredRule
-   ✅ `createNullableRule()` создаёт NullableRule
-   ✅ `createConditionalRule()` создаёт ConditionalRule с правильными параметрами
-   ✅ `createDistinctRule()` создаёт DistinctRule
-   ✅ `createFieldComparisonRule()` создаёт FieldComparisonRule с полем
-   ✅ `createFieldComparisonRule()` создаёт FieldComparisonRule с константой

---

## 6. Unit-тесты: Rule классы

### 6.1. RequiredRule

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/RequiredRuleTest.php`

-   ✅ `getType()` возвращает 'required'
-   ✅ `getParams()` возвращает пустой массив

### 6.2. NullableRule

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/NullableRuleTest.php`

-   ✅ `getType()` возвращает 'nullable'
-   ✅ `getParams()` возвращает пустой массив

### 6.3. MinRule

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/MinRuleTest.php`

-   ✅ `getType()` возвращает 'min'
-   ✅ `getParams()` возвращает массив с 'value'
-   ✅ `getValue()` возвращает переданное значение
-   ✅ Правильно хранит числовые значения
-   ✅ Правильно хранит строковые значения

### 6.4. MaxRule

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/MaxRuleTest.php`

-   ✅ `getType()` возвращает 'max'
-   ✅ `getParams()` возвращает массив с 'value'
-   ✅ `getValue()` возвращает переданное значение

### 6.5. PatternRule

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/PatternRuleTest.php`

-   ✅ `getType()` возвращает 'pattern'
-   ✅ `getParams()` возвращает массив с 'pattern'
-   ✅ `getPattern()` возвращает переданный паттерн
-   ✅ Правильно хранит регулярные выражения

### 6.6. DistinctRule

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/DistinctRuleTest.php`

-   ✅ `getType()` возвращает 'distinct'
-   ✅ `getParams()` возвращает пустой массив

### 6.7. ConditionalRule

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/ConditionalRuleTest.php`

-   ✅ `getType()` возвращает переданный тип
-   ✅ `getParams()` возвращает массив с 'field', 'value', 'operator'
-   ✅ `getField()` возвращает путь к полю
-   ✅ `getValue()` возвращает значение условия
-   ✅ `getOperator()` возвращает оператор (по умолчанию '==')
-   ✅ Правильно обрабатывает все типы: required_if, prohibited_unless, required_unless, prohibited_if

### 6.8. FieldComparisonRule

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/FieldComparisonRuleTest.php`

-   ✅ `getType()` возвращает 'field_comparison'
-   ✅ `getParams()` возвращает массив с 'operator', 'other_field', 'constant_value'
-   ✅ `getOperator()` возвращает оператор
-   ✅ `getOtherField()` возвращает путь к другому полю
-   ✅ `getConstantValue()` возвращает константное значение
-   ✅ Правильно обрабатывает сравнение с полем
-   ✅ Правильно обрабатывает сравнение с константой

---

## 7. Unit-тесты: RuleHandler классы

### 7.1. RequiredRuleHandler

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/Handlers/RequiredRuleHandlerTest.php`

-   ✅ `supports()` возвращает true для 'required'
-   ✅ `supports()` возвращает false для других типов
-   ✅ `handle()` возвращает ['required'] для RequiredRule
-   ✅ `handle()` выбрасывает исключение для неправильного типа правила

### 7.2. NullableRuleHandler

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/Handlers/NullableRuleHandlerTest.php`

-   ✅ `supports()` возвращает true для 'nullable'
-   ✅ `supports()` возвращает false для других типов
-   ✅ `handle()` возвращает ['nullable'] для NullableRule
-   ✅ `handle()` выбрасывает исключение для неправильного типа правила

### 7.3. MinRuleHandler

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/Handlers/MinRuleHandlerTest.php`

-   ✅ `supports()` возвращает true для 'min'
-   ✅ `supports()` возвращает false для других типов
-   ✅ `handle()` возвращает ['min:5'] для MinRule(5)
-   ✅ `handle()` обрабатывает float значения
-   ✅ `handle()` обрабатывает int значения
-   ✅ `handle()` возвращает ['min:0'] для не-числовых значений
-   ✅ `handle()` выбрасывает исключение для неправильного типа правила

### 7.4. MaxRuleHandler

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/Handlers/MaxRuleHandlerTest.php`

-   ✅ `supports()` возвращает true для 'max'
-   ✅ `supports()` возвращает false для других типов
-   ✅ `handle()` возвращает ['max:100'] для MaxRule(100)
-   ✅ `handle()` обрабатывает float значения
-   ✅ `handle()` обрабатывает int значения
-   ✅ `handle()` возвращает ['max:0'] для не-числовых значений
-   ✅ `handle()` выбрасывает исключение для неправильного типа правила

### 7.5. PatternRuleHandler

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/Handlers/PatternRuleHandlerTest.php`

-   ✅ `supports()` возвращает true для 'pattern'
-   ✅ `supports()` возвращает false для других типов
-   ✅ `handle()` возвращает ['regex:/^test$/'] для PatternRule('/^test$/')
-   ✅ `handle()` правильно экранирует паттерн
-   ✅ `handle()` выбрасывает исключение для неправильного типа правила

### 7.6. DistinctRuleHandler

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/Handlers/DistinctRuleHandlerTest.php`

-   ✅ `supports()` возвращает true для 'distinct'
-   ✅ `supports()` возвращает false для других типов
-   ✅ `handle()` возвращает ['distinct'] для DistinctRule
-   ✅ `handle()` выбрасывает исключение для неправильного типа правила

### 7.7. ConditionalRuleHandler

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/Handlers/ConditionalRuleHandlerTest.php`

-   ✅ `supports()` возвращает true для 'required_if'
-   ✅ `supports()` возвращает true для 'prohibited_unless'
-   ✅ `supports()` возвращает true для 'required_unless'
-   ✅ `supports()` возвращает true для 'prohibited_if'
-   ✅ `supports()` возвращает false для других типов
-   ✅ `handle()` возвращает правильное Laravel правило для required_if
-   ✅ `handle()` возвращает правильное Laravel правило для prohibited_unless
-   ✅ `handle()` возвращает правильное Laravel правило для required_unless
-   ✅ `handle()` возвращает правильное Laravel правило для prohibited_if
-   ✅ `handle()` правильно обрабатывает оператор сравнения
-   ✅ `handle()` выбрасывает исключение для неправильного типа правила

### 7.8. FieldComparisonRuleHandler

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/Handlers/FieldComparisonRuleHandlerTest.php`

-   ✅ `supports()` возвращает true для 'field_comparison'
-   ✅ `supports()` возвращает false для других типов
-   ✅ `handle()` возвращает правильное Laravel правило для сравнения с полем
-   ✅ `handle()` возвращает правильное Laravel правило для сравнения с константой
-   ✅ `handle()` правильно обрабатывает все операторы (>=, <=, >, <, ==, !=)
-   ✅ `handle()` выбрасывает исключение для неправильного типа правила

### 7.9. RuleHandlerRegistry

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/Handlers/RuleHandlerRegistryTest.php`

-   ✅ `register()` регистрирует handler для типа правила
-   ✅ `getHandler()` возвращает зарегистрированный handler
-   ✅ `getHandler()` возвращает null для незарегистрированного типа
-   ✅ `hasHandler()` возвращает true для зарегистрированного типа
-   ✅ `hasHandler()` возвращает false для незарегистрированного типа
-   ✅ `getRegisteredTypes()` возвращает список всех зарегистрированных типов
-   ✅ Перезапись handler для того же типа правила

---

## 8. Unit-тесты: LaravelValidationAdapter

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Adapters/LaravelValidationAdapterTest.php`

### 8.1. Базовое преобразование

-   ✅ `adapt()` возвращает пустой массив для пустого RuleSet
-   ✅ `adapt()` преобразует одно правило для одного поля
-   ✅ `adapt()` преобразует несколько правил для одного поля
-   ✅ `adapt()` преобразует правила для нескольких полей

### 8.2. Интеграция с handlers

-   ✅ Использует RuleHandlerRegistry для получения handlers
-   ✅ Выбрасывает исключение если handler не найден
-   ✅ Правильно вызывает handle() для каждого правила

### 8.3. Формат результата

-   ✅ Возвращает массив с ключами - путями полей
-   ✅ Возвращает массивы строк Laravel правил в качестве значений
-   ✅ Правильно объединяет результаты от нескольких handlers

---

## 9. Feature-тесты: Интеграция с FormRequest

**Файл:** `tests/Feature/Api/Admin/Entries/EntryBlueprintValidationTest.php`

### 9.1. Базовые сценарии

-   ✅ Создание Entry с валидным data_json по правилам Blueprint
-   ✅ Создание Entry с невалидным data_json (не проходит валидацию)
-   ✅ Обновление Entry с валидным data_json
-   ✅ Обновление Entry с невалидным data_json

### 9.2. Правила валидации

-   ✅ Required правило: поле обязательно
-   ✅ Min правило: минимальная длина/значение
-   ✅ Max правило: максимальная длина/значение
-   ✅ Pattern правило: регулярное выражение
-   ✅ Distinct правило: уникальность элементов массива

### 9.3. Условная валидация

-   ✅ Required_if: поле обязательно при условии
-   ✅ Prohibited_unless: поле запрещено если условие не выполнено
-   ✅ Required_unless: поле обязательно если условие не выполнено
-   ✅ Prohibited_if: поле запрещено при условии

### 9.4. Сравнение полей

-   ✅ Field_comparison: сравнение с другим полем
-   ✅ Field_comparison: сравнение с константой

### 9.5. Вложенные структуры

-   ✅ Валидация простых вложенных полей
-   ✅ Валидация массивов (cardinality='many')
-   ✅ Валидация многоуровневых массивов
-   ✅ Валидация смешанных структур

### 9.6. Граничные случаи

-   ✅ Entry без Blueprint (не применяются правила)
-   ✅ Blueprint без paths (не применяются правила)
-   ✅ Path без validation_rules (не применяются правила)
-   ✅ Пустой data_json
-   ✅ Отсутствие data_json в запросе

---

## 10. Unit-тесты: Суперсложные структуры

### 10.1. EntryValidationService: Глубоко вложенные структуры

**Файл:** `tests/Unit/Domain/Blueprint/Validation/EntryValidationServiceTest.php` (расширение)

#### 10.1.1. Многоуровневая вложенность (5+ уровней)

-   ✅ Правильно обрабатывает путь `level1.level2.level3.level4.level5.field` → `data_json.level1.level2.level3.level4.level5.field`
-   ✅ Правильно обрабатывает путь с массивами на разных уровнях:
    -   `items[].variants[].options[].values[].name` → `data_json.*.*.*.*.name`
    -   Где `items`, `variants`, `options`, `values` имеют `cardinality='many'`
-   ✅ Правильно обрабатывает смешанную структуру:
    -   `events[].location.address.coordinates.lat` → `data_json.*.location.address.coordinates.lat`
    -   Где `events` - массив, `location`, `address`, `coordinates` - объекты

#### 10.1.2. Массивы в массивах (nested arrays)

-   ✅ Массив массивов строк: `tags[][].name` → `data_json.*.*.name`
-   ✅ Массив массивов объектов: `sections[][].blocks[].content` → `data_json.*.*.*.content`
-   ✅ Трёхуровневые массивы: `matrix[][][].value` → `data_json.*.*.*.value`
-   ✅ Массивы с объектами внутри: `items[].metadata.tags[].name` → `data_json.*.metadata.*.name`

#### 10.1.3. Объекты в массивах (nested objects in arrays)

-   ✅ Массив объектов с вложенными объектами:
    -   `authors[].contact.address.street` → `data_json.*.contact.address.street`
-   ✅ Массив объектов с массивами внутри:
    -   `products[].categories[].name` → `data_json.*.*.name`
-   ✅ Глубокая вложенность объектов в массивах:
    -   `events[].venue.location.coordinates.lat` → `data_json.*.venue.location.coordinates.lat`

#### 10.1.4. Смешанные структуры (arrays + objects)

-   ✅ Объект → массив → объект → массив:
    -   `config.servers[].databases[].tables[].name` → `data_json.config.*.*.*.name`
-   ✅ Массив → объект → массив → объект:
    -   `items[].metadata.tags[].meta.key` → `data_json.*.metadata.*.meta.key`
-   ✅ Чередование уровней: `a[].b.c[].d.e[].f` → `data_json.*.b.*.d.*.f`

#### 10.1.5. Множественные правила на глубоко вложенных полях

-   ✅ Применяет все правила валидации к глубоко вложенным полям
-   ✅ Правильно группирует правила по путям с wildcards
-   ✅ Обрабатывает комбинации правил на разных уровнях вложенности

### 10.2. FieldPathBuilder: Суперсложные пути

**Файл:** `tests/Unit/Domain/Blueprint/Validation/FieldPathBuilderTest.php` (расширение)

#### 10.2.1. Глубокая вложенность (10+ уровней)

-   ✅ Обрабатывает путь с 10 уровнями вложенности
-   ✅ Обрабатывает путь с 15 уровнями вложенности
-   ✅ Обрабатывает путь с максимальной глубиной (20+ уровней)

#### 10.2.2. Множественные wildcards

-   ✅ Путь с 3 wildcards: `a[].b[].c[].d` → `data_json.*.*.*.d`
-   ✅ Путь с 5 wildcards: `a[].b[].c[].d[].e[].f` → `data_json.*.*.*.*.*.f`
-   ✅ Путь с 10 wildcards подряд
-   ✅ Чередование wildcards и обычных сегментов:
    -   `a[].b.c[].d.e[].f` → `data_json.*.b.*.d.*.f`

#### 10.2.3. Сложные комбинации cardinality

-   ✅ Все родители - массивы: `a[].b[].c[].d` → `data_json.*.*.*.d`
-   ✅ Все родители - объекты: `a.b.c.d` → `data_json.a.b.c.d`
-   ✅ Смешанные: `a[].b.c[].d.e` → `data_json.*.b.*.d.e`
-   ✅ Первый уровень - массив, остальные - объекты: `items[].meta.key` → `data_json.*.meta.key`
-   ✅ Последний уровень - массив: `config.items[].name` → `data_json.config.*.name`

#### 10.2.4. Граничные случаи сложных структур

-   ✅ Путь где каждый второй уровень - массив
-   ✅ Путь где только последний уровень - массив
-   ✅ Путь где только первый уровень - массив
-   ✅ Путь с максимальным количеством wildcards (20+)

### 10.3. Feature-тесты: Суперсложные структуры в реальных запросах

**Файл:** `tests/Feature/Api/Admin/Entries/EntryBlueprintValidationComplexTest.php` (новый файл)

#### 10.3.1. Глубоко вложенные структуры

-   ✅ Создание Entry с 5 уровнями вложенности объектов
-   ✅ Создание Entry с 5 уровнями вложенности массивов
-   ✅ Создание Entry со смешанной структурой (10+ уровней)
-   ✅ Валидация всех правил на глубоко вложенных полях

#### 10.3.2. Массивы в массивах

-   ✅ Валидация `data_json.items[][].name` (массив массивов)
-   ✅ Валидация `data_json.matrix[][][].value` (трёхуровневый массив)
-   ✅ Валидация с distinct на элементах вложенных массивов
-   ✅ Валидация с min/max на размерах вложенных массивов

#### 10.3.3. Объекты в массивах

-   ✅ Валидация `data_json.authors[].contact.email` (объект в массиве)
-   ✅ Валидация `data_json.products[].metadata.tags[].name` (объект → массив в массиве)
-   ✅ Валидация с required на полях объектов в массивах
-   ✅ Валидация с pattern на полях объектов в массивах

#### 10.3.4. Условная валидация на глубоко вложенных полях

-   ✅ `required_if` на поле 5-го уровня вложенности
    -   Пример: `data_json.events[].venue.location.coordinates.lat` required_if `data_json.events[].venue.location.type == 'gps'`
-   ✅ `prohibited_unless` на поле в массиве массивов
-   ✅ Условные правила, зависящие от полей на разных уровнях:
    -   `data_json.items[].price` required_if `data_json.items[].category == 'premium'`
    -   `data_json.sections[][].blocks[].content` required_if `data_json.sections[][].blocks[].type == 'text'`

#### 10.3.5. Сравнение полей через несколько уровней

-   ✅ `field_comparison` между полями на разных уровнях:
    -   `data_json.events[].end_date` >= `data_json.events[].start_date`
    -   `data_json.items[].variants[].price` >= `data_json.items[].base_price`
-   ✅ `field_comparison` между полями в разных массивах:
    -   `data_json.orders[].total` >= `data_json.products[].min_price`
-   ✅ `field_comparison` с константой на глубоко вложенном поле:
    -   `data_json.config.servers[].port` >= 1024

#### 10.3.6. Комбинации всех правил на сложных структурах

-   ✅ Все типы правил одновременно на глубоко вложенном поле:
    -   `data_json.items[].variants[].options[].values[].name`:
        -   required: true
        -   min: 3
        -   max: 100
        -   pattern: '/^[a-z]+$/'
        -   distinct: true
-   ✅ Условные правила + сравнение полей на сложной структуре
-   ✅ Множество правил на разных уровнях одной структуры

#### 10.3.7. Реальные сценарии использования

**Сценарий 1: E-commerce каталог**

```json
{
    "products": [
        {
            "name": "Product",
            "variants": [
                {
                    "sku": "SKU-001",
                    "price": 100,
                    "options": [
                        {
                            "name": "Color",
                            "values": ["red", "blue"]
                        }
                    ]
                }
            ],
            "categories": [
                {
                    "id": 1,
                    "name": "Electronics"
                }
            ]
        }
    ]
}
```

-   ✅ Валидация всех полей на всех уровнях
-   ✅ `price` >= `base_price` (field_comparison)
-   ✅ `sku` required, distinct, pattern
-   ✅ `values[]` distinct

**Сценарий 2: Event management**

```json
{
    "events": [
        {
            "title": "Event",
            "start_date": "2024-01-01",
            "end_date": "2024-01-02",
            "venue": {
                "name": "Venue",
                "location": {
                    "address": {
                        "street": "Street",
                        "coordinates": {
                            "lat": 55.7558,
                            "lng": 37.6173
                        }
                    }
                }
            },
            "speakers": [
                {
                    "name": "Speaker",
                    "contact": {
                        "email": "speaker@example.com"
                    }
                }
            ]
        }
    ]
}
```

-   ✅ `end_date` >= `start_date` (field_comparison)
-   ✅ `email` required_if `contact.type == 'email'`, pattern
-   ✅ `coordinates.lat` required, min: -90, max: 90

**Сценарий 3: CMS с блоками контента**

```json
{
    "sections": [
        [
            {
                "type": "text",
                "content": "Text content",
                "blocks": [
                    {
                        "type": "paragraph",
                        "text": "Paragraph"
                    }
                ]
            }
        ]
    ]
}
```

-   ✅ `content` required_if `type == 'text'`
-   ✅ `blocks[].text` required_if `blocks[].type == 'paragraph'`
-   ✅ Массив массивов с валидацией на всех уровнях

## 11. Интеграционные тесты: Полный цикл

**Файл:** `tests/Integration/BlueprintValidationIntegrationTest.php`

### 11.1. Сложные сценарии

-   ✅ Создание Entry с полным Blueprint (множество paths, все типы правил)
-   ✅ Обновление Entry с изменением структуры Blueprint
-   ✅ Валидация с вложенными массивами и объектами
-   ✅ Валидация с условными правилами, зависящими от других полей data_json

### 11.2. Суперсложные интеграционные сценарии

#### 11.2.1. Diamond dependency с валидацией

-   ✅ Blueprint A встраивается в B и C
-   ✅ B и C встраиваются в D
-   ✅ Валидация правил на всех уровнях вложенности
-   ✅ Условные правила, зависящие от полей из разных встроенных blueprint

#### 11.2.2. Транзитивная вложенность (5+ уровней)

-   ✅ Blueprint A → B → C → D → E
-   ✅ Валидация правил на каждом уровне
-   ✅ Сравнение полей между разными уровнями
-   ✅ Условные правила через несколько уровней

#### 11.2.3. Массивы встроенных blueprint

-   ✅ Blueprint с массивом встроенных blueprint (cardinality='many')
-   ✅ Валидация правил на элементах массива встроенных blueprint
-   ✅ Условные правила на встроенных blueprint в массиве

### 11.3. Производительность

-   ✅ Валидация большого количества paths (100+)
-   ✅ Валидация глубоко вложенных структур (10+ уровней)
-   ✅ Валидация с множеством правил на одно поле
-   ✅ Валидация массивов с большим количеством элементов (1000+)
-   ✅ Валидация структур с множественными wildcards (10+)

---

## 12. Performance-тесты: Суперсложные структуры

**Файл:** `tests/Performance/BlueprintValidationPerformanceTest.php`

### 12.1. Производительность глубокой вложенности

-   ✅ Валидация структуры с 5 уровнями вложенности (< 100ms)
-   ✅ Валидация структуры с 10 уровнями вложенности (< 500ms)
-   ✅ Валидация структуры с 20 уровнями вложенности (< 2000ms)
-   ✅ Сравнение производительности: объекты vs массивы vs смешанные

### 12.2. Производительность массивов

-   ✅ Валидация массива с 10 элементами (< 50ms)
-   ✅ Валидация массива с 100 элементами (< 200ms)
-   ✅ Валидация массива с 1000 элементами (< 1000ms)
-   ✅ Валидация массива массивов (10×10) (< 200ms)
-   ✅ Валидация массива массивов (100×10) (< 1000ms)

### 12.3. Производительность множественных правил

-   ✅ Валидация поля с 1 правилом
-   ✅ Валидация поля с 5 правилами
-   ✅ Валидация поля с 10 правилами
-   ✅ Валидация 100 полей с 5 правилами каждое

### 12.4. Производительность условных правил

-   ✅ Валидация с 1 условным правилом
-   ✅ Валидация с 10 условными правилами
-   ✅ Валидация с условными правилами на разных уровнях
-   ✅ Валидация с вложенными условными правилами

### 12.5. Производительность сравнения полей

-   ✅ Валидация с 1 field_comparison
-   ✅ Валидация с 10 field_comparison на разных уровнях
-   ✅ Валидация с field_comparison между глубоко вложенными полями

---

## 13. Примеры суперсложных структур для тестирования

### 13.1. E-commerce каталог (полная структура)

```json
{
    "products": [
        {
            "name": "Product Name",
            "description": "Product description",
            "base_price": 100,
            "variants": [
                {
                    "sku": "SKU-001",
                    "price": 120,
                    "stock": 50,
                    "options": [
                        {
                            "name": "Color",
                            "value": "red",
                            "values": ["red", "blue", "green"]
                        },
                        {
                            "name": "Size",
                            "value": "M",
                            "values": ["S", "M", "L"]
                        }
                    ],
                    "images": [
                        {
                            "url": "https://example.com/image.jpg",
                            "alt": "Product image",
                            "metadata": {
                                "width": 1920,
                                "height": 1080,
                                "format": "jpg"
                            }
                        }
                    ]
                }
            ],
            "categories": [
                {
                    "id": 1,
                    "name": "Electronics",
                    "parent": {
                        "id": 0,
                        "name": "Root"
                    }
                }
            ],
            "reviews": [
                {
                    "rating": 5,
                    "comment": "Great product",
                    "author": {
                        "name": "John Doe",
                        "email": "john@example.com"
                    },
                    "responses": [
                        {
                            "text": "Thank you!",
                            "author": {
                                "name": "Admin",
                                "role": "moderator"
                            }
                        }
                    ]
                }
            ]
        }
    ]
}
```

**Правила валидации:**

-   `products[].name`: required, min: 3, max: 255
-   `products[].variants[].sku`: required, distinct, pattern: '/^SKU-\d+$/'
-   `products[].variants[].price`: required, field_comparison: >= `products[].base_price`
-   `products[].variants[].options[].values[]`: distinct
-   `products[].variants[].images[].url`: required_if `products[].variants[].images[].type == 'external'`, pattern: '/^https?:\/\//'
-   `products[].reviews[].rating`: required, min: 1, max: 5
-   `products[].reviews[].author.email`: required_if `products[].reviews[].author.type == 'registered'`, pattern: '/^[^@]+@[^@]+$/'

### 13.2. Event management система

```json
{
    "events": [
        {
            "title": "Conference 2024",
            "start_date": "2024-06-01T10:00:00Z",
            "end_date": "2024-06-03T18:00:00Z",
            "venue": {
                "name": "Convention Center",
                "location": {
                    "address": {
                        "street": "123 Main St",
                        "city": "New York",
                        "country": "USA",
                        "postal_code": "10001"
                    },
                    "coordinates": {
                        "lat": 40.7128,
                        "lng": -74.006
                    }
                },
                "capacity": 1000,
                "rooms": [
                    {
                        "name": "Main Hall",
                        "capacity": 500,
                        "equipment": [
                            {
                                "type": "projector",
                                "model": "Epson X1234"
                            }
                        ]
                    }
                ]
            },
            "speakers": [
                {
                    "name": "John Doe",
                    "bio": "Expert in...",
                    "contact": {
                        "email": "john@example.com",
                        "phone": "+1-555-0123",
                        "social": [
                            {
                                "platform": "twitter",
                                "handle": "@johndoe"
                            }
                        ]
                    },
                    "sessions": [
                        {
                            "title": "Keynote",
                            "start_time": "2024-06-01T10:00:00Z",
                            "end_time": "2024-06-01T11:00:00Z",
                            "room": {
                                "name": "Main Hall"
                            }
                        }
                    ]
                }
            ],
            "tickets": [
                {
                    "type": "VIP",
                    "price": 500,
                    "available": 50,
                    "sold": 10,
                    "discounts": [
                        {
                            "code": "EARLYBIRD",
                            "percent": 20,
                            "valid_until": "2024-05-01T00:00:00Z"
                        }
                    ]
                }
            ]
        }
    ]
}
```

**Правила валидации:**

-   `events[].title`: required, min: 5, max: 255
-   `events[].end_date`: required, field_comparison: >= `events[].start_date`
-   `events[].venue.location.coordinates.lat`: required, min: -90, max: 90
-   `events[].venue.location.coordinates.lng`: required, min: -180, max: 180
-   `events[].venue.rooms[].capacity`: required, field_comparison: <= `events[].venue.capacity`
-   `events[].speakers[].contact.email`: required_if `events[].speakers[].contact.type == 'email'`, pattern: '/^[^@]+@[^@]+$/'
-   `events[].speakers[].sessions[].end_time`: required, field_comparison: >= `events[].speakers[].sessions[].start_time`
-   `events[].tickets[].sold`: required, field_comparison: <= `events[].tickets[].available`
-   `events[].tickets[].discounts[].percent`: required, min: 0, max: 100

### 13.3. CMS с блоками контента

```json
{
    "sections": [
        [
            {
                "type": "hero",
                "content": {
                    "title": "Welcome",
                    "subtitle": "Subtitle",
                    "background": {
                        "type": "image",
                        "url": "https://example.com/bg.jpg",
                        "overlay": {
                            "color": "#000000",
                            "opacity": 0.5
                        }
                    }
                },
                "blocks": [
                    {
                        "type": "text",
                        "content": "Text block",
                        "style": {
                            "font_size": 16,
                            "color": "#333333"
                        }
                    },
                    {
                        "type": "image",
                        "content": {
                            "url": "https://example.com/img.jpg",
                            "alt": "Image",
                            "caption": "Caption"
                        }
                    }
                ]
            }
        ],
        [
            {
                "type": "grid",
                "columns": 3,
                "items": [
                    {
                        "title": "Item 1",
                        "description": "Description 1",
                        "link": {
                            "url": "/item1",
                            "text": "Read more"
                        }
                    }
                ]
            }
        ]
    ],
    "metadata": {
        "seo": {
            "title": "Page Title",
            "description": "Page description",
            "keywords": ["keyword1", "keyword2"],
            "og": {
                "image": "https://example.com/og.jpg",
                "type": "website"
            }
        },
        "analytics": {
            "tracking_id": "UA-123456",
            "events": [
                {
                    "name": "page_view",
                    "params": {
                        "page_path": "/page",
                        "page_title": "Page Title"
                    }
                }
            ]
        }
    }
}
```

**Правила валидации:**

-   `sections[][].type`: required, pattern: '/^(hero|grid|text|image)$/'
-   `sections[][].content.title`: required_if `sections[][].type == 'hero'`, min: 3, max: 255
-   `sections[][].blocks[].content`: required_if `sections[][].blocks[].type == 'text'`
-   `sections[][].blocks[].content.url`: required_if `sections[][].blocks[].type == 'image'`, pattern: '/^https?:\/\//'
-   `metadata.seo.keywords[]`: distinct, max: 10
-   `metadata.analytics.events[].name`: required, pattern: '/^[a-z_]+$/'

---

## 14. Тесты для граничных случаев сложных структур

### 14.1. Пустые массивы и объекты

-   ✅ Валидация пустого массива `items: []`
-   ✅ Валидация массива с пустыми объектами `items: [{}]`
-   ✅ Валидация объекта с пустыми массивами `config: { items: [] }`
-   ✅ Валидация глубоко вложенных пустых структур

### 14.2. Null значения

-   ✅ Валидация null на обязательных полях в массивах
-   ✅ Валидация null на опциональных полях в массивах
-   ✅ Валидация null на глубоко вложенных полях

### 14.3. Неправильные типы данных

-   ✅ Строка вместо массива на уровне с `cardinality='many'`
-   ✅ Массив вместо объекта
-   ✅ Неправильный тип на глубоко вложенном поле

### 14.4. Циклические ссылки (если поддерживаются)

-   ✅ Валидация структуры с циклическими ссылками
-   ✅ Предотвращение бесконечной рекурсии при валидации

---

## 15. Вспомогательные утилиты для сложных тестов

### 15.1. Фабрики для суперсложных структур

**Файл:** `tests/Helpers/Factories/ComplexBlueprintFactory.php`

```php
// Создание Blueprint с глубокой вложенностью
createDeepNestedBlueprint(int $levels, array $cardinalities): Blueprint

// Создание Blueprint с массивами в массивах
createNestedArraysBlueprint(int $arrayLevels): Blueprint

// Создание Blueprint для E-commerce
createEcommerceBlueprint(): Blueprint

// Создание Blueprint для Event management
createEventManagementBlueprint(): Blueprint

// Создание Blueprint для CMS
createCmsBlueprint(): Blueprint
```

### 15.2. Генераторы тестовых данных

**Файл:** `tests/Helpers/Fixtures/blueprints/complex_structures.php`

-   Примеры JSON структур для всех сложных сценариев
-   Валидные и невалидные варианты
-   Структуры с разной глубиной вложенности
-   Структуры с разными комбинациями массивов и объектов

### 15.3. Утилиты для проверки производительности

**Файл:** `tests/Helpers/Traits/PerformanceAssertions.php`

```php
// Проверка времени выполнения
assertValidationTimeLessThan(callable $validation, int $milliseconds): void

// Бенчмарк валидации
benchmarkValidation(callable $validation, int $iterations): array
```

---

## Приоритеты реализации

### Высокий приоритет (MVP)

1. EntryValidationServiceTest (базовые сценарии)
2. FieldPathBuilderTest (все сценарии)
3. PathValidationRulesConverterTest (базовые правила)
4. RuleSetTest (все методы)
5. LaravelValidationAdapterTest (базовое преобразование)
6. EntryBlueprintValidationTest (базовые сценарии)

### Средний приоритет

7. Все Rule классы (getType, getParams)
8. Все RuleHandler классы (supports, handle)
9. RuleHandlerRegistryTest
10. RuleFactoryImplTest
11. PathValidationRulesConverterTest (условные правила, field_comparison)

### Низкий приоритет (полировка)

12. EntryBlueprintValidationTest (все граничные случаи)
13. BlueprintValidationIntegrationTest
14. Тесты производительности

---

## Вспомогательные утилиты

### Фабрики для тестов

**Файл:** `tests/Helpers/Factories/BlueprintFactory.php` (если не существует)

-   `createBlueprintWithPaths(array $pathsConfig)` - создаёт Blueprint с заданными paths
-   `createPath(array $attributes)` - создаёт Path с заданными атрибутами

### Фикстуры

**Файл:** `tests/Helpers/Fixtures/blueprints/validation.php`

-   Примеры validation_rules для разных типов правил
-   Примеры сложных структур Blueprint

---

## Метрики покрытия

Целевое покрытие: **90%+**

Критичные компоненты (100% покрытие):

-   EntryValidationService
-   FieldPathBuilder
-   PathValidationRulesConverter
-   LaravelValidationAdapter

---

## Запуск тестов

```bash
# Все тесты валидации
php artisan test tests/Unit/Domain/Blueprint/Validation
php artisan test tests/Feature/Api/Admin/Entries/EntryBlueprintValidationTest.php

# Конкретный файл
php artisan test tests/Unit/Domain/Blueprint/Validation/EntryValidationServiceTest.php

# С покрытием
php artisan test --coverage tests/Unit/Domain/Blueprint/Validation

# Только суперсложные тесты
php artisan test tests/Feature/Api/Admin/Entries/EntryBlueprintValidationComplexTest.php
php artisan test tests/Integration/BlueprintValidationIntegrationTest.php
php artisan test tests/Performance/BlueprintValidationPerformanceTest.php
```

---

## Чек-лист реализации тестов

### Фаза 1: Базовые тесты (MVP) - 1-2 недели

#### Unit-тесты

-   [ ] EntryValidationServiceTest (базовые сценарии, простые пути)
-   [ ] FieldPathBuilderTest (простые и вложенные пути)
-   [ ] PathValidationRulesConverterTest (базовые правила)
-   [ ] RuleSetTest (все методы)
-   [ ] LaravelValidationAdapterTest (базовое преобразование)

#### Feature-тесты

-   [ ] EntryBlueprintValidationTest (базовые сценарии)

**Результат:** Покрытие базовой функциональности ~60%

---

### Фаза 2: Расширенные тесты - 1-2 недели

#### Unit-тесты

-   [ ] Все Rule классы (8 файлов)
-   [ ] Все RuleHandler классы (9 файлов)
-   [ ] RuleHandlerRegistryTest
-   [ ] RuleFactoryImplTest
-   [ ] PathValidationRulesConverterTest (условные правила, field_comparison)
-   [ ] EntryValidationServiceTest (расширение: обработка validation_rules)
-   [ ] FieldPathBuilderTest (расширение: обработка cardinality)

#### Feature-тесты

-   [ ] EntryBlueprintValidationTest (расширение: все правила валидации)
-   [ ] EntryBlueprintValidationTest (расширение: вложенные структуры)

**Результат:** Покрытие ~80%

---

### Фаза 3: Суперсложные тесты - 2-3 недели

#### Unit-тесты

-   [ ] EntryValidationServiceTest (суперсложные структуры)
-   [ ] FieldPathBuilderTest (суперсложные пути)

#### Feature-тесты

-   [ ] EntryBlueprintValidationComplexTest (все суперсложные сценарии)

#### Интеграционные тесты

-   [ ] BlueprintValidationIntegrationTest (полный цикл)

**Результат:** Покрытие ~95%

---

### Фаза 4: Производительность и полировка - 1 неделя

#### Performance-тесты

-   [ ] BlueprintValidationPerformanceTest (все сценарии)

#### Документация и рефакторинг

-   [ ] Обновление документации
-   [ ] Рефакторинг дублирующегося кода
-   [ ] Оптимизация медленных тестов

**Результат:** Покрытие 90%+, все тесты проходят, документация актуальна

---

## Статистика плана

### Общее количество тестовых файлов: **~40**

**По типам:**

-   Unit-тесты: ~25 файлов
-   Feature-тесты: ~3 файла
-   Integration-тесты: ~1 файл
-   Performance-тесты: ~1 файл
-   Вспомогательные: ~10 файлов

### Общее количество тестовых кейсов: **~250+**

**По приоритетам:**

-   Высокий приоритет (MVP): ~80 кейсов
-   Средний приоритет: ~100 кейсов
-   Низкий приоритет (суперсложные): ~70 кейсов

### Покрытие кода

**Целевое покрытие:**

-   Критичные компоненты: **100%**
-   Все компоненты: **90%+**

**Критичные компоненты:**

-   EntryValidationService
-   FieldPathBuilder
-   PathValidationRulesConverter
-   LaravelValidationAdapter
-   Все RuleHandler классы

---

## Рекомендации по реализации

### 1. Начните с простых тестов

Начните с базовых сценариев для EntryValidationService и FieldPathBuilder - это основа системы.

### 2. Используйте фабрики

Создайте фабрики для Blueprint и Path сразу, это ускорит написание остальных тестов.

### 3. Тестируйте граничные случаи

Особое внимание уделите граничным случаям - они часто выявляют баги.

### 4. Группируйте тесты

Используйте группы тестов (например, `@group complex`) для удобного запуска.

### 5. Документируйте сложные тесты

Добавляйте комментарии к суперсложным тестам, объясняющие структуру данных.

### 6. Регулярно запускайте тесты

Запускайте тесты после каждого изменения, чтобы быстро находить регрессии.

### 7. Оптимизируйте медленные тесты

Если тест выполняется > 1 секунды, рассмотрите возможность оптимизации.

---

## Известные ограничения и будущие улучшения

### Текущие ограничения

-   Нет поддержки валидации циклических ссылок
-   Нет поддержки кастомных типов данных
-   Нет поддержки динамических правил (на основе других полей)

### Будущие улучшения

-   [ ] Поддержка валидации циклических ссылок
-   [ ] Поддержка кастомных валидаторов
-   [ ] Кэширование правил валидации для производительности
-   [ ] Поддержка асинхронной валидации для больших структур
-   [ ] Валидация на основе контекста (например, роль пользователя)
