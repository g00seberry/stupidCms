<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Blueprint\StoreBlueprintRequest;
use App\Http\Requests\Admin\Blueprint\UpdateBlueprintRequest;
use App\Http\Resources\Admin\BlueprintResource;
use App\Models\Blueprint;
use App\Services\Blueprint\BlueprintStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Контроллер для управления Blueprint.
 *
 * Предоставляет CRUD операции для Blueprint: создание, чтение, обновление, удаление.
 * Управляет зависимостями и встраиваниями blueprint'ов.
 *
 * @group Admin ▸ Blueprints
 * @package App\Http\Controllers\Admin\V1
 */
class BlueprintController extends Controller
{
    /**
     * @param BlueprintStructureService $structureService
     */
    public function __construct(
        private readonly BlueprintStructureService $structureService
    ) {}

    /**
     * Список Blueprint.
     *
     * @group Admin ▸ Blueprints
     * @name List blueprints
     * @authenticated
     * @queryParam search string Поиск по name/code. Example: article
     * @queryParam sort_by string Поле сортировки. Values: created_at,name,code. Default: created_at.
     * @queryParam sort_dir string Направление сортировки. Values: asc,desc. Default: desc.
     * @queryParam per_page int Записей на страницу (10-100). Default: 15.
     * @response status=200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Article",
     *       "code": "article",
     *       "description": "Blog article structure",
     *       "paths_count": 5,
     *       "embeds_count": 2,
     *       "created_at": "2025-01-10T12:00:00+00:00",
     *       "updated_at": "2025-01-10T12:00:00+00:00"
     *     }
     *   ]
     * }
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Blueprint::query()
            ->withCount(['paths', 'embeds', 'postTypes']);

        // Поиск
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Сортировка
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = (int) $request->input('per_page', 15);
        $blueprints = $query->paginate($perPage);

        return BlueprintResource::collection($blueprints);
    }

    /**
     * Создать Blueprint.
     *
     * @group Admin ▸ Blueprints
     * @name Create blueprint
     * @authenticated
     * @bodyParam name string required Название blueprint. Example: Article
     * @bodyParam code string required Уникальный код (a-z0-9_). Example: article
     * @bodyParam description string Описание. Example: Blog article structure
     * @response status=201 {
     *   "data": {
     *     "id": 1,
     *     "name": "Article",
     *     "code": "article",
     *     "description": "Blog article structure",
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00"
     *   }
     * }
     *
     * @param StoreBlueprintRequest $request
     * @return BlueprintResource
     */
    public function store(StoreBlueprintRequest $request): BlueprintResource
    {
        $blueprint = $this->structureService->createBlueprint($request->validated());

        return new BlueprintResource($blueprint);
    }

    /**
     * Просмотр Blueprint.
     *
     * @group Admin ▸ Blueprints
     * @name Show blueprint
     * @authenticated
     * @urlParam blueprint integer required ID blueprint. Example: 1
     * @response status=200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Article",
     *     "code": "article",
     *     "description": "Blog article structure",
     *     "paths_count": 5,
     *     "embeds_count": 2,
     *     "embedded_in_count": 1,
     *     "post_types_count": 3,
     *     "post_types": [
     *       {"id": 1, "slug": "article", "name": "Article"}
     *     ],
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00"
     *   }
     * }
     *
     * @param Blueprint $blueprint
     * @return BlueprintResource
     */
    public function show(Blueprint $blueprint): BlueprintResource
    {
        $blueprint->loadCount(['paths', 'embeds', 'embeddedIn', 'postTypes'])
            ->load(['postTypes']);

        return new BlueprintResource($blueprint);
    }

    /**
     * Обновить Blueprint.
     *
     * @group Admin ▸ Blueprints
     * @name Update blueprint
     * @authenticated
     * @urlParam blueprint integer required ID blueprint. Example: 1
     * @bodyParam name string Название blueprint. Example: Article Updated
     * @bodyParam code string Уникальный код (a-z0-9_). Example: article_updated
     * @bodyParam description string Описание. Example: Updated description
     * @response status=200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Article Updated",
     *     "code": "article",
     *     "description": "Updated description",
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T13:00:00+00:00"
     *   }
     * }
     *
     * @param UpdateBlueprintRequest $request
     * @param Blueprint $blueprint
     * @return BlueprintResource
     */
    public function update(UpdateBlueprintRequest $request, Blueprint $blueprint): BlueprintResource
    {
        $updated = $this->structureService->updateBlueprint(
            $blueprint,
            $request->validated()
        );

        return new BlueprintResource($updated);
    }

    /**
     * Удалить Blueprint.
     *
     * @group Admin ▸ Blueprints
     * @name Delete blueprint
     * @authenticated
     * @urlParam blueprint integer required ID blueprint. Example: 1
     * @response status=200 {
     *   "message": "Blueprint удалён"
     * }
     * @response status=422 {
     *   "message": "Невозможно удалить blueprint",
     *   "reasons": ["Используется в 3 PostType", "Встроен в 2 других blueprint"]
     * }
     *
     * @param Blueprint $blueprint
     * @return JsonResponse
     */
    public function destroy(Blueprint $blueprint): JsonResponse
    {
        $check = $this->structureService->canDeleteBlueprint($blueprint);

        if (!$check['can_delete']) {
            return response()->json([
                'message' => 'Невозможно удалить blueprint',
                'reasons' => $check['reasons'],
            ], 422);
        }

        $this->structureService->deleteBlueprint($blueprint);

        return response()->json(['message' => 'Blueprint удалён'], 200);
    }

    /**
     * Проверить возможность удаления.
     *
     * @group Admin ▸ Blueprints
     * @name Check can delete blueprint
     * @authenticated
     * @urlParam blueprint integer required ID blueprint. Example: 1
     * @response status=200 {
     *   "can_delete": false,
     *   "reasons": ["Используется в 3 PostType", "Встроен в 2 других blueprint"]
     * }
     *
     * @param Blueprint $blueprint
     * @return JsonResponse
     */
    public function canDelete(Blueprint $blueprint): JsonResponse
    {
        $check = $this->structureService->canDeleteBlueprint($blueprint);

        return response()->json($check);
    }

    /**
     * Получить граф зависимостей.
     *
     * @group Admin ▸ Blueprints
     * @name Get blueprint dependencies
     * @authenticated
     * @urlParam blueprint integer required ID blueprint. Example: 1
     * @response status=200 {
     *   "depends_on": [
     *     {"id": 2, "code": "address", "name": "Address"},
     *     {"id": 3, "code": "geo", "name": "Geo"}
     *   ],
     *   "depended_by": [
     *     {"id": 5, "code": "company", "name": "Company"},
     *     {"id": 7, "code": "department", "name": "Department"}
     *   ]
     * }
     *
     * @param Blueprint $blueprint
     * @return JsonResponse
     */
    public function dependencies(Blueprint $blueprint): JsonResponse
    {
        $graph = $this->structureService->getDependencyGraph($blueprint);

        return response()->json([
            'depends_on' => Blueprint::whereIn('id', $graph['depends_on'])->get(['id', 'code', 'name']),
            'depended_by' => Blueprint::whereIn('id', $graph['depended_by'])->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * Получить список Blueprint, которые можно встроить.
     *
     * @group Admin ▸ Blueprints
     * @name Get embeddable blueprints
     * @authenticated
     * @urlParam blueprint integer required ID blueprint. Example: 1
     * @response status=200 {
     *   "data": [
     *     {"id": 2, "code": "address", "name": "Address"},
     *     {"id": 3, "code": "geo", "name": "Geo"}
     *   ]
     * }
     *
     * @param Blueprint $blueprint
     * @return JsonResponse
     */
    public function embeddable(Blueprint $blueprint): JsonResponse
    {
        $embeddable = $this->structureService->getEmbeddableBlueprintsFor($blueprint);

        return response()->json([
            'data' => $embeddable->map(fn($bp) => [
                'id' => $bp->id,
                'code' => $bp->code,
                'name' => $bp->name,
            ]),
        ]);
    }
}

