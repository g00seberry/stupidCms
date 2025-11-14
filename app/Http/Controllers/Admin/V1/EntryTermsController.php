<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Admin\V1\Concerns\ManagesEntryTerms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttachTermsRequest;
use App\Http\Requests\Admin\SyncTermsRequest;
use App\Http\Resources\Admin\EntryTermsResource;
use App\Models\Entry;
use App\Models\Term;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Контроллер для управления термами записей в админ-панели.
 *
 * Предоставляет операции для управления привязкой термов к записям:
 * просмотр, добавление, синхронизация термов записи.
 *
 * @package App\Http\Controllers\Admin\V1
 */
class EntryTermsController extends Controller
{
    use ManagesEntryTerms;
    use ThrowsErrors;

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
     *       "1": [
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "51111111-2222-3333-4444-555555555557",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-51111111222233334444555555555557-5111111122223333-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Entry not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Entry with ID 42 does not exist.",
     *   "meta": {
     *     "request_id": "51111111-2222-3333-4444-555555555558",
     *     "entry_id": 42
     *   },
     *   "trace_id": "00-51111111222233334444555555555558-5111111122223333-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "Taxonomy 'tags' is not allowed for the entry post type.",
     *   "meta": {
     *     "request_id": "51111111-2222-3333-4444-555555555559",
     *     "errors": {
     *       "term_ids": [
     *         "Taxonomy 'tags' is not allowed for the entry post type."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-51111111222233334444555555555559-5111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "56666666-7777-8888-9999-000000000001",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-56666666777788889999000000000001-5666666677778888-01"
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
     *       "1": [
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "51111111-2222-3333-4444-555555555556",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-51111111222233334444555555555556-5111111122223333-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Entry not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Entry with ID 42 does not exist.",
     *   "meta": {
     *     "request_id": "51111111-2222-3333-4444-555555555557",
     *     "entry_id": 42
     *   },
     *   "trace_id": "00-51111111222233334444555555555557-5111111122223333-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "Taxonomy 'tags' is not allowed for the entry post type.",
     *   "meta": {
     *     "request_id": "51111111-2222-3333-4444-555555555558",
     *     "errors": {
     *       "term_ids": [
     *         "Taxonomy 'tags' is not allowed for the entry post type."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-51111111222233334444555555555558-5111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "56666666-7777-8888-9999-000000000000",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-56666666777788889999000000000000-5666666677778888-01"
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "51111111-2222-3333-4444-555555555560",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-51111111222233334444555555555660-5111111122223333-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Entry not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Entry with ID 42 does not exist.",
     *   "meta": {
     *     "request_id": "51111111-2222-3333-4444-555555555561",
     *     "entry_id": 42
     *   },
     *   "trace_id": "00-51111111222233334444555555555661-5111111122223333-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The term_ids field is required.",
     *   "meta": {
     *     "request_id": "51111111-2222-3333-4444-555555555562",
     *     "errors": {
     *       "term_ids": [
     *         "The term_ids field is required."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-51111111222233334444555555555662-5111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "56666666-7777-8888-9999-000000000002",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-56666666777788889999000000000002-5666666677778888-01"
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "51111111-2222-3333-4444-555555555563",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-51111111222233334444555555555663-5111111122223333-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/not-found",
     *   "title": "Entry not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Entry with ID 42 does not exist.",
     *   "meta": {
     *     "request_id": "51111111-2222-3333-4444-555555555564",
     *     "entry_id": 42
     *   },
     *   "trace_id": "00-51111111222233334444555555555664-5111111122223333-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "Taxonomy 'tags' is not allowed for the entry post type.",
     *   "meta": {
     *     "request_id": "51111111-2222-3333-4444-555555555565",
     *     "errors": {
     *       "term_ids": [
     *         "Taxonomy 'tags' is not allowed for the entry post type."
     *       ]
     *     }
     *   },
     *   "trace_id": "00-51111111222233334444555555555665-5111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "56666666-7777-8888-9999-000000000003",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-56666666777788889999000000000003-5666666677778888-01"
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

    /**
     * Создать ресурс с термами записи.
     *
     * @param \App\Models\Entry $entry Запись с загруженными термами
     * @return \App\Http\Resources\Admin\EntryTermsResource Ресурс
     */
    private function entryTermsResource(Entry $entry): EntryTermsResource
    {
        $payload = $this->buildEntryTermsPayload($entry);

        return new EntryTermsResource($payload);
    }

    /**
     * Найти запись по ID (без удалённых).
     *
     * @param int $entryId ID записи
     * @return \App\Models\Entry|null Запись или null
     */
    private function findEntry(int $entryId): ?Entry
    {
        return Entry::query()
            ->with(['terms.taxonomy', 'postType'])
            ->where('id', $entryId)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Выбросить ошибку "запись не найдена".
     *
     * @param int $entryId ID записи
     * @return never
     */
    private function throwEntryNotFound(int $entryId): never
    {
        $this->throwError(
            ErrorCode::NOT_FOUND,
            sprintf('Entry with ID %d does not exist.', $entryId),
            ['entry_id' => $entryId],
        );
    }
}

