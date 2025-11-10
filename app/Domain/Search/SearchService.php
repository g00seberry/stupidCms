<?php

declare(strict_types=1);

namespace App\Domain\Search;

use App\Domain\Search\ValueObjects\SearchTermFilter;
use App\Support\Http\ProblemType;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class SearchService
{
    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly bool $enabled,
        private readonly string $readAlias
    ) {
    }

    public function search(SearchQuery $query): SearchResult
    {
        if (! $this->enabled) {
            return SearchResult::empty($query->page(), $query->perPage());
        }

        if ($query->isBlank()) {
            return SearchResult::empty($query->page(), $query->perPage());
        }

        $body = $this->buildRequestBody($query);

        try {
            $response = $this->client->search($this->readAlias, $body);
        } catch (RequestException $exception) {
            Log::error('Search query failed', [
                'exception' => $exception->getMessage(),
                'body' => $body,
            ]);

            throw new ServiceUnavailableHttpException(
                retryAfter: null,
                message: ProblemType::SERVICE_UNAVAILABLE->defaultDetail(),
                previous: $exception
            );
        }

        return $this->mapResponse($response, $query);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestBody(SearchQuery $query): array
    {
        $mustClauses = [[
            'multi_match' => [
                'query' => $query->query(),
                'fields' => [
                    'title^3',
                    'excerpt^2',
                    'body_plain',
                ],
                'type' => 'best_fields',
                'operator' => 'and',
            ],
        ]];

        $filters = [];

        if ($query->postTypes() !== []) {
            $filters[] = [
                'terms' => [
                    'post_type' => $query->postTypes(),
                ],
            ];
        }

        foreach ($query->terms() as $term) {
            $filters[] = $this->buildTermFilter($term);
        }

        $dateRange = [];
        if ($query->from()) {
            $dateRange['gte'] = $query->from()?->toIso8601String();
        }
        if ($query->to()) {
            $dateRange['lte'] = $query->to()?->toIso8601String();
        }
        if ($dateRange !== []) {
            $filters[] = [
                'range' => [
                    'published_at' => $dateRange,
                ],
            ];
        }

        return [
            'from' => $query->offset(),
            'size' => $query->perPage(),
            'track_total_hits' => true,
            'query' => [
                'bool' => array_filter([
                    'must' => $mustClauses,
                    'filter' => $filters,
                ]),
            ],
            'highlight' => [
                'pre_tags' => ['<em>'],
                'post_tags' => ['</em>'],
                'fields' => [
                    'title' => new \stdClass(),
                    'excerpt' => [
                        'fragment_size' => 160,
                        'number_of_fragments' => 1,
                    ],
                    'body_plain' => [
                        'fragment_size' => 160,
                        'number_of_fragments' => 3,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTermFilter(SearchTermFilter $term): array
    {
        return [
            'nested' => [
                'path' => 'terms',
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['terms.taxonomy' => $term->taxonomy]],
                            ['term' => ['terms.slug' => $term->slug]],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function mapResponse(array $response, SearchQuery $query): SearchResult
    {
        $hitsSection = $response['hits'] ?? [];

        $total = (int) Arr::get($hitsSection, 'total.value', 0);
        $took = (int) ($response['took'] ?? 0);

        $hits = [];
        foreach (Arr::get($hitsSection, 'hits', []) as $hit) {
            $source = $hit['_source'] ?? [];
            $highlight = $hit['highlight'] ?? [];

            $hits[] = new SearchHit(
                id: (string) ($source['id'] ?? $hit['_id'] ?? ''),
                postType: (string) ($source['post_type'] ?? ''),
                slug: (string) ($source['slug'] ?? ''),
                title: (string) ($source['title'] ?? ''),
                excerpt: isset($source['excerpt']) ? (string) $source['excerpt'] : null,
                score: isset($hit['_score']) ? (float) $hit['_score'] : null,
                highlight: $this->normalizeHighlight($highlight),
            );
        }

        return new SearchResult(
            $hits,
            $total,
            $query->page(),
            $query->perPage(),
            $took
        );
    }

    /**
     * @param array<string, mixed> $highlight
     * @return array<string, list<string>>
     */
    private function normalizeHighlight(array $highlight): array
    {
        $result = [];

        foreach ($highlight as $field => $value) {
            if (is_string($value)) {
                $result[$field] = [$value];
            } elseif (is_array($value)) {
                $result[$field] = array_values(array_filter(
                    array_map(static fn ($fragment) => is_string($fragment) ? $fragment : null, $value),
                ));
            }
        }

        return $result;
    }
}


