<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Requests\Admin\IndexEntriesRequest;
use App\Http\Requests\Admin\StoreEntryRequest;
use App\Http\Requests\Admin\UpdateEntryRequest;
use App\Http\Resources\Admin\EntryCollection;
use App\Http\Resources\Admin\EntryResource;
use App\Models\Entry;
use App\Models\PostType;
use App\Support\Slug\Slugifier;
use App\Support\Slug\UniqueSlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Display a listing of entries with filters.
     */
    public function index(IndexEntriesRequest $request): JsonResponse
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

        return (new EntryCollection($entries))->toResponse($request);
    }

    /**
     * Display the specified entry.
     */
    public function show(int $id): JsonResponse
    {
        $entry = Entry::query()
            ->with(['postType', 'author', 'terms.taxonomy'])
            ->withTrashed()
            ->find($id);

        if (! $entry) {
            return $this->problem(
                status: 404,
                title: 'Entry not found',
                detail: "Entry with ID {$id} does not exist.",
                ext: ['type' => 'https://stupidcms.dev/problems/not-found'],
                headers: [
                    'Cache-Control' => 'no-store, private',
                    'Vary' => 'Cookie',
                ]
            );
        }

        $this->authorize('view', $entry);

        $resource = new EntryResource($entry);
        return $resource->toResponse(request());
    }

    /**
     * Store a newly created entry.
     */
    public function store(StoreEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Get post_type_id
        $postType = PostType::query()->where('slug', $validated['post_type'])->first();
        
        if (! $postType) {
            return $this->problem(
                status: 422,
                title: 'Validation error',
                detail: 'The specified post type does not exist.',
                ext: [
                    'type' => 'https://stupidcms.dev/problems/validation-error',
                    'errors' => ['post_type' => ['The specified post type does not exist.']],
                ],
                headers: [
                    'Cache-Control' => 'no-store, private',
                    'Vary' => 'Cookie',
                ]
            );
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

        $resource = new EntryResource($entry);
        return $resource->toResponse(request())->setStatusCode(201);
    }

    /**
     * Update the specified entry.
     */
    public function update(UpdateEntryRequest $request, int $id): JsonResponse
    {
        $entry = Entry::query()->withTrashed()->find($id);

        if (! $entry) {
            return $this->problem(
                status: 404,
                title: 'Entry not found',
                detail: "Entry with ID {$id} does not exist.",
                ext: ['type' => 'https://stupidcms.dev/problems/not-found'],
                headers: [
                    'Cache-Control' => 'no-store, private',
                    'Vary' => 'Cookie',
                ]
            );
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

        $resource = new EntryResource($entry);
        return $resource->toResponse(request());
    }

    /**
     * Soft delete the specified entry.
     */
    public function destroy(int $id): JsonResponse
    {
        $entry = Entry::query()->find($id);

        if (! $entry) {
            return $this->problem(
                status: 404,
                title: 'Entry not found',
                detail: "Entry with ID {$id} does not exist.",
                ext: ['type' => 'https://stupidcms.dev/problems/not-found'],
                headers: [
                    'Cache-Control' => 'no-store, private',
                    'Vary' => 'Cookie',
                ]
            );
        }

        $this->authorize('delete', $entry);

        $entry->delete();

        return response()->json(null, 204)
            ->header('Cache-Control', 'no-store, private')
            ->header('Vary', 'Cookie');
    }

    /**
     * Restore a soft-deleted entry.
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $entry = Entry::query()->onlyTrashed()->find($id);

        if (! $entry) {
            return $this->problem(
                status: 404,
                title: 'Entry not found',
                detail: "Trashed entry with ID {$id} does not exist.",
                ext: ['type' => 'https://stupidcms.dev/problems/not-found'],
                headers: [
                    'Cache-Control' => 'no-store, private',
                    'Vary' => 'Cookie',
                ]
            );
        }

        $this->authorize('restore', $entry);

        $entry->restore();
        $entry->load(['postType', 'author', 'terms.taxonomy']);

        $resource = new EntryResource($entry);
        return $resource->toResponse($request);
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
}

