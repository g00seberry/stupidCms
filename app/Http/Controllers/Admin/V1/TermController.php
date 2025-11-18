<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\Concerns\ManagesEntryTerms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexTermsRequest;
use App\Http\Requests\Admin\StoreTermRequest;
use App\Http\Requests\Admin\UpdateTermRequest;
use App\Http\Resources\Admin\TermCollection;
use App\Http\Resources\Admin\TermResource;
use App\Models\Taxonomy;
use App\Models\Term;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use App\Support\Http\AdminResponse;
use App\Support\TermHierarchy\TermHierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Контроллер для управления термами таксономий в админ-панели.
 *
 * Предоставляет CRUD операции для термов: создание, чтение, обновление, удаление.
 * Управляет иерархией термов (родитель-потомок).
 * Привязка термов к записям выполняется через EntryTermsController.
 *
 * @package App\Http\Controllers\Admin\V1
 */
class TermController extends Controller
{
    use ManagesEntryTerms;
    use ThrowsErrors;

    /**
     * @param \App\Support\TermHierarchy\TermHierarchyService $hierarchyService Сервис для управления иерархией термов
     */
    public function __construct(
        private readonly TermHierarchyService $hierarchyService
    ) {
    }

    /**
     * Список термов внутри таксономии.
     *
     * @group Admin ▸ Terms
     * @name List terms
     * @authenticated
     * @urlParam taxonomy int required ID таксономии. Example: 1
     * @queryParam q string Поиск по имени (<=255). Example: guides
     * @queryParam sort string Сортировка. Values: created_at.desc,created_at.asc,name.asc,name.desc. Default: created_at.desc.
     * @queryParam per_page int Размер страницы (10-100). Default: 15.
     * @response status=200 {
     *   "data": [
     *     {
     *       "id": 3,
     *       "taxonomy": 1,
     *       "name": "Guides",
     *       "meta_json": {},
     *       "created_at": "2025-01-10T12:00:00+00:00",
     *       "updated_at": "2025-01-10T12:00:00+00:00",
     *       "deleted_at": null
     *     }
     *   ]
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "5e9f51ce-7cee-d543-b8b7-cb6c30033f3f",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-5e9f51ce7ceed543b8b7cb6c30033f3f-5e9f51ce7ceed543-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Taxonomy not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Taxonomy with ID 1 does not exist.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e5e9f51ce",
     *     "taxonomy_id": 1
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e5e9f51ce-eed543b8b7cb6c30-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e5e9f51cf",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e5e9f51cf-eed543b8b7cb6c30-01"
     * }
     */
    /**
     * Список термов внутри таксономии.
     *
     * @param \App\Http\Requests\Admin\IndexTermsRequest $request Запрос с параметрами
     * @param int $taxonomy ID таксономии
     * @return \App\Http\Resources\Admin\TermCollection Коллекция термов
     */
    public function indexByTaxonomy(IndexTermsRequest $request, int $taxonomy): TermCollection
    {
        $taxonomyModel = $this->findTaxonomy($taxonomy);

        if (! $taxonomyModel) {
            $this->throwTaxonomyNotFound($taxonomy);
        }

        $validated = $request->validated();

        $query = Term::query()
            ->with('taxonomy')
            ->where('taxonomy_id', $taxonomyModel->id);

        if (! empty($validated['q'])) {
            $search = $validated['q'];
            $query->where('name', 'like', '%' . $search . '%');
        }

        [$column, $direction] = $this->resolveSort($validated['sort'] ?? 'created_at.desc');
        $query->orderBy($column, $direction);

        $perPage = $validated['per_page'] ?? 15;
        $perPage = max(10, min(100, $perPage));

        $collection = new TermCollection($query->paginate($perPage));

        return $collection;
    }

    /**
     * Получение дерева терминов таксономии.
     *
     * @group Admin ▸ Terms
     * @name Get terms tree
     * @authenticated
     * @urlParam taxonomy int required ID таксономии. Example: 1
     * @response status=200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "taxonomy": 1,
     *       "name": "Технологии",
     *       "meta_json": {},
     *       "parent_id": null,
     *       "created_at": "2025-01-10T12:00:00+00:00",
     *       "updated_at": "2025-01-10T12:00:00+00:00",
     *       "deleted_at": null,
     *       "children": [
     *         {
     *           "id": 2,
     *           "taxonomy": 1,
     *           "name": "Laravel",
     *           "meta_json": {},
     *           "parent_id": 1,
     *           "created_at": "2025-01-10T12:05:00+00:00",
     *           "updated_at": "2025-01-10T12:05:00+00:00",
     *           "deleted_at": null,
     *           "children": []
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    /**
     * Получение дерева терминов таксономии.
     *
     * @param int $taxonomy ID таксономии
     * @return \Illuminate\Http\JsonResponse JSON ответ с деревом термов
     */
    public function tree(int $taxonomy): \Illuminate\Http\JsonResponse
    {
        $taxonomyModel = $this->findTaxonomy($taxonomy);

        if (! $taxonomyModel) {
            $this->throwTaxonomyNotFound($taxonomy);
        }

        // Если таксономия не иерархическая, возвращаем плоский список
        if (! $taxonomyModel->hierarchical) {
            $terms = Term::query()
                ->where('taxonomy_id', $taxonomyModel->id)
                ->get();
            
            return response()->json([
                'data' => TermResource::collection($terms),
            ]);
        }

        // Получаем все термины с загрузкой parent для вычисления parent_id
        $allTerms = Term::query()
            ->where('taxonomy_id', $taxonomyModel->id)
            ->with('parent')
            ->get()
            ->keyBy('id');

        // Загружаем детей для каждого термина
        foreach ($allTerms as $term) {
            $children = $allTerms->filter(function ($child) use ($term) {
                $childParentId = $child->parent_id;
                return $childParentId === $term->id;
            })->sortBy('name')->values();
            
            $term->setRelation('children', $children);
        }

        // Находим корневые термины (без родителя)
        $rootTerms = $allTerms->filter(function ($term) {
            return $term->parent_id === null;
        })->sortBy('name')->values();

        return response()->json([
            'data' => TermResource::collection($rootTerms),
        ]);
    }

    /**
     * Создание терма.
     *
     * @group Admin ▸ Terms
     * @name Create term
     * @authenticated
     * @urlParam taxonomy int required ID таксономии. Example: 1
     * @bodyParam name string required Название (<=255). Example: Guides
     * @bodyParam meta_json object Мета-данные. Example: {"color":"#ffcc00"}
     * @response status=201 {
     *   "data": {
     *     "id": 3,
     *     "taxonomy": 1,
     *     "name": "Guides",
     *     "meta_json": {},
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "cb6c3003-3f5e-5e9f-51ce-7ceed543b8b7",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-cb6c30033f5e5e9f51ce7ceed543b8b7-cb6c30033f5e5e9f-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Taxonomy not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Taxonomy with ID 1 does not exist.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5ecb6c3003",
     *     "taxonomy_id": 1
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5ecb6c3003-eed543b8b7cb6c30-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The name field is required.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5ecb6c3004",
     *     "errors": {
     *       "name": [
     *         "The name field is required."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5ecb6c3004-eed543b8b7cb6c30-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5ecb6c3005",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5ecb6c3005-eed543b8b7cb6c30-01"
     * }
     */
    /**
     * Создание терма.
     *
     * @param \App\Http\Requests\Admin\StoreTermRequest $request Запрос с данными
     * @param int $taxonomy ID таксономии
     * @return \App\Http\Resources\Admin\TermResource Ресурс терма
     */
    public function store(StoreTermRequest $request, int $taxonomy): TermResource
    {
        $taxonomyModel = $this->findTaxonomy($taxonomy);

        if (! $taxonomyModel) {
            $this->throwTaxonomyNotFound($taxonomy);
        }

        $validated = $request->validated();
        $name = trim((string) $validated['name']);
        $meta = $validated['meta_json'] ?? null;
        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;

        $term = null;

        DB::transaction(function () use (&$term, $taxonomyModel, $name, $meta, $parentId) {
            $term = Term::query()->create([
                'taxonomy_id' => $taxonomyModel->id,
                'name' => $name,
                'meta_json' => $meta,
            ]);

            // Устанавливаем иерархию, если таксономия поддерживает её
            if ($taxonomyModel->hierarchical) {
                try {
                    $this->hierarchyService->setParent($term, $parentId);
                } catch (\InvalidArgumentException $e) {
                    throw ValidationException::withMessages([
                        'parent_id' => [$e->getMessage()],
                    ]);
                }
            }
        });

        $term->load('taxonomy');

        Log::info('Admin term created', [
            'term_id' => $term->id,
            'taxonomy_id' => $taxonomyModel->id,
        ]);

        return new TermResource($term, true);
    }

    /**
     * Получение терма по ID.
     *
     * @group Admin ▸ Terms
     * @name Show term
     * @authenticated
     * @urlParam term int required ID терма. Example: 3
     * @response status=200 {
     *   "data": {
     *     "id": 3,
     *     "taxonomy": 1,
     *     "name": "Guides",
     *     "meta_json": {},
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00",
     *     "deleted_at": null
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "5ecb6c30-033f-3f5e-5e9f-51ce7ceed543",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-5ecb6c30033f3f5e5e9f51ce7ceed543-5ecb6c30033f3f5e-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Term not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Term with ID 3 does not exist.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e5ecb6c30",
     *     "term_id": 3
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e5ecb6c30-eed543b8b7cb6c30-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e5ecb6c31",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e5ecb6c31-eed543b8b7cb6c30-01"
     * }
     */
    public function show(int $term): TermResource
    {
        $termModel = Term::query()
            ->with(['taxonomy', 'parent'])
            ->find($term);

        if (! $termModel) {
            $this->throwTermNotFound($term);
        }

        return new TermResource($termModel);
    }

    /**
     * Обновление терма.
     *
     * @group Admin ▸ Terms
     * @name Update term
     * @authenticated
     * @urlParam term int required ID терма. Example: 3
     * @bodyParam name string Новое название (<=255). Example: Tutorials
     * @bodyParam meta_json object Обновлённые мета-данные. Example: {"color":"#3366ff"}
     * @response status=200 {
     *   "data": {
     *     "id": 3,
     *     "taxonomy": 1,
     *     "name": "Tutorials",
     *     "meta_json": {
     *       "color": "#3366ff"
     *     },
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:05:00+00:00",
     *     "deleted_at": null
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "7ceed543-b8b7-cb6c-3003-3f5e5ecb6c32",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-7ceed543b8b7cb6c30033f3f5ecb6c32-7ceed543b8b7cb6c-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Term not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Term with ID 3 does not exist.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e5ecb6c33",
     *     "term_id": 3
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e5ecb6c33-eed543b8b7cb6c30-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The given data was invalid.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e5ecb6c34",
     *     "errors": {
     *       "name": [
     *         "The name field is required."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e5ecb6c34-eed543b8b7cb6c30-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e5ecb6c35",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e5ecb6c35-eed543b8b7cb6c30-01"
     * }
     */
    public function update(UpdateTermRequest $request, int $term): TermResource
    {
        $termModel = Term::query()
            ->with('taxonomy')
            ->find($term);

        if (! $termModel) {
            $this->throwTermNotFound($term);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($termModel, $validated) {
            if (array_key_exists('name', $validated)) {
                $termModel->name = trim((string) $validated['name']);
            }

            if (array_key_exists('meta_json', $validated)) {
                $termModel->meta_json = $validated['meta_json'];
            }

            // Обновляем иерархию, если таксономия поддерживает её и parent_id изменился
            if ($termModel->taxonomy->hierarchical && array_key_exists('parent_id', $validated)) {
                $newParentId = $validated['parent_id'] !== null ? (int) $validated['parent_id'] : null;
                $currentParentId = $termModel->parent_id;
                
                if ($newParentId !== $currentParentId) {
                    try {
                        $this->hierarchyService->setParent($termModel, $newParentId);
                    } catch (\InvalidArgumentException $e) {
                        throw ValidationException::withMessages([
                            'parent_id' => [$e->getMessage()],
                        ]);
                    }
                }
            }

            $termModel->save();
        });

        Log::info('Admin term updated', [
            'term_id' => $termModel->id,
            'taxonomy_id' => $termModel->taxonomy_id,
        ]);

        $termModel->refresh()->load('taxonomy');

        return new TermResource($termModel);
    }

    /**
     * Удаление терма.
     *
     * @group Admin ▸ Terms
     * @name Delete term
     * @authenticated
     * @urlParam term int required ID терма. Example: 3
     * @queryParam forceDetach boolean Автоматически отвязать терм от записей. Example: true
     * @response status=204 {}
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e5ecb6c36",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e5ecb6c36-eed543b8b7cb6c30-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Term not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Term with ID 3 does not exist.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e5ecb6c37",
     *     "term_id": 3
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e5ecb6c37-eed543b8b7cb6c30-01"
     * }
     * @response status=409 {
     *   "type": "https://stupidcms.dev/problems/conflict",
     *   "title": "Term still attached",
     *   "status": 409,
     *   "code": "CONFLICT",
     *   "detail": "Cannot delete term while it is attached to entries. Use forceDetach=1 to detach automatically.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e5ecb6c38",
     *     "term_id": 3,
     *     "force_detach": false
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e5ecb6c38-eed543b8b7cb6c30-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e5ecb6c39",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e5ecb6c39-eed543b8b7cb6c30-01"
     * }
     */
    public function destroy(Request $request, int $term): Response
    {
        $termModel = Term::query()->find($term);

        if (! $termModel) {
            $this->throwTermNotFound($term);
        }

        $forceDetach = $request->boolean('forceDetach');

        $hasEntries = $termModel->entries()->exists();
        if ($hasEntries && ! $forceDetach) {
            $this->throwTermStillAttached();
        }

        DB::transaction(function () use ($termModel, $forceDetach) {
            if ($forceDetach) {
                $termModel->entries()->detach();
            }

            $termModel->delete();
        });

        Log::info('Admin term deleted', [
            'term_id' => $termModel->id,
            'force_detach' => $forceDetach,
        ]);

        return AdminResponse::noContent();
    }

    /**
     * Разобрать строку сортировки на поле и направление.
     *
     * @param string $sort Строка сортировки (например, "created_at.desc")
     * @return array{0: string, 1: string} Массив [поле, направление]
     */
    private function resolveSort(string $sort): array
    {
        [$field, $direction] = array_pad(explode('.', $sort), 2, 'desc');
        $fieldMap = [
            'created_at' => 'created_at',
            'name' => 'name',
        ];

        $column = $fieldMap[$field] ?? 'created_at';
        $dir = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return [$column, $dir];
    }


    /**
     * Найти таксономию по ID.
     *
     * @param int $id ID таксономии
     * @return \App\Models\Taxonomy|null Таксономия или null
     */
    private function findTaxonomy(int $id): ?Taxonomy
    {
        return Taxonomy::query()->find($id);
    }

    /**
     * Выбросить ошибку "таксономия не найдена".
     *
     * @param int $id ID таксономии
     * @return never
     */
    private function throwTaxonomyNotFound(int $id): never
    {
        $this->throwError(
            ErrorCode::NOT_FOUND,
            sprintf('Taxonomy with ID %d does not exist.', $id),
            ['taxonomy_id' => $id],
        );
    }

    /**
     * Выбросить ошибку "терм не найден".
     *
     * @param int $termId ID терма
     * @return never
     */
    private function throwTermNotFound(int $termId): never
    {
        $this->throwError(
            ErrorCode::NOT_FOUND,
            sprintf('Term with ID %d does not exist.', $termId),
            ['term_id' => $termId],
        );
    }

    /**
     * Проверить, что терм принадлежит указанной таксономии.
     *
     * @param \App\Models\Term $term Терм
     * @param \App\Models\Taxonomy $taxonomy Таксономия
     * @return void
     * @throws \Illuminate\Validation\ValidationException Если терм не принадлежит таксономии
     */
    private function validateTermBelongsToTaxonomy(Term $term, Taxonomy $taxonomy): void
    {
        if ($term->taxonomy_id !== $taxonomy->id) {
            throw ValidationException::withMessages([
                'term_id' => [
                    sprintf('Term %d does not belong to taxonomy %d.', $term->id, $taxonomy->id),
                ],
            ]);
        }
    }

    /**
     * Выбросить ошибку "терм всё ещё привязан к записям".
     *
     * @return never
     */
    private function throwTermStillAttached(): never
    {
        $this->throwError(
            ErrorCode::CONFLICT,
            'Cannot delete term while it is attached to entries. Use forceDetach=1 to detach automatically.',
        );
    }
}


