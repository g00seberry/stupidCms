<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Доменное правило валидации: уникальность элементов массива.
 *
 * Проверяет, что все элементы массива уникальны.
 * Применяется только к полям с cardinality: 'many'.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class ArrayUniqueRule implements Rule
{
    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return 'array_unique';
    }

    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return [];
    }
}

