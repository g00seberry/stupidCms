<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Path\StorePathRequest;
use App\Http\Requests\Admin\Path\UpdatePathRequest;
use App\Http\Resources\Admin\PathResource;
use App\Models\Blueprint;
use App\Models\Path;
use App\Services\Blueprint\BlueprintStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Контроллер для управления Path (полями Blueprint).
 *
 * Предоставляет CRUD операции для Path: создание, чтение, обновление, удаление.
 * Управляет иерархией полей и материализацией путей.
 *
 * @group Admin ▸ Paths
 * @package App\Http\Controllers\Admin\V1
 */
class PathController extends Controller
{
    /**
     * @param BlueprintStructureService $structureService
     */
    public function __construct(
        private readonly BlueprintStructureService $structureService
    ) {}

    /**
     * Список Path для Blueprint.
     *
     * Возвращает дерево paths (собственные + материализованные).
     *
     * @group Admin ▸ Paths
     * @name List paths for blueprint
     * @authenticated
     * @urlParam blueprint integer required ID blueprint. Example: 1
     * @response status=200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "blueprint_id": 1,
     *       "parent_id": null,
     *       "name": "title",
     *       "full_path": "title",
     *       "data_type": "string",
     *       "cardinality": "one",
     *       "is_indexed": true,
     *       "is_readonly": false,
     *       "sort_order": 0,
     *       "validation_rules": {"required": true},
     *       "children": []
     *     }
     *   ]
     * }
     *
     * @param Blueprint $blueprint
     * @return AnonymousResourceCollection
     */
    public function index(Blueprint $blueprint): AnonymousResourceCollection
    {
        $paths = $blueprint->paths()
            ->with(['parent', 'sourceBlueprint', 'blueprintEmbed'])
            ->orderBy('sort_order')
            ->get();

        // Построить дерево
        $tree = $this->buildTree($paths);

        return PathResource::collection($tree);
    }

    /**
     * Создать Path.
     *
     * @group Admin ▸ Paths
     * @name Create path
     * @authenticated
     * @urlParam blueprint integer required ID blueprint. Example: 1
     * @bodyParam name string required Имя поля (a-z0-9_). Example: title
     * @bodyParam parent_id integer ID родительского поля (должен принадлежать тому же blueprint). Example: 5
     * @bodyParam data_type string required Тип данных. Values: string,text,int,float,bool,datetime,json,ref. Example: string
     * @bodyParam cardinality string Кардинальность. Values: one,many. Default: one.
     * @bodyParam is_indexed boolean Индексировать поле. Default: false.
     * @bodyParam sort_order integer Порядок сортировки. Default: 0.
     * @bodyParam validation_rules array Правила валидации (JSON). Example: {"required": true, "min": 1, "max": 100}
     * @bodyParam validation_rules.required boolean Обязательное поле. Example: true
     * @response status=201 {
     *   "data": {
     *     "id": 1,
     *     "blueprint_id": 1,
     *     "parent_id": null,
     *     "name": "title",
     *     "full_path": "title",
     *     "data_type": "string",
     *     "cardinality": "one",
     *     "is_indexed": true,
     *     "is_readonly": false,
     *     "sort_order": 0,
     *     "validation_rules": {"required": true},
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00"
     *   }
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/unprocessable-entity",
     *   "title": "Unprocessable Entity",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "Родительское поле должно принадлежать тому же blueprint 'article'.",
     *   "meta": {
     *     "errors": {
     *       "parent_id": ["Родительское поле должно принадлежать тому же blueprint 'article'."]
     *     }
     *   }
     * }
     * @response status=409 {
     *   "type": "https://stupidcms.dev/problems/conflict",
     *   "title": "Conflict",
     *   "status": 409,
     *   "code": "CONFLICT",
     *   "detail": "Путь 'author.test' уже существует в blueprint 'article'. Используйте другое имя поля или удалите существующий путь.",
     *   "meta": {
     *     "full_path": "author.test",
     *     "blueprint_code": "article"
     *   }
     * }
     *
     * @param StorePathRequest $request
     * @param Blueprint $blueprint
     * @return PathResource
     */
    public function store(StorePathRequest $request, Blueprint $blueprint): PathResource
    {
        $path = $this->structureService->createPath(
            $blueprint,
            $request->validated()
        );

        return new PathResource($path);
    }

    /**
     * Просмотр Path.
     *
     * @group Admin ▸ Paths
     * @name Show path
     * @authenticated
     * @urlParam path integer required ID path. Example: 1
     * @response status=200 {
     *   "data": {
     *     "id": 1,
     *     "blueprint_id": 1,
     *     "parent_id": null,
     *     "name": "title",
     *     "full_path": "title",
     *     "data_type": "string",
     *     "cardinality": "one",
     *     "is_indexed": true,
     *     "is_readonly": false,
     *     "sort_order": 0,
     *     "validation_rules": {"required": true},
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00"
     *   }
     * }
     *
     * @param Path $path
     * @return PathResource
     */
    public function show(Path $path): PathResource
    {
        $path->load(['blueprint', 'parent', 'children', 'sourceBlueprint', 'blueprintEmbed']);

        return new PathResource($path);
    }

    /**
     * Обновить Path.
     *
     * @group Admin ▸ Paths
     * @name Update path
     * @authenticated
     * @urlParam path integer required ID path. Example: 1
     * @bodyParam name string Имя поля (a-z0-9_). Example: title_updated
     * @bodyParam parent_id integer ID родительского поля. Example: 5
     * @bodyParam data_type string Тип данных. Values: string,text,int,float,bool,datetime,json,ref.
     * @bodyParam cardinality string Кардинальность. Values: one,many.
     * @bodyParam is_indexed boolean Индексировать поле.
     * @bodyParam sort_order integer Порядок сортировки.
     * @bodyParam validation_rules array Правила валидации (JSON).
     * @bodyParam validation_rules.required boolean Обязательное поле.
     * @response status=200 {
     *   "data": {
     *     "id": 1,
     *     "name": "title_updated",
     *     "full_path": "title_updated",
     *     "updated_at": "2025-01-10T13:00:00+00:00"
     *   }
     * }
     * @response status=422 {
     *   "message": "Невозможно редактировать скопированное поле 'author.contacts.phone'. Измените исходное поле в blueprint 'contact_info'."
     * }
     *
     * @param UpdatePathRequest $request
     * @param Path $path
     * @return PathResource
     */
    public function update(UpdatePathRequest $request, Path $path): PathResource
    {
        $updated = $this->structureService->updatePath(
            $path,
            $request->validated()
        );

        return new PathResource($updated);
    }

    /**
     * Удалить Path.
     *
     * @group Admin ▸ Paths
     * @name Delete path
     * @authenticated
     * @urlParam path integer required ID path. Example: 1
     * @response status=200 {
     *   "message": "Path удалён"
     * }
     * @response status=422 {
     *   "message": "Невозможно удалить скопированное поле 'author.contacts.phone'. Удалите встраивание в blueprint 'article'."
     * }
     *
     * @param Path $path
     * @return JsonResponse
     */
    public function destroy(Path $path): JsonResponse
    {
        $this->structureService->deletePath($path);

        return response()->json(['message' => 'Path удалён'], 200);
    }

    /**
     * Построить дерево paths.
     *
     * Рекурсивно группирует paths по parent_id для формирования иерархии.
     *
     * @param \Illuminate\Support\Collection<int, Path> $paths
     * @return \Illuminate\Support\Collection<int, Path>
     */
    private function buildTree($paths): \Illuminate\Support\Collection
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
}

