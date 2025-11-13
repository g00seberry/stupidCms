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

/**
 * Контроллер для управления поиском в админ-панели.
 *
 * Предоставляет операции для запуска реиндексации поискового индекса
 * в фоновом режиме через очередь.
 *
 * @package App\Http\Controllers\Admin\V1
 */
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
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "61111111-2222-3333-4444-555555555555",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-61111111222233334444555555555555-6111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "66666666-7777-8888-9999-000000000000",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-66666666777788889999000000000000-6666666677778888-01"
     * }
     * @response status=503 {
     *   "type": "https://stupidcms.dev/problems/service-unavailable",
     *   "title": "Service Unavailable",
     *   "status": 503,
     *   "code": "SERVICE_UNAVAILABLE",
     *   "detail": "Search service is temporarily unavailable.",
     *   "meta": {
     *     "request_id": "61111111-2222-3333-4444-555555555556",
     *     "service": "search"
     *   },
     *   "trace_id": "00-61111111222233334444555555555556-6111111122223333-01"
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


