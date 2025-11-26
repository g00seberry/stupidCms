# Альтернативный подход к межполейным зависимостям

## Анализ текущей архитектуры

Текущая система валидации построена на принципе:
1. **Path.validation_rules** (JSON) → доменные **Rule** объекты → **Handlers** → Laravel правила
2. Уже есть поддержка условных правил через `ConditionalRule` (required_if, prohibited_unless и т.д.)
3. Все правила хранятся в одном месте (`validation_rules`) и обрабатываются единообразно

## Проблема предложенного подхода

План предлагает:
- Отдельное поле `depends_on` в таблице `paths`
- Отдельный слой `FieldDependency` и `DependencyResolver`
- Дублирование логики (зависимости отдельно от правил)

**Недостатки:**
- Нарушает принцип единого источника правды (`validation_rules`)
- Создаёт дополнительный слой абстракции
- Усложняет поддержку (правила в двух местах)

## Предлагаемое решение

Использовать существующую систему правил для межполейных зависимостей.

### 1. Новое правило: `FieldComparisonRule`

```php
// app/Domain/Blueprint/Validation/Rules/FieldComparisonRule.php
final class FieldComparisonRule implements Rule
{
    /**
     * @param string $operator Оператор сравнения ('>=', '<=', '>', '<', '==', '!=')
     * @param string $otherField Путь к другому полю для сравнения (например, 'content_json.start_date')
     * @param mixed|null $constantValue Константное значение для сравнения (если указано, используется вместо otherField)
     */
    public function __construct(
        private readonly string $operator,
        private readonly string $otherField,
        private readonly mixed $constantValue = null
    ) {}
    
    public function getType(): string
    {
        return 'field_comparison';
    }
    
    public function getParams(): array
    {
        return [
            'operator' => $this->operator,
            'other_field' => $this->otherField,
            'constant_value' => $this->constantValue,
        ];
    }
    
    public function getOperator(): string
    {
        return $this->operator;
    }
    
    public function getOtherField(): string
    {
        return $this->otherField;
    }
    
    public function getConstantValue(): mixed
    {
        return $this->constantValue;
    }
}
```

### 2. Handler для правила

```php
// app/Domain/Blueprint/Validation/Rules/Handlers/FieldComparisonRuleHandler.php
final class FieldComparisonRuleHandler implements RuleHandlerInterface
{
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'field_comparison';
    }
    
    public function handle(Rule $rule, string $dataType): array
    {
        if (! $rule instanceof FieldComparisonRule) {
            throw new \InvalidArgumentException('Expected FieldComparisonRule instance');
        }
        
        // Возвращаем имя класса кастомного Laravel правила
        // Это правило будет создано в следующем шаге
        return [new \App\Rules\FieldComparison(
            $rule->getOperator(),
            $rule->getOtherField(),
            $rule->getConstantValue()
        )];
    }
}
```

### 3. Laravel custom rule

```php
// app/Rules/FieldComparison.php
final class FieldComparison implements ValidationRule, DataAwareRule
{
    private array $data = [];
    
    public function __construct(
        private readonly string $operator,
        private readonly string $otherField,
        private readonly mixed $constantValue = null
    ) {}
    
    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }
    
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Получаем значение для сравнения
        $compareValue = $this->constantValue;
        if ($compareValue === null) {
            $compareValue = data_get($this->data, $this->otherField);
        }
        
        if ($compareValue === null) {
            return; // Если сравниваемое поле отсутствует, пропускаем валидацию
        }
        
        // Выполняем сравнение
        $result = match ($this->operator) {
            '>=' => $this->compare($value, $compareValue) >= 0,
            '<=' => $this->compare($value, $compareValue) <= 0,
            '>' => $this->compare($value, $compareValue) > 0,
            '<' => $this->compare($value, $compareValue) < 0,
            '==' => $this->compare($value, $compareValue) === 0,
            '!=' => $this->compare($value, $compareValue) !== 0,
            default => false,
        };
        
        if (! $result) {
            $fail("The :attribute must be {$this->operator} {$this->otherField}.");
        }
    }
    
    private function compare(mixed $a, mixed $b): int
    {
        // Для дат используем сравнение через Carbon
        if ($a instanceof \DateTimeInterface && $b instanceof \DateTimeInterface) {
            return $a <=> $b;
        }
        
        // Для строк приводим к датам, если возможно
        if (is_string($a) && is_string($b)) {
            try {
                $dateA = \Carbon\Carbon::parse($a);
                $dateB = \Carbon\Carbon::parse($b);
                return $dateA <=> $dateB;
            } catch (\Exception $e) {
                // Не даты, сравниваем как строки
            }
        }
        
        // Стандартное сравнение
        return $a <=> $b;
    }
}
```

### 4. Обновление RuleFactory

```php
// Добавить в app/Domain/Blueprint/Validation/Rules/RuleFactory.php
public function createFieldComparisonRule(
    string $operator,
    string $otherField,
    mixed $constantValue = null
): FieldComparisonRule;
```

### 5. Обновление PathValidationRulesConverter

```php
// В методе convert() добавить обработку:
'field_comparison' => $this->handleFieldComparisonRule($rules, $value),

// Новый метод:
private function handleFieldComparisonRule(array &$rules, mixed $value): void
{
    if (is_array($value)) {
        $operator = $value['operator'] ?? '>=';
        $otherField = $value['field'] ?? null;
        $constantValue = $value['value'] ?? null;
        
        if ($otherField !== null) {
            $rules[] = $this->ruleFactory->createFieldComparisonRule(
                $operator,
                $otherField,
                $constantValue
            );
        }
    }
}
```

### 6. Формат в validation_rules

```json
{
  "min": 0,
  "max": 100,
  "field_comparison": {
    "operator": ">=",
    "field": "content_json.start_date"
  }
}
```

Или с константным значением:
```json
{
  "field_comparison": {
    "operator": ">=",
    "value": "2024-01-01"
  }
}
```

## Преимущества подхода

1. **Консистентность**: все правила в одном месте (`validation_rules`)
2. **Единообразие**: используется та же архитектура (Rule → Handler → Laravel)
3. **Простота**: не нужен отдельный слой `DependencyResolver`
4. **Гибкость**: можно сравнивать с другим полем или константой
5. **Расширяемость**: легко добавить новые операторы через handler

## Миграция не требуется

Не нужно добавлять поле `depends_on` в таблицу `paths` — всё хранится в `validation_rules`.

## Примеры использования

### Пример 1: end_date >= start_date

```json
// Для поля end_date в validation_rules:
{
  "field_comparison": {
    "operator": ">=",
    "field": "content_json.start_date"
  }
}
```

### Пример 2: price >= min_price (константа)

```json
// Для поля price в validation_rules:
{
  "field_comparison": {
    "operator": ">=",
    "value": 0
  }
}
```

### Пример 3: Комбинация с другими правилами

```json
{
  "required": true,
  "field_comparison": {
    "operator": ">=",
    "field": "content_json.start_date"
  },
  "min": 0
}
```

## Тестирование

Тесты должны покрывать:
1. `FieldComparisonRule` — создание и получение параметров
2. `FieldComparisonRuleHandler` — преобразование в Laravel правило
3. `FieldComparison` (Laravel rule) — логику сравнения
4. `PathValidationRulesConverter` — парсинг из validation_rules
5. Интеграционные тесты с реальными данными

## Сравнение с предложенным подходом

| Аспект | Предложенный подход | Альтернативный подход |
|--------|---------------------|----------------------|
| Хранение | Отдельное поле `depends_on` | В `validation_rules` |
| Архитектура | Отдельный слой `DependencyResolver` | Использует существующую систему правил |
| Консистентность | Правила в двух местах | Все правила в одном месте |
| Сложность | Выше (дополнительный слой) | Ниже (расширение существующей системы) |
| Гибкость | Только сравнение полей | Сравнение с полем или константой |

## Рекомендация

Использовать альтернативный подход, так как он:
- Лучше вписывается в текущую архитектуру
- Не требует миграции БД
- Проще в поддержке
- Более гибкий

