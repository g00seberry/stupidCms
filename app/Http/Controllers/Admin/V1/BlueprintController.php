<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Blueprint\StoreBlueprintRequest;
use App\Http\Requests\Admin\Blueprint\UpdateBlueprintRequest;
use App\Http\Resources\Admin\BlueprintResource;
use App\Models\Blueprint;
use App\Models\Path;
use App\Services\Blueprint\BlueprintStructureService;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorResponseFactory;
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
     * @param ErrorFactory $errors
     */
    public function __construct(
        private readonly BlueprintStructureService $structureService,
        private readonly ErrorFactory $errors
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
     *       "post_types_count": 3,
     *       "created_at": "2025-01-10T12:00:00+00:00",
     *       "updated_at": "2025-01-10T12:00:00+00:00"
     *     }
     *   ],
     *   "links": {
     *     "first": "http://example.com/api/admin/v1/blueprints?page=1",
     *     "last": "http://example.com/api/admin/v1/blueprints?page=1",
     *     "prev": null,
     *     "next": null
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "from": 1,
     *     "last_page": 1,
     *     "path": "http://example.com/api/admin/v1/blueprints",
     *     "per_page": 15,
     *     "to": 1,
     *     "total": 1
     *   }
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
     *       {"id": 1, "name": "Article"}
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
     *   "type": "https://stupidcms.dev/problems/unprocessable-entity",
     *   "title": "Unprocessable Entity",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "Невозможно удалить blueprint",
     *   "meta": {
     *     "reasons": ["Используется в 3 PostType", "Встроен в 2 других blueprint"]
     *   }
     * }
     *
     * @param Blueprint $blueprint
     * @return JsonResponse
     */
    public function destroy(Blueprint $blueprint): JsonResponse
    {
        $check = $this->structureService->canDeleteBlueprint($blueprint);

        if (!$check['can_delete']) {
            $payload = $this->errors->for(ErrorCode::VALIDATION_ERROR)
                ->detail('Невозможно удалить blueprint')
                ->meta([
                    'reasons' => $check['reasons'],
                    'blueprint_code' => $blueprint->code,
                ])
                ->build();

            return ErrorResponseFactory::make($payload);
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
            ])->values(),
        ]);
    }

    /**
     * Получить готовую JSON схему Blueprint из paths.
     *
     * Возвращает иерархическую JSON структуру со вложенными данными,
     * представляющую все поля blueprint и их свойства.
     *
     * @group Admin ▸ Blueprints
     * @name Get blueprint schema
     * @authenticated
     * @urlParam blueprint integer required ID blueprint. Example: 1
     * @response status=200 {
     *   "schema": {
     *     "title": {
     *       "type": "string",
     *       "indexed": true,
     *       "cardinality": "one",
     *       "validation": {"required": true}
     *     },
     *     "author": {
     *       "type": "json",
     *       "indexed": false,
     *       "cardinality": "one",
     *       "validation": {},
     *       "children": {
     *         "name": {
     *           "type": "string",
     *           "indexed": false,
     *           "cardinality": "one",
     *           "validation": {"required": true}
     *         },
     *         "email": {
     *           "type": "string",
     *           "indexed": true,
     *           "cardinality": "one",
     *           "validation": {"required": true}
     *         }
     *       }
     *     }
     *   }
     * }
     *
     * @param Blueprint $blueprint
     * @return JsonResponse
     */
    public function schema(Blueprint $blueprint): JsonResponse
    {
        $paths = $blueprint->paths()
            ->orderBy('sort_order')
            ->get();

        // Построить дерево
        $tree = $this->buildPathTree($paths);

        // Преобразовать в JSON схему
        $schema = $this->buildSchema($tree);

        return response()->json(['schema' => $schema]);
    }

    /**
     * Построить дерево paths.
     *
     * Рекурсивно группирует paths по parent_id для формирования иерархии.
     *
     * @param \Illuminate\Support\Collection<int, Path> $paths
     * @return \Illuminate\Support\Collection<int, Path>
     */
    private function buildPathTree($paths): \Illuminate\Support\Collection
    {
        $grouped = $paths->groupBy('parent_id');

        $buildChildren = function ($parentId = null) use ($grouped, &$buildChildren) {
            if (!isset($grouped[$parentId])) {
                return collect();
            }

            return $grouped[$parentId]->map(function ($path) use ($buildChildren) {
                $path->children = $buildChildren($path->id);
                return $path;
            });
        };

        return $buildChildren(null);
    }

    /**
     * Построить JSON схему из дерева paths.
     *
     * Преобразует иерархическую структуру paths в JSON схему со вложенными данными.
     *
     * @param \Illuminate\Support\Collection<int, Path> $tree Дерево paths
     * @return array<string, mixed> JSON схема
     */
    private function buildSchema($tree): array
    {
        $schema = [];

        foreach ($tree as $path) {
            $fieldSchema = [
                'type' => $path->data_type,
                'indexed' => (bool) $path->is_indexed,
                'cardinality' => $path->cardinality,
                'validation' => $path->validation_rules ?? new \stdClass(),
            ];

            // Если есть дочерние элементы, добавляем их в children
            if ($path->children && $path->children->isNotEmpty()) {
                $fieldSchema['children'] = $this->buildSchema($path->children);
            }

            $schema[$path->name] = $fieldSchema;
        }

        return $schema;
    }
}

