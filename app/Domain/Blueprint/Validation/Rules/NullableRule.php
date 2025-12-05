<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Правило валидации: поле опционально (nullable).
 *
 * Указывает, что поле может отсутствовать или быть null.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class NullableRule implements Rule
{
    /**
     * Получить тип правила.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'nullable';
    }

    /**
     * Получить параметры правила.
     *
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return [];
    }
}

