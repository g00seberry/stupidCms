<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Search\Jobs\ReindexSearchJob;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Models\Entry;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class SearchAdminController extends Controller
{
    use Problems;

    public function reindex(): JsonResponse
    {
        if (! config('search.enabled')) {
            return $this->problemFromPreset(
                ProblemDetails::serviceUnavailable(),
                headers: [
                    'Cache-Control' => 'no-store, private',
                    'Vary' => 'Cookie',
                ]
            );
        }

        $trackingId = (string) Str::ulid();
        Bus::dispatch(new ReindexSearchJob($trackingId));

        $estimatedTotal = Entry::query()->published()->count();

        return response()->json([
            'job_id' => $trackingId,
            'batch_size' => (int) config('search.batch.size', 500),
            'estimated_total' => $estimatedTotal,
        ], Response::HTTP_ACCEPTED, [
            'Cache-Control' => 'no-store, private',
            'Vary' => 'Cookie',
        ]);
    }
}


