<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\ActionResolvers;

use App\Enums\RouteNodeActionType;
use App\Models\RouteNode;

/**
 * Резолвер для view: действий.
 *
 * Обрабатывает формат action: view:pages.about
 * Создаёт closure для view().
 *
 * @package App\Services\DynamicRoutes\ActionResolvers
 */
class ViewActionResolver extends AbstractActionResolver
{
    /**
     * Проверить, поддерживает ли резолвер указанный узел.
     *
     * Поддерживает узлы с action_type=CONTROLLER и action, начинающимся с 'view:'.
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return bool true если резолвер поддерживает узел, false иначе
     */
    public function supports(RouteNode $node): bool
    {
        if ($node->action_type !== RouteNodeActionType::CONTROLLER) {
            return false;
        }

        if (!$node->action) {
            return false;
        }

        return str_starts_with($node->action, 'view:');
    }

    /**
     * Выполнить разрешение действия.
     *
     * Извлекает имя view из action и создаёт closure для view().
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return callable Closure для view()
     */
    protected function doResolve(RouteNode $node): callable
    {
        $viewName = $this->extractViewName($node->action);
        return fn() => view($viewName);
    }

    /**
     * Извлечь имя view из action.
     *
     * Убирает префикс 'view:' из action.
     *
     * @param string $action Действие в формате view:pages.about
     * @return string Имя view (pages.about)
     */
    private function extractViewName(string $action): string
    {
        return substr($action, 5); // Убираем 'view:'
    }
}

