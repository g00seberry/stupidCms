<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexTaxonomiesRequest;
use App\Http\Requests\Admin\StoreTaxonomyRequest;
use App\Http\Requests\Admin\UpdateTaxonomyRequest;
use App\Http\Resources\Admin\TaxonomyCollection;
use App\Http\Resources\Admin\TaxonomyResource;
use App\Models\Taxonomy;
use App\Models\Term;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use App\Support\Http\AdminResponse;
use App\Support\Slug\Slugifier;
use App\Support\Slug\UniqueSlugService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Контроллер для управления таксономиями в админ-панели.
 *
 * Предоставляет CRUD операции для таксономий: создание, чтение, обновление, удаление.
 * Управляет иерархическими и плоскими таксономиями.
 *
 * @package App\Http\Controllers\Admin\V1
 */
class TaxonomyController extends Controller
{
    use ThrowsErrors;

    /**
     * @param \App\Support\Slug\Slugifier $slugifier Генератор slug'ов
     * @param \App\Support\Slug\UniqueSlugService $uniqueSlugService Сервис для генерации уникальных slug'ов
     */
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
     *       "id": 1,
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "21111111-2222-3333-4444-555555555555",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-21111111222233334444555555555555-2111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "26666666-7777-8888-9999-000000000000",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-26666666777788889999000000000000-2666666677778888-01"
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
     *     "id": 1,
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "21111111-2222-3333-4444-555555555556",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-21111111222233334444555555555556-2111111122223333-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The given data was invalid.",
     *   "meta": {
     *     "request_id": "21111111-2222-3333-4444-555555555557",
     *     "errors": {
     *       "slug": [
     *         "The slug has already been taken."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-21111111222233334444555555555557-2111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "26666666-7777-8888-9999-000000000001",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-26666666777788889999000000000001-2666666677778888-01"
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
     *     "id": 1,
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "21111111-2222-3333-4444-555555555558",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-21111111222233334444555555555558-2111111122223333-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Taxonomy not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Taxonomy with slug category does not exist.",
     *   "meta": {
     *     "request_id": "21111111-2222-3333-4444-555555555559",
     *     "slug": "category"
     *   },
     *   "trace_id": "00-21111111222233334444555555555559-2111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "26666666-7777-8888-9999-000000000002",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-26666666777788889999000000000002-2666666677778888-01"
     * }
     */
    public function show(string $slug): TaxonomyResource
    {
        $taxonomy = Taxonomy::query()->where('slug', $slug)->first();

        if (! $taxonomy) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                sprintf('Taxonomy with slug %s does not exist.', $slug),
                ['slug' => $slug],
            );
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
     *     "id": 1,
     *     "slug": "categories",
     *     "label": "Categories",
     *     "hierarchical": true,
     *     "options_json": {
     *       "color": "#ffcc00"
     *     },
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:05:00+00:00"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "21111111-2222-3333-4444-555555555560",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-21111111222233334444555555555660-2111111122223333-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Taxonomy not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Taxonomy with slug category does not exist.",
     *   "meta": {
     *     "request_id": "21111111-2222-3333-4444-555555555561",
     *     "slug": "category"
     *   },
     *   "trace_id": "00-21111111222233334444555555555661-2111111122223333-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The slug format is invalid. Only lowercase letters, numbers, and hyphens are allowed.",
     *   "meta": {
     *     "request_id": "21111111-2222-3333-4444-555555555562",
     *     "errors": {
     *       "slug": [
     *         "The slug format is invalid. Only lowercase letters, numbers, and hyphens are allowed."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-21111111222233334444555555555662-2111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "26666666-7777-8888-9999-000000000003",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-26666666777788889999000000000003-2666666677778888-01"
     * }
     */
    public function update(UpdateTaxonomyRequest $request, string $slug): TaxonomyResource
    {
        $taxonomy = Taxonomy::query()->where('slug', $slug)->first();

        if (! $taxonomy) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                sprintf('Taxonomy with slug %s does not exist.', $slug),
                ['slug' => $slug],
            );
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "21111111-2222-3333-4444-555555555563",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-21111111222233334444555555555663-2111111122223333-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Taxonomy not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Taxonomy with slug category does not exist.",
     *   "meta": {
     *     "request_id": "21111111-2222-3333-4444-555555555564",
     *     "slug": "category"
     *   },
     *   "trace_id": "00-21111111222233334444555555555664-2111111122223333-01"
     * }
     * @response status=409 {
     *   "type": "https://stupidcms.dev/problems/conflict",
     *   "title": "Taxonomy has terms",
     *   "status": 409,
     *   "code": "CONFLICT",
     *   "detail": "Cannot delete taxonomy while terms exist. Use force=1 to cascade delete.",
     *   "meta": {
     *     "request_id": "21111111-2222-3333-4444-555555555565",
     *     "terms_count": 5
     *   },
     *   "trace_id": "00-21111111222233334444555555555665-2111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "26666666-7777-8888-9999-000000000004",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-26666666777788889999000000000004-2666666677778888-01"
     * }
     */
    public function destroy(Request $request, string $slug): Response
    {
        $taxonomy = Taxonomy::query()->where('slug', $slug)->first();

        if (! $taxonomy) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                sprintf('Taxonomy with slug %s does not exist.', $slug),
                ['slug' => $slug],
            );
        }

        $force = $request->boolean('force');

        $termsCount = $taxonomy->terms()->count();
        if ($termsCount > 0 && ! $force) {
            $this->throwError(
                ErrorCode::CONFLICT,
                'Cannot delete taxonomy while terms exist. Use force=1 to cascade delete.',
                ['terms_count' => $termsCount],
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
            'slug' => 'slug',
            'label' => 'name',
        ];

        $column = $fieldMap[$field] ?? 'created_at';
        $dir = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return [$column, $dir];
    }

    /**
     * Нормализовать slug таксономии.
     *
     * @param string $value Исходное значение
     * @return string Нормализованный slug
     */
    private function sanitizeSlug(string $value): string
    {
        $slug = $this->slugifier->slugify($value);

        if ($slug === '') {
            $slug = Str::slug($value, '-');
        }

        return $slug !== '' ? $slug : 'taxonomy';
    }

    /**
     * Сгенерировать уникальный slug из label.
     *
     * @param string $label Человекочитаемое название
     * @return string Уникальный slug
     */
    private function generateUniqueSlug(string $label): string
    {
        $base = $this->slugifier->slugify($label);
        if ($base === '') {
            $base = 'taxonomy';
        }

        return $this->ensureUniqueSlug($base);
    }

    /**
     * Обеспечить уникальность slug таксономии.
     *
     * @param string $base Базовый slug
     * @param int|null $ignoreId ID таксономии для игнорирования (при обновлении)
     * @return string Уникальный slug
     */
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
        $this->throwError(
            ErrorCode::NOT_FOUND,
            sprintf('Taxonomy with slug %s does not exist.', $slug),
            ['slug' => $slug],
        );
    }
}


