<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\Builders;

use App\Enums\RouteNodeKind;
use Illuminate\Support\Facades\Log;

/**
 * Фабрика для создания билдеров узлов маршрутов.
 *
 * Выбирает правильный билдер на основе типа узла (kind).
 * Поддерживает регистрацию билдеров для разных типов узлов.
 *
 * @package App\Services\DynamicRoutes\Builders
 */
class RouteNodeBuilderFactory
{
    /**
     * Зарегистрированные билдеры.
     *
     * Массив, где ключ - значение RouteNodeKind (строка), значение - экземпляр билдера.
     *
     * @var array<string, RouteNodeBuilderInterface>
     */
    private array $builders = [];

    /**
     * Создать фабрику с предустановленными билдерами.
     *
     * Регистрирует билдеры по умолчанию:
     * - RouteNodeKind::GROUP → RouteGroupNodeBuilder
     * - RouteNodeKind::ROUTE → RouteRouteNodeBuilder
     *
     * @return self
     */
    public static function createDefault(): self
    {
        $factory = new self();
        $factory->register(RouteNodeKind::GROUP, new RouteGroupNodeBuilder());
        $factory->register(RouteNodeKind::ROUTE, new RouteRouteNodeBuilder());

        return $factory;
    }

    /**
     * Зарегистрировать билдер для типа узла.
     *
     * @param \App\Enums\RouteNodeKind $kind Тип узла
     * @param \App\Services\DynamicRoutes\Builders\RouteNodeBuilderInterface $builder Билдер
     * @return void
     */
    public function register(RouteNodeKind $kind, RouteNodeBuilderInterface $builder): void
    {
        if (!$builder->supports($kind)) {
            Log::warning('RouteNodeBuilderFactory: builder does not support kind', [
                'kind' => $kind->value,
                'builder' => $builder::class,
            ]);
            return;
        }

        $this->builders[$kind->value] = $builder;
    }

    /**
     * Создать билдер для указанного типа узла.
     *
     * Возвращает зарегистрированный билдер для указанного kind.
     * Если билдер не найден, логирует предупреждение и возвращает null.
     *
     * @param \App\Enums\RouteNodeKind $kind Тип узла
     * @return \App\Services\DynamicRoutes\Builders\RouteNodeBuilderInterface|null Билдер или null, если не найден
     */
    public function create(RouteNodeKind $kind): ?RouteNodeBuilderInterface
    {
        if (!isset($this->builders[$kind->value])) {
            Log::warning('RouteNodeBuilderFactory: builder not found for kind', [
                'kind' => $kind->value,
                'available_kinds' => array_keys($this->builders),
            ]);
            return null;
        }

        return $this->builders[$kind->value];
    }

    /**
     * Проверить, зарегистрирован ли билдер для типа узла.
     *
     * @param \App\Enums\RouteNodeKind $kind Тип узла
     * @return bool true если билдер зарегистрирован, false иначе
     */
    public function hasBuilder(RouteNodeKind $kind): bool
    {
        return isset($this->builders[$kind->value]);
    }
}

