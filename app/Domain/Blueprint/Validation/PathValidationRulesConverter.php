<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\Exceptions\InvalidValidationRuleException;
use App\Domain\Blueprint\Validation\Rules\Rule;
use App\Domain\Blueprint\Validation\Rules\RuleFactory;

/**
 * Конвертер правил валидации из Path в доменные Rule объекты.
 *
 * Преобразует validation_rules из модели Path в массив доменных Rule объектов.
 * Не выполняет проверок совместимости правил с типами данных или cardinality.
 * Пользователь сам отвечает за корректность настройки правил.
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
     * Преобразует все ключи из validation_rules в Rule объекты напрямую,
     * без проверок совместимости с типами данных или cardinality.
     *
     * @param array<string, mixed>|null $validationRules Правила валидации из Path (может быть null)
     * @return list<\App\Domain\Blueprint\Validation\Rules\Rule> Массив доменных Rule объектов
     * @throws \App\Domain\Blueprint\Validation\Exceptions\InvalidValidationRuleException Если встречено неизвестное правило
     */
    public function convert(?array $validationRules): array {
        $rules = [];

        // Если нет validation_rules, возвращаем пустой массив
        if ($validationRules === null || $validationRules === []) {
            return $rules;
        }

        foreach ($validationRules as $key => $value) {
            match ($key) {
                'required' => $this->handleRequiredRule($rules, $value),
                'min' => $rules[] = $this->ruleFactory->createMinRule($value),
                'max' => $rules[] = $this->ruleFactory->createMaxRule($value),
                'pattern' => $rules[] = $this->ruleFactory->createPatternRule($value),
                'distinct' => $rules[] = $this->ruleFactory->createDistinctRule(),
                'required_if', 'prohibited_unless', 'required_unless', 'prohibited_if' => $this->handleConditionalRule($rules, $key, $value),
                'field_comparison' => $this->handleFieldComparisonRule($rules, $value),
                default => throw new InvalidValidationRuleException("Неизвестное правило валидации: {$key}"),
            };
        }

        return $rules;
    }

    /**
     * Обработать правило required/nullable.
     *
     * Добавляет RequiredRule или NullableRule в зависимости от значения.
     *
     * @param list<\App\Domain\Blueprint\Validation\Rules\Rule> $rules Массив правил (изменяется по ссылке)
     * @param bool $isRequired Обязательность поля
     * @return void
     */
    private function handleRequiredRule(array &$rules, bool $isRequired): void
    {
        if ($isRequired) {
            $rules[] = $this->ruleFactory->createRequiredRule();
        } else {
            $rules[] = $this->ruleFactory->createNullableRule();
        }
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
     * @throws \App\Domain\Blueprint\Validation\Exceptions\InvalidValidationRuleException Если значение не является массивом или отсутствует обязательное поле 'field'
     */
    private function handleConditionalRule(array &$rules, string $type, mixed $value): void
    {
        if (! is_array($value)) {
            throw new InvalidValidationRuleException(
                "Условное правило '{$type}' должно быть массивом с ключами 'field', 'value' и опционально 'operator'."
            );
        }

        $field = $value['field'] ?? null;
        $conditionValue = $value['value'] ?? true;
        $operator = $value['operator'] ?? null;

        if ($field === null || $field === '') {
            throw new InvalidValidationRuleException(
                "Условное правило '{$type}' должно содержать обязательное поле 'field'."
            );
        }

        $rules[] = $this->ruleFactory->createConditionalRule($type, $field, $conditionValue, $operator);
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
