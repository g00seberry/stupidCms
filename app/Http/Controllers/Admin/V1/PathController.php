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
use App\Services\Path\Constraints\PathConstraintsBuilderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

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
     * @param PathConstraintsBuilderRegistry $constraintsBuilderRegistry
     */
    public function __construct(
        private readonly BlueprintStructureService $structureService,
        private readonly PathConstraintsBuilderRegistry $constraintsBuilderRegistry
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
            ->with(['parent', 'blueprintEmbed.embeddedBlueprint'])
            ->orderBy('id')
            ->get();

        // Загрузить constraints для всех paths через билдеры
        foreach ($paths as $path) {
            $this->loadConstraintsRelations($path);
        }

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
        $validated = $request->validated();
        
        // Извлечь constraints из данных перед передачей в сервис
        $constraints = $validated['constraints'] ?? null;
        unset($validated['constraints']);

        $path = $this->structureService->createPath(
            $blueprint,
            $validated
        );

        // Обработать constraints после создания Path
        if ($constraints !== null) {
            $dataType = $validated['data_type'] ?? $path->data_type;
            $this->syncConstraints($path, $constraints, $dataType);
        }

        // Загрузить constraints для ответа
        $this->loadConstraintsRelations($path);

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
        $path->load(['blueprint', 'parent', 'children', 'blueprintEmbed.embeddedBlueprint']);

        // Загрузить constraints через билдер
        $this->loadConstraintsRelations($path);

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
        $validated = $request->validated();
        
        // Извлечь constraints из данных перед передачей в сервис
        $constraints = $validated['constraints'] ?? null;
        unset($validated['constraints']);

        $updated = $this->structureService->updatePath(
            $path,
            $validated
        );

        // Синхронизировать constraints, если они переданы в запросе
        if ($constraints !== null) {
            $this->syncConstraints($updated, $constraints, $updated->data_type);
        }

        // Загрузить constraints для ответа
        $updated->load('refConstraints');

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

    /**
     * Синхронизировать constraints для Path на основе типа данных.
     *
     * Использует регистр билдеров для получения соответствующего билдера
     * и делегирует синхронизацию ему.
     *
     * @param Path $path Path, для которого синхронизируются constraints
     * @param array<string, mixed> $constraints Массив constraints в формате API
     * @param string $dataType Тип данных поля
     * @return void
     */
    private function syncConstraints(Path $path, array $constraints, string $dataType): void
    {
        $builder = $this->constraintsBuilderRegistry->getBuilder($dataType);

        if ($builder !== null) {
            $builder->sync($path, $constraints);
        }
    }

    /**
     * Загрузить необходимые связи для constraints всех типов.
     *
     * Использует регистр билдеров для загрузки связей в зависимости от типа данных Path.
     *
     * @param Path $path Path для загрузки связей
     * @return void
     */
    private function loadConstraintsRelations(Path $path): void
    {
        $builder = $this->constraintsBuilderRegistry->getBuilder($path->data_type);

        if ($builder !== null) {
            $builder->loadRelations($path);
        }
    }
}

