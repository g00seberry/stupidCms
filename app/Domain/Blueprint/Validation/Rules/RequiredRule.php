<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Правило валидации: поле обязательно.
 *
 * Указывает, что поле должно присутствовать и не может быть null или пустым.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class RequiredRule implements Rule
{
    /**
     * Получить тип правила.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'required';
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

