<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Доменное правило валидации: максимальное количество элементов в массиве.
 *
 * Применяется только к полям с cardinality: 'many'.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class ArrayMaxItemsRule implements Rule
{
    /**
     * @param int $value Максимальное количество элементов
     */
    public function __construct(
        private readonly int $value
    ) {}

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return 'array_max_items';
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
     * Получить максимальное количество элементов.
     *
     * @return int
     */
    public function getValue(): int
    {
        return $this->value;
    }
}

