<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderRouteNodesRequest;
use App\Http\Requests\Admin\StoreRouteNodeRequest;
use App\Http\Requests\Admin\UpdateRouteNodeRequest;
use App\Http\Resources\Admin\RouteNodeCollection;
use App\Http\Resources\Admin\RouteNodeResource;
use App\Models\RouteNode;
use App\Services\DynamicRoutes\RouteNodeDeletionService;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Контроллер для управления узлами маршрутов (RouteNode) в админ-панели.
 *
 * Предоставляет CRUD операции для узлов маршрутов: создание, чтение, обновление, удаление.
 * Управляет иерархическим деревом маршрутов.
 *
 * @package App\Http\Controllers\Admin\V1
 */
class RouteNodeController extends Controller
{
    use AuthorizesRequests;
    use ThrowsErrors;

    /**
     * Конструктор контроллера.
     */
    public function __construct()
    {
    }

    /**
     * Список узлов маршрутов.
     *
     * @group Admin ▸ Routes
     * @name List route nodes
     * @authenticated
     * @queryParam parent_id int Фильтр по ID родителя. Example: 1
     * @queryParam kind string Фильтр по типу. Values: group,route. Example: route
     * @queryParam enabled boolean Фильтр по статусу. Example: true
     * @queryParam per_page int Размер страницы (10-100). Default: 15.
     * @response status=200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "parent_id": null,
     *       "sort_order": 0,
     *       "enabled": true,
     *       "kind": "route",
     *       "name": "home",
     *       "uri": "/",
     *       "action_type": "controller",
     *       "action": "App\\Http\\Controllers\\HomeController",
     *       "created_at": "2025-01-10T12:00:00+00:00",
     *       "updated_at": "2025-01-10T12:00:00+00:00"
     *     }
     *   ],
     *   "links": {...},
     *   "meta": {...}
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED"
     * }
     */
    public function index(Request $request): RouteNodeCollection
    {
        $this->authorize('viewAny', RouteNode::class);

        $query = RouteNode::query()
            ->with(['parent', 'children', 'entry']);

        // Фильтр по parent_id
        if ($request->has('parent_id')) {
            $parentId = $request->input('parent_id');
            if ($parentId === 'null' || $parentId === null) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', (int) $parentId);
            }
        }

        // Фильтр по kind
        if ($request->has('kind')) {
            $kind = $request->input('kind');
            if (in_array($kind, ['group', 'route'], true)) {
                $query->where('kind', $kind);
            }
        }

        // Фильтр по enabled
        if ($request->has('enabled')) {
            $enabled = filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN);
            $query->where('enabled', $enabled);
        }

        // Сортировка
        $query->orderBy('sort_order')->orderBy('id');

        // Пагинация
        $perPage = (int) ($request->input('per_page', 15));
        $perPage = max(10, min(100, $perPage));

        $collection = $query->paginate($perPage);

        return new RouteNodeCollection($collection);
    }

    /**
     * Создание узла маршрута.
     *
     * @group Admin ▸ Routes
     * @name Create route node
     * @authenticated
     * @bodyParam kind string required Тип узла. Values: group,route. Example: route
     * @bodyParam parent_id int ID родителя. Example: 1
     * @bodyParam sort_order int Порядок сортировки. Default: 0.
     * @bodyParam enabled boolean Включён ли узел. Default: true.
     * @bodyParam name string Имя маршрута. Example: home
     * @bodyParam uri string URI паттерн (для kind=route). Example: /
     * @bodyParam methods array HTTP методы (для kind=route). Example: ["GET"]
     * @bodyParam action_type string Тип действия. Values: controller,entry. Example: controller
     * @bodyParam action string Действие. Example: App\\Http\\Controllers\\HomeController
     * @bodyParam entry_id int ID Entry (для action_type=entry). Example: 1
     * @response status=201 {
     *   "data": {
     *     "id": 1,
     *     "kind": "route",
     *     "uri": "/",
     *     "action_type": "controller",
     *     "action": "App\\Http\\Controllers\\HomeController",
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED"
     * }
     */
    public function store(StoreRouteNodeRequest $request): RouteNodeResource
    {
        $this->authorize('create', RouteNode::class);

        $validated = $request->validated();

        /** @var RouteNode $node */
        $node = DB::transaction(function () use ($validated) {
            return RouteNode::query()->create($validated);
        });

        Log::info('Admin route node created', [
            'route_node_id' => $node->id,
        ]);

        return new RouteNodeResource($node->load(['parent', 'children', 'entry']));
    }

    /**
     * Получение узла маршрута.
     *
     * @group Admin ▸ Routes
     * @name Show route node
     * @authenticated
     * @urlParam id int required ID узла. Example: 1
     * @response status=200 {
     *   "data": {
     *     "id": 1,
     *     "kind": "route",
     *     "uri": "/",
     *     "action_type": "controller",
     *     "action": "App\\Http\\Controllers\\HomeController",
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Route node not found",
     *   "status": 404,
     *   "code": "NOT_FOUND"
     * }
     */
    public function show(int $id): RouteNodeResource
    {
        $routeNode = RouteNode::query()->find($id);

        if (! $routeNode) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                sprintf('Route node with ID %d does not exist.', $id),
                ['route_node_id' => $id],
            );
        }

        $routeNode->load(['parent', 'children', 'entry']);

        return new RouteNodeResource($routeNode);
    }

    /**
     * Обновление узла маршрута.
     *
     * @group Admin ▸ Routes
     * @name Update route node
     * @authenticated
     * @urlParam id int required ID узла. Example: 1
     * @bodyParam kind string Тип узла. Values: group,route.
     * @bodyParam parent_id int ID родителя.
     * @bodyParam sort_order int Порядок сортировки.
     * @bodyParam enabled boolean Включён ли узел.
     * @bodyParam name string Имя маршрута.
     * @bodyParam uri string URI паттерн.
     * @bodyParam methods array HTTP методы.
     * @bodyParam action_type string Тип действия. Values: controller,entry.
     * @bodyParam action string Действие.
     * @bodyParam entry_id int ID Entry.
     * @response status=200 {
     *   "data": {
     *     "id": 1,
     *     "kind": "route",
     *     "uri": "/updated",
     *     "updated_at": "2025-01-10T12:05:00+00:00"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Route node not found",
     *   "status": 404,
     *   "code": "NOT_FOUND"
     * }
     */
    public function update(UpdateRouteNodeRequest $request, int $id): RouteNodeResource
    {
        $routeNode = RouteNode::query()->find($id);

        if (! $routeNode) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                sprintf('Route node with ID %d does not exist.', $id),
                ['route_node_id' => $id],
            );
        }

        $validated = $request->validated();

        DB::transaction(function () use ($routeNode, $validated) {
            $routeNode->update($validated);
        });

        Log::info('Admin route node updated', [
            'route_node_id' => $routeNode->id,
        ]);

        $routeNode->refresh();
        $routeNode->load(['parent', 'children', 'entry']);

        return new RouteNodeResource($routeNode);
    }

    /**
     * Удаление узла маршрута.
     *
     * Выполняет мягкое удаление (soft delete) узла и всех его дочерних узлов
     * (каскадное удаление). Все операции выполняются в транзакции.
     *
     * @group Admin ▸ Routes
     * @name Delete route node
     * @authenticated
     * @urlParam id int required ID узла. Example: 1
     * @response status=204
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Route node not found",
     *   "status": 404,
     *   "code": "NOT_FOUND"
     * }
     */
    public function destroy(int $id): Response
    {
        $routeNode = RouteNode::query()->find($id);

        if (! $routeNode) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                sprintf('Route node with ID %d does not exist.', $id),
                ['route_node_id' => $id],
            );
        }

        $this->authorize('delete', $routeNode);

        $deletionService = app(RouteNodeDeletionService::class);
        $deletedCount = $deletionService->deleteWithChildren($routeNode);

        Log::info('Admin route node deleted with children', [
            'route_node_id' => $id,
            'deleted_count' => $deletedCount,
        ]);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }

    /**
     * Переупорядочивание узлов маршрутов.
     *
     * Массовое изменение parent_id и sort_order для множества узлов.
     * Выполняется в транзакции для атомарности.
     *
     * @group Admin ▸ Routes
     * @name Reorder route nodes
     * @authenticated
     * @bodyParam nodes array required Массив узлов для переупорядочивания. Example: [{"id":1,"parent_id":null,"sort_order":0},{"id":2,"parent_id":1,"sort_order":0}]
     * @bodyParam nodes.*.id int required ID узла. Example: 1
     * @bodyParam nodes.*.parent_id int ID родителя (null для корневых). Example: null
     * @bodyParam nodes.*.sort_order int Порядок сортировки. Example: 0
     * @response status=200 {
     *   "data": {
     *     "updated": 2
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The given data was invalid."
     * }
     */
    public function reorder(ReorderRouteNodesRequest $request): JsonResponse
    {
        $this->authorize('manage', RouteNode::class);

        $validated = $request->validated();
        $nodes = $validated['nodes'];

        $updated = 0;

        try {
            DB::transaction(function () use ($nodes, &$updated) {
                foreach ($nodes as $nodeData) {
                    $id = (int) $nodeData['id'];
                    $parentId = isset($nodeData['parent_id']) ? (int) $nodeData['parent_id'] : null;
                    $sortOrder = isset($nodeData['sort_order']) ? (int) $nodeData['sort_order'] : 0;

                    $affected = RouteNode::query()
                        ->where('id', $id)
                        ->update([
                            'parent_id' => $parentId,
                            'sort_order' => $sortOrder,
                        ]);

                    if ($affected > 0) {
                        $updated++;
                    }
                }
            });

            Log::info('Admin route nodes reordered', [
                'updated_count' => $updated,
            ]);

            return response()->json([
                'data' => [
                    'updated' => $updated,
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            Log::error('Admin route nodes reorder failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->throwError(
                ErrorCode::INTERNAL_ERROR,
                'Ошибка при переупорядочивании узлов маршрутов.',
                ['error' => $e->getMessage()],
            );
        }
    }
}

