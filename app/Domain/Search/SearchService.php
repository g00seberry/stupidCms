<?php

declare(strict_types=1);

namespace App\Domain\Search;

use App\Domain\Search\ValueObjects\SearchTermFilter;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorReporter;
use App\Support\Errors\HttpErrorException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;

/**
 * Сервис для выполнения поисковых запросов.
 *
 * Обрабатывает поисковые запросы через поисковый движок (Elasticsearch).
 * Строит запросы, обрабатывает ошибки, маппит результаты.
 *
 * @package App\Domain\Search
 */
final class SearchService
{
    /**
     * @param \App\Domain\Search\SearchClientInterface $client Клиент поискового движка
     * @param bool $enabled Флаг включения поиска (если false, возвращает пустые результаты)
     * @param string $readAlias Алиас индекса для чтения
     * @param \App\Support\Errors\ErrorFactory $errors Фабрика ошибок
     */
    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly bool $enabled,
        private readonly string $readAlias,
        private readonly ErrorFactory $errors
    ) {
    }

    /**
     * Выполнить поисковый запрос.
     *
     * Если поиск отключен или запрос пустой, возвращает пустой результат.
     * При ошибке поискового движка выбрасывает HttpErrorException.
     *
     * @param \App\Domain\Search\SearchQuery $query Поисковый запрос
     * @return \App\Domain\Search\SearchResult Результаты поиска
     * @throws \App\Support\Errors\HttpErrorException Если поисковый движок недоступен
     */
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
            $payload = $this->errors
                ->for(ErrorCode::SERVICE_UNAVAILABLE)
                ->detail('Search service is temporarily unavailable.')
                ->meta(['body' => $body])
                ->build();

            ErrorReporter::report($exception, $payload, null);

            throw new HttpErrorException($payload);
        }

        return $this->mapResponse($response, $query);
    }

    /**
     * Построить тело запроса для Elasticsearch.
     *
     * Формирует multi_match запрос с фильтрами по типам записей, термам и датам.
     * Настраивает подсветку совпадений (highlighting).
     *
     * @param \App\Domain\Search\SearchQuery $query Поисковый запрос
     * @return array<string, mixed> Тело запроса для Elasticsearch
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
     * Построить фильтр для терма (nested query).
     *
     * Создаёт nested запрос для фильтрации по термам таксономии.
     *
     * @param \App\Domain\Search\ValueObjects\SearchTermFilter $term Фильтр терма
     * @return array<string, mixed> Nested query для Elasticsearch
     */
    private function buildTermFilter(SearchTermFilter $term): array
    {
        return [
            'nested' => [
                'path' => 'terms',
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['terms.taxonomy' => $term->taxonomyId]],
                            ['term' => ['terms.slug' => $term->slug]],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Маппить ответ поискового движка в SearchResult.
     *
     * Извлекает hits, total, took из ответа и создаёт SearchHit объекты.
     *
     * @param array<string, mixed> $response Ответ от поискового движка
     * @param \App\Domain\Search\SearchQuery $query Исходный запрос
     * @return \App\Domain\Search\SearchResult Результаты поиска
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
     * Нормализовать подсветку совпадений из ответа.
     *
     * Приводит формат подсветки к единому виду: массив полей -> список фрагментов.
     *
     * @param array<string, mixed> $highlight Подсветка из ответа Elasticsearch
     * @return array<string, list<string>> Нормализованная подсветка
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


