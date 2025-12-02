# Реализация автоматического создания правил типов данных

## Описание задачи

Реализовать автоматическое создание правил валидации типов данных на основе поля `data_type` модели `Path`. Правила должны создаваться по умолчанию при построении `RuleSet` в `EntryValidationService`, если в `validation_rules` не указано явное правило типа.

**Цель:** Упростить настройку валидации — пользователю не нужно явно указывать тип данных в `validation_rules`, если он уже указан в `data_type`.

---

## Текущее состояние

### Модель Path
- Поле `data_type`: `string|text|int|float|bool|datetime|json|ref`
- Поле `validation_rules`: `array|null` — правила валидации в JSON формате
- В `EntryValidationService` загружается только `validation_rules`, `data_type` не используется

### Маппинг data_type → Laravel правила

| data_type | Laravel правило | Примечание |
|-----------|----------------|------------|
| `string` | `string` | Строка |
| `text` | `string` | Длинный текст (тоже string) |
| `int` | `integer` | Целое число |
| `float` | `numeric` | Число (int или float) |
| `bool` | `boolean` | Булево значение |
| `datetime` | `date` | Дата/время |
| `json` | `array` | JSON массив/объект |
| `ref` | `integer` | Ссылка на Entry (ID) |

---

## План реализации (10 пунктов)

### Пункт 1: Создать доменное правило TypeRule

**Файл:** `app/Domain/Blueprint/Validation/Rules/TypeRule.php`

**Описание:**  
Создать доменное правило `TypeRule`, которое хранит тип данных для валидации.

**Код:**
```php
<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Правило валидации: тип данных.
 *
 * Автоматически создаётся на основе data_type из Path.
 * Преобразуется в соответствующее Laravel правило валидации (string, integer, numeric, boolean, date, array).
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class TypeRule implements Rule
{
    /**
     * @param string $type Тип данных (string, integer, numeric, boolean, date, array)
     */
    public function __construct(
        private readonly string $type
    ) {}

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return 'type';
    }

    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return ['type' => $this->type];
    }

    /**
     * Получить тип данных.
     *
     * @return string Тип данных
     */
    public function getDataType(): string
    {
        return $this->type;
    }
}
```

**Чек-лист:**
- [ ] Создан файл `TypeRule.php`
- [ ] Реализован интерфейс `Rule`
- [ ] Добавлен метод `getDataType()`
- [ ] PHPDoc соответствует коду

---

### Пункт 2: Создать TypeRuleHandler

**Файл:** `app/Domain/Blueprint/Validation/Rules/Handlers/TypeRuleHandler.php`

**Описание:**  
Создать handler для преобразования `TypeRule` в Laravel правила валидации.

**Код:**
```php
<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\Rule;
use App\Domain\Blueprint\Validation\Rules\TypeRule;

/**
 * Обработчик правила TypeRule.
 *
 * Преобразует TypeRule в строку Laravel правила валидации
 * (например, 'string', 'integer', 'numeric', 'boolean', 'date', 'array').
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class TypeRuleHandler implements RuleHandlerInterface
{
    /**
     * Маппинг типов данных на Laravel правила.
     *
     * @var array<string, string>
     */
    private const TYPE_MAPPING = [
        'string' => 'string',
        'integer' => 'integer',
        'numeric' => 'numeric',
        'boolean' => 'boolean',
        'date' => 'date',
        'array' => 'array',
    ];

    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'type';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule): array
    {
        if (! $rule instanceof TypeRule) {
            throw new \InvalidArgumentException('Expected TypeRule instance');
        }

        $type = $rule->getDataType();

        if (! isset(self::TYPE_MAPPING[$type])) {
            // Если тип не найден в маппинге, возвращаем пустой массив
            // (не должно происходить при корректной работе)
            return [];
        }

        return [self::TYPE_MAPPING[$type]];
    }
}
```

**Чек-лист:**
- [ ] Создан файл `TypeRuleHandler.php`
- [ ] Реализован интерфейс `RuleHandlerInterface`
- [ ] Добавлен маппинг типов данных
- [ ] Обработаны неизвестные типы

---

### Пункт 3: Добавить метод в RuleFactory

**Файл:** `app/Domain/Blueprint/Validation/Rules/RuleFactory.php`

**Описание:**  
Добавить метод `createTypeRule()` в интерфейс `RuleFactory`.

**Изменения:**
```php
/**
 * Создать правило типа данных.
 *
 * @param string $type Тип данных (string, integer, numeric, boolean, date, array)
 * @return \App\Domain\Blueprint\Validation\Rules\TypeRule
 */
public function createTypeRule(string $type): TypeRule;
```

**Чек-лист:**
- [ ] Добавлен метод в интерфейс `RuleFactory`
- [ ] Обновлён PHPDoc

---

### Пункт 4: Реализовать метод в RuleFactoryImpl

**Файл:** `app/Domain/Blueprint/Validation/Rules/RuleFactoryImpl.php`

**Описание:**  
Реализовать метод `createTypeRule()` в `RuleFactoryImpl`.

**Изменения:**
```php
/**
 * Создать правило типа данных.
 *
 * @param string $type Тип данных (string, integer, numeric, boolean, date, array)
 * @return \App\Domain\Blueprint\Validation\Rules\TypeRule
 */
public function createTypeRule(string $type): TypeRule
{
    return new TypeRule($type);
}
```

**Чек-лист:**
- [ ] Реализован метод в `RuleFactoryImpl`
- [ ] PHPDoc соответствует сигнатуре

---

### Пункт 5: Создать DataTypeMapper

**Файл:** `app/Domain/Blueprint/Validation/DataTypeMapper.php`

**Описание:**  
Создать сервис для маппинга `data_type` из Path в тип для валидации.

**Код:**
```php
<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

/**
 * Маппер типов данных Path в типы для валидации.
 *
 * Преобразует data_type из Path (string, text, int, float, bool, datetime, json, ref)
 * в типы для валидации (string, integer, numeric, boolean, date, array).
 *
 * @package App\Domain\Blueprint\Validation
 */
final class DataTypeMapper
{
    /**
     * Маппинг data_type → тип для валидации.
     *
     * @var array<string, string>
     */
    private const DATA_TYPE_MAPPING = [
        'string' => 'string',
        'text' => 'string',
        'int' => 'integer',
        'float' => 'numeric',
        'bool' => 'boolean',
        'datetime' => 'date',
        'json' => 'array',
        'ref' => 'integer',
    ];

    /**
     * Преобразовать data_type в тип для валидации.
     *
     * @param string $dataType data_type из Path (string, text, int, float, bool, datetime, json, ref)
     * @return string|null Тип для валидации или null, если тип неизвестен
     */
    public function mapToValidationType(string $dataType): ?string
    {
        return self::DATA_TYPE_MAPPING[$dataType] ?? null;
    }

    /**
     * Проверить, поддерживается ли data_type.
     *
     * @param string $dataType data_type из Path
     * @return bool true, если тип поддерживается
     */
    public function isSupported(string $dataType): bool
    {
        return isset(self::DATA_TYPE_MAPPING[$dataType]);
    }
}
```

**Чек-лист:**
- [ ] Создан файл `DataTypeMapper.php`
- [ ] Реализован маппинг всех типов данных
- [ ] Добавлены методы `mapToValidationType()` и `isSupported()`

---

### Пункт 6: Обновить EntryValidationService для загрузки data_type

**Файл:** `app/Domain/Blueprint/Validation/EntryValidationService.php`

**Описание:**  
Обновить `EntryValidationService` для загрузки `data_type` и автоматического создания правил типов.

**Изменения:**

1. Добавить зависимость `DataTypeMapper` в конструктор
2. Загружать `data_type` при выборке Path
3. Автоматически создавать `TypeRule` на основе `data_type`, если в `validation_rules` нет явного правила типа

**Код:**
```php
<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Models\Blueprint;
use App\Domain\Blueprint\Validation\FieldPathBuilder;
use App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface;
use App\Domain\Blueprint\Validation\Rules\RuleSet;

/**
 * Доменный сервис валидации контента Entry на основе Blueprint.
 *
 * Строит RuleSet для поля content_json на основе структуры Path в Blueprint.
 * Преобразует full_path в точечную нотацию и применяет validation_rules из каждого Path.
 * Автоматически создаёт правила типов данных на основе data_type, если они не указаны явно.
 *
 * @package App\Domain\Blueprint\Validation
 */
final class EntryValidationService implements EntryValidationServiceInterface
{
    /**
     * @param \App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface $converter Конвертер правил валидации
     * @param \App\Domain\Blueprint\Validation\FieldPathBuilder $fieldPathBuilder Построитель путей полей
     * @param \App\Domain\Blueprint\Validation\DataTypeMapper $dataTypeMapper Маппер типов данных
     * @param \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory Фабрика правил
     */
    public function __construct(
        private readonly PathValidationRulesConverterInterface $converter,
        private readonly FieldPathBuilder $fieldPathBuilder,
        private readonly DataTypeMapper $dataTypeMapper,
        private readonly \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory
    ) {}

    /**
     * Построить RuleSet для Blueprint.
     *
     * Анализирует все Path в blueprint и преобразует их validation_rules
     * в доменный RuleSet для поля content_json.
     * Автоматически добавляет правила типов данных на основе data_type,
     * если они не указаны явно в validation_rules.
     *
     * @param \App\Models\Blueprint $blueprint Blueprint для валидации
     * @return \App\Domain\Blueprint\Validation\Rules\RuleSet Набор правил валидации
     */
    public function buildRulesFor(Blueprint $blueprint): RuleSet
    {
        $ruleSet = new RuleSet();

        // Загружаем все Path из blueprint (включая скопированные)
        // Теперь загружаем также data_type для автоматического создания правил типов
        $paths = $blueprint->paths()
            ->select(['id', 'name', 'full_path', 'cardinality', 'data_type', 'validation_rules'])
            ->orderByRaw('LENGTH(full_path), full_path')
            ->get();

        if ($paths->isEmpty()) {
            return $ruleSet;
        }

        // Создаём маппинг full_path → cardinality для FieldPathBuilder
        $pathCardinalities = [];
        foreach ($paths as $path) {
            $pathCardinalities[$path->full_path] = $path->cardinality;
        }

        // Обрабатываем каждый Path
        foreach ($paths as $path) {
            // Преобразуем validation_rules в Rule объекты
            $fieldRules = $this->converter->convert($path->validation_rules);
            
            // Проверяем, есть ли явное правило типа в validation_rules
            $hasExplicitTypeRule = $this->hasExplicitTypeRule($path->validation_rules);
            
            // Если нет явного правила типа и data_type указан, создаём автоматически
            if (! $hasExplicitTypeRule && $path->data_type !== null) {
                $validationType = $this->dataTypeMapper->mapToValidationType($path->data_type);
                if ($validationType !== null) {
                    $fieldRules[] = $this->ruleFactory->createTypeRule($validationType);
                }
            }
            
            $fieldPath = $this->fieldPathBuilder->buildFieldPath(
                $path->full_path,
                $pathCardinalities,
            );
            
            // Добавляем все правила для поля
            foreach ($fieldRules as $rule) {
                $ruleSet->addRule($fieldPath, $rule);
            }
        }

        return $ruleSet;
    }

    /**
     * Проверить, есть ли явное правило типа в validation_rules.
     *
     * Проверяет наличие ключей 'type' или стандартных Laravel правил типов
     * (string, integer, numeric, boolean, date, array) в validation_rules.
     *
     * @param array<string, mixed>|null $validationRules Правила валидации
     * @return bool true, если найдено явное правило типа
     */
    private function hasExplicitTypeRule(?array $validationRules): bool
    {
        if ($validationRules === null || $validationRules === []) {
            return false;
        }

        // Проверяем наличие ключа 'type'
        if (isset($validationRules['type'])) {
            return true;
        }

        // Проверяем наличие стандартных Laravel правил типов
        $typeKeys = ['string', 'integer', 'int', 'numeric', 'boolean', 'bool', 'date', 'array'];
        foreach ($typeKeys as $typeKey) {
            if (isset($validationRules[$typeKey])) {
                return true;
            }
        }

        return false;
    }
}
```

**Чек-лист:**
- [ ] Добавлена зависимость `DataTypeMapper` в конструктор
- [ ] Добавлена зависимость `RuleFactory` в конструктор
- [ ] Обновлён `select()` для загрузки `data_type`
- [ ] Добавлен метод `hasExplicitTypeRule()`
- [ ] Реализована логика автоматического создания правил типов
- [ ] Обновлён PHPDoc

---

### Пункт 7: Зарегистрировать TypeRuleHandler в AppServiceProvider

**Файл:** `app/Providers/AppServiceProvider.php`

**Описание:**  
Зарегистрировать `TypeRuleHandler` в реестре обработчиков правил.

**Изменения:**
```php
use App\Domain\Blueprint\Validation\Rules\Handlers\TypeRuleHandler;

// В методе register(), в секции регистрации RuleHandlerRegistry:
$registry->register('type', new TypeRuleHandler());
```

**Чек-лист:**
- [ ] Добавлен use для `TypeRuleHandler`
- [ ] Зарегистрирован handler в `RuleHandlerRegistry`
- [ ] Проверена корректность регистрации

---

### Пункт 8: Написать unit-тесты для TypeRuleHandler

**Файл:** `tests/Unit/Domain/Blueprint/Validation/Rules/Handlers/TypeRuleHandlerTest.php`

**Описание:**  
Написать тесты для `TypeRuleHandler`, проверяющие преобразование всех типов данных.

**Код:**
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\Handlers\TypeRuleHandler;
use App\Domain\Blueprint\Validation\Rules\TypeRule;
use Tests\TestCase;

final class TypeRuleHandlerTest extends TestCase
{
    private TypeRuleHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new TypeRuleHandler();
    }

    public function test_supports_type_rule_type(): void
    {
        $this->assertTrue($this->handler->supports('type'));
        $this->assertFalse($this->handler->supports('other'));
    }

    public function test_handles_string_type(): void
    {
        $rule = new TypeRule('string');
        $result = $this->handler->handle($rule);

        $this->assertEquals(['string'], $result);
    }

    public function test_handles_integer_type(): void
    {
        $rule = new TypeRule('integer');
        $result = $this->handler->handle($rule);

        $this->assertEquals(['integer'], $result);
    }

    public function test_handles_numeric_type(): void
    {
        $rule = new TypeRule('numeric');
        $result = $this->handler->handle($rule);

        $this->assertEquals(['numeric'], $result);
    }

    public function test_handles_boolean_type(): void
    {
        $rule = new TypeRule('boolean');
        $result = $this->handler->handle($rule);

        $this->assertEquals(['boolean'], $result);
    }

    public function test_handles_date_type(): void
    {
        $rule = new TypeRule('date');
        $result = $this->handler->handle($rule);

        $this->assertEquals(['date'], $result);
    }

    public function test_handles_array_type(): void
    {
        $rule = new TypeRule('array');
        $result = $this->handler->handle($rule);

        $this->assertEquals(['array'], $result);
    }

    public function test_handles_unknown_type_returns_empty(): void
    {
        $rule = new TypeRule('unknown');
        $result = $this->handler->handle($rule);

        $this->assertEquals([], $result);
    }

    public function test_throws_exception_for_wrong_rule_type(): void
    {
        $wrongRule = new \App\Domain\Blueprint\Validation\Rules\RequiredRule();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected TypeRule instance');

        $this->handler->handle($wrongRule);
    }
}
```

**Чек-лист:**
- [ ] Создан файл тестов
- [ ] Написаны тесты для всех типов данных
- [ ] Написан тест для неизвестного типа
- [ ] Написан тест для неправильного типа правила
- [ ] Все тесты проходят

---

### Пункт 9: Написать unit-тесты для DataTypeMapper

**Файл:** `tests/Unit/Domain/Blueprint/Validation/DataTypeMapperTest.php`

**Описание:**  
Написать тесты для `DataTypeMapper`, проверяющие маппинг всех типов данных.

**Код:**
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\DataTypeMapper;
use Tests\TestCase;

final class DataTypeMapperTest extends TestCase
{
    private DataTypeMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new DataTypeMapper();
    }

    public function test_maps_string_to_string(): void
    {
        $this->assertEquals('string', $this->mapper->mapToValidationType('string'));
    }

    public function test_maps_text_to_string(): void
    {
        $this->assertEquals('string', $this->mapper->mapToValidationType('text'));
    }

    public function test_maps_int_to_integer(): void
    {
        $this->assertEquals('integer', $this->mapper->mapToValidationType('int'));
    }

    public function test_maps_float_to_numeric(): void
    {
        $this->assertEquals('numeric', $this->mapper->mapToValidationType('float'));
    }

    public function test_maps_bool_to_boolean(): void
    {
        $this->assertEquals('boolean', $this->mapper->mapToValidationType('bool'));
    }

    public function test_maps_datetime_to_date(): void
    {
        $this->assertEquals('date', $this->mapper->mapToValidationType('datetime'));
    }

    public function test_maps_json_to_array(): void
    {
        $this->assertEquals('array', $this->mapper->mapToValidationType('json'));
    }

    public function test_maps_ref_to_integer(): void
    {
        $this->assertEquals('integer', $this->mapper->mapToValidationType('ref'));
    }

    public function test_returns_null_for_unknown_type(): void
    {
        $this->assertNull($this->mapper->mapToValidationType('unknown'));
    }

    public function test_is_supported_returns_true_for_known_types(): void
    {
        $types = ['string', 'text', 'int', 'float', 'bool', 'datetime', 'json', 'ref'];
        
        foreach ($types as $type) {
            $this->assertTrue($this->mapper->isSupported($type), "Type {$type} should be supported");
        }
    }

    public function test_is_supported_returns_false_for_unknown_type(): void
    {
        $this->assertFalse($this->mapper->isSupported('unknown'));
    }
}
```

**Чек-лист:**
- [ ] Создан файл тестов
- [ ] Написаны тесты для всех типов данных
- [ ] Написан тест для неизвестного типа
- [ ] Написан тест для `isSupported()`
- [ ] Все тесты проходят

---

### Пункт 10: Написать интеграционные тесты для EntryValidationService

**Файл:** `tests/Unit/Domain/Blueprint/Validation/EntryValidationServiceTest.php` (обновить существующий или создать новый)

**Описание:**  
Написать интеграционные тесты, проверяющие автоматическое создание правил типов данных.

**Тестовые сценарии:**

1. **Автоматическое создание правила типа, если не указано явно:**
   - Path с `data_type = 'string'`, без `validation_rules['type']`
   - Ожидается: правило `string` добавлено автоматически

2. **Не создавать правило типа, если указано явно:**
   - Path с `data_type = 'string'`, с `validation_rules['type'] = 'integer'`
   - Ожидается: используется явное правило `integer`, автоматическое не создаётся

3. **Не создавать правило типа, если указано стандартное Laravel правило:**
   - Path с `data_type = 'string'`, с `validation_rules['string'] = true`
   - Ожидается: используется явное правило, автоматическое не создаётся

4. **Создавать правило типа для всех типов данных:**
   - Проверить все типы: string, text, int, float, bool, datetime, json, ref

5. **Не создавать правило типа, если data_type = null:**
   - Path без `data_type`
   - Ожидается: правило типа не создаётся

**Пример кода:**
```php
public function test_automatically_creates_type_rule_when_not_explicit(): void
{
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_type' => 'string',
        'validation_rules' => ['required' => true],
    ]);

    $service = app(EntryValidationServiceInterface::class);
    $ruleSet = $service->buildRulesFor($blueprint);

    $fieldPath = 'content_json.' . $path->full_path;
    $rules = $ruleSet->getRulesForField($fieldPath);

    // Проверяем, что есть правило типа string
    $hasStringRule = false;
    foreach ($rules as $rule) {
        if ($rule->getType() === 'type' && $rule instanceof TypeRule) {
            if ($rule->getDataType() === 'string') {
                $hasStringRule = true;
                break;
            }
        }
    }

    $this->assertTrue($hasStringRule, 'Type rule should be automatically created');
}

public function test_does_not_create_type_rule_when_explicit(): void
{
    $blueprint = Blueprint::factory()->create();
    $path = Path::factory()->create([
        'blueprint_id' => $blueprint->id,
        'data_type' => 'string',
        'validation_rules' => ['type' => 'integer'],
    ]);

    $service = app(EntryValidationServiceInterface::class);
    $ruleSet = $service->buildRulesFor($blueprint);

    $fieldPath = 'content_json.' . $path->full_path;
    $rules = $ruleSet->getRulesForField($fieldPath);

    // Проверяем, что нет автоматического правила типа string
    $hasAutoStringRule = false;
    foreach ($rules as $rule) {
        if ($rule->getType() === 'type' && $rule instanceof TypeRule) {
            if ($rule->getDataType() === 'string') {
                $hasAutoStringRule = true;
                break;
            }
        }
    }

    $this->assertFalse($hasAutoStringRule, 'Auto type rule should not be created when explicit');
}
```

**Чек-лист:**
- [ ] Написаны тесты для всех сценариев
- [ ] Тесты проверяют автоматическое создание правил
- [ ] Тесты проверяют отсутствие дублирования при явном указании
- [ ] Все тесты проходят

---

## Общий чек-лист реализации

- [ ] Пункт 1: Создан `TypeRule`
- [ ] Пункт 2: Создан `TypeRuleHandler`
- [ ] Пункт 3: Добавлен метод в `RuleFactory`
- [ ] Пункт 4: Реализован метод в `RuleFactoryImpl`
- [ ] Пункт 5: Создан `DataTypeMapper`
- [ ] Пункт 6: Обновлён `EntryValidationService`
- [ ] Пункт 7: Зарегистрирован handler в `AppServiceProvider`
- [ ] Пункт 8: Написаны тесты для `TypeRuleHandler`
- [ ] Пункт 9: Написаны тесты для `DataTypeMapper`
- [ ] Пункт 10: Написаны интеграционные тесты
- [ ] Все тесты проходят: `php artisan test`
- [ ] Обновлена документация (если требуется)
- [ ] Проверено соответствие PHPDoc

---

## Порядок выполнения

Рекомендуемый порядок реализации:

1. **Пункт 5** → Создать `DataTypeMapper` (независимый компонент)
2. **Пункт 1** → Создать `TypeRule`
3. **Пункт 2** → Создать `TypeRuleHandler`
4. **Пункт 3-4** → Добавить методы в `RuleFactory` и `RuleFactoryImpl`
5. **Пункт 7** → Зарегистрировать handler
6. **Пункт 6** → Обновить `EntryValidationService`
7. **Пункт 9** → Написать тесты для `DataTypeMapper`
8. **Пункт 8** → Написать тесты для `TypeRuleHandler`
9. **Пункт 10** → Написать интеграционные тесты
10. **Финальная проверка** → Запустить все тесты, проверить PHPDoc

---

## Примеры использования

### До реализации
```json
{
  "data_type": "string",
  "validation_rules": {
    "required": true,
    "max": 255
  }
}
```
**Проблема:** Нужно явно указывать `"type": "string"` в `validation_rules`.

### После реализации
```json
{
  "data_type": "string",
  "validation_rules": {
    "required": true,
    "max": 255
  }
}
```
**Результат:** Правило `string` создаётся автоматически на основе `data_type`.

### Явное указание типа (приоритет)
```json
{
  "data_type": "string",
  "validation_rules": {
    "type": "integer",
    "required": true
  }
}
```
**Результат:** Используется явное правило `integer`, автоматическое не создаётся.

---

## Примечания

1. **Приоритет правил:** Явно указанные правила в `validation_rules` имеют приоритет над автоматическими.
2. **Обратная совместимость:** Существующие Blueprint продолжат работать, так как автоматические правила добавляются только если не указаны явно.
3. **Производительность:** Загрузка `data_type` не влияет на производительность, так как поле уже загружается в том же запросе.
4. **Расширяемость:** При добавлении новых типов данных нужно обновить `DataTypeMapper::DATA_TYPE_MAPPING`.

---

**Дата создания:** 2025-01-02  
**Версия:** 1.0  
**Автор:** AI Assistant

