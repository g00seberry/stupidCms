# План расширения системы валидации Blueprint

## Текущее состояние

### Реализованные правила (9 типов)

1. **required** / **nullable** — обязательность поля
2. **min** — минимальное значение/длина
3. **max** — максимальное значение/длина
4. **pattern** — регулярное выражение
5. **distinct** — уникальность элементов массива
6. **required_if** / **prohibited_unless** / **required_unless** / **prohibited_if** — условные правила
7. **field_comparison** — сравнение полей (>=, <=, >, <, ==, !=)

### Архитектура

-   **Доменные Rule объекты** (`app/Domain/Blueprint/Validation/Rules/`)
-   **RuleHandler** для преобразования в Laravel правила (`app/Domain/Blueprint/Validation/Rules/Handlers/`)
-   **PathValidationRulesConverter** — конвертер из JSON в Rule объекты
-   **LaravelValidationAdapter** — адаптер в Laravel правила

---

## План добавления новых правил

Правила отсортированы по приоритету: **Критический** → **Высокий** → **Средний** → **Низкий**.

---

## Приоритет 1: Критический (базовые типы данных)

### 1.1. Типы данных (string, integer, numeric, boolean, array, date)

**Приоритет:** Критический  
**Сложность:** Низкая  
**Время:** 2-3 часа

**Описание:**  
Базовые правила для проверки типов данных. Необходимы для корректной валидации полей.

**Laravel правила:**

-   `string` — строка
-   `integer` / `int` — целое число
-   `numeric` — число (int или float)
-   `boolean` / `bool` — булево значение
-   `array` — массив
-   `date` — дата
-   `date_format:Y-m-d` — дата в формате
-   `email` — email адрес
-   `url` — URL
-   `ip` — IP адрес
-   `json` — валидный JSON

**Формат в validation_rules:**

```json
{
    "type": "string",
    "type": "integer",
    "type": "numeric",
    "type": "boolean",
    "type": "array",
    "type": "date",
    "type": "date_format",
    "date_format_value": "Y-m-d",
    "type": "email",
    "type": "url",
    "type": "ip",
    "type": "json"
}
```

**Шаги реализации:**

1. Создать `TypeRule` с параметром `type: string`
2. Создать `TypeRuleHandler` для преобразования в Laravel правила
3. Добавить обработку в `PathValidationRulesConverter`
4. Зарегистрировать handler в `AppServiceProvider`
5. Написать тесты

**Файлы для создания:**

-   `app/Domain/Blueprint/Validation/Rules/TypeRule.php`
-   `app/Domain/Blueprint/Validation/Rules/Handlers/TypeRuleHandler.php`
-   `tests/Unit/Domain/Blueprint/Validation/Rules/Handlers/TypeRuleHandlerTest.php`

**Файлы для изменения:**

-   `app/Domain/Blueprint/Validation/PathValidationRulesConverter.php`
-   `app/Domain/Blueprint/Validation/Rules/RuleFactory.php`
-   `app/Domain/Blueprint/Validation/Rules/RuleFactoryImpl.php`
-   `app/Providers/AppServiceProvider.php`

---

## Приоритет 2: Высокий (часто используемые правила)

### 2.1. Размеры для строк и массивов (size, between)

**Приоритет:** Высокий  
**Сложность:** Низкая  
**Время:** 1-2 часа

**Описание:**  
Правила для точного размера и диапазона значений.

**Laravel правила:**

-   `size:10` — точный размер (для строк — длина, для чисел — значение, для массивов — количество)
-   `between:min,max` — значение в диапазоне

**Формат в validation_rules:**

```json
{
    "size": 10,
    "between": [5, 20]
}
```

**Шаги реализации:**

1. Создать `SizeRule` и `BetweenRule`
2. Создать handlers
3. Добавить в конвертер
4. Зарегистрировать
5. Тесты

**Файлы:**

-   `app/Domain/Blueprint/Validation/Rules/SizeRule.php`
-   `app/Domain/Blueprint/Validation/Rules/BetweenRule.php`
-   `app/Domain/Blueprint/Validation/Rules/Handlers/SizeRuleHandler.php`
-   `app/Domain/Blueprint/Validation/Rules/Handlers/BetweenRuleHandler.php`

---

### 2.2. Уникальность (unique)

**Приоритет:** Высокий  
**Сложность:** Средняя  
**Время:** 3-4 часа

**Описание:**  
Проверка уникальности значения в таблице БД. Требует доступа к БД.

**Laravel правило:**

-   `unique:table,column,except,idColumn`

**Формат в validation_rules:**

```json
{
    "unique": {
        "table": "users",
        "column": "email",
        "except": null,
        "id_column": "id"
    }
}
```

**Особенности:**

-   Нужен доступ к схеме БД или конфигурации
-   Может требовать контекст (например, исключение текущей записи при обновлении)

**Шаги реализации:**

1. Создать `UniqueRule` с параметрами table, column, except, idColumn
2. Создать `UniqueRuleHandler` (возвращает строку `unique:...`)
3. Добавить в конвертер
4. Зарегистрировать
5. Тесты

**Файлы:**

-   `app/Domain/Blueprint/Validation/Rules/UniqueRule.php`
-   `app/Domain/Blueprint/Validation/Rules/Handlers/UniqueRuleHandler.php`

---

### 2.3. Существование (exists)

**Приоритет:** Высокий  
**Сложность:** Средняя  
**Время:** 2-3 часа

**Описание:**  
Проверка существования значения в таблице БД.

**Laravel правило:**

-   `exists:table,column`

**Формат в validation_rules:**

```json
{
    "exists": {
        "table": "post_types",
        "column": "slug"
    }
}
```

**Шаги реализации:**

1. Создать `ExistsRule`
2. Создать handler
3. Добавить в конвертер
4. Зарегистрировать
5. Тесты

**Файлы:**

-   `app/Domain/Blueprint/Validation/Rules/ExistsRule.php`
-   `app/Domain/Blueprint/Validation/Rules/Handlers/ExistsRuleHandler.php`

---

### 2.4. Условные правила (sometimes, required_with, required_without)

**Приоритет:** Высокий  
**Сложность:** Низкая  
**Время:** 2-3 часа

**Описание:**  
Дополнительные условные правила для гибкой валидации.

**Laravel правила:**

-   `sometimes` — правило применяется только если поле присутствует
-   `required_with:field1,field2` — обязательно, если указанные поля присутствуют
-   `required_without:field1,field2` — обязательно, если указанные поля отсутствуют
-   `required_with_all:field1,field2` — обязательно, если все указанные поля присутствуют
-   `required_without_all:field1,field2` — обязательно, если все указанные поля отсутствуют

**Формат в validation_rules:**

```json
{
    "sometimes": true,
    "required_with": ["field1", "field2"],
    "required_without": ["field1", "field2"],
    "required_with_all": ["field1", "field2"],
    "required_without_all": ["field1", "field2"]
}
```

**Шаги реализации:**

1. Создать `SometimesRule`, `RequiredWithRule`, `RequiredWithoutRule` и т.д.
2. Создать handlers (можно объединить в один `ConditionalPresenceRuleHandler`)
3. Добавить в конвертер
4. Зарегистрировать
5. Тесты

**Файлы:**

-   `app/Domain/Blueprint/Validation/Rules/SometimesRule.php`
-   `app/Domain/Blueprint/Validation/Rules/RequiredWithRule.php`
-   `app/Domain/Blueprint/Validation/Rules/Handlers/ConditionalPresenceRuleHandler.php`

---

## Приоритет 3: Средний (полезные правила)

### 3.1. Валидация массивов (array rules)

**Приоритет:** Средний  
**Сложность:** Средняя  
**Время:** 3-4 часа

**Описание:**  
Правила для работы с массивами и их элементами.

**Laravel правила:**

-   `min:1` / `max:10` — для массивов: количество элементов
-   `distinct` — уже реализовано
-   `array:key1,key2` — массив должен содержать указанные ключи
-   `in:value1,value2` — значение в списке
-   `not_in:value1,value2` — значение не в списке

**Формат в validation_rules:**

```json
{
    "array_keys": ["key1", "key2"],
    "in": ["value1", "value2"],
    "not_in": ["value1", "value2"]
}
```

**Шаги реализации:**

1. Создать `ArrayKeysRule`, `InRule`, `NotInRule`
2. Создать handlers
3. Добавить в конвертер
4. Зарегистрировать
5. Тесты

**Файлы:**

-   `app/Domain/Blueprint/Validation/Rules/ArrayKeysRule.php`
-   `app/Domain/Blueprint/Validation/Rules/InRule.php`
-   `app/Domain/Blueprint/Validation/Rules/NotInRule.php`
-   Соответствующие handlers

---

### 3.2. Валидация строк (string rules)

**Приоритет:** Средний  
**Сложность:** Низкая  
**Время:** 2-3 часа

**Описание:**  
Специфичные правила для строк.

**Laravel правила:**

-   `alpha` — только буквы
-   `alpha_dash` — буквы, цифры, дефисы и подчёркивания
-   `alpha_num` — буквы и цифры
-   `starts_with:prefix` — начинается с
-   `ends_with:suffix` — заканчивается на
-   `contains:substring` — содержит подстроку
-   `lowercase` — только строчные буквы
-   `uppercase` — только заглавные буквы
-   `uuid` — UUID формат
-   `ulid` — ULID формат

**Формат в validation_rules:**

```json
{
    "alpha": true,
    "alpha_dash": true,
    "alpha_num": true,
    "starts_with": "prefix",
    "ends_with": "suffix",
    "contains": "substring",
    "lowercase": true,
    "uppercase": true,
    "uuid": true,
    "ulid": true
}
```

**Шаги реализации:**

1. Создать правила (можно объединить в `StringFormatRule` с типом)
2. Создать handler
3. Добавить в конвертер
4. Зарегистрировать
5. Тесты

**Файлы:**

-   `app/Domain/Blueprint/Validation/Rules/StringFormatRule.php`
-   `app/Domain/Blueprint/Validation/Rules/Handlers/StringFormatRuleHandler.php`

---

### 3.3. Валидация дат (date rules)

**Приоритет:** Средний  
**Сложность:** Низкая  
**Время:** 2-3 часа

**Описание:**  
Дополнительные правила для дат.

**Laravel правила:**

-   `before:date` — дата до указанной
-   `before_or_equal:date` — дата до или равна
-   `after:date` — дата после указанной
-   `after_or_equal:date` — дата после или равна
-   `date_equals:date` — дата равна
-   `timezone` — валидная временная зона

**Формат в validation_rules:**

```json
{
    "before": "2024-12-31",
    "before_field": "content_json.end_date",
    "after": "2024-01-01",
    "after_field": "content_json.start_date",
    "date_equals": "2024-06-15",
    "timezone": true
}
```

**Шаги реализации:**

1. Создать `DateComparisonRule` (расширить `FieldComparisonRule` или создать отдельное)
2. Создать handler
3. Добавить в конвертер
4. Зарегистрировать
5. Тесты

**Файлы:**

-   `app/Domain/Blueprint/Validation/Rules/DateComparisonRule.php`
-   `app/Domain/Blueprint/Validation/Rules/Handlers/DateComparisonRuleHandler.php`

---

### 3.4. Валидация файлов (file rules)

**Приоритет:** Средний  
**Сложность:** Высокая  
**Время:** 4-6 часов

**Описание:**  
Правила для валидации загружаемых файлов.

**Laravel правила:**

-   `file` — файл
-   `image` — изображение
-   `mimes:jpeg,png` — MIME типы
-   `mimetypes:image/jpeg,image/png` — MIME типы (полные)
-   `size:1024` — размер файла в килобайтах
-   `dimensions:min_width=100,min_height=100` — размеры изображения

**Формат в validation_rules:**

```json
{
    "file": true,
    "image": true,
    "mimes": ["jpeg", "png", "gif"],
    "mimetypes": ["image/jpeg", "image/png"],
    "file_size": 1024,
    "dimensions": {
        "min_width": 100,
        "min_height": 100,
        "max_width": 2000,
        "max_height": 2000
    }
}
```

**Особенности:**

-   Требует контекст загрузки файлов
-   Может быть не применимо для JSON-полей (если файлы хранятся отдельно)

**Шаги реализации:**

1. Создать `FileRule`, `ImageRule`, `MimesRule`, `FileSizeRule`, `DimensionsRule`
2. Создать handlers
3. Добавить в конвертер
4. Зарегистрировать
5. Тесты

**Файлы:**

-   `app/Domain/Blueprint/Validation/Rules/FileRule.php`
-   `app/Domain/Blueprint/Validation/Rules/ImageRule.php`
-   `app/Domain/Blueprint/Validation/Rules/MimesRule.php`
-   Соответствующие handlers

---

## Приоритет 4: Низкий (специализированные правила)

### 4.1. Валидация чисел (numeric rules)

**Приоритет:** Низкий  
**Сложность:** Низкая  
**Время:** 1-2 часа

**Описание:**  
Специфичные правила для чисел.

**Laravel правила:**

-   `digits:5` — точное количество цифр
-   `digits_between:5,10` — количество цифр в диапазоне
-   `multiple_of:5` — кратно числу

**Формат в validation_rules:**

```json
{
    "digits": 5,
    "digits_between": [5, 10],
    "multiple_of": 5
}
```

**Шаги реализации:**

1. Создать правила
2. Создать handlers
3. Добавить в конвертер
4. Зарегистрировать
5. Тесты

---

### 4.2. Валидация паролей (password rules)

**Приоритет:** Низкий  
**Сложность:** Средняя  
**Время:** 2-3 часа

**Описание:**  
Специализированные правила для паролей (Laravel 12 имеет встроенную поддержку).

**Laravel правило:**

-   `Password::defaults()` — комплексная валидация пароля

**Формат в validation_rules:**

```json
{
    "password": {
        "min": 8,
        "letters": true,
        "mixed_case": true,
        "numbers": true,
        "symbols": true,
        "uncompromised": true
    }
}
```

**Особенности:**

-   Требует использование Laravel Password rule object
-   Может быть избыточно для Blueprint (пароли обычно не в content_json)

---

### 4.3. Валидация сетей (network rules)

**Приоритет:** Низкий  
**Сложность:** Низкая  
**Время:** 1-2 часа

**Описание:**  
Правила для сетевых адресов.

**Laravel правила:**

-   `ipv4` — IPv4 адрес
-   `ipv6` — IPv6 адрес
-   `mac_address` — MAC адрес

**Формат в validation_rules:**

```json
{
    "ipv4": true,
    "ipv6": true,
    "mac_address": true
}
```

---

### 4.4. Валидация кредитных карт (payment rules)

**Приоритет:** Низкий  
**Сложность:** Средняя  
**Время:** 2-3 часа

**Описание:**  
Правила для валидации платёжных данных.

**Laravel правила:**

-   `credit_card` — номер кредитной карты (требует пакет)

**Особенности:**

-   Может требовать внешний пакет
-   Специализированное использование

---

## Общий план реализации

### Этап 1: Критический (1-2 недели)

1. Типы данных (string, integer, numeric, boolean, array, date, email, url, ip, json)
2. Размеры (size, between)
3. Уникальность (unique)
4. Существование (exists)
5. Условные правила (sometimes, required_with, required_without)

### Этап 2: Высокий (2-3 недели)

1. Валидация массивов (array_keys, in, not_in)
2. Валидация строк (alpha, starts_with, ends_with, lowercase, uppercase, uuid, ulid)
3. Валидация дат (before, after, date_equals)

### Этап 3: Средний (3-4 недели)

1. Валидация файлов (file, image, mimes, file_size, dimensions)
2. Валидация чисел (digits, digits_between, multiple_of)

### Этап 4: Низкий (по необходимости)

1. Валидация паролей
2. Валидация сетей
3. Валидация кредитных карт

---

## Шаблон реализации нового правила

### 1. Создать доменное Rule

```php
<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Правило валидации: [описание].
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class ExampleRule implements Rule
{
    /**
     * @param mixed $value Значение правила
     */
    public function __construct(
        private readonly mixed $value
    ) {}

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return 'example';
    }

    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return ['value' => $this->value];
    }
}
```

### 2. Создать Handler

```php
<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\ExampleRule;
use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Обработчик правила ExampleRule.
 *
 * Преобразует ExampleRule в строку Laravel правила валидации.
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class ExampleRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'example';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule): array
    {
        if (! $rule instanceof ExampleRule) {
            throw new \InvalidArgumentException('Expected ExampleRule instance');
        }

        $params = $rule->getParams();
        $value = $params['value'] ?? null;

        return ["example:{$value}"];
    }
}
```

### 3. Добавить в RuleFactory

```php
// В RuleFactory.php добавить метод
public function createExampleRule(mixed $value): ExampleRule;

// В RuleFactoryImpl.php реализовать
public function createExampleRule(mixed $value): ExampleRule
{
    return new ExampleRule($value);
}
```

### 4. Добавить в PathValidationRulesConverter

```php
// В методе convert() добавить case
'example' => $rules[] = $this->ruleFactory->createExampleRule($value),
```

### 5. Зарегистрировать в AppServiceProvider

```php
$registry->register('example', new ExampleRuleHandler());
```

### 6. Написать тесты

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\ExampleRule;
use App\Domain\Blueprint\Validation\Rules\Handlers\ExampleRuleHandler;
use Tests\TestCase;

final class ExampleRuleHandlerTest extends TestCase
{
    private ExampleRuleHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new ExampleRuleHandler();
    }

    public function test_supports_example_rule_type(): void
    {
        $this->assertTrue($this->handler->supports('example'));
        $this->assertFalse($this->handler->supports('other'));
    }

    public function test_handles_example_rule(): void
    {
        $rule = new ExampleRule('value');
        $result = $this->handler->handle($rule);

        $this->assertEquals(['example:value'], $result);
    }
}
```

---

## Чек-лист для каждого нового правила

-   [ ] Создан доменный Rule класс
-   [ ] Создан Handler класс
-   [ ] Добавлен метод в RuleFactory
-   [ ] Реализован метод в RuleFactoryImpl
-   [ ] Добавлена обработка в PathValidationRulesConverter
-   [ ] Зарегистрирован handler в AppServiceProvider
-   [ ] Написаны unit-тесты для Handler
-   [ ] Написаны интеграционные тесты (если требуется)
-   [ ] Обновлена документация (если требуется)
-   [ ] Проверено соответствие PHPDoc (количество параметров, типы, @return)

---

## Примечания

1. **Приоритеты основаны на:**

    - Частоте использования в типичных CMS-сценариях
    - Сложности реализации
    - Зависимости от внешних сервисов (БД, файловая система)

2. **Рекомендации:**

    - Начинать с правил Приоритета 1 (критический)
    - Реализовывать по одному правилу за раз
    - Писать тесты сразу после реализации
    - Обновлять документацию после каждого правила

3. **Особые случаи:**
    - Правила, требующие доступа к БД (unique, exists) могут требовать дополнительной настройки
    - Правила для файлов могут быть не применимы для JSON-полей
    - Некоторые правила могут требовать внешние пакеты

---

**Дата создания:** 2025-01-02  
**Версия:** 1.0  
**Автор:** AI Assistant
