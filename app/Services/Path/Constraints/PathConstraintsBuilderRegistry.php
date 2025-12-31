<?php

declare(strict_types=1);

namespace App\Services\Path\Constraints;

/**
 * Регистр билдеров constraints для Path.
 *
 * Хранит и управляет билдерами для различных типов данных.
 * Каждый билдер связан с конкретным data_type (например, 'ref', 'media').
 *
 * Используется для получения соответствующего билдера по типу данных Path,
 * что позволяет избежать switch-конструкций в контроллерах и ресурсах.
 *
 * @package App\Services\Path\Constraints
 */
class PathConstraintsBuilderRegistry
{
    /**
     * Регистр билдеров: data_type => PathConstraintsBuilderInterface.
     *
     * @var array<string, PathConstraintsBuilderInterface>
     */
    private array $builders = [];

    /**
     * Зарегистрировать билдер для определённого типа данных.
     *
     * Если билдер для данного типа данных уже зарегистрирован,
     * он будет перезаписан новым билдером.
     *
     * @param string $dataType Тип данных (например, 'ref', 'media')
     * @param PathConstraintsBuilderInterface $builder Билдер constraints
     * @return void
     */
    public function register(string $dataType, PathConstraintsBuilderInterface $builder): void
    {
        $this->builders[$dataType] = $builder;
    }

    /**
     * Получить билдер для определённого типа данных.
     *
     * @param string $dataType Тип данных (например, 'ref', 'media')
     * @return PathConstraintsBuilderInterface|null Билдер или null, если не найден
     */
    public function getBuilder(string $dataType): ?PathConstraintsBuilderInterface
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
     * @return array<string, PathConstraintsBuilderInterface> Массив билдеров
     */
    public function getAllBuilders(): array
    {
        return $this->builders;
    }
}

