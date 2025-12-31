<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Path\Constraints;

/**
 * Регистр билдеров правил валидации constraints для Path.
 *
 * Хранит и управляет билдерами валидации для различных типов данных.
 * Каждый билдер связан с конкретным data_type (например, 'ref', 'media').
 *
 * @package App\Http\Requests\Admin\Path\Constraints
 */
final class ConstraintsValidationBuilderRegistry
{
    /**
     * Регистр билдеров: data_type => ConstraintsValidationBuilderInterface.
     *
     * @var array<string, ConstraintsValidationBuilderInterface>
     */
    private array $builders = [];

    /**
     * Зарегистрировать билдер для определённого типа данных.
     *
     * Если билдер для данного типа данных уже зарегистрирован,
     * он будет перезаписан новым билдером.
     *
     * @param string $dataType Тип данных (например, 'ref', 'media')
     * @param ConstraintsValidationBuilderInterface $builder Билдер правил валидации
     * @return void
     */
    public function register(string $dataType, ConstraintsValidationBuilderInterface $builder): void
    {
        $this->builders[$dataType] = $builder;
    }

    /**
     * Получить билдер для определённого типа данных.
     *
     * @param string $dataType Тип данных (например, 'ref', 'media')
     * @return ConstraintsValidationBuilderInterface|null Билдер или null, если не найден
     */
    public function getBuilder(string $dataType): ?ConstraintsValidationBuilderInterface
    {
        return $this->builders[$dataType] ?? null;
    }

    /**
     * Проверить, зарегистрирован ли билдер для типа данных.
     *
     * @param string $dataType Тип данных (например, 'ref', 'media')
     * @return bool true, если билдер зарегистрирован
     */
    public function hasBuilder(string $dataType): bool
    {
        return isset($this->builders[$dataType]);
    }

    /**
     * Получить список всех поддерживаемых типов данных.
     *
     * @return array<string> Массив типов данных, для которых зарегистрированы билдеры
     */
    public function getSupportedDataTypes(): array
    {
        return array_keys($this->builders);
    }

    /**
     * Получить все зарегистрированные билдеры.
     *
     * @return array<string, ConstraintsValidationBuilderInterface> Массив билдеров
     */
    public function getAllBuilders(): array
    {
        return $this->builders;
    }
}

