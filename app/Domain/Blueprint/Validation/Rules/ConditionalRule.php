<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Доменное правило валидации: условное правило.
 *
 * Применяется в зависимости от значения другого поля.
 * Типы: 'required_if', 'prohibited_unless', 'required_unless', 'prohibited_if'
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class ConditionalRule implements Rule
{
    /**
     * @param string $type Тип условного правила ('required_if', 'prohibited_unless', 'required_unless', 'prohibited_if')
     * @param string $field Путь к полю, от которого зависит условие (например, 'is_published')
     * @param mixed $value Значение поля для условия
     * @param string|null $operator Оператор сравнения ('==', '!=', '>', '<', '>=', '<='), по умолчанию '=='
     */
    public function __construct(
        private readonly string $type,
        private readonly string $field,
        private readonly mixed $value,
        private readonly ?string $operator = null
    ) {}

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return [
            'field' => $this->field,
            'value' => $this->value,
            'operator' => $this->operator ?? '==',
        ];
    }

    /**
     * Получить путь к полю условия.
     *
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Получить значение условия.
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Получить оператор сравнения.
     *
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator ?? '==';
    }
}

