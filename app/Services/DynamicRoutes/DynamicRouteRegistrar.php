<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes;

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;
use App\Models\RouteNode;
use App\Repositories\RouteNodeRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Сервис для регистрации динамических маршрутов из БД и декларативных маршрутов из файлов.
 *
 * Загружает общее дерево маршрутов, которое включает декларативные маршруты из routes/
 * и динамические маршруты из route_nodes, регистрирует их в Laravel Router.
 * Декларативные маршруты идут первыми в дереве (имеют приоритет).
 * Поддерживает группы маршрутов, различные типы действий (Controller, View, Redirect),
 * проверку безопасности через DynamicRouteGuard.
 * Декларативные и динамические маршруты объединены в общее дерево через RouteNodeRepository::getEnabledTree().
 *
 * @package App\Services\DynamicRoutes
 */
class DynamicRouteRegistrar
{
    /**
     * @param \App\Repositories\RouteNodeRepository $repository Репозиторий для загрузки дерева маршрутов
     * @param \App\Services\DynamicRoutes\DynamicRouteGuard $guard Guard для проверки безопасности
     */
    public function __construct(
        private RouteNodeRepository $repository,
        private DynamicRouteGuard $guard,
    ) {}

    /**
     * Зарегистрировать все маршруты (декларативные и динамические).
     *
     * Регистрирует все маршруты из общего дерева, которое включает
     * декларативные маршруты из routes/ и динамические маршруты из БД.
     * Декларативные маршруты идут первыми в дереве (имеют приоритет).
     *
     * @return void
     */
    public function register(): void
    {
        // Регистрируем все маршруты из общего дерева
        // (декларативные и динамические объединены в getEnabledTree())
        $this->registerDynamicRoutes();
    }

    /**
     * Зарегистрировать все маршруты (декларативные и динамические).
     *
     * Загружает общее дерево включённых маршрутов, которое включает
     * декларативные маршруты из routes/ и динамические маршруты из БД.
     * Декларативные маршруты идут первыми в дереве (имеют приоритет).
     *
     * @return void
     */
    private function registerDynamicRoutes(): void
    {
        try {
            $tree = $this->repository->getEnabledTree();

            foreach ($tree as $rootNode) {
                $this->registerNode($rootNode);
            }
        } catch (\Throwable $e) {
            Log::error('Dynamic routes: ошибка при регистрации маршрутов', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Зарегистрировать коллекцию RouteNode (например, декларативные маршруты).
     *
     * Регистрирует маршруты из коллекции, которая может содержать как группы, так и отдельные маршруты.
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, \App\Models\RouteNode> $nodes Коллекция RouteNode
     * @return void
     */
    public function registerCollection(Collection $nodes): void
    {
        try {
            foreach ($nodes as $node) {
                $this->registerNode($node);
            }
        } catch (\Throwable $e) {
            Log::error('Dynamic routes: ошибка при регистрации коллекции маршрутов', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Зарегистрировать узел (группу или маршрут).
     *
     * @param \App\Models\RouteNode $node Узел для регистрации
     * @return void
     */
    private function registerNode(RouteNode $node): void
    {
        if (!$node->enabled) {
            return;
        }

        if ($node->kind === RouteNodeKind::GROUP) {
            $this->registerGroup($node);
        } else {
            $this->registerRoute($node);
        }
    }

    /**
     * Зарегистрировать группу маршрутов.
     *
     * @param \App\Models\RouteNode $node Узел группы
     * @return void
     */
    private function registerGroup(RouteNode $node): void
    {
        $attributes = $this->buildGroupAttributes($node);
        Log::debug('Dynamic route: регистрация группы маршрутов', [
            'route_node_id' => $node->id,
            'attributes' => $attributes,
        ]);

        
        Route::group($attributes, function () use ($node): void {
            foreach ($node->children as $child) {
                $this->registerNode($child);
            }
        });
    }

    /**
     * Построить атрибуты для группы маршрутов.
     *
     * @param \App\Models\RouteNode $node Узел группы
     * @return array<string, mixed> Атрибуты для Route::group()
     */
    private function buildGroupAttributes(RouteNode $node): array
    {
        $attributes = [];

        if ($node->prefix) {
            $attributes['prefix'] = $node->prefix;
        }

        if ($node->domain) {
            $attributes['domain'] = $node->domain;
        }

        if ($node->namespace) {
            $attributes['namespace'] = $node->namespace;
        }

        if ($node->middleware) {
            $sanitized = $this->guard->sanitizeMiddleware($node->middleware);
            if (!empty($sanitized)) {
                $attributes['middleware'] = $sanitized;
            }
        }

        if ($node->where) {
            $attributes['where'] = $node->where;
        }

        return $attributes;
    }

    /**
     * Зарегистрировать конкретный маршрут.
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return void
     */
    private function registerRoute(RouteNode $node): void
    {
        if (!$node->uri || !$node->methods) {
            Log::warning('Dynamic route: пропущен маршрут без uri или methods', [
                'route_node_id' => $node->id,
            ]);
            return;
        }

        $methods = $node->methods;
        $uri = $node->uri;
        $action = $this->resolveAction($node);

        if ($action === null) {
            return; // Ошибка уже залогирована в resolveAction()
        }

        // Проверяем существование контроллера перед регистрацией
        // Это предотвращает ошибки при route:list для несуществующих контроллеров
        if (is_array($action) && count($action) === 2) {
            [$controller, $method] = $action;
            if (!class_exists($controller)) {
                Log::warning('Dynamic route: контроллер не существует, маршрут пропущен', [
                    'route_node_id' => $node->id,
                    'controller' => $controller,
                    'uri' => $uri,
                ]);
                return;
            }
            if (!method_exists($controller, $method)) {
                Log::warning('Dynamic route: метод не существует в контроллере, маршрут пропущен', [
                    'route_node_id' => $node->id,
                    'controller' => $controller,
                    'method' => $method,
                    'uri' => $uri,
                ]);
                return;
            }
        } elseif (is_string($action) && !class_exists($action)) {
            Log::warning('Dynamic route: invokable контроллер не существует, маршрут пропущен', [
                'route_node_id' => $node->id,
                'controller' => $action,
                'uri' => $uri,
            ]);
            return;
        }

        $route = Route::match($methods, $uri, $action);

        // Применяем дополнительные настройки маршрута
        if ($node->name) {
            $route->name($node->name);
        }

        if ($node->domain) {
            $route->domain($node->domain);
        }

        if ($node->middleware) {
            $sanitized = $this->guard->sanitizeMiddleware($node->middleware);
            if (!empty($sanitized)) {
                $route->middleware($sanitized);
            }
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

        // Для action_type=ENTRY добавляем route_node_id в defaults
        if ($node->action_type === RouteNodeActionType::ENTRY) {
            $route->defaults('route_node_id', $node->id);
        }
    }

    /**
     * Разрешить действие для маршрута.
     *
     * Обрабатывает различные форматы action в зависимости от action_type:
     * - CONTROLLER: Controller@method, Invokable, view:..., redirect:...
     * - ENTRY: EntryPageController@show с default route_node_id
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return callable|string|array<string>|null Действие для маршрута или null при ошибке
     */
    private function resolveAction(RouteNode $node): callable|string|array|null
    {
        if ($node->action_type === RouteNodeActionType::ENTRY) {
            // Для action_type=ENTRY используем EntryPageController@show
            // route_node_id будет передан через defaults
            return [\App\Http\Controllers\EntryPageController::class, 'show'];
        }

        if (!$node->action) {
            Log::warning('Dynamic route: отсутствует action для CONTROLLER', [
                'route_node_id' => $node->id,
            ]);
            return fn() => abort(404);
        }

        // Парсинг action
        $action = $node->action;

        // View: view:pages.about
        if (str_starts_with($action, 'view:')) {
            $viewName = substr($action, 5); // Убираем 'view:'
            return fn() => view($viewName);
        }

        // Redirect: redirect:/new-page:301 или redirect:/new-page
        if (str_starts_with($action, 'redirect:')) {
            $redirectPart = substr($action, 9); // Убираем 'redirect:'
            $parts = explode(':', $redirectPart, 2);
            $url = $parts[0];
            $status = isset($parts[1]) ? (int) $parts[1] : 302;
            return fn() => redirect($url, $status);
        }

        // Controller@method или Invokable controller
        if (str_contains($action, '@')) {
            // Controller@method
            [$controller, $method] = explode('@', $action, 2);
            if (!$this->guard->isControllerAllowed($controller)) {
                Log::error('Dynamic route: неразрешённый контроллер', [
                    'route_node_id' => $node->id,
                    'controller' => $controller,
                ]);
                return fn() => abort(404);
            }
            // Проверяем существование класса контроллера
            if (!class_exists($controller)) {
                Log::error('Dynamic route: контроллер не существует', [
                    'route_node_id' => $node->id,
                    'controller' => $controller,
                ]);
                return fn() => abort(404);
            }
            return [$controller, $method];
        } else {
            // Invokable controller
            if (!$this->guard->isControllerAllowed($action)) {
                Log::error('Dynamic route: неразрешённый контроллер', [
                    'route_node_id' => $node->id,
                    'controller' => $action,
                ]);
                return fn() => abort(404);
            }
            // Проверяем существование класса контроллера
            if (!class_exists($action)) {
                Log::error('Dynamic route: контроллер не существует', [
                    'route_node_id' => $node->id,
                    'controller' => $action,
                ]);
                return fn() => abort(404);
            }
            return $action;
        }
    }

    /**
     * Преобразовать RouteNode в массив для логирования.
     *
     * @param \App\Models\RouteNode $node
     * @return array<string, mixed>
     */
    private function nodeToArray(RouteNode $node): array
    {
        $data = [
            'id' => $node->id,
            'kind' => $node->kind?->value ?? null,
            'sort_order' => $node->sort_order,
            'enabled' => $node->enabled,
            'prefix' => $node->prefix,
            'domain' => $node->domain,
            'namespace' => $node->namespace,
            'uri' => $node->uri,
            'methods' => $node->methods,
            'name' => $node->name,
            'action_type' => $node->action_type?->value ?? null,
            'action' => $node->action,
            'middleware' => $node->middleware,
            'where' => $node->where,
            'defaults' => $node->defaults,
            'parent_id' => $node->parent_id,
        ];

        // Рекурсивно обрабатываем дочерние узлы
        if ($node->relationLoaded('children') && $node->children) {
            $data['children'] = $node->children->map(fn($child) => $this->nodeToArray($child))->toArray();
        }

        return $data;
    }
}

