<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\ActionResolvers;

use App\Enums\RouteNodeActionType;
use App\Models\RouteNode;

/**
 * Резолвер для VIEW действий.
 *
 * Обрабатывает узлы с action_type=VIEW.
 * Читает данные из action_meta['view'] и action_meta['data'].
 * Создаёт closure для view().
 *
 * @package App\Services\DynamicRoutes\ActionResolvers
 */
class ViewActionResolver extends AbstractActionResolver
{
    /**
     * Проверить, поддерживает ли резолвер указанный узел.
     *
     * Поддерживает узлы с action_type=VIEW.
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return bool true если резолвер поддерживает узел, false иначе
     */
    public function supports(RouteNode $node): bool
    {
        return $node->action_type === RouteNodeActionType::VIEW;
    }

    /**
     * Выполнить разрешение действия.
     *
     * Читает имя view и опциональные данные из action_meta и создаёт closure для view().
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return callable Closure для view()
     */
    protected function doResolve(RouteNode $node): callable
    {
        $actionMeta = $node->action_meta ?? [];
        $viewName = $actionMeta['view'] ?? null;
        $data = $actionMeta['data'] ?? [];

        if (!$viewName) {
            return $this->createFallbackAction();
        }

        return fn() => view($viewName, $data);
    }
}

