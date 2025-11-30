<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Фабрика для создания доменных Rule объектов.
 *
 * Инкапсулирует логику создания правил валидации.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
interface RuleFactory
{
    /**
     * Создать правило минимального значения/длины.
     *
     * @param mixed $value Значение минимума
     * @param string $dataType Тип данных (string, text, int, float)
     * @return \App\Domain\Blueprint\Validation\Rules\MinRule
     */
    public function createMinRule(mixed $value, string $dataType): MinRule;

    /**
     * Создать правило максимального значения/длины.
     *
     * @param mixed $value Значение максимума
     * @param string $dataType Тип данных (string, text, int, float)
     * @return \App\Domain\Blueprint\Validation\Rules\MaxRule
     */
    public function createMaxRule(mixed $value, string $dataType): MaxRule;

    /**
     * Создать правило регулярного выражения.
     *
     * @param mixed $pattern Паттерн регулярного выражения
     * @return \App\Domain\Blueprint\Validation\Rules\PatternRule
     */
    public function createPatternRule(mixed $pattern): PatternRule;

    /**
     * Создать правило обязательности поля.
     *
     * @return \App\Domain\Blueprint\Validation\Rules\RequiredRule
     */
    public function createRequiredRule(): RequiredRule;

    /**
     * Создать правило опциональности поля (nullable).
     *
     * @return \App\Domain\Blueprint\Validation\Rules\NullableRule
     */
    public function createNullableRule(): NullableRule;

    /**
     * Создать правило минимального количества элементов массива.
     *
     * @param int $value Минимальное количество элементов
     * @return \App\Domain\Blueprint\Validation\Rules\ArrayMinItemsRule
     */
    public function createArrayMinItemsRule(int $value): ArrayMinItemsRule;

    /**
     * Создать правило максимального количества элементов массива.
     *
     * @param int $value Максимальное количество элементов
     * @return \App\Domain\Blueprint\Validation\Rules\ArrayMaxItemsRule
     */
    public function createArrayMaxItemsRule(int $value): ArrayMaxItemsRule;

    /**
     * Создать условное правило валидации.
     *
     * @param string $type Тип правила ('required_if', 'prohibited_unless', 'required_unless', 'prohibited_if')
     * @param string $field Путь к полю условия
     * @param mixed $value Значение для условия
     * @param string|null $operator Оператор сравнения (по умолчанию '==')
     * @return \App\Domain\Blueprint\Validation\Rules\ConditionalRule
     */
    public function createConditionalRule(string $type, string $field, mixed $value, ?string $operator = null): ConditionalRule;

    /**
     * Создать правило уникальности элементов массива.
     *
     * @return \App\Domain\Blueprint\Validation\Rules\ArrayUniqueRule
     */
    public function createArrayUniqueRule(): ArrayUniqueRule;

    /**
     * Создать правило сравнения поля с другим полем или константой.
     *
     * @param string $operator Оператор сравнения ('>=', '<=', '>', '<', '==', '!=')
     * @param string $otherField Путь к другому полю для сравнения (например, 'content_json.start_date')
     * @param mixed|null $constantValue Константное значение для сравнения (если указано, используется вместо otherField)
     * @return \App\Domain\Blueprint\Validation\Rules\FieldComparisonRule
     */
    public function createFieldComparisonRule(
        string $operator,
        string $otherField,
        mixed $constantValue = null
    ): FieldComparisonRule;
}

