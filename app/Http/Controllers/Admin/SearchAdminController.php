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


