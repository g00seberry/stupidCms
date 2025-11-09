<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\Concerns\ManagesEntryTerms;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\Admin\IndexTermsRequest;
use App\Http\Requests\Admin\StoreTermRequest;
use App\Http\Requests\Admin\UpdateTermRequest;
use App\Http\Resources\Admin\TermCollection;
use App\Http\Resources\Admin\TermResource;
use App\Models\Entry;
use App\Models\Taxonomy;
use App\Models\Term;
use App\Support\Slug\Slugifier;
use App\Support\Slug\UniqueSlugService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class TermController extends Controller
{
    use Problems;
    use ManagesEntryTerms;

    public function __construct(
        private readonly Slugifier $slugifier,
        private readonly UniqueSlugService $uniqueSlugService
    ) {
    }

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

    public function destroy(Request $request, int $term): Response
    {
        $termModel = Term::query()->find($term);

        if (! $termModel) {
            $this->throwTermNotFound($term);
        }

        $forceDetach = $request->boolean('forceDetach');

        $hasEntries = $termModel->entries()->exists();
        if ($hasEntries && ! $forceDetach) {
            throw new HttpResponseException(
                $this->problem(
                    status: 409,
                    title: 'Term still attached',
                    detail: 'Cannot delete term while it is attached to entries. Use forceDetach=1 to detach automatically.',
                    ext: ['type' => 'https://stupidcms.dev/problems/conflict'],
                    headers: [
                        'Cache-Control' => 'no-store, private',
                        'Vary' => 'Cookie',
                    ]
                )
            );
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

        return response()
            ->noContent()
            ->header('Cache-Control', 'no-store, private')
            ->header('Vary', 'Cookie');
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
        throw new HttpResponseException(
            $this->problem(
                status: 404,
                title: 'Taxonomy not found',
                detail: "Taxonomy with slug {$slug} does not exist.",
                ext: ['type' => 'https://stupidcms.dev/problems/not-found'],
                headers: [
                    'Cache-Control' => 'no-store, private',
                    'Vary' => 'Cookie',
                ]
            )
        );
    }

    private function throwTermNotFound(int $termId): never
    {
        throw new HttpResponseException(
            $this->problem(
                status: 404,
                title: 'Term not found',
                detail: "Term with ID {$termId} does not exist.",
                ext: ['type' => 'https://stupidcms.dev/problems/not-found'],
                headers: [
                    'Cache-Control' => 'no-store, private',
                    'Vary' => 'Cookie',
                ]
            )
        );
    }
}


