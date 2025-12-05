<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Правило валидации: тип данных.
 *
 * Автоматически создаётся на основе data_type из Path.
 * Преобразуется в соответствующее Laravel правило валидации (string, integer, numeric, boolean, date, array).
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class TypeRule implements Rule
{
    /**
     * @param string $type Тип данных (string, integer, numeric, boolean, date, array)
     */
    public function __construct(
        private readonly string $type
    ) {}

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return 'type';
    }

    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return ['type' => $this->type];
    }

    /**
     * Получить тип данных.
     *
     * @return string Тип данных
     */
    public function getDataType(): string
    {
        return $this->type;
    }
}

