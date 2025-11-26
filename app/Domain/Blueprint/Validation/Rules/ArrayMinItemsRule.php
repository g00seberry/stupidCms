<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Доменное правило валидации: минимальное количество элементов в массиве.
 *
 * Применяется только к полям с cardinality: 'many'.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class ArrayMinItemsRule implements Rule
{
    /**
     * @param int $value Минимальное количество элементов
     */
    public function __construct(
        private readonly int $value
    ) {}

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return 'array_min_items';
    }

    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return [
            'value' => $this->value,
        ];
    }

    /**
     * Получить минимальное количество элементов.
     *
     * @return int
     */
    public function getValue(): int
    {
        return $this->value;
    }
}

