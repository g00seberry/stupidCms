<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\Concerns\ManagesEntryTerms;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\Admin\AttachTermsRequest;
use App\Http\Requests\Admin\SyncTermsRequest;
use App\Http\Resources\Admin\EntryTermsResource;
use App\Models\Entry;
use App\Models\Term;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EntryTermsController extends Controller
{
    use Problems;
    use ManagesEntryTerms;

    /**
     * Получение термов записи.
     *
     * @group Admin ▸ Entry terms
     * @name List entry terms
     * @authenticated
     * @urlParam entry int required ID записи. Example: 42
     * @response status=200 {
     *   "data": {
     *     "entry_id": 42,
     *     "terms": [
     *       {
     *         "id": 3,
     *         "name": "Guides",
     *         "slug": "guides",
     *         "taxonomy": "category"
     *       }
     *     ],
     *     "terms_by_taxonomy": {
     *       "category": [
     *         {
     *           "id": 3,
     *           "name": "Guides",
     *           "slug": "guides"
     *         }
     *       ]
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
     *   "title": "Entry not found",
     *   "status": 404,
     *   "detail": "Entry with ID 42 does not exist."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function index(int $entry): EntryTermsResource
    {
        $entryModel = $this->findEntry($entry);

        if (! $entryModel) {
            $this->throwEntryNotFound($entry);
        }

        return $this->entryTermsResource($entryModel);
    }

    /**
     * Привязка термов к записи (без детача существующих).
     *
     * @group Admin ▸ Entry terms
     * @name Attach entry terms
     * @authenticated
     * @urlParam entry int required ID записи. Example: 42
     * @bodyParam term_ids int[] required Список ID термов (>=1). Example: [3,8]
     * @response status=200 {
     *   "data": {
     *     "entry_id": 42,
     *     "terms": [
     *       {
     *         "id": 3,
     *         "name": "Guides",
     *         "slug": "guides",
     *         "taxonomy": "category"
     *       }
     *     ],
     *     "terms_by_taxonomy": {
     *       "category": [
     *         {
     *           "id": 3,
     *           "name": "Guides",
     *           "slug": "guides"
     *         }
     *       ]
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
     *   "title": "Entry not found",
     *   "status": 404,
     *   "detail": "Entry with ID 42 does not exist."
     * }
     * @response status=422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "term_ids": [
     *       "Taxonomy 'tags' is not allowed for the entry post type."
     *     ]
     *   }
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function attach(AttachTermsRequest $request, int $entry): EntryTermsResource
    {
        $entryModel = $this->findEntry($entry);

        if (! $entryModel) {
            $this->throwEntryNotFound($entry);
        }

        $validated = $request->validated();
        $termIds = $validated['term_ids'];

        $terms = Term::query()
            ->with('taxonomy')
            ->whereIn('id', $termIds)
            ->get();

        $this->ensureTermsAllowedForEntry($entryModel, $terms);

        DB::transaction(function () use ($entryModel, $termIds) {
            $entryModel->terms()->syncWithoutDetaching($termIds);
        });

        Log::info('Admin entry terms attached', [
            'entry_id' => $entryModel->id,
            'term_ids' => $termIds,
        ]);

        return $this->entryTermsResource($entryModel->fresh());
    }

    /**
     * Отвязка выбранных термов.
     *
     * @group Admin ▸ Entry terms
     * @name Detach entry terms
     * @authenticated
     * @urlParam entry int required ID записи. Example: 42
     * @bodyParam term_ids int[] required Список ID термов (>=1). Example: [3,8]
     * @response status=200 {
     *   "data": {
     *     "entry_id": 42,
     *     "terms": []
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
     *     "term_ids": [
     *       "The term_ids field is required."
     *     ]
     *   }
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function detach(AttachTermsRequest $request, int $entry): EntryTermsResource
    {
        $entryModel = $this->findEntry($entry);

        if (! $entryModel) {
            $this->throwEntryNotFound($entry);
        }

        $validated = $request->validated();
        $termIds = $validated['term_ids'];

        DB::transaction(function () use ($entryModel, $termIds) {
            $entryModel->terms()->detach($termIds);
        });

        Log::info('Admin entry terms detached', [
            'entry_id' => $entryModel->id,
            'term_ids' => $termIds,
        ]);

        return $this->entryTermsResource($entryModel->fresh());
    }

    /**
     * Полная синхронизация термов (detach + attach).
     *
     * @group Admin ▸ Entry terms
     * @name Sync entry terms
     * @authenticated
     * @urlParam entry int required ID записи. Example: 42
     * @bodyParam term_ids int[] required Список ID термов (может быть пустым для очистки). Example: [3,8]
     * @response status=200 {
     *   "data": {
     *     "entry_id": 42,
     *     "terms": [
     *       {
     *         "id": 8,
     *         "name": "Announcements",
     *         "slug": "announcements",
     *         "taxonomy": "category"
     *       }
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
     *   "title": "Entry not found",
     *   "status": 404,
     *   "detail": "Entry with ID 42 does not exist."
     * }
     * @response status=422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "term_ids": [
     *       "Taxonomy 'tags' is not allowed for the entry post type."
     *     ]
     *   }
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function sync(SyncTermsRequest $request, int $entry): EntryTermsResource
    {
        $entryModel = $this->findEntry($entry);

        if (! $entryModel) {
            $this->throwEntryNotFound($entry);
        }

        $validated = $request->validated();
        $termIds = $validated['term_ids'];

        $terms = Term::query()
            ->with('taxonomy')
            ->whereIn('id', $termIds)
            ->get();

        if ($terms->isNotEmpty()) {
            $this->ensureTermsAllowedForEntry($entryModel, $terms);
        }

        DB::transaction(function () use ($entryModel, $termIds) {
            $entryModel->terms()->sync($termIds);
        });

        Log::info('Admin entry terms synced', [
            'entry_id' => $entryModel->id,
            'term_ids' => $termIds,
        ]);

        return $this->entryTermsResource($entryModel->fresh());
    }

    private function entryTermsResource(Entry $entry): EntryTermsResource
    {
        $payload = $this->buildEntryTermsPayload($entry);

        return new EntryTermsResource($payload);
    }

    private function findEntry(int $entryId): ?Entry
    {
        return Entry::query()
            ->with(['terms.taxonomy', 'postType'])
            ->where('id', $entryId)
            ->whereNull('deleted_at')
            ->first();
    }

    private function throwEntryNotFound(int $entryId): never
    {
        throw new HttpResponseException(
            $this->problem(
                status: 404,
                title: 'Entry not found',
                detail: "Entry with ID {$entryId} does not exist.",
                ext: ['type' => 'https://stupidcms.dev/problems/not-found'],
                headers: [
                    'Cache-Control' => 'no-store, private',
                    'Vary' => 'Cookie',
                ]
            )
        );
    }
}

