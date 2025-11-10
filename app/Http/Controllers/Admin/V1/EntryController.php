<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\Admin\IndexEntriesRequest;
use App\Http\Requests\Admin\StoreEntryRequest;
use App\Http\Requests\Admin\UpdateEntryRequest;
use App\Http\Resources\Admin\EntryCollection;
use App\Http\Resources\Admin\EntryResource;
use App\Models\Entry;
use App\Models\PostType;
use App\Support\Http\AdminResponse;
use App\Support\Http\Problems\EntryNotFoundProblem;
use App\Support\Http\Problems\InvalidEntryPostTypeProblem;
use App\Support\Slug\Slugifier;
use App\Support\Slug\UniqueSlugService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EntryController extends Controller
{
    use Problems, AuthorizesRequests;

    public function __construct(
        private Slugifier $slugifier,
        private UniqueSlugService $uniqueSlugService
    ) {
    }

    /**
     * Список записей с фильтрами и пагинацией.
     *
     * @group Admin ▸ Entries
     * @name List entries
     * @authenticated
     * @queryParam post_type string Фильтр по slug PostType. Example: article
     * @queryParam status string Фильтр по статусу. Values: all,draft,published,scheduled,trashed. Default: all.
     * @queryParam q string Поиск по названию/slug (<=500 символов). Example: landing
     * @queryParam author_id int ID автора. Example: 7
     * @queryParam term[] int ID термов (множественный фильтр). Example: [3,8]
     * @queryParam date_field string Поле даты для диапазона. Values: updated,published. Default: updated.
     * @queryParam date_from date Начальная дата (ISO 8601). Example: 2025-01-01
     * @queryParam date_to date Конечная дата (>= date_from). Example: 2025-01-31
     * @queryParam sort string Поле сортировки. Values: updated_at.desc,updated_at.asc,published_at.desc,published_at.asc,title.asc,title.desc. Default: updated_at.desc.
     * @queryParam per_page int Количество элементов на страницу (10-100). Default: 15.
     * @response status=200 {
     *   "data": [
     *     {
     *       "id": 42,
     *       "post_type": "article",
     *       "title": "Headless CMS launch checklist",
     *       "slug": "launch-checklist",
     *       "status": "draft",
     *       "is_published": false,
     *       "published_at": null
     *     }
     *   ],
     *   "links": {
     *     "first": "https://api.stupidcms.dev/api/v1/admin/entries?page=1",
     *     "last": "https://api.stupidcms.dev/api/v1/admin/entries?page=5",
     *     "prev": null,
     *     "next": "https://api.stupidcms.dev/api/v1/admin/entries?page=2"
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 5,
     *     "per_page": 15,
     *     "total": 74
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=403 {
     *   "type": "https://stupidcms.dev/problems/forbidden",
     *   "title": "Forbidden",
     *   "status": 403,
     *   "detail": "Admin privileges are required."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function index(IndexEntriesRequest $request): EntryCollection
    {
        $validated = $request->validated();

        $query = Entry::query()
            ->with(['postType', 'author', 'terms.taxonomy']);

        // Filter by post_type
        if (! empty($validated['post_type'])) {
            $query->whereHas('postType', function ($q) use ($validated) {
                $q->where('slug', $validated['post_type']);
            });
        }

        // Filter by status
        $status = $validated['status'] ?? 'all';
        match ($status) {
            'draft' => $query->where('status', 'draft')->whereNull('deleted_at'),
            'published' => $query->published(),
            'scheduled' => $query->where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '>', Carbon::now('UTC'))
                ->whereNull('deleted_at'),
            'trashed' => $query->onlyTrashed(),
            default => null, // 'all' - no filter
        };

        // Search by title/slug
        if (! empty($validated['q'])) {
            $search = $validated['q'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Filter by author
        if (! empty($validated['author_id'])) {
            $query->where('author_id', $validated['author_id']);
        }

        // Filter by terms
        if (! empty($validated['term']) && is_array($validated['term'])) {
            $query->whereHas('terms', function ($q) use ($validated) {
                $q->whereIn('terms.id', $validated['term']);
            });
        }

        // Filter by date range
        $dateField = match ($validated['date_field'] ?? 'updated') {
            'published' => 'published_at',
            default => 'updated_at',
        };

        if (! empty($validated['date_from'])) {
            $query->where($dateField, '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->where($dateField, '<=', $validated['date_to']);
        }

        // Sorting
        $sort = $validated['sort'] ?? 'updated_at.desc';
        [$sortField, $sortDir] = explode('.', $sort);
        $query->orderBy($sortField, $sortDir);

        // Pagination
        $perPage = $validated['per_page'] ?? 15;
        $perPage = max(10, min(100, $perPage));

        $entries = $query->paginate($perPage);

        return new EntryCollection($entries);
    }

    /**
     * Получение записи по ID (включая удалённые).
     *
     * @group Admin ▸ Entries
     * @name Show entry
     * @authenticated
     * @urlParam id int required ID записи. Example: 42
     * @response status=200 {
     *   "data": {
     *     "id": 42,
     *     "post_type": "article",
     *     "title": "Headless CMS launch checklist",
     *     "slug": "launch-checklist",
     *     "status": "published",
     *     "is_published": true,
     *     "published_at": "2025-02-10T08:00:00+00:00",
     *     "content_json": {},
     *     "meta_json": {},
     *     "author": {
     *       "id": 7,
     *       "name": "Admin User"
     *     },
     *     "terms": [
     *       {
     *         "id": 3,
     *         "name": "Guides",
     *         "slug": "guides",
     *         "taxonomy": "category"
     *       }
     *     ],
     *     "created_at": "2025-02-09T10:15:00+00:00",
     *     "updated_at": "2025-02-10T08:05:00+00:00",
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
     *   "title": "Entry not found",
     *   "status": 404,
     *   "detail": "Entry with ID 42 does not exist."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function show(int $id): EntryResource
    {
        $entry = Entry::query()
            ->with(['postType', 'author', 'terms.taxonomy'])
            ->withTrashed()
            ->find($id);

        if (! $entry) {
            $this->throwEntryNotFound($id);
        }

        $this->authorize('view', $entry);

        return new EntryResource($entry);
    }

    /**
     * Создание записи.
     *
     * @group Admin ▸ Entries
     * @name Create entry
     * @authenticated
     * @bodyParam post_type string required Существующий slug PostType. Example: article
     * @bodyParam title string required Заголовок (<=500 символов). Example: Headless CMS launch checklist
     * @bodyParam slug string Уникальный slug (генерируется автоматически, если не указан). Example: launch-checklist
     * @bodyParam content_json object Произвольные структурированные данные. Example: {"hero":{"title":"Launch"}}
     * @bodyParam meta_json object SEO-метаданные. Example: {"title":"Launch","description":"Checklist"}
     * @bodyParam is_published boolean Опубликовать сразу. Default: false.
     * @bodyParam published_at datetime Дата публикации (ISO 8601). Example: 2025-02-10T08:00:00Z
     * @bodyParam template_override string Кастомный blade/template ключ. Example: templates.landing
     * @bodyParam term_ids int[] Список ID термов для привязки. Example: [3,8]
     * @response status=201 {
     *   "data": {
     *     "id": 42,
     *     "post_type": "article",
     *     "title": "Headless CMS launch checklist",
     *     "slug": "launch-checklist",
     *     "status": "published",
     *     "is_published": true,
     *     "published_at": "2025-02-10T08:00:00+00:00",
     *     "content_json": {
     *       "hero": {
     *         "title": "Launch"
     *       }
     *     },
     *     "meta_json": {
     *       "title": "Launch"
     *     },
     *     "template_override": "templates.landing"
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
     *     "post_type": [
     *       "The specified post type does not exist."
     *     ],
     *     "slug": [
     *       "The slug format is invalid. Only lowercase letters, numbers, and hyphens are allowed."
     *     ]
     *   }
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function store(StoreEntryRequest $request): EntryResource
    {
        $validated = $request->validated();

        // Get post_type_id
        $postType = PostType::query()->where('slug', $validated['post_type'])->first();
        
        if (! $postType) {
            throw new InvalidEntryPostTypeProblem();
        }

        // Auto-generate slug if not provided
        $slug = $validated['slug'] ?? null;
        if (empty($slug)) {
            $slug = $this->generateUniqueSlug($validated['title'], $postType->slug);
        }

        // Determine status and published_at
        $isPublished = $validated['is_published'] ?? false;
        $status = $isPublished ? 'published' : 'draft';
        $publishedAt = null;

        if ($isPublished) {
            $publishedAt = $validated['published_at'] ?? Carbon::now('UTC');
        }

        $entry = DB::transaction(function () use ($validated, $postType, $slug, $status, $publishedAt) {
            $entry = Entry::create([
                'post_type_id' => $postType->id,
                'title' => $validated['title'],
                'slug' => $slug,
                'status' => $status,
                'published_at' => $publishedAt,
                'author_id' => Auth::id(),
                'data_json' => $validated['content_json'] ?? [],
                'seo_json' => $validated['meta_json'] ?? null,
                'template_override' => $validated['template_override'] ?? null,
            ]);

            // Attach terms
            if (! empty($validated['term_ids'])) {
                $entry->terms()->sync($validated['term_ids']);
            }

            return $entry;
        });

        $entry->load(['postType', 'author', 'terms.taxonomy']);

        return new EntryResource($entry);
    }

    /**
     * Обновление записи.
     *
     * @group Admin ▸ Entries
     * @name Update entry
     * @authenticated
     * @urlParam id int required ID записи. Example: 42
     * @bodyParam title string Заголовок (<=500 символов). Example: Updated checklist
     * @bodyParam slug string Уникальный slug (обязателен при публикации). Example: launch-checklist
     * @bodyParam content_json object Произвольные данные. Example: {"body":{"blocks":[]}}
     * @bodyParam meta_json object SEO-метаданные. Example: {"description":"Updated"}
     * @bodyParam is_published boolean Переключение статуса публикации.
     * @bodyParam published_at datetime Дата публикации (ISO 8601).
     * @bodyParam template_override string Кастомный шаблон. Example: templates.landing
     * @bodyParam term_ids int[] Полный список термов (sync).
     * @response status=200 {
     *   "data": {
     *     "id": 42,
     *     "title": "Updated checklist",
     *     "slug": "launch-checklist",
     *     "status": "draft",
     *     "is_published": false,
     *     "published_at": null
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
     *   "title": "Entry not found",
     *   "status": 404,
     *   "detail": "Entry with ID 42 does not exist."
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
    public function update(UpdateEntryRequest $request, int $id): EntryResource
    {
        $entry = Entry::query()->withTrashed()->find($id);

        if (! $entry) {
            $this->throwEntryNotFound($id);
        }

        $this->authorize('update', $entry);

        $validated = $request->validated();

        DB::transaction(function () use ($entry, $validated) {
            // Update basic fields
            if (isset($validated['title'])) {
                $entry->title = $validated['title'];
            }

            if (isset($validated['slug'])) {
                $entry->slug = $validated['slug'];
            }

            if (array_key_exists('content_json', $validated)) {
                $entry->data_json = $validated['content_json'] ?? [];
            }

            if (array_key_exists('meta_json', $validated)) {
                $entry->seo_json = $validated['meta_json'];
            }

            if (array_key_exists('template_override', $validated)) {
                $entry->template_override = $validated['template_override'];
            }

            // Handle publication status
            if (isset($validated['is_published'])) {
                $isPublished = $validated['is_published'];
                $entry->status = $isPublished ? 'published' : 'draft';

                if ($isPublished) {
                    if (isset($validated['published_at'])) {
                        $entry->published_at = $validated['published_at'];
                    } elseif (! $entry->published_at) {
                        $entry->published_at = Carbon::now('UTC');
                    }
                } else {
                    // Unpublishing - clear published_at
                    $entry->published_at = null;
                }
            } elseif (isset($validated['published_at'])) {
                $entry->published_at = $validated['published_at'];
            }

            $entry->save();

            // Sync terms
            if (array_key_exists('term_ids', $validated)) {
                $entry->terms()->sync($validated['term_ids'] ?? []);
            }
        });

        $entry->refresh();
        $entry->load(['postType', 'author', 'terms.taxonomy']);

        return new EntryResource($entry);
    }

    /**
     * Мягкое удаление записи.
     *
     * @group Admin ▸ Entries
     * @name Delete entry
     * @authenticated
     * @urlParam id int required ID записи. Example: 42
     * @response status=204 {}
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Entry not found",
     *   "status": 404,
     *   "detail": "Entry with ID 42 does not exist."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function destroy(int $id): Response
    {
        $entry = Entry::query()->find($id);

        if (! $entry) {
            $this->throwEntryNotFound($id);
        }

        $this->authorize('delete', $entry);

        $entry->delete();

        return AdminResponse::noContent();
    }

    /**
     * Восстановление мягко удалённой записи.
     *
     * @group Admin ▸ Entries
     * @name Restore entry
     * @authenticated
     * @urlParam id int required ID записи. Example: 42
     * @response status=200 {
     *   "data": {
     *     "id": 42,
     *     "status": "draft",
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
     *   "title": "Entry not found",
     *   "status": 404,
     *   "detail": "Trashed entry with ID 42 does not exist."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function restore(Request $request, int $id): EntryResource
    {
        $entry = Entry::query()->onlyTrashed()->find($id);

        if (! $entry) {
            throw new EntryNotFoundProblem($id, true);
        }

        $this->authorize('restore', $entry);

        $entry->restore();
        $entry->load(['postType', 'author', 'terms.taxonomy']);

        return new EntryResource($entry);
    }

    /**
     * Generate a unique slug for the entry.
     */
    private function generateUniqueSlug(string $title, string $postTypeSlug): string
    {
        $base = $this->slugifier->slugify($title);

        if (empty($base)) {
            $base = 'entry';
        }

        return $this->uniqueSlugService->ensureUnique(
            $base,
            function (string $slug) use ($postTypeSlug) {
                $postType = PostType::query()->where('slug', $postTypeSlug)->first();
                
                if (! $postType) {
                    return false;
                }

                return Entry::query()
                    ->withTrashed()
                    ->where('post_type_id', $postType->id)
                    ->where('slug', $slug)
                    ->exists();
            }
        );
    }

    private function throwEntryNotFound(int $id): never
    {
        throw new EntryNotFoundProblem($id);
    }
}

