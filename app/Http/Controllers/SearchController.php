<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Search\SearchService;
use App\Http\Requests\Public\Search\QuerySearchRequest;
use App\Http\Resources\SearchHitResource;
use Illuminate\Http\JsonResponse;

/**
 * Контроллер для публичного поиска контента.
 *
 * Предоставляет API для поиска опубликованных записей с фильтрами
 * по типам записей, термам и датам публикации.
 *
 * @package App\Http\Controllers
 */
final class SearchController extends Controller
{
    /**
     * @param \App\Domain\Search\SearchService $search Сервис поиска
     */
    public function __construct(
        private readonly SearchService $search
    ) {
    }

    /**
     * Поиск опубликованного контента с фильтрами.
     *
     * @group Search
     * @name Search entries
     * @unauthenticated
     * @queryParam q string Поисковая строка (2-200 символов). Example: headless cms
     * @queryParam post_type[] string Список slug типов записей (до 10). Example: ["article","event"]
     * @queryParam term[] string Фильтр по термам в формате taxonomy:term (до 20 значений). Example: ["category:guides"]
     * @queryParam from date Дата публикации c (ISO 8601). Example: 2025-01-01
     * @queryParam to date Дата публикации до (>= from). Example: 2025-12-31
     * @queryParam page int Номер страницы (>=1). Default: 1.
     * @queryParam per_page int Количество элементов на странице (1-100, по умолчанию config('search.pagination.per_page')). Example: 20
     * @responseHeader Cache-Control "public, max-age=30"
     * @responseHeader ETag W/"{sha256}"
     * @response status=200 {
     *   "data": [
     *     {
     *       "id": "entries:42",
     *       "post_type": "article",
     *       "slug": "how-to-headless",
     *       "title": "How to build a headless CMS",
     *       "excerpt": "Step-by-step launch guide...",
     *       "score": 12.45,
     *       "highlight": {
     *         "title": ["<em>headless</em> CMS"]
     *       }
     *     }
     *   ],
     *   "meta": {
     *     "total": 120,
     *     "page": 1,
     *     "per_page": 20,
     *     "took_ms": 18
     *   }
     * }
     */
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
     * Создать ETag для ответа.
     *
     * Генерирует слабый ETag (W/") на основе SHA256 хэша JSON payload.
     *
     * @param mixed $payload Данные для хэширования
     * @return string ETag в формате W/"{hash}"
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


