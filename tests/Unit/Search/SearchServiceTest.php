<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use App\Domain\Search\SearchClientInterface;
use App\Domain\Search\SearchQuery;
use App\Domain\Search\SearchService;
use App\Domain\Search\ValueObjects\SearchTermFilter;
use App\Support\Errors\ErrorFactory;
use Carbon\CarbonImmutable;
use Mockery as m;
use Tests\TestCase;

final class SearchServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_returns_empty_result_when_disabled(): void
    {
        $client = m::mock(SearchClientInterface::class);
        $client->shouldNotReceive('search');

        $service = new SearchService($client, false, 'entries_read', app(ErrorFactory::class));

        $query = new SearchQuery('about', [], [], null, null, 1, 20);
        $result = $service->search($query);

        self::assertSame(0, $result->total());
        self::assertSame([], $result->hits());
    }

    public function test_builds_expected_payload_and_maps_response(): void
    {
        $client = m::mock(SearchClientInterface::class);

        $client->shouldReceive('search')
            ->once()
            ->with('entries_read', m::on(static function (array $payload): bool {
                return $payload['query']['bool']['must'][0]['multi_match']['query'] === 'about'
                    && count($payload['query']['bool']['filter']) === 3
                    && $payload['from'] === 10
                    && $payload['size'] === 5;
            }))
            ->andReturn([
                'took' => 7,
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [[
                        '_id' => '1',
                        '_score' => 2.5,
                        '_source' => [
                            'id' => '1',
                            'post_type' => 'page',
                            'slug' => 'about',
                            'title' => 'About us',
                            'excerpt' => 'About excerpt',
                            'body_plain' => 'About body',
                        ],
                        'highlight' => [
                            'body_plain' => ['<em>about</em> body'],
                        ],
                    ]],
                ],
            ]);

        $service = new SearchService($client, true, 'entries_read', app(ErrorFactory::class));

        $query = new SearchQuery(
            query: 'about',
            postTypes: ['page'],
            terms: [SearchTermFilter::fromString('1:1')],
            from: CarbonImmutable::parse('2024-01-01T00:00:00Z'),
            to: CarbonImmutable::parse('2024-12-31T00:00:00Z'),
            page: 3,
            perPage: 5
        );

        $result = $service->search($query);

        self::assertSame(1, $result->total());
        self::assertSame(7, $result->tookMs());
        self::assertCount(1, $result->hits());
        $hit = $result->hits()[0];
        self::assertSame('1', $hit->id);
        self::assertArrayHasKey('body_plain', $hit->highlight);
    }
}


