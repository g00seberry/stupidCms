<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\Registrars;

use App\Enums\RouteNodeActionType;
use App\Models\RouteNode;
use App\Services\DynamicRoutes\ActionResolvers\ActionResolverFactory;
use App\Services\DynamicRoutes\Validators\DynamicRouteValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Регистратор для ROUTE узлов маршрутов.
 *
 * Регистрирует конкретные маршруты через Route::match() с применением
 * настроек маршрута (name, domain, middleware, where, defaults)
 * и использованием ActionResolverFactory для разрешения действия.
 *
 * @package App\Services\DynamicRoutes\Registrars
 */
class RouteRouteRegistrar extends AbstractRouteNodeRegistrar
{
    /**
     * @param \App\Services\DynamicRoutes\Validators\DynamicRouteValidator $guard Guard для проверки безопасности
     * @param \App\Services\DynamicRoutes\Registrars\RouteNodeRegistrarFactory|null $registrarFactory Фабрика для создания регистраторов дочерних узлов
     * @param \App\Services\DynamicRoutes\ActionResolvers\ActionResolverFactory|null $actionResolverFactory Фабрика для разрешения действий
     */
    public function __construct(
        DynamicRouteValidator $guard,
        ?RouteNodeRegistrarFactory $registrarFactory = null,
        private ?ActionResolverFactory $actionResolverFactory = null,
    ) {
        parent::__construct($guard, $registrarFactory);
    }

    /**
     * Выполнить регистрацию маршрута.
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return void
     */
    protected function doRegister(RouteNode $node): void
    {
        if (!$this->validateRouteNode($node)) {
            return;
        }

        $methods = $node->methods;
        $uri = $node->uri;

        // Разрешаем действие через фабрику резолверов
        if ($this->actionResolverFactory === null) {
            Log::error('Dynamic route: фабрика резолверов действий не установлена', [
                'route_node_id' => $node->id,
            ]);
            return;
        }

        $action = $this->actionResolverFactory->resolve($node);
        if ($action === null) {
            return; // Ошибка уже залогирована в резолвере
        }

        // Проверяем существование контроллера перед регистрацией
        // Это предотвращает ошибки при route:list для несуществующих контроллеров
        if (!$this->validateAction($action, $node->id, $uri)) {
            return;
        }

        $route = Route::match($methods, $uri, $action);

        // Применяем дополнительные настройки маршрута
        $this->applyRouteSettings($route, $node);
    }

    /**
     * Проверить валидность узла маршрута.
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return bool true если узел валиден, false иначе
     */
    private function validateRouteNode(RouteNode $node): bool
    {
        if (!$node->uri || !$node->methods) {
            Log::warning('Dynamic route: пропущен маршрут без uri или methods', [
                'route_node_id' => $node->id,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Проверить валидность действия перед регистрацией.
     *
     * Проверяет существование контроллера и метода для предотвращения
     * ошибок при route:list для несуществующих контроллеров.
     *
     * @param callable|string|array<string> $action Действие для проверки
     * @param int $routeNodeId ID узла маршрута (для логирования)
     * @param string $uri URI маршрута (для логирования)
     * @return bool true если действие валидно, false иначе
     */
    private function validateAction(callable|string|array $action, int $routeNodeId, string $uri): bool
    {
        if (is_array($action) && count($action) === 2) {
            [$controller, $method] = $action;
            if (!class_exists($controller)) {
                Log::warning('Dynamic route: контроллер не существует, маршрут пропущен', [
                    'route_node_id' => $routeNodeId,
                    'controller' => $controller,
                    'uri' => $uri,
                ]);
                return false;
            }
            if (!method_exists($controller, $method)) {
                Log::warning('Dynamic route: метод не существует в контроллере, маршрут пропущен', [
                    'route_node_id' => $routeNodeId,
                    'controller' => $controller,
                    'method' => $method,
                    'uri' => $uri,
                ]);
                return false;
            }
        } elseif (is_string($action) && !class_exists($action)) {
            Log::warning('Dynamic route: invokable контроллер не существует, маршрут пропущен', [
                'route_node_id' => $routeNodeId,
                'controller' => $action,
                'uri' => $uri,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Применить настройки маршрута.
     *
     * Применяет все дополнительные настройки маршрута:
     * name, domain, middleware, where, defaults.
     *
     * @param \Illuminate\Routing\Route $route Объект маршрута Laravel
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return void
     */
    private function applyRouteSettings(\Illuminate\Routing\Route $route, RouteNode $node): void
    {
        if ($node->name) {
            $route->name($node->name);
        }

        if ($node->domain) {
            $route->domain($node->domain);
        }

        if (!empty($node->middleware)) {
            $route->middleware($node->middleware);
        }

        if ($node->where) {
            foreach ($node->where as $param => $pattern) {
                $route->where($param, $pattern);
            }
        }

        if ($node->defaults) {
            foreach ($node->defaults as $key => $value) {
                $route->defaults($key, $value);
            }
        }
    }
}

