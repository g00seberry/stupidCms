<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\Registrars;

use App\Models\RouteNode;
use App\Services\DynamicRoutes\DynamicRouteGuard;
use Illuminate\Support\Facades\Log;

/**
 * Абстрактный базовый класс для регистраторов узлов маршрутов.
 *
 * Содержит общую логику регистрации:
 * - Проверка enabled
 * - Логирование ошибок
 * - Доступ к DynamicRouteGuard
 * - Рекурсивная регистрация дочерних узлов через фабрику
 *
 * @package App\Services\DynamicRoutes\Registrars
 */
abstract class AbstractRouteNodeRegistrar implements RouteNodeRegistrarInterface
{
    /**
     * @param \App\Services\DynamicRoutes\DynamicRouteGuard $guard Guard для проверки безопасности
     * @param \App\Services\DynamicRoutes\Registrars\RouteNodeRegistrarFactory|null $registrarFactory Фабрика для создания регистраторов дочерних узлов
     */
    public function __construct(
        protected DynamicRouteGuard $guard,
        protected ?RouteNodeRegistrarFactory $registrarFactory = null,
    ) {}

    /**
     * Зарегистрировать узел маршрута.
     *
     * Реализация по умолчанию: проверяет enabled и вызывает doRegister().
     * Дочерние классы должны реализовать doRegister() для специфичной логики.
     *
     * @param \App\Models\RouteNode $node Узел для регистрации
     * @return void
     */
    public function register(RouteNode $node): void
    {
        if (!$this->shouldRegister($node)) {
            return;
        }

        try {
            $this->doRegister($node);
        } catch (\Throwable $e) {
            Log::error('Dynamic route: ошибка при регистрации узла', [
                'route_node_id' => $node->id,
                'kind' => $node->kind?->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Проверить, нужно ли регистрировать узел.
     *
     * @param \App\Models\RouteNode $node Узел для проверки
     * @return bool true если узел должен быть зарегистрирован, false иначе
     */
    protected function shouldRegister(RouteNode $node): bool
    {
        return $node->enabled === true;
    }

    /**
     * Зарегистрировать дочерние узлы рекурсивно.
     *
     * Использует фабрику регистраторов для создания нужных регистраторов
     * и рекурсивной регистрации дочерних узлов.
     *
     * @param \App\Models\RouteNode $node Родительский узел
     * @return void
     */
    protected function registerChildren(RouteNode $node): void
    {
        if ($this->registrarFactory === null) {
            Log::warning('Dynamic route: фабрика регистраторов не установлена, дочерние узлы не будут зарегистрированы', [
                'route_node_id' => $node->id,
            ]);
            return;
        }

        if (!$node->relationLoaded('children') || !$node->children) {
            return;
        }

        foreach ($node->children as $child) {
            $registrar = $this->registrarFactory->create($child->kind);
            if ($registrar === null) {
                Log::warning('Dynamic route: регистратор не найден для дочернего узла', [
                    'parent_id' => $node->id,
                    'child_id' => $child->id,
                    'child_kind' => $child->kind?->value,
                ]);
                continue;
            }

            $registrar->register($child);
        }
    }

    /**
     * Выполнить регистрацию узла.
     *
     * Этот метод должен быть реализован в дочерних классах
     * для выполнения специфичной логики регистрации.
     *
     * @param \App\Models\RouteNode $node Узел для регистрации
     * @return void
     */
    abstract protected function doRegister(RouteNode $node): void;
}

