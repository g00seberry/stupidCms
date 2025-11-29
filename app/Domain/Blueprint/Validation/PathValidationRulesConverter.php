<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\Rules\Rule;
use App\Domain\Blueprint\Validation\Rules\RuleFactory;
use App\Domain\Blueprint\Validation\ValidationConstants;

/**
 * Конвертер правил валидации из Path в доменные Rule объекты.
 *
 * Преобразует validation_rules из модели Path в массив доменных Rule объектов,
 * учитывая data_type, required (из validation_rules) и cardinality.
 *
 * @package App\Domain\Blueprint\Validation
 */
final class PathValidationRulesConverter implements PathValidationRulesConverterInterface
{
    /**
     * @param \App\Domain\Blueprint\Validation\Rules\RuleFactory $ruleFactory Фабрика для создания правил
     */
    public function __construct(
        private readonly RuleFactory $ruleFactory
    ) {}

    /**
     * Преобразовать validation_rules из Path в доменные Rule объекты.
     *
     * Преобразует правила валидации с учётом:
     * - data_type: определяет базовый тип валидации (string, integer, numeric, boolean, date, datetime)
     * - required: извлекается из validation_rules['required'], добавляет RequiredRule или NullableRule (только для cardinality: 'one')
     * - cardinality: для 'many' возвращает правила для элементов массива (без required/nullable,
     *   так как они применяются к самому массиву в BlueprintContentValidator)
     * - validation_rules: преобразует min/max, pattern и другие правила в соответствующие Rule объекты
     *
     * @param array<string, mixed>|null $validationRules Правила валидации из Path (может быть null)
     *                                                   Должен содержать 'required' => true/false для обязательности поля
     * @param string $dataType Тип данных Path (string, text, int, float, bool, date, datetime, json, ref)
     * @param string $cardinality Кардинальность: 'one' или 'many'
     * @param string|null $fieldName Имя поля (последний сегмент full_path) для использования в unique/exists правилах
     * @return list<\App\Domain\Blueprint\Validation\Rules\Rule> Массив доменных Rule объектов
     *         Для cardinality: 'one' - правила для самого поля
     *         Для cardinality: 'many' - правила для элементов массива (без RequiredRule/NullableRule)
     */
    public function convert(
        ?array $validationRules,
        string $dataType,
        string $cardinality,
        ?string $fieldName = null
    ): array {
        $rules = [];

        // Извлекаем required из validation_rules
        $isRequired = $validationRules['required'] ?? false;

        // Добавляем required или nullable
        // Для cardinality: 'many' required/nullable применяется к самому массиву,
        // а не к элементам, поэтому здесь не добавляем
        if ($cardinality !== ValidationConstants::CARDINALITY_MANY) {
            if ($isRequired) {
                $rules[] = $this->ruleFactory->createRequiredRule();
            } else {
                $rules[] = $this->ruleFactory->createNullableRule();
            }
        }

        // Если нет validation_rules, возвращаем только базовые правила (required/nullable)
        if ($validationRules === null || $validationRules === []) {
            return $rules;
        }

        // Валидируем и преобразуем validation_rules в Rule объекты
        $minValue = null;
        $maxValue = null;
        $arrayMinItems = null;
        $arrayMaxItems = null;

        foreach ($validationRules as $key => $value) {
            match ($key) {
                'min' => $minValue = $value,
                'max' => $maxValue = $value,
                'pattern' => $rules[] = $this->ruleFactory->createPatternRule($value),
                'array_min_items' => $arrayMinItems = $value,
                'array_max_items' => $arrayMaxItems = $value,
                'array_unique' => $this->handleArrayUniqueRule($rules, $cardinality),
                'required_if', 'prohibited_unless', 'required_unless', 'prohibited_if' => $this->handleConditionalRule($rules, $key, $value),
                'unique' => $this->handleUniqueRule($rules, $value, $fieldName),
                'exists' => $this->handleExistsRule($rules, $value, $fieldName),
                'field_comparison' => $this->handleFieldComparisonRule($rules, $value),
                default => null, // Игнорируем неизвестные ключи
            };
        }

        // Добавляем правила для массивов (только для cardinality: 'many')
        if ($cardinality === ValidationConstants::CARDINALITY_MANY) {
            if ($arrayMinItems !== null && is_numeric($arrayMinItems)) {
                $rules[] = $this->ruleFactory->createArrayMinItemsRule((int) $arrayMinItems);
            }
            if ($arrayMaxItems !== null && is_numeric($arrayMaxItems)) {
                $rules[] = $this->ruleFactory->createArrayMaxItemsRule((int) $arrayMaxItems);
            }
        }

        // Валидируем min/max: min должен быть меньше или равен max
        if ($minValue !== null && $maxValue !== null) {
            $minNumeric = is_numeric($minValue) ? ($dataType === 'float' ? (float) $minValue : (int) $minValue) : null;
            $maxNumeric = is_numeric($maxValue) ? ($dataType === 'float' ? (float) $maxValue : (int) $maxValue) : null;

            if ($minNumeric !== null && $maxNumeric !== null && $minNumeric > $maxNumeric) {
                // Если min > max, игнорируем оба правила (валидация не пройдёт)
                // В реальном сценарии это должно логироваться, но для валидации просто пропускаем
                return $rules;
            }
        }

        // Добавляем min и max правила после валидации
        if ($minValue !== null) {
            $rules[] = $this->ruleFactory->createMinRule($minValue, $dataType);
        }
        if ($maxValue !== null) {
            $rules[] = $this->ruleFactory->createMaxRule($maxValue, $dataType);
        }

        return $rules;
    }

    /**
     * Обработать условное правило валидации.
     *
     * Поддерживает только расширенный формат:
     * - 'required_if' => ['field' => 'is_published', 'value' => true, 'operator' => '==']
     *
     * @param list<\App\Domain\Blueprint\Validation\Rules\Rule> $rules Массив правил (изменяется по ссылке)
     * @param string $type Тип условного правила ('required_if', 'prohibited_unless', 'required_unless', 'prohibited_if')
     * @param mixed $value Значение условия (должно быть массивом с ключами 'field', 'value', 'operator')
     * @return void
     * @throws \InvalidArgumentException Если значение не является массивом или отсутствует обязательное поле 'field'
     */
    private function handleConditionalRule(array &$rules, string $type, mixed $value): void
    {
        if (! is_array($value)) {
            throw new \InvalidArgumentException(
                "Условное правило '{$type}' должно быть массивом с ключами 'field', 'value' и опционально 'operator'."
            );
        }

        $field = $value['field'] ?? null;
        $conditionValue = $value['value'] ?? true;
        $operator = $value['operator'] ?? null;

        if ($field === null || $field === '') {
            throw new \InvalidArgumentException(
                "Условное правило '{$type}' должно содержать обязательное поле 'field'."
            );
        }

        $rules[] = $this->ruleFactory->createConditionalRule($type, $field, $conditionValue, $operator);
    }

    /**
     * Обработать правило уникальности элементов массива.
     *
     * @param list<\App\Domain\Blueprint\Validation\Rules\Rule> $rules Массив правил (изменяется по ссылке)
     * @param string $cardinality Кардинальность поля
     * @return void
     */
    private function handleArrayUniqueRule(array &$rules, string $cardinality): void
    {
        // Правило применяется только к массивам
        if ($cardinality === ValidationConstants::CARDINALITY_MANY) {
            $rules[] = $this->ruleFactory->createArrayUniqueRule();
        }
    }

    /**
     * Обработать правило уникальности значения.
     *
     * Поддерживает только расширенный формат:
     * - 'unique' => ['table' => 'table_name', 'column' => 'column_name']
     * - 'unique' => ['table' => 'table_name', 'column' => 'column_name', 'except' => ['column' => 'id', 'value' => 1]]
     * - 'unique' => ['table' => 'table_name', 'column' => 'column_name', 'where' => ['column' => 'status', 'value' => 'active']]
     *
     * @param list<\App\Domain\Blueprint\Validation\Rules\Rule> $rules Массив правил (изменяется по ссылке)
     * @param mixed $value Значение правила (должно быть массивом с обязательным ключом 'table')
     * @param string|null $fieldName Имя поля (последний сегмент full_path) для использования как колонка по умолчанию
     * @return void
     * @throws \InvalidArgumentException Если значение не является массивом или отсутствует обязательное поле 'table'
     */
    private function handleUniqueRule(array &$rules, mixed $value, ?string $fieldName = null): void
    {
        if (! is_array($value)) {
            throw new \InvalidArgumentException(
                "Правило 'unique' должно быть массивом с обязательным ключом 'table'."
            );
        }

        $table = $value['table'] ?? null;
        $column = $value['column'] ?? ($fieldName ?? 'id');
        $exceptColumn = $value['except']['column'] ?? null;
        $exceptValue = $value['except']['value'] ?? null;
        $whereColumn = $value['where']['column'] ?? null;
        $whereValue = $value['where']['value'] ?? null;

        if ($table === null || $table === '') {
            throw new \InvalidArgumentException(
                "Правило 'unique' должно содержать обязательное поле 'table'."
            );
        }

        $rules[] = $this->ruleFactory->createUniqueRule($table, $column, $exceptColumn, $exceptValue, $whereColumn, $whereValue);
    }

    /**
     * Обработать правило существования значения.
     *
     * Поддерживает только расширенный формат:
     * - 'exists' => ['table' => 'table_name', 'column' => 'column_name']
     * - 'exists' => ['table' => 'table_name', 'column' => 'column_name', 'where' => ['column' => 'status', 'value' => 'active']]
     *
     * @param list<\App\Domain\Blueprint\Validation\Rules\Rule> $rules Массив правил (изменяется по ссылке)
     * @param mixed $value Значение правила (должно быть массивом с обязательным ключом 'table')
     * @param string|null $fieldName Имя поля (последний сегмент full_path) для использования как колонка по умолчанию
     * @return void
     * @throws \InvalidArgumentException Если значение не является массивом или отсутствует обязательное поле 'table'
     */
    private function handleExistsRule(array &$rules, mixed $value, ?string $fieldName = null): void
    {
        if (! is_array($value)) {
            throw new \InvalidArgumentException(
                "Правило 'exists' должно быть массивом с обязательным ключом 'table'."
            );
        }

        $table = $value['table'] ?? null;
        $column = $value['column'] ?? ($fieldName ?? 'id');
        $whereColumn = $value['where']['column'] ?? null;
        $whereValue = $value['where']['value'] ?? null;

        if ($table === null || $table === '') {
            throw new \InvalidArgumentException(
                "Правило 'exists' должно содержать обязательное поле 'table'."
            );
        }

        $rules[] = $this->ruleFactory->createExistsRule($table, $column, $whereColumn, $whereValue);
    }

    /**
     * Обработать правило сравнения поля с другим полем или константой.
     *
     * Поддерживает форматы:
     * - 'field_comparison' => ['operator' => '>=', 'field' => 'content_json.start_date']
     * - 'field_comparison' => ['operator' => '>=', 'value' => '2024-01-01'] (с константой)
     * - 'field_comparison' => ['operator' => '>=', 'field' => 'content_json.start_date', 'value' => null] (только поле)
     *
     * @param list<\App\Domain\Blueprint\Validation\Rules\Rule> $rules Массив правил (изменяется по ссылке)
     * @param mixed $value Значение правила (массив)
     * @return void
     */
    private function handleFieldComparisonRule(array &$rules, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        $operator = $value['operator'] ?? '>=';
        $otherField = $value['field'] ?? null;
        $constantValue = $value['value'] ?? null;

        // Если указано поле, используем его (приоритет полю над константой)
        if ($otherField !== null && $otherField !== '') {
            $rules[] = $this->ruleFactory->createFieldComparisonRule($operator, $otherField, null);
            return;
        }

        // Если указано только константное значение, используем пустое поле (будет использоваться constantValue)
        if ($constantValue !== null) {
            $rules[] = $this->ruleFactory->createFieldComparisonRule($operator, '', $constantValue);
        }
    }

}
