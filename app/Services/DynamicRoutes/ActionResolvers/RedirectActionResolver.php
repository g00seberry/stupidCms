<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\ActionResolvers;

use App\Enums\RouteNodeActionType;
use App\Models\RouteNode;

/**
 * Резолвер для redirect: действий.
 *
 * Обрабатывает форматы action:
 * - redirect:/new-page:301
 * - redirect:/new-page (по умолчанию статус 302)
 *
 * Создаёт closure для redirect().
 *
 * @package App\Services\DynamicRoutes\ActionResolvers
 */
class RedirectActionResolver extends AbstractActionResolver
{
    /**
     * Проверить, поддерживает ли резолвер указанный узел.
     *
     * Поддерживает узлы с action_type=CONTROLLER и action, начинающимся с 'redirect:'.
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

        return str_starts_with($node->action, 'redirect:');
    }

    /**
     * Выполнить разрешение действия.
     *
     * Парсит action и создаёт closure для redirect() с указанным URL и статусом.
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return callable Closure для redirect()
     */
    protected function doResolve(RouteNode $node): callable
    {
        [$url, $status] = $this->parseRedirectAction($node->action);
        return fn() => redirect($url, $status);
    }

    /**
     * Распарсить action в формате redirect:url:status или redirect:url.
     *
     * @param string $action Действие в формате redirect:/new-page:301 или redirect:/new-page
     * @return array{0: string, 1: int} Массив [url, status]
     */
    private function parseRedirectAction(string $action): array
    {
        $redirectPart = substr($action, 9); // Убираем 'redirect:'
        $parts = explode(':', $redirectPart, 2);
        $url = $parts[0];
        $status = isset($parts[1]) ? (int) $parts[1] : 302;
        
        return [$url, $status];
    }
}

