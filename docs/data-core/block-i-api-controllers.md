# Блок I: API контроллеры и ресурсы

**Трудоёмкость:** 34 часа (Should Have)  
**Критичность:** 🟡 Важно для работы с системой  
**Результат:** REST API для Blueprint, Path, BlueprintEmbed, Entry + Resources

---

## I.1. BlueprintController

`app/Http/Controllers/Admin/BlueprintController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Blueprint\StoreBlueprintRequest;
use App\Http\Requests\Admin\Blueprint\UpdateBlueprintRequest;
use App\Http\Resources\Admin\BlueprintResource;
use App\Models\Blueprint;
use App\Services\Blueprint\BlueprintStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Управление Blueprint.
 *
 * @group Blueprint Management
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
     * @queryParam search string Поиск по name/code
     * @queryParam per_page int Записей на страницу (default: 15)
     *
     * @return ResourceCollection
     */
    public function index(Request $request): ResourceCollection
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
```

### Request: StoreBlueprintRequest

`app/Http/Requests/Admin/Blueprint/StoreBlueprintRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Blueprint;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlueprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization via middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:blueprints,code', 'regex:/^[a-z0-9_]+$/'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Код может содержать только строчные буквы, цифры и подчёркивания.',
            'code.unique' => 'Blueprint с таким кодом уже существует.',
        ];
    }
}
```

### Request: UpdateBlueprintRequest

`app/Http/Requests/Admin/Blueprint/UpdateBlueprintRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Blueprint;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBlueprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('blueprints', 'code')->ignore($this->blueprint),
                'regex:/^[a-z0-9_]+$/',
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

---

## I.2. PathController

`app/Http/Controllers/Admin/PathController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Path\StorePathRequest;
use App\Http\Requests\Admin\Path\UpdatePathRequest;
use App\Http\Resources\Admin\PathResource;
use App\Models\Blueprint;
use App\Models\Path;
use App\Services\Blueprint\BlueprintStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Управление Path (полями Blueprint).
 *
 * @group Path Management
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
     * @param Blueprint $blueprint
     * @return ResourceCollection
     */
    public function index(Blueprint $blueprint): ResourceCollection
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
     * @param \Illuminate\Support\Collection $paths
     * @return \Illuminate\Support\Collection
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
```

### Request: StorePathRequest

`app/Http/Requests/Admin/Path/StorePathRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Path;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePathRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
            'parent_id' => ['nullable', 'integer', 'exists:paths,id'],
            'data_type' => ['required', Rule::in(['string', 'text', 'int', 'float', 'bool', 'date', 'datetime', 'json', 'ref'])],
            'cardinality' => ['sometimes', Rule::in(['one', 'many'])],
            'is_required' => ['sometimes', 'boolean'],
            'is_indexed' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'validation_rules' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Имя поля может содержать только строчные буквы, цифры и подчёркивания.',
        ];
    }
}
```

### Request: UpdatePathRequest

`app/Http/Requests/Admin/Path/UpdatePathRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Path;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePathRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:paths,id'],
            'data_type' => ['sometimes', Rule::in(['string', 'text', 'int', 'float', 'bool', 'date', 'datetime', 'json', 'ref'])],
            'cardinality' => ['sometimes', Rule::in(['one', 'many'])],
            'is_required' => ['sometimes', 'boolean'],
            'is_indexed' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'validation_rules' => ['nullable', 'array'],
        ];
    }
}
```

---

## I.3. BlueprintEmbedController

`app/Http/Controllers/Admin/BlueprintEmbedController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlueprintEmbed\StoreEmbedRequest;
use App\Http\Resources\Admin\BlueprintEmbedResource;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use App\Services\Blueprint\BlueprintStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Управление встраиваниями Blueprint.
 *
 * @group Blueprint Embeds
 */
class BlueprintEmbedController extends Controller
{
    /**
     * @param BlueprintStructureService $structureService
     */
    public function __construct(
        private readonly BlueprintStructureService $structureService
    ) {}

    /**
     * Список встраиваний Blueprint.
     *
     * @param Blueprint $blueprint
     * @return ResourceCollection
     */
    public function index(Blueprint $blueprint): ResourceCollection
    {
        $embeds = $blueprint->embeds()
            ->with(['embeddedBlueprint', 'hostPath'])
            ->get();

        return BlueprintEmbedResource::collection($embeds);
    }

    /**
     * Создать встраивание.
     *
     * @param StoreEmbedRequest $request
     * @param Blueprint $blueprint
     * @return BlueprintEmbedResource
     */
    public function store(StoreEmbedRequest $request, Blueprint $blueprint): BlueprintEmbedResource
    {
        $embedded = Blueprint::findOrFail($request->input('embedded_blueprint_id'));

        $hostPath = $request->input('host_path_id')
            ? Path::findOrFail($request->input('host_path_id'))
            : null;

        $embed = $this->structureService->createEmbed(
            $blueprint,
            $embedded,
            $hostPath
        );

        $embed->load(['embeddedBlueprint', 'hostPath']);

        return new BlueprintEmbedResource($embed);
    }

    /**
     * Просмотр встраивания.
     *
     * @param BlueprintEmbed $embed
     * @return BlueprintEmbedResource
     */
    public function show(BlueprintEmbed $embed): BlueprintEmbedResource
    {
        $embed->load(['blueprint', 'embeddedBlueprint', 'hostPath']);

        return new BlueprintEmbedResource($embed);
    }

    /**
     * Удалить встраивание.
     *
     * @param BlueprintEmbed $embed
     * @return JsonResponse
     */
    public function destroy(BlueprintEmbed $embed): JsonResponse
    {
        $this->structureService->deleteEmbed($embed);

        return response()->json(['message' => 'Встраивание удалено'], 200);
    }
}
```

### Request: StoreEmbedRequest

`app/Http/Requests/Admin/BlueprintEmbed/StoreEmbedRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\BlueprintEmbed;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmbedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'embedded_blueprint_id' => ['required', 'integer', 'exists:blueprints,id'],
            'host_path_id' => ['nullable', 'integer', 'exists:paths,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'embedded_blueprint_id.required' => 'Укажите Blueprint для встраивания.',
            'embedded_blueprint_id.exists' => 'Указанный Blueprint не найден.',
            'host_path_id.exists' => 'Указанный Path не найден.',
        ];
    }
}
```

---

## I.5. API Resources

### BlueprintResource

`app/Http/Resources/Admin/BlueprintResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Blueprint
 */
class BlueprintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,

            // Счётчики (если загружены)
            'paths_count' => $this->whenCounted('paths'),
            'embeds_count' => $this->whenCounted('embeds'),
            'embedded_in_count' => $this->whenCounted('embeddedIn'),
            'post_types_count' => $this->whenCounted('postTypes'),

            // Связи
            'post_types' => $this->whenLoaded('postTypes', function () {
                return $this->postTypes->map(fn($pt) => [
                    'id' => $pt->id,
                    'slug' => $pt->slug,
                    'name' => $pt->name,
                ]);
            }),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
```

### PathResource

`app/Http/Resources/Admin/PathResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Path
 */
class PathResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'blueprint_id' => $this->blueprint_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'full_path' => $this->full_path,
            'data_type' => $this->data_type,
            'cardinality' => $this->cardinality,
            'is_required' => $this->is_required,
            'is_indexed' => $this->is_indexed,
            'is_readonly' => $this->is_readonly,
            'sort_order' => $this->sort_order,
            'validation_rules' => $this->validation_rules,

            // Источник копии (если копия)
            'source_blueprint_id' => $this->source_blueprint_id,
            'source_blueprint' => $this->whenLoaded('sourceBlueprint', function () {
                return [
                    'id' => $this->sourceBlueprint->id,
                    'code' => $this->sourceBlueprint->code,
                    'name' => $this->sourceBlueprint->name,
                ];
            }),

            // Embed (если копия)
            'blueprint_embed_id' => $this->blueprint_embed_id,

            // Дочерние поля (если загружены)
            'children' => PathResource::collection($this->whenLoaded('children')),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
```

### BlueprintEmbedResource

`app/Http/Resources/Admin/BlueprintEmbedResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\BlueprintEmbed
 */
class BlueprintEmbedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'blueprint_id' => $this->blueprint_id,
            'embedded_blueprint_id' => $this->embedded_blueprint_id,
            'host_path_id' => $this->host_path_id,

            // Связи
            'blueprint' => $this->whenLoaded('blueprint', fn() => [
                'id' => $this->blueprint->id,
                'code' => $this->blueprint->code,
                'name' => $this->blueprint->name,
            ]),

            'embedded_blueprint' => $this->whenLoaded('embeddedBlueprint', fn() => [
                'id' => $this->embeddedBlueprint->id,
                'code' => $this->embeddedBlueprint->code,
                'name' => $this->embeddedBlueprint->name,
            ]),

            'host_path' => $this->whenLoaded('hostPath', function () {
                return $this->hostPath ? [
                    'id' => $this->hostPath->id,
                    'name' => $this->hostPath->name,
                    'full_path' => $this->hostPath->full_path,
                ] : null;
            }),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
```

---

## Маршруты

`routes/api_admin.php`:

```php
<?php

use App\Http\Controllers\Admin\BlueprintController;
use App\Http\Controllers\Admin\PathController;
use App\Http\Controllers\Admin\BlueprintEmbedController;
use Illuminate\Support\Facades\Route;

Route::prefix('blueprints')->group(function () {
    // CRUD Blueprint
    Route::get('/', [BlueprintController::class, 'index']);
    Route::post('/', [BlueprintController::class, 'store']);
    Route::get('/{blueprint}', [BlueprintController::class, 'show']);
    Route::put('/{blueprint}', [BlueprintController::class, 'update']);
    Route::delete('/{blueprint}', [BlueprintController::class, 'destroy']);

    // Вспомогательные endpoints
    Route::get('/{blueprint}/can-delete', [BlueprintController::class, 'canDelete']);
    Route::get('/{blueprint}/dependencies', [BlueprintController::class, 'dependencies']);
    Route::get('/{blueprint}/embeddable', [BlueprintController::class, 'embeddable']);

    // CRUD Path
    Route::get('/{blueprint}/paths', [PathController::class, 'index']);
    Route::post('/{blueprint}/paths', [PathController::class, 'store']);

    // CRUD BlueprintEmbed
    Route::get('/{blueprint}/embeds', [BlueprintEmbedController::class, 'index']);
    Route::post('/{blueprint}/embeds', [BlueprintEmbedController::class, 'store']);
});

// Path (глобальные операции)
Route::prefix('paths')->group(function () {
    Route::get('/{path}', [PathController::class, 'show']);
    Route::put('/{path}', [PathController::class, 'update']);
    Route::delete('/{path}', [PathController::class, 'destroy']);
});

// BlueprintEmbed (глобальные операции)
Route::prefix('embeds')->group(function () {
    Route::get('/{embed}', [BlueprintEmbedController::class, 'show']);
    Route::delete('/{embed}', [BlueprintEmbedController::class, 'destroy']);
});
```

---

## Примеры API запросов

### Создать Blueprint

```bash
POST /api/admin/blueprints
Content-Type: application/json

{
  "name": "Article",
  "code": "article",
  "description": "Blog article structure"
}

# Response
{
  "data": {
    "id": 1,
    "name": "Article",
    "code": "article",
    "description": "Blog article structure",
    "paths_count": 0,
    "embeds_count": 0,
    "created_at": "2025-11-20T10:00:00Z",
    "updated_at": "2025-11-20T10:00:00Z"
  }
}
```

### Добавить поле

```bash
POST /api/admin/blueprints/1/paths
Content-Type: application/json

{
  "name": "title",
  "data_type": "string",
  "is_required": true,
  "is_indexed": true
}

# Response
{
  "data": {
    "id": 1,
    "blueprint_id": 1,
    "name": "title",
    "full_path": "title",
    "data_type": "string",
    "cardinality": "one",
    "is_required": true,
    "is_indexed": true,
    "is_readonly": false,
    "sort_order": 0
  }
}
```

### Добавить вложенное поле

```bash
POST /api/admin/blueprints/1/paths
Content-Type: application/json

{
  "name": "name",
  "parent_id": 5,
  "data_type": "string",
  "is_indexed": true
}

# Response
{
  "data": {
    "id": 6,
    "parent_id": 5,
    "name": "name",
    "full_path": "author.name",
    ...
  }
}
```

### Создать встраивание

```bash
POST /api/admin/blueprints/1/embeds
Content-Type: application/json

{
  "embedded_blueprint_id": 2,
  "host_path_id": 5
}

# Response
{
  "data": {
    "id": 1,
    "blueprint_id": 1,
    "embedded_blueprint_id": 2,
    "host_path_id": 5,
    "embedded_blueprint": {
      "id": 2,
      "code": "address",
      "name": "Address"
    },
    "host_path": {
      "id": 5,
      "name": "office",
      "full_path": "office"
    }
  }
}
```

### Проверить возможность удаления

```bash
GET /api/admin/blueprints/1/can-delete

# Response
{
  "can_delete": false,
  "reasons": [
    "Используется в 3 PostType",
    "Встроен в 2 других blueprint"
  ]
}
```

### Получить граф зависимостей

```bash
GET /api/admin/blueprints/1/dependencies

# Response
{
  "depends_on": [
    {"id": 2, "code": "address", "name": "Address"},
    {"id": 3, "code": "geo", "name": "Geo"}
  ],
  "depended_by": [
    {"id": 5, "code": "company", "name": "Company"},
    {"id": 7, "code": "department", "name": "Department"}
  ]
}
```

### Получить дерево полей

```bash
GET /api/admin/blueprints/1/paths

# Response
{
  "data": [
    {
      "id": 1,
      "name": "title",
      "full_path": "title",
      "is_readonly": false,
      "children": []
    },
    {
      "id": 2,
      "name": "author",
      "full_path": "author",
      "is_readonly": false,
      "children": [
        {
          "id": 3,
          "name": "name",
          "full_path": "author.name",
          "is_readonly": false
        },
        {
          "id": 4,
          "name": "contacts",
          "full_path": "author.contacts",
          "is_readonly": false,
          "children": [
            {
              "id": 5,
              "name": "phone",
              "full_path": "author.contacts.phone",
              "is_readonly": true,
              "source_blueprint_id": 3
            }
          ]
        }
      ]
    }
  ]
}
```

---

## Обработка ошибок

### Попытка создать цикл

```bash
POST /api/admin/blueprints/1/embeds
{
  "embedded_blueprint_id": 2
}

# Response (422)
{
  "message": "Циклическая зависимость: 'address' уже зависит от 'article'"
}
```

### Конфликт путей

```bash
POST /api/admin/blueprints/1/embeds
{
  "embedded_blueprint_id": 2
}

# Response (422)
{
  "message": "Невозможно встроить blueprint 'address' в 'article': конфликт путей: 'email'"
}
```

### Попытка редактировать скопированное поле

```bash
PUT /api/admin/paths/10
{
  "name": "new_name"
}

# Response (422)
{
  "message": "Невозможно редактировать скопированное поле 'author.contacts.phone'. Измените исходное поле в blueprint 'contact_info'."
}
```

---

## Команды

```bash
# Создать контроллеры
php artisan make:controller Admin/BlueprintController --api
php artisan make:controller Admin/PathController --api
php artisan make:controller Admin/BlueprintEmbedController --api

# Создать Request классы
php artisan make:request Admin/Blueprint/StoreBlueprintRequest
php artisan make:request Admin/Blueprint/UpdateBlueprintRequest
php artisan make:request Admin/Path/StorePathRequest
php artisan make:request Admin/Path/UpdatePathRequest
php artisan make:request Admin/BlueprintEmbed/StoreEmbedRequest

# Создать Resources
php artisan make:resource Admin/BlueprintResource
php artisan make:resource Admin/PathResource
php artisan make:resource Admin/BlueprintEmbedResource

# Сгенерировать документацию Scribe
composer scribe:gen
```

---

## Feature тесты

`tests/Feature/Admin/BlueprintControllerTest.php`:

```php
<?php

use App\Models\Blueprint;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('можно создать blueprint', function () {
    $response = $this->postJson('/api/admin/blueprints', [
        'name' => 'Test Blueprint',
        'code' => 'test_bp',
        'description' => 'Test',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'test_bp');

    $this->assertDatabaseHas('blueprints', ['code' => 'test_bp']);
});

test('нельзя создать blueprint с дублирующимся code', function () {
    Blueprint::factory()->create(['code' => 'existing']);

    $response = $this->postJson('/api/admin/blueprints', [
        'name' => 'Test',
        'code' => 'existing',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

test('можно получить список blueprints', function () {
    Blueprint::factory()->count(3)->create();

    $response = $this->getJson('/api/admin/blueprints');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('можно удалить неиспользуемый blueprint', function () {
    $blueprint = Blueprint::factory()->create();

    $response = $this->deleteJson("/api/admin/blueprints/{$blueprint->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('blueprints', ['id' => $blueprint->id]);
});
```

---

**Результат:** REST API готов, валидация работает, ошибки понятны, ресурсы структурированы.

**Создано 7 документов (230 часов):**
- A-H: Must Have (196 ч)
- I: API контроллеры (34 ч)

**Осталось опционально:** J (тестирование), K-M (оптимизация, мониторинг, документация).

