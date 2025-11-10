<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\Admin\IndexTaxonomiesRequest;
use App\Http\Requests\Admin\StoreTaxonomyRequest;
use App\Http\Requests\Admin\UpdateTaxonomyRequest;
use App\Http\Resources\Admin\TaxonomyCollection;
use App\Http\Resources\Admin\TaxonomyResource;
use App\Models\Taxonomy;
use App\Models\Term;
use App\Support\Http\AdminResponse;
use App\Support\Http\ProblemType;
use App\Support\Slug\Slugifier;
use App\Support\Slug\UniqueSlugService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TaxonomyController extends Controller
{
    use Problems;

    public function __construct(
        private readonly Slugifier $slugifier,
        private readonly UniqueSlugService $uniqueSlugService
    ) {
    }

    /**
     * Список таксономий.
     *
     * @group Admin ▸ Taxonomies
     * @name List taxonomies
     * @authenticated
     * @queryParam q string Поиск по slug/label (<=255 символов). Example: category
     * @queryParam sort string Сортировка. Values: created_at.desc,created_at.asc,slug.asc,slug.desc,label.asc,label.desc. Default: created_at.desc.
     * @queryParam per_page int Размер страницы (10-100). Default: 15.
     * @response status=200 {
     *   "data": [
     *     {
     *       "slug": "category",
     *       "label": "Categories",
     *       "hierarchical": true,
     *       "options_json": {},
     *       "created_at": "2025-01-10T12:00:00+00:00",
     *       "updated_at": "2025-01-10T12:00:00+00:00"
     *     }
     *   ]
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function index(IndexTaxonomiesRequest $request): TaxonomyCollection
    {
        $validated = $request->validated();

        $query = Taxonomy::query();

        if (! empty($validated['q'])) {
            $search = $validated['q'];
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('slug', 'like', $like)
                    ->orWhere('name', 'like', $like);
            });
        }

        [$sortColumn, $sortDirection] = $this->resolveSort($validated['sort'] ?? 'created_at.desc');
        $query->orderBy($sortColumn, $sortDirection);

        $perPage = $validated['per_page'] ?? 15;
        $perPage = max(10, min(100, $perPage));

        $collection = $query->paginate($perPage);

        return new TaxonomyCollection($collection);
    }

    /**
     * Создание таксономии.
     *
     * @group Admin ▸ Taxonomies
     * @name Create taxonomy
     * @authenticated
     * @bodyParam label string required Человекочитаемое название (<=255). Example: Categories
     * @bodyParam slug string Уникальный slug (а-z0-9_-). Генерируется из label, если не указан. Example: category
     * @bodyParam hierarchical boolean Иерархическая ли таксономия. Default: false.
     * @bodyParam options_json object Дополнительные настройки. Example: {"color":"#ffcc00"}
     * @response status=201 {
     *   "data": {
     *     "slug": "category",
     *     "label": "Categories",
     *     "hierarchical": true,
     *     "options_json": {},
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "slug": [
     *       "The slug has already been taken."
     *     ]
     *   }
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function store(StoreTaxonomyRequest $request): TaxonomyResource
    {
        $validated = $request->validated();

        $label = trim((string) $validated['label']);
        $hierarchical = (bool) ($validated['hierarchical'] ?? false);
        $slugInput = $validated['slug'] ?? null;

        $slug = $slugInput !== null && $slugInput !== ''
            ? $this->sanitizeSlug($slugInput)
            : $this->generateUniqueSlug($label);

        $data = [
            'slug' => $this->ensureUniqueSlug($slug),
            'label' => $label,
            'hierarchical' => $hierarchical,
        ];

        if (array_key_exists('options_json', $validated)) {
            $data['options_json'] = $validated['options_json'];
        }

        /** @var Taxonomy $taxonomy */
        $taxonomy = DB::transaction(function () use ($data) {
            return Taxonomy::query()->create($data);
        });

        Log::info('Admin taxonomy created', [
            'taxonomy_id' => $taxonomy->id,
            'slug' => $taxonomy->slug,
        ]);

        return new TaxonomyResource($taxonomy->fresh(), true);
    }

    /**
     * Получение таксономии.
     *
     * @group Admin ▸ Taxonomies
     * @name Show taxonomy
     * @authenticated
     * @urlParam slug string required Slug таксономии. Example: category
     * @response status=200 {
     *   "data": {
     *     "slug": "category",
     *     "label": "Categories",
     *     "hierarchical": true,
     *     "options_json": {}
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Taxonomy not found",
     *   "status": 404,
     *   "detail": "Taxonomy with slug category does not exist."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function show(string $slug): TaxonomyResource
    {
        $taxonomy = Taxonomy::query()->where('slug', $slug)->first();

        if (! $taxonomy) {
            $this->throwNotFound($slug);
        }

        return new TaxonomyResource($taxonomy);
    }

    /**
     * Обновление таксономии.
     *
     * @group Admin ▸ Taxonomies
     * @name Update taxonomy
     * @authenticated
     * @urlParam slug string required Slug таксономии. Example: category
     * @bodyParam label string Новое название (<=255). Example: Categories
     * @bodyParam slug string Новый slug (а-z0-9_-). Example: categories
     * @bodyParam hierarchical boolean Иерархическая ли таксономия.
     * @bodyParam options_json object Дополнительные настройки. Example: {"color":"#ffcc00"}
     * @response status=200 {
     *   "data": {
     *     "slug": "categories",
     *     "label": "Categories",
     *     "hierarchical": true,
     *     "options_json": {
     *       "color": "#ffcc00"
     *     }
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Taxonomy not found",
     *   "status": 404,
     *   "detail": "Taxonomy with slug category does not exist."
     * }
     * @response status=422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "slug": [
     *       "The slug format is invalid. Only lowercase letters, numbers, and hyphens are allowed."
     *     ]
     *   }
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function update(UpdateTaxonomyRequest $request, string $slug): TaxonomyResource
    {
        $taxonomy = Taxonomy::query()->where('slug', $slug)->first();

        if (! $taxonomy) {
            $this->throwNotFound($slug);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($taxonomy, $validated) {
            if (array_key_exists('label', $validated)) {
                $taxonomy->label = trim((string) $validated['label']);
            }

            if (array_key_exists('hierarchical', $validated)) {
                $taxonomy->hierarchical = (bool) $validated['hierarchical'];
            }

            if (array_key_exists('options_json', $validated)) {
                $taxonomy->options_json = $validated['options_json'];
            }

            if (array_key_exists('slug', $validated)) {
                $slugValue = $validated['slug'];
                if ($slugValue === null || $slugValue === '') {
                    $baseLabel = $validated['label'] ?? $taxonomy->label ?? 'taxonomy';
                    $candidate = $this->slugifier->slugify($baseLabel);
                    $slugToSet = $this->ensureUniqueSlug(
                        $candidate !== '' ? $candidate : 'taxonomy',
                        $taxonomy->id
                    );
                } else {
                    $candidate = $this->sanitizeSlug($slugValue);
                    $slugToSet = $this->ensureUniqueSlug($candidate, $taxonomy->id);
                }

                $taxonomy->slug = $slugToSet;
            }

            $taxonomy->save();
        });

        Log::info('Admin taxonomy updated', [
            'taxonomy_id' => $taxonomy->id,
            'slug' => $taxonomy->slug,
        ]);

        return new TaxonomyResource($taxonomy->fresh());
    }

    /**
     * Удаление таксономии.
     *
     * @group Admin ▸ Taxonomies
     * @name Delete taxonomy
     * @authenticated
     * @urlParam slug string required Slug таксономии. Example: category
     * @queryParam force boolean Каскадно удалить термы и связи. Example: true
     * @response status=204 {}
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Taxonomy not found",
     *   "status": 404,
     *   "detail": "Taxonomy with slug category does not exist."
     * }
     * @response status=409 {
     *   "type": "https://stupidcms.dev/problems/conflict",
     *   "title": "Taxonomy has terms",
     *   "status": 409,
     *   "detail": "Cannot delete taxonomy while terms exist. Use force=1 to cascade delete.",
     *   "path": "/api/v1/admin/taxonomies/category"
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function destroy(Request $request, string $slug): Response
    {
        $taxonomy = Taxonomy::query()->where('slug', $slug)->first();

        if (! $taxonomy) {
            $this->throwNotFound($slug);
        }

        $force = $request->boolean('force');

        $termsCount = $taxonomy->terms()->count();
        if ($termsCount > 0 && ! $force) {
            throw new HttpResponseException(
                $this->problem(
                    ProblemType::CONFLICT,
                    detail: 'Cannot delete taxonomy while terms exist. Use force=1 to cascade delete.',
                    title: 'Taxonomy has terms'
                )
            );
        }

        DB::transaction(function () use ($taxonomy, $force) {
            if ($force) {
                $taxonomy->terms()->withTrashed()->get()->each(function (Term $term): void {
                    $term->entries()->detach();
                    $term->forceDelete();
                });
            }

            $taxonomy->delete();
        });

        Log::info('Admin taxonomy deleted', [
            'taxonomy_id' => $taxonomy->id,
            'force' => $force,
        ]);

        return AdminResponse::noContent();
    }

    private function resolveSort(string $sort): array
    {
        [$field, $direction] = array_pad(explode('.', $sort), 2, 'desc');
        $fieldMap = [
            'created_at' => 'created_at',
            'slug' => 'slug',
            'label' => 'name',
        ];

        $column = $fieldMap[$field] ?? 'created_at';
        $dir = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return [$column, $dir];
    }

    private function sanitizeSlug(string $value): string
    {
        $slug = $this->slugifier->slugify($value);

        if ($slug === '') {
            $slug = Str::slug($value, '-');
        }

        return $slug !== '' ? $slug : 'taxonomy';
    }

    private function generateUniqueSlug(string $label): string
    {
        $base = $this->slugifier->slugify($label);
        if ($base === '') {
            $base = 'taxonomy';
        }

        return $this->ensureUniqueSlug($base);
    }

    private function ensureUniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $base = $base !== '' ? $base : 'taxonomy';

        return $this->uniqueSlugService->ensureUnique($base, function (string $candidate) use ($ignoreId) {
            $query = Taxonomy::query()->where('slug', $candidate);
            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }

            return $query->exists();
        });
    }

    private function throwNotFound(string $slug): never
    {
        throw new HttpResponseException(
            $this->problem(
                ProblemType::NOT_FOUND,
                detail: "Taxonomy with slug {$slug} does not exist.",
                title: 'Taxonomy not found'
            )
        );
    }
}


