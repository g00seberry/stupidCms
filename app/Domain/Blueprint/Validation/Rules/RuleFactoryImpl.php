<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

use App\Domain\Blueprint\Validation\Rules\FieldComparisonRule;

/**
 * Реализация фабрики правил валидации.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class RuleFactoryImpl implements RuleFactory
{
    /**
     * Создать правило минимального значения/длины.
     *
     * @param mixed $value Значение минимума
     * @return \App\Domain\Blueprint\Validation\Rules\MinRule
     */
    public function createMinRule(mixed $value): MinRule
    {
        return new MinRule($value);
    }

    /**
     * Создать правило максимального значения/длины.
     *
     * @param mixed $value Значение максимума
     * @return \App\Domain\Blueprint\Validation\Rules\MaxRule
     */
    public function createMaxRule(mixed $value): MaxRule
    {
        return new MaxRule($value);
    }

    /**
     * Создать правило регулярного выражения.
     *
     * Валидирует и нормализует паттерн перед созданием правила.
     *
     * @param mixed $pattern Паттерн регулярного выражения
     * @return \App\Domain\Blueprint\Validation\Rules\PatternRule
     */
    public function createPatternRule(mixed $pattern): PatternRule
    {
        if (! is_string($pattern) || $pattern === '') {
            // Возвращаем паттерн, который принимает всё (для безопасности)
            return new PatternRule('.*');
        }

        return new PatternRule($pattern);
    }

    /**
     * Создать правило обязательности поля.
     *
     * @return \App\Domain\Blueprint\Validation\Rules\RequiredRule
     */
    public function createRequiredRule(): RequiredRule
    {
        return new RequiredRule();
    }

    /**
     * Создать правило опциональности поля (nullable).
     *
     * @return \App\Domain\Blueprint\Validation\Rules\NullableRule
     */
    public function createNullableRule(): NullableRule
    {
        return new NullableRule();
    }

    /**
     * Создать условное правило валидации.
     *
     * @param string $type Тип правила ('required_if', 'prohibited_unless', 'required_unless', 'prohibited_if')
     * @param string $field Путь к полю условия
     * @param mixed $value Значение для условия
     * @param string|null $operator Оператор сравнения (по умолчанию '==')
     * @return \App\Domain\Blueprint\Validation\Rules\ConditionalRule
     */
    public function createConditionalRule(string $type, string $field, mixed $value, ?string $operator = null): ConditionalRule
    {
        return new ConditionalRule($type, $field, $value, $operator);
    }

    /**
     * Создать правило уникальности элементов массива.
     *
     * @return \App\Domain\Blueprint\Validation\Rules\DistinctRule
     */
    public function createDistinctRule(): DistinctRule
    {
        return new DistinctRule();
    }

    /**
     * Создать правило сравнения поля с другим полем или константой.
     *
     * @param string $operator Оператор сравнения ('>=', '<=', '>', '<', '==', '!=')
     * @param string $otherField Путь к другому полю для сравнения (например, 'data_json.start_date')
     * @param mixed|null $constantValue Константное значение для сравнения (если указано, используется вместо otherField)
     * @return \App\Domain\Blueprint\Validation\Rules\FieldComparisonRule
     */
    public function createFieldComparisonRule(
        string $operator,
        string $otherField,
        mixed $constantValue = null
    ): FieldComparisonRule {
        return new FieldComparisonRule($operator, $otherField, $constantValue);
    }

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
}

