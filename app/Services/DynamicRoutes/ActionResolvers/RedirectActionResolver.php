<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\ActionResolvers;

use App\Enums\RouteNodeActionType;
use App\Models\RouteNode;

/**
 * Резолвер для REDIRECT действий.
 *
 * Обрабатывает узлы с action_type=REDIRECT.
 * Читает данные из action_meta['to'] и action_meta['status'].
 * Создаёт closure для redirect().
 *
 * @package App\Services\DynamicRoutes\ActionResolvers
 */
class RedirectActionResolver extends AbstractActionResolver
{
    /**
     * Проверить, поддерживает ли резолвер указанный узел.
     *
     * Поддерживает узлы с action_type=REDIRECT.
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return bool true если резолвер поддерживает узел, false иначе
     */
    public function supports(RouteNode $node): bool
    {
        return $node->action_type === RouteNodeActionType::REDIRECT;
    }

    /**
     * Выполнить разрешение действия.
     *
     * Читает URL и опциональный статус из action_meta и создаёт closure для redirect().
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return callable Closure для redirect()
     */
    protected function doResolve(RouteNode $node): callable
    {
        $actionMeta = $node->action_meta ?? [];
        $url = $actionMeta['to'] ?? null;
        $status = $actionMeta['status'] ?? 302;

        if (!$url) {
            return $this->createFallbackAction();
        }

        return fn() => redirect($url, $status);
    }
}

