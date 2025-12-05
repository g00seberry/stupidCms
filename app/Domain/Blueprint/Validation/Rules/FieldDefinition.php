<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Определение поля для валидации.
 *
 * Содержит метаданные поля из Path, необходимые для построения правил валидации.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class FieldDefinition
{
    /**
     * @param string $path Полный путь поля (например, 'title' или 'author.name')
     * @param string $dataType Тип данных (string, text, int, float, bool, date, datetime, json, ref)
     * @param bool $isRequired Обязательное ли поле
     * @param string $cardinality Кардинальность: 'one' или 'many'
     * @param array<string, mixed>|null $validationRules Правила валидации из Path (min, max, pattern и т.д.)
     */
    public function __construct(
        public readonly string $path,
        public readonly string $dataType,
        public readonly bool $isRequired,
        public readonly string $cardinality,
        public readonly ?array $validationRules = null
    ) {}

    /**
     * Проверить, является ли поле массивом (cardinality: 'many').
     *
     * @return bool
     */
    public function isArray(): bool
    {
        return $this->cardinality === 'many';
    }

    /**
     * Проверить, является ли поле одиночным значением (cardinality: 'one').
     *
     * @return bool
     */
    public function isSingle(): bool
    {
        return $this->cardinality === 'one';
    }

    /**
     * Проверить, есть ли правила валидации.
     *
     * @return bool
     */
    public function hasValidationRules(): bool
    {
        return $this->validationRules !== null && ! empty($this->validationRules);
    }
}

