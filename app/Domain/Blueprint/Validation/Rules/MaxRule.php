<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Правило валидации: максимальное значение/длина.
 *
 * Может применяться к любым типам данных.
 * Пользователь сам отвечает за корректность применения правила.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class MaxRule implements Rule
{
    /**
     * @param mixed $value Максимальное значение
     */
    public function __construct(
        private readonly mixed $value
    ) {}

    /**
     * Получить тип правила.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'max';
    }

    /**
     * Получить параметры правила.
     *
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return [
            'value' => $this->value,
        ];
    }

    /**
     * Получить значение максимума.
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}

