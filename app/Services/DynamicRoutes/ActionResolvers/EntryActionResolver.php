<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\ActionResolvers;

use App\Enums\RouteNodeActionType;
use App\Models\RouteNode;

/**
 * Резолвер для ENTRY действий.
 *
 * Обрабатывает узлы с action_type=ENTRY.
 * Возвращает [EntryPageController::class, 'show'].
 * route_node_id будет добавлен в defaults через RouteRouteRegistrar.
 *
 * @package App\Services\DynamicRoutes\ActionResolvers
 */
class EntryActionResolver extends AbstractActionResolver
{
    /**
     * Проверить, поддерживает ли резолвер указанный узел.
     *
     * Поддерживает узлы с action_type=ENTRY.
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return bool true если резолвер поддерживает узел, false иначе
     */
    public function supports(RouteNode $node): bool
    {
        return $node->action_type === RouteNodeActionType::ENTRY;
    }

    /**
     * Выполнить разрешение действия.
     *
     * Возвращает [EntryPageController::class, 'show'].
     * route_node_id будет передан через defaults в RouteRouteRegistrar.
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return array<string> Массив [EntryPageController::class, 'show']
     */
    protected function doResolve(RouteNode $node): array
    {
        // Для action_type=ENTRY используем EntryPageController@show
        // route_node_id будет передан через defaults в RouteRouteRegistrar
        return [\App\Http\Controllers\EntryPageController::class, 'show'];
    }
}

