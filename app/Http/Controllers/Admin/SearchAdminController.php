<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Search\Jobs\ReindexSearchJob;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Models\Entry;
use App\Support\ProblemDetails;
use App\Http\Resources\Admin\SearchReindexAcceptedResource;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class SearchAdminController extends Controller
{
    use Problems;

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
            throw new HttpResponseException(
                $this->problemFromPreset(
                    ProblemDetails::serviceUnavailable(),
                    headers: [
                        'Cache-Control' => 'no-store, private',
                        'Vary' => 'Cookie',
                    ]
                )
            );
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


