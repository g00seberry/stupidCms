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
use App\Models\Entry;
use App\Models\Taxonomy;
use App\Models\Term;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use App\Support\Slug\Slugifier;
use App\Support\Slug\UniqueSlugService;
use App\Support\Http\AdminResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class TermController extends Controller
{
    use ManagesEntryTerms;
    use ThrowsErrors;

    public function __construct(
        private readonly Slugifier $slugifier,
        private readonly UniqueSlugService $uniqueSlugService
    ) {
    }

    /**
     * Список термов внутри таксономии.
     *
     * @group Admin ▸ Terms
     * @name List terms
     * @authenticated
     * @urlParam taxonomy string required Slug таксономии. Example: category
     * @queryParam q string Поиск по имени/slug (<=255). Example: guides
     * @queryParam sort string Сортировка. Values: created_at.desc,created_at.asc,name.asc,name.desc,slug.asc,slug.desc. Default: created_at.desc.
     * @queryParam per_page int Размер страницы (10-100). Default: 15.
     * @response status=200 {
     *   "data": [
     *     {
     *       "id": 3,
     *       "taxonomy": "category",
     *       "name": "Guides",
     *       "slug": "guides",
     *       "meta_json": {},
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
    public function indexByTaxonomy(IndexTermsRequest $request, string $taxonomy): TermCollection
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
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            });
        }

        [$column, $direction] = $this->resolveSort($validated['sort'] ?? 'created_at.desc');
        $query->orderBy($column, $direction);

        $perPage = $validated['per_page'] ?? 15;
        $perPage = max(10, min(100, $perPage));

        $collection = new TermCollection($query->paginate($perPage));

        return $collection;
    }

    /**
     * Создание терма.
     *
     * @group Admin ▸ Terms
     * @name Create term
     * @authenticated
     * @urlParam taxonomy string required Slug таксономии. Example: category
     * @bodyParam name string required Название (<=255). Example: Guides
     * @bodyParam slug string Уникальный slug (а-z0-9_-). Генерируется из name, если не указан. Example: guides
     * @bodyParam meta_json object Мета-данные. Example: {"color":"#ffcc00"}
     * @bodyParam attach_entry_id int Привязать к записи (ID) сразу после создания. Example: 42
     * @response status=201 {
     *   "data": {
     *     "id": 3,
     *     "taxonomy": "category",
     *     "name": "Guides",
     *     "slug": "guides",
     *     "meta_json": {},
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00"
     *   }
     * }
     * @response status=201 scenario="attach_entry" {
     *   "data": { "...": "..." },
     *   "entry_terms": {
     *     "entry_id": 42,
     *     "terms": [
     *       { "id": 3, "name": "Guides", "slug": "guides", "taxonomy": "category" }
     *     ]
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
     *     "name": [
     *       "The name field is required."
     *     ]
     *   }
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function store(StoreTermRequest $request, string $taxonomy): TermResource
    {
        $taxonomyModel = $this->findTaxonomy($taxonomy);

        if (! $taxonomyModel) {
            $this->throwTaxonomyNotFound($taxonomy);
        }

        $validated = $request->validated();
        $name = trim((string) $validated['name']);
        $slugInput = $validated['slug'] ?? null;
        $meta = $validated['meta_json'] ?? null;
        $attachEntryId = $validated['attach_entry_id'] ?? null;

        $slugBase = $slugInput !== null && $slugInput !== ''
            ? $this->sanitizeTermSlug($slugInput)
            : $this->slugifier->slugify($name);

        if ($slugBase === '') {
            $slugBase = 'term';
        }

        $term = null;

        DB::transaction(function () use (&$term, $taxonomyModel, $name, $slugBase, $meta, $attachEntryId) {
            $term = Term::query()->create([
                'taxonomy_id' => $taxonomyModel->id,
                'name' => $name,
                'slug' => $this->ensureUniqueTermSlug($taxonomyModel, $slugBase),
                'meta_json' => $meta,
            ]);

            if ($attachEntryId) {
                $this->attachTermToEntry($term, $attachEntryId);
            }
        });

        $term->load('taxonomy');

        Log::info('Admin term created', [
            'term_id' => $term->id,
            'taxonomy_id' => $taxonomyModel->id,
        ]);

        $resource = new TermResource($term, true);

        if ($attachEntryId) {
            $entry = Entry::query()->with(['terms.taxonomy', 'postType'])->find($attachEntryId);
            if ($entry) {
                $resource = $resource->additional([
                    'entry_terms' => $this->buildEntryTermsPayload($entry),
                ]);
            }
        }

        return $resource;
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
     *     "taxonomy": "category",
     *     "name": "Guides",
     *     "slug": "guides",
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
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Term not found",
     *   "status": 404,
     *   "detail": "Term with ID 3 does not exist."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function show(int $term): TermResource
    {
        $termModel = Term::query()
            ->with('taxonomy')
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
     * @bodyParam slug string Новый slug (а-z0-9_-). Example: tutorials
     * @bodyParam meta_json object Обновлённые мета-данные. Example: {"color":"#3366ff"}
     * @response status=200 {
     *   "data": {
     *     "id": 3,
     *     "taxonomy": "category",
     *     "name": "Tutorials",
     *     "slug": "tutorials",
     *     "meta_json": {
     *       "color": "#3366ff"
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
     *   "title": "Term not found",
     *   "status": 404,
     *   "detail": "Term with ID 3 does not exist."
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

            if (array_key_exists('slug', $validated)) {
                $slugValue = $validated['slug'];
                if ($slugValue === null || $slugValue === '') {
                    $base = $this->slugifier->slugify($validated['name'] ?? $termModel->name);
                    if ($base === '') {
                        $base = 'term';
                    }
                    $termModel->slug = $this->ensureUniqueTermSlug($termModel->taxonomy, $base, $termModel->id);
                } else {
                    $candidate = $this->sanitizeTermSlug($slugValue);
                    $termModel->slug = $this->ensureUniqueTermSlug($termModel->taxonomy, $candidate, $termModel->id);
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
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Term not found",
     *   "status": 404,
     *   "detail": "Term with ID 3 does not exist."
     * }
     * @response status=409 {
     *   "type": "https://stupidcms.dev/problems/conflict",
     *   "title": "Term still attached",
     *   "status": 409,
     *   "detail": "Cannot delete term while it is attached to entries. Use forceDetach=1 to detach automatically."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
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

    private function resolveSort(string $sort): array
    {
        [$field, $direction] = array_pad(explode('.', $sort), 2, 'desc');
        $fieldMap = [
            'created_at' => 'created_at',
            'name' => 'name',
            'slug' => 'slug',
        ];

        $column = $fieldMap[$field] ?? 'created_at';
        $dir = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return [$column, $dir];
    }

    private function ensureUniqueTermSlug(Taxonomy $taxonomy, string $base, ?int $ignoreId = null): string
    {
        $base = $base !== '' ? $base : 'term';

        return $this->uniqueSlugService->ensureUnique($base, function (string $candidate) use ($taxonomy, $ignoreId) {
            $query = Term::query()
                ->where('taxonomy_id', $taxonomy->id)
                ->where('slug', $candidate)
                ->whereNull('deleted_at');

            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }

            return $query->exists();
        });
    }

    private function sanitizeTermSlug(string $value): string
    {
        $slug = $this->slugifier->slugify($value);
        return $slug !== '' ? $slug : 'term';
    }

    private function findTaxonomy(string $slug): ?Taxonomy
    {
        return Taxonomy::query()->where('slug', $slug)->first();
    }

    private function attachTermToEntry(Term $term, int $entryId): void
    {
        $entry = Entry::query()->with(['postType', 'terms.taxonomy'])->find($entryId);

        if (! $entry || $entry->deleted_at !== null) {
            throw ValidationException::withMessages([
                'attach_entry_id' => 'The specified entry is not available.',
            ]);
        }

        $term->loadMissing('taxonomy');
        $this->ensureTermsAllowedForEntry($entry, [$term], 'attach_entry_id');

        $entry->terms()->syncWithoutDetaching([$term->id]);
    }

    private function throwTaxonomyNotFound(string $slug): never
    {
        $this->throwError(
            ErrorCode::NOT_FOUND,
            sprintf('Taxonomy with slug %s does not exist.', $slug),
            ['slug' => $slug],
        );
    }

    private function throwTermNotFound(int $termId): never
    {
        $this->throwError(
            ErrorCode::NOT_FOUND,
            sprintf('Term with ID %d does not exist.', $termId),
            ['term_id' => $termId],
        );
    }

    private function validateTermBelongsToTaxonomy(Term $term, Taxonomy $taxonomy): void
    {
        if ($term->taxonomy_id !== $taxonomy->id) {


