<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Правило валидации: уникальность элементов массива.
 *
 * Преобразуется в кастомное правило DistinctObjects, которое сравнивает
 * элементы массива по их JSON-сериализации. Это обеспечивает корректную
 * работу с массивами объектов - объекты сравниваются как строки (по их
 * JSON-представлению).
 *
 * Может применяться к любым типам данных (простые значения и объекты).
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
