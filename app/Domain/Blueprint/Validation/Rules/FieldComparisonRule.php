<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Доменное правило валидации: сравнение значения поля с другим полем или константой.
 *
 * Поддерживает операторы: '>=', '<=', '>', '<', '==', '!='
 * Может сравнивать с другим полем (otherField) или с константным значением (constantValue).
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
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

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return 'field_comparison';
    }

    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return [
            'operator' => $this->operator,
            'other_field' => $this->otherField,
            'constant_value' => $this->constantValue,
        ];
    }

    /**
     * Получить оператор сравнения.
     *
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * Получить путь к другому полю для сравнения.
     *
     * @return string
     */
    public function getOtherField(): string
    {
        return $this->otherField;
    }

    /**
     * Получить константное значение для сравнения.
     *
     * @return mixed|null
     */
    public function getConstantValue(): mixed
    {
        return $this->constantValue;
    }
}

