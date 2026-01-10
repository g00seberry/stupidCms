<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\ActionResolvers;

use App\Models\RouteNode;
use App\Services\DynamicRoutes\Validators\DynamicRouteValidator;
use Illuminate\Support\Facades\Log;

/**
 * Фабрика для разрешения действий маршрутов.
 *
 * Использует цепочку резолверов для разрешения действия из RouteNode.
 * Проверяет каждый резолвер через метод supports() и использует первый подходящий.
 *
 * Порядок проверки резолверов:
 * 1. VIEW
 * 2. REDIRECT
 * 3. CONTROLLER
 *
 * @package App\Services\DynamicRoutes\ActionResolvers
 */
class ActionResolverFactory
{
    /**
     * Зарегистрированные резолверы.
     *
     * @var array<int, ActionResolverInterface>
     */
    private array $resolvers = [];

    /**
     * Создать фабрику с предустановленными резолверами.
     *
     * Регистрирует резолверы по умолчанию в правильном порядке:
     * 1. VIEW
     * 2. REDIRECT
     * 3. CONTROLLER
     *
     * @param \App\Services\DynamicRoutes\Validators\DynamicRouteValidator $guard Guard для проверки безопасности
     * @return self
     */
    public static function createDefault(DynamicRouteValidator $guard): self
    {
        $factory = new self();
        
        // Порядок: VIEW → REDIRECT → CONTROLLER
        $factory->register(new ViewActionResolver($guard));
        $factory->register(new RedirectActionResolver($guard));
        $factory->register(new ControllerActionResolver($guard));
        
        return $factory;
    }

    /**
     * Зарегистрировать резолвер действий.
     *
     * @param \App\Services\DynamicRoutes\ActionResolvers\ActionResolverInterface $resolver Резолвер
     * @return void
     */
    public function register(ActionResolverInterface $resolver): void
    {
        $this->resolvers[] = $resolver;
    }

    /**
     * Разрешить действие для маршрута.
     *
     * Проходит по цепочке зарегистрированных резолверов и использует
     * первый, который поддерживает узел (supports() возвращает true).
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return callable|string|array<string>|null Действие для маршрута или null при ошибке
     */
    public function resolve(RouteNode $node): callable|string|array|null
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($node)) {
                return $resolver->resolve($node);
            }
        }

        Log::warning('Dynamic route: резолвер действий не найден', [
            'route_node_id' => $node->id,
            'action_type' => $node->action_type?->value,
            'action_meta' => $node->action_meta,
        ]);

        return null;
    }
}

