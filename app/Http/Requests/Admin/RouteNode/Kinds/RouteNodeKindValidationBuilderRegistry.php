<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\RouteNode\Kinds;

/**
 * Регистр билдеров правил валидации RouteNode по kind.
 *
 * Хранит и управляет билдерами валидации для различных типов узлов маршрутов.
 * Каждый билдер связан с конкретным kind (например, 'group', 'route').
 *
 * @package App\Http\Requests\Admin\RouteNode\Kinds
 */
final class RouteNodeKindValidationBuilderRegistry
{
    /**
     * Регистр билдеров: kind => RouteNodeKindValidationBuilderInterface.
     *
     * @var array<string, RouteNodeKindValidationBuilderInterface>
     */
    private array $builders = [];

    /**
     * Зарегистрировать билдер для определённого kind.
     *
     * Если билдер для данного kind уже зарегистрирован,
     * он будет перезаписан новым билдером.
     *
     * @param string $kind Тип узла (например, 'group', 'route')
     * @param \App\Http\Requests\Admin\RouteNode\Kinds\RouteNodeKindValidationBuilderInterface $builder Билдер правил валидации
     * @return void
     */
    public function register(string $kind, RouteNodeKindValidationBuilderInterface $builder): void
    {
        $this->builders[$kind] = $builder;
    }

    /**
     * Получить билдер для определённого kind.
     *
     * @param string $kind Тип узла (например, 'group', 'route')
     * @return \App\Http\Requests\Admin\RouteNode\Kinds\RouteNodeKindValidationBuilderInterface|null Билдер или null, если не найден
     */
    public function getBuilder(string $kind): ?RouteNodeKindValidationBuilderInterface
    {
        return $this->builders[$kind] ?? null;
    }

    /**
     * Проверить, зарегистрирован ли билдер для kind.
     *
     * @param string $kind Тип узла (например, 'group', 'route')
     * @return bool true, если билдер зарегистрирован
     */
    public function hasBuilder(string $kind): bool
    {
        return isset($this->builders[$kind]);
    }

    /**
     * Получить список всех поддерживаемых kind.
     *
     * @return array<string> Массив kind, для которых зарегистрированы билдеры
     */
    public function getSupportedKinds(): array
    {
        return array_keys($this->builders);
    }

    /**
     * Получить все зарегистрированные билдеры.
     *
     * @return array<string, RouteNodeKindValidationBuilderInterface> Массив билдеров
     */
    public function getAllBuilders(): array
    {
        return $this->builders;
    }
}

