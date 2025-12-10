<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexEntriesRequest;
use App\Http\Requests\Admin\StoreEntryRequest;
use App\Http\Requests\Admin\UpdateEntryRequest;
use App\Http\Resources\Admin\EntryCollection;
use App\Http\Resources\Admin\EntryResource;
use App\Models\Entry;
use App\Models\PostType;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use App\Support\Http\AdminResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Контроллер для управления записями (entries) в админ-панели.
 *
 * Предоставляет CRUD операции для записей: создание, чтение, обновление, удаление,
 * восстановление, управление статусами.
 *
 * @package App\Http\Controllers\Admin\V1
 */
class EntryController extends Controller
{
    use AuthorizesRequests;
    use ThrowsErrors;

    public function __construct()
    {
    }

    /**
     * Список записей с фильтрами и пагинацией.
     *
     * @group Admin ▸ Entries
     * @name List entries
     * @authenticated
     * @queryParam post_type_id int Фильтр по ID PostType. Example: 1
     * @queryParam status string Фильтр по статусу. Values: all,draft,published,scheduled,trashed. Default: all.
     * @queryParam q string Поиск по названию (<=500 символов). Example: landing
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
     *       "post_type_id": 1,
     *       "title": "Headless CMS launch checklist",
     *       "status": "draft",
     *       "data_json": null,
     *       "is_published": false,
     *       "published_at": null,
     *       "template_override": null,
     *       "created_at": "2025-01-10T12:00:00+00:00",
     *       "updated_at": "2025-01-10T12:00:00+00:00",
     *       "deleted_at": null
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "f51ce7c9-eed5-43b8-b7cb-6c30033f3f5e",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-f51ce7c9eed543b8b7cb6c30033f3f5e-f51ce7c9eed543b8-01"
     * }
     * @response status=403 {
     *   "type": "https://stupidcms.dev/problems/forbidden",
     *   "title": "Forbidden",
     *   "status": 403,
     *   "code": "FORBIDDEN",
     *   "detail": "Admin privileges are required.",
     *   "meta": {
     *     "request_id": "0b6c3003-3f5e-9f51-ce7c-eed543b8b7cb",
     *     "permission": "entries.view"
     *   },
     *   "trace_id": "00-0b6c30033f5e9f51ce7ceed543b8b7cb-0b6c30033f5e9f51-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5ef51ce7c9",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5ef51ce7c9-eed543b8b7cb6c30-01"
     * }
     */
    public function index(IndexEntriesRequest $request): EntryCollection
    {
        $validated = $request->validated();

        $query = Entry::query()
            ->with(['postType', 'author', 'terms.taxonomy']);

        // Filter by post_type_id
        if (! empty($validated['post_type_id'])) {
            $query->where('post_type_id', $validated['post_type_id']);
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

        // Search by title
        if (! empty($validated['q'])) {
            $search = $validated['q'];
            $query->where('title', 'like', "%{$search}%");
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
     * Возвращает запись с blueprint, назначенным в родительском PostType.
     *
     * @group Admin ▸ Entries
     * @name Show entry
     * @authenticated
     * @urlParam id int required ID записи. Example: 42
     * @response status=200 {
     *   "data": {
     *     "id": 42,
     *     "post_type_id": 1,
     *     "title": "Headless CMS launch checklist",
     *     "status": "published",
     *     "is_published": true,
     *     "published_at": "2025-02-10T08:00:00+00:00",
     *     "data_json": {},
     *     "template_override": null,
     *     "author": {
     *       "id": 7,
     *       "name": "Admin User"
     *     },
     *     "terms": [
     *       {
     *         "id": 3,
     *         "name": "Guides",
     *         "taxonomy": 1
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "1b8b7cb6-c300-33f3-f5e9-f51ce7ceed54",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-1b8b7cb6c30033f3f5e9f51ce7ceed54-1b8b7cb6c30033f3-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Entry not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Entry with ID 42 does not exist.",
     *   "meta": {
     *     "request_id": "f5e9f51c-e7ce-ed54-3b8b-7cb6c30033f3",
     *     "entry_id": 42
     *   },
     *   "trace_id": "00-f5e9f51ce7ceed543b8b7cb6c30033f3-f5e9f51ce7ceed54-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e9f51ce7c",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e9f51ce7c-eed543b8b7cb6c30-01"
     * }
     */
    public function show(int $id): EntryResource
    {
        $entry = Entry::query()
            ->with(['postType.blueprint', 'author', 'terms.taxonomy'])
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
     * @bodyParam post_type_id int required Существующий ID PostType. Example: 1
     * @bodyParam title string required Заголовок (<=500 символов). Example: Headless CMS launch checklist
     * @bodyParam data_json object Произвольные структурированные данные. Example: {"hero":{"title":"Launch"}}
     * @bodyParam is_published boolean Опубликовать сразу. Default: false.
     * @bodyParam published_at datetime Дата публикации (ISO 8601). Example: 2025-02-10T08:00:00Z
     * @bodyParam template_override string Кастомный blade/template ключ. Example: templates.landing
     * @bodyParam term_ids int[] Список ID термов для привязки. Example: [3,8]
     * @response status=201 {
     *   "data": {
     *     "id": 42,
     *     "post_type_id": 1,
     *     "title": "Headless CMS launch checklist",
     *     "status": "published",
     *     "is_published": true,
     *     "published_at": "2025-02-10T08:00:00+00:00",
     *     "data_json": {
     *       "hero": {
     *         "title": "Launch"
     *       }
     *     },
     *     "template_override": "templates.landing",
     *     "author": {
     *       "id": 1,
     *       "name": "Admin"
     *     },
     *     "terms": [],
     *     "created_at": "2025-02-10T08:00:00+00:00",
     *     "updated_at": "2025-02-10T08:00:00+00:00",
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
     *     "request_id": "f3f5e9f5-1ce7-ceed-543b-8b7cb6c30033",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-f3f5e9f51ce7ceed543b8b7cb6c30033-f3f5e9f51ce7ceed-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The specified post type does not exist.",
     *   "meta": {
     *     "request_id": "7ceed543-b8b7-cb6c-3003-3f5ef51ce7c9",
     *     "errors": {
     *       "post_type_id": [
     *         "The specified post type does not exist."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-7ceed543b8b7cb6c30033f5ef51ce7c9-7ceed543b8b7cb6c-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5ef51ce7c9",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5ef51ce7c9-eed543b8b7cb6c30-01"
     * }
     */
    public function store(StoreEntryRequest $request): EntryResource
    {
        $validated = $request->validated();

        // Get post_type
        $postType = PostType::findOrFail($validated['post_type_id']);

        // Determine status and published_at
        $isPublished = $validated['is_published'] ?? false;
        $status = $isPublished ? 'published' : 'draft';
        $publishedAt = null;

        if ($isPublished) {
            $publishedAt = $validated['published_at'] ?? Carbon::now('UTC');
        }

        $entry = DB::transaction(function () use ($validated, $postType, $status, $publishedAt) {
            $entry = Entry::create([
                'post_type_id' => $postType->id,
                'title' => $validated['title'],
                'status' => $status,
                'published_at' => $publishedAt,
                'author_id' => Auth::id(),
                'data_json' => $validated['data_json'] ?? [],
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
     * @bodyParam data_json object Произвольные данные. Example: {"body":{"blocks":[]}}
     * @bodyParam is_published boolean Переключение статуса публикации.
     * @bodyParam published_at datetime Дата публикации (ISO 8601).
     * @bodyParam template_override string Кастомный шаблон. Example: templates.landing
     * @bodyParam term_ids int[] Полный список термов (sync).
     * @response status=200 {
     *   "data": {
     *     "id": 42,
     *     "post_type_id": 1,
     *     "title": "Updated checklist",
     *     "status": "draft",
     *     "is_published": false,
     *     "published_at": null,
     *     "data_json": {},
     *     "template_override": null,
     *     "created_at": "2025-02-09T10:15:00+00:00",
     *     "updated_at": "2025-02-10T08:05:00+00:00",
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
     *     "request_id": "b7cb6c30-033f-3f5e-f51c-e7ceed543b8b",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-b7cb6c30033f3f5ef51ce7ceed543b8b-b7cb6c30033f3f5e-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Entry not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Entry with ID 42 does not exist.",
     *   "meta": {
     *     "request_id": "33f3f5e9-f51c-e7ce-ed54-3b8b7cb6c300",
     *     "entry_id": 42
     *   },
     *   "trace_id": "00-33f3f5e9f51ce7ceed543b8b7cb6c300-33f3f5e9f51ce7ce-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The given data was invalid.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5eb7cb6c30",
     *     "errors": {}
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5eb7cb6c30-eed543b8b7cb6c30-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5eb8b7cb6c",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5eb8b7cb6c-eed543b8b7cb6c30-01"
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

            if (array_key_exists('data_json', $validated)) {
                $entry->data_json = $validated['data_json'] ?? [];
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "c30033f3-f5e9-f51c-e7ce-ed543b8b7cb6",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-c30033f3f5e9f51ce7ceed543b8b7cb6-c30033f3f5e9f51c-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Entry not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Entry with ID 42 does not exist.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5ec30033f3",
     *     "entry_id": 42
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5ec30033f3-eed543b8b7cb6c30-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5ec30033ff",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5ec30033ff-eed543b8b7cb6c30-01"
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "9f51ce7c-eed5-43b8-b7cb-6c30033f3f5e",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-9f51ce7ceed543b8b7cb6c30033f3f5e-9f51ce7ceed543b8-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Entry not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Trashed entry with ID 42 does not exist.",
     *   "meta": {
     *     "request_id": "7cb6c300-33f3-4b8b-9f51-ceed543b8b7c",
     *     "entry_id": 42,
     *     "trashed": true
     *   },
     *   "trace_id": "00-7cb6c30033f34b8b9f51ceed543b8b7c-7cb6c30033f34b8b-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e9f51ce7c",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e9f51ce7c-eed543b8b7cb6c30-01"
     * }
     */
    public function restore(Request $request, int $id): EntryResource
    {
        $entry = Entry::query()->onlyTrashed()->find($id);

        if (! $entry) {
            $this->throwEntryNotFound($id, true);
        }

        $this->authorize('restore', $entry);

        $entry->restore();
        $entry->load(['postType', 'author', 'terms.taxonomy']);

        return new EntryResource($entry);
    }

    /**
     * Получение списка возможных статусов записей.
     *
     * @group Admin ▸ Entries
     * @name Get entry statuses
     * @authenticated
     * @response status=200 {
     *   "data": [
     *     "draft",
     *     "published"
     *   ]
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "9f51ce7c-eed5-43b8-b7cb-6c30033f3f5e",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-9f51ce7ceed543b8b7cb6c30033f3f5e-9f51ce7ceed543b8-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "eed543b8-b7cb-6c30-033f-3f5e9f51ce7c",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-eed543b8b7cb6c30033f3f5e9f51ce7c-eed543b8b7cb6c30-01"
     * }
     */
    public function statuses(): JsonResponse
    {
        $this->authorize('viewAny', Entry::class);

        return AdminResponse::json([
            'data' => Entry::getStatuses(),
        ]);
    }


    private function throwEntryNotFound(int $id, bool $trashed = false): never
    {
        $detail = $trashed
            ? sprintf('Trashed entry with ID %d does not exist.', $id)
            : sprintf('Entry with ID %d does not exist.', $id);

        $this->throwError(
            ErrorCode::NOT_FOUND,
            $detail,
            [
                'entry_id' => $id,
                'trashed' => $trashed,
            ],
        );
    }

}

