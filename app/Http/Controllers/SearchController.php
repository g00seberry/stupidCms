<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Search\SearchService;
use App\Http\Requests\Public\Search\QuerySearchRequest;
use App\Http\Resources\SearchHitResource;
use Illuminate\Http\JsonResponse;

final class SearchController extends Controller
{
    public function __construct(
        private readonly SearchService $search
    ) {
    }

    public function index(QuerySearchRequest $request): JsonResponse
    {
        $query = $request->toSearchQuery();
        $result = $this->search->search($query);

        $resource = SearchHitResource::collection($result->hits())
            ->additional([
                'meta' => [
                    'total' => $result->total(),
                    'page' => $result->page(),
                    'per_page' => $result->perPage(),
                    'took_ms' => $result->tookMs(),
                ],
            ]);

        $response = $resource->response()->setStatusCode(200);

        $etag = $this->makeEtag($response->getData(true));

        $response->headers->set('Cache-Control', 'public, max-age=30');
        $response->headers->set('ETag', $etag);
        $response->headers->set('Vary', 'Accept-Encoding');
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @param mixed $payload
     */
    private function makeEtag($payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return sprintf('W/"%s"', uniqid('search', true));
        }

        return sprintf('W/"%s"', hash('sha256', $encoded));
    }
}


