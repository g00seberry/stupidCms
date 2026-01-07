<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\ActionResolvers;

use App\Enums\RouteNodeActionType;
use App\Models\RouteNode;
use App\Services\DynamicRoutes\DynamicRouteGuard;
use Illuminate\Support\Facades\Log;

/**
 * Резолвер для CONTROLLER действий.
 *
 * Обрабатывает следующие форматы action:
 * - Controller@method: App\Http\Controllers\BlogController@show
 * - Invokable controller: App\Http\Controllers\HomeController
 *
 * Выполняет валидацию существования класса и метода.
 *
 * @package App\Services\DynamicRoutes\ActionResolvers
 */
class ControllerActionResolver extends AbstractActionResolver
{
    /**
     * Проверить, поддерживает ли резолвер указанный узел.
     *
     * Поддерживает узлы с action_type=CONTROLLER.
     * View и redirect действия обрабатываются отдельными резолверами,
     * которые проверяются раньше в ActionResolverFactory.
     * Отсутствие action обрабатывается в doResolve() с fallback.
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return bool true если резолвер поддерживает узел, false иначе
     */
    public function supports(RouteNode $node): bool
    {
        return $node->action_type === RouteNodeActionType::CONTROLLER;
    }

    /**
     * Выполнить разрешение действия.
     *
     * Парсит action и возвращает контроллер в формате [Controller::class, 'method'] или string для invokable.
     * Если action отсутствует, возвращает fallback (abort(404)).
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return callable|string|array<string>|null Действие для маршрута или null при ошибке
     */
    protected function doResolve(RouteNode $node): callable|string|array|null
    {
        if (!$node->action) {
            Log::warning('Dynamic route: отсутствует action для CONTROLLER', [
                'route_node_id' => $node->id,
            ]);
            return $this->createFallbackAction();
        }

        if (str_contains($node->action, '@')) {
            return $this->resolveControllerMethod($node->action, $node->id);
        }

        return $this->resolveInvokableController($node->action, $node->id);
    }

    /**
     * Разрешить Controller@method действие.
     *
     * @param string $action Действие в формате Controller@method
     * @param int $routeNodeId ID узла маршрута (для логирования)
     * @return callable|array<string> Действие для маршрута или fallback при ошибке
     */
    private function resolveControllerMethod(string $action, int $routeNodeId): callable|array
    {
        [$controller, $method] = explode('@', $action, 2);

        if (!$this->validateAndResolveController($controller, $routeNodeId, $method)) {
            return $this->createFallbackAction();
        }

        return [$controller, $method];
    }

    /**
     * Разрешить invokable контроллер.
     *
     * @param string $action Имя класса контроллера
     * @param int $routeNodeId ID узла маршрута (для логирования)
     * @return callable|string Действие для маршрута или fallback при ошибке
     */
    private function resolveInvokableController(string $action, int $routeNodeId): callable|string
    {
        if (!$this->validateAndResolveController($action, $routeNodeId)) {
            return $this->createFallbackAction();
        }

        return $action;
    }

    /**
     * Валидировать и разрешить контроллер.
     *
     * Выполняет проверки контроллера: существование класса и метода (если указан).
     *
     * @param string $controller Полное имя контроллера
     * @param int $routeNodeId ID узла маршрута (для логирования)
     * @param string|null $method Имя метода (опционально, для Controller@method)
     * @return bool true если контроллер валиден, false иначе
     */
    private function validateAndResolveController(string $controller, int $routeNodeId, ?string $method = null): bool
    {
        if (!$this->validateController($controller)) {
            return false;
        }

        if ($method !== null && !$this->validateMethod($controller, $method)) {
            return false;
        }

        return true;
    }

    /**
     * Проверить существование контроллера.
     *
     * @param string $controller Полное имя контроллера
     * @return bool true если контроллер существует, false иначе
     */
    private function validateController(string $controller): bool
    {
        if (!class_exists($controller)) {
            Log::error('Dynamic route: контроллер не существует', [
                'controller' => $controller,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Проверить существование метода в контроллере.
     *
     * @param string $controller Полное имя контроллера
     * @param string $method Имя метода
     * @return bool true если метод существует, false иначе
     */
    private function validateMethod(string $controller, string $method): bool
    {
        if (!method_exists($controller, $method)) {
            Log::error('Dynamic route: метод не существует в контроллере', [
                'controller' => $controller,
                'method' => $method,
            ]);
            return false;
        }

        return true;
    }

}

