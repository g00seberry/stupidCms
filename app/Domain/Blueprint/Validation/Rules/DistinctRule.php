<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Правило валидации: уникальность элементов массива.
 *
 * Преобразуется в Laravel правило 'distinct'.
 * Может применяться к любым типам данных.
 * Пользователь сам отвечает за корректность применения правила.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class DistinctRule implements Rule
{
    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return 'distinct';
    }

    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return [];
    }
}
