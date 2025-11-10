<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Domain\Search\Jobs\ReindexSearchJob;
use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use App\Http\Resources\Admin\SearchReindexAcceptedResource;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

final class SearchAdminController extends Controller
{
    use ThrowsErrors;


    /**
     * Запуск фоновой переиндексации поиска.
     *
     * @group Admin ▸ Search
     * @name Reindex search
     * @authenticated
     * @response status=202 {
     *   "job_id": "01HZYQNGQK74ZP6YVZ6E7SFJ2D",
     *   "batch_size": 500,
     *   "estimated_total": 320
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
     * @response status=503 {
     *   "type": "https://stupidcms.dev/problems/service-unavailable",
     *   "title": "Service Unavailable",
     *   "status": 503,
     *   "detail": "Search service is temporarily unavailable."
     * }
     */
    public function reindex(): SearchReindexAcceptedResource
    {
        if (! config('search.enabled')) {
            $this->throwError(ErrorCode::SERVICE_UNAVAILABLE, 'Search service is disabled.');
        }

        $trackingId = (string) Str::ulid();
        Bus::dispatch(new ReindexSearchJob($trackingId));

        $estimatedTotal = Entry::query()->published()->count();

        return new SearchReindexAcceptedResource(
            $trackingId,
            (int) config('search.batch.size', 500),
            $estimatedTotal
        );
    }
}


