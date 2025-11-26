<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Правило валидации: максимальное значение/длина.
 *
 * Для строковых типов (string, text) означает максимальную длину.
 * Для числовых типов (int, float) означает максимальное значение.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class MaxRule implements Rule
{
    /**
     * @param mixed $value Максимальное значение
     * @param string $dataType Тип данных (string, text, int, float)
     */
    public function __construct(
        private readonly mixed $value,
        private readonly string $dataType
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
            'data_type' => $this->dataType,
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

    /**
     * Получить тип данных.
     *
     * @return string
     */
    public function getDataType(): string
    {
        return $this->dataType;
    }
}

