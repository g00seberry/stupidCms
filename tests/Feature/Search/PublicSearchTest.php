<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Domain\Search\SearchClientInterface;
use App\Domain\Search\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class PublicSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_200_and_empty_result_when_index_is_empty(): void
    {
        Config::set('search.enabled', true);

        $client = $this->registerSearchClient([
            'took' => 0,
            'hits' => [
                'total' => ['value' => 0],
                'hits' => [],
            ],
        ]);

        $response = $this->getJson('/api/v1/search');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'data' => [],
            'meta' => [
                'total' => 0,
                'page' => 1,
                'per_page' => 20,
                'took_ms' => 0,
            ],
        ]);

        self::assertSame('max-age=30, public', $response->headers->get('Cache-Control'));
        $response->assertHeader('ETag');
        self::assertSame(0, $client->calls);
    }

    public function test_accepts_empty_query_and_returns_empty_result(): void
    {
        Config::set('search.enabled', true);

        $client = $this->registerSearchClient([
            'took' => 0,
            'hits' => [
                'total' => ['value' => 0],
                'hits' => [],
            ],
        ]);

        $response = $this->getJson('/api/v1/search?q=');

        $response->assertOk();
        $response->assertJson([
            'data' => [],
            'meta' => [
                'total' => 0,
                'page' => 1,
                'per_page' => 20,
                'took_ms' => 0,
            ],
        ]);
        self::assertSame(0, $client->calls);
    }

    public function test_searches_by_query_and_respects_filters(): void
    {
        Config::set('search.enabled', true);

        $client = $this->registerSearchClient([
            'took' => 5,
            'hits' => [
                'total' => ['value' => 1],
                'hits' => [[
                    '_id' => '1',
                    '_score' => 1.23,
                    '_source' => [
                        'id' => '1',
                        'post_type' => 'page',
                        'slug' => 'about',
                        'title' => 'About Us',
                        'excerpt' => 'About excerpt',
                        'body_plain' => 'About body',
                    ],
                    'highlight' => [
                        'body_plain' => ['<em>about</em> us'],
                    ],
                ]],
            ],
        ]);

        $response = $this->getJson('/api/v1/search?q=about&post_type=page&term=category:news&page=2&per_page=10');

        $response->assertOk();
        $response->assertJson([
            'meta' => [
                'total' => 1,
                'page' => 2,
                'per_page' => 10,
                'took_ms' => 5,
            ],
        ]);

        $data = $response->json('data');
        self::assertIsArray($data);
        self::assertCount(1, $data);
        self::assertSame('1', $data[0]['id']);
        self::assertSame('page', $data[0]['post_type']);
        self::assertSame('about', $data[0]['slug']);
        self::assertSame('About Us', $data[0]['title']);
        self::assertSame(1.23, $data[0]['score']);

        self::assertSame('entries_read', $client->lastAlias);
        self::assertNotNull($client->lastPayload);
        $payload = $client->lastPayload;
        self::assertSame('about', $payload['query']['bool']['must'][0]['multi_match']['query']);
        self::assertSame(['page'], $payload['query']['bool']['filter'][0]['terms']['post_type']);
    }

    private function registerSearchClient(array $response): RecordingSearchClient
    {
        $client = new RecordingSearchClient($response);
        $this->app->instance(SearchClientInterface::class, $client);
        $this->app->forgetInstance(SearchService::class);

        return $client;
    }
}

final class RecordingSearchClient implements SearchClientInterface
{
    public int $calls = 0;
    public ?array $lastPayload = null;
    public ?string $lastAlias = null;

    public function __construct(private array $response)
    {
    }

    public function search(string $indexAlias, array $body): array
    {
        $this->calls++;
        $this->lastAlias = $indexAlias;
        $this->lastPayload = $body;

        return $this->response;
    }

    public function createIndex(string $indexName, array $settings, array $mappings): void
    {
        //
    }

    public function deleteIndex(string $indexName): void
    {
        //
    }

    public function updateAliases(array $actions): void
    {
        //
    }

    public function getIndicesForAlias(string $alias): array
    {
        return [];
    }

    public function bulk(array $operations): void
    {
        //
    }

    public function refresh(string $indexName): void
    {
        //
    }
}



