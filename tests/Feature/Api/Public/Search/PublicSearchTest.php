<?php

declare(strict_types=1);

use App\Domain\Search\Contracts\SearchServiceInterface;
use App\Domain\Search\SearchHit;
use App\Domain\Search\SearchQuery;
use App\Domain\Search\SearchResult;
use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->postType = PostType::factory()->create(['name' => 'Article']);
});

test('public can search published entries', function () {
    $mockService = $this->mock(SearchServiceInterface::class);
    $mockService->shouldReceive('search')
        ->once()
        ->with(\Mockery::type(SearchQuery::class))
        ->andReturn(new SearchResult(
            hits: [
                new SearchHit(
                    id: '01HZZTEST1111111111111111',
                    postType: $this->postType->id,
                    slug: 'test-article',
                    title: 'Test Article',
                    excerpt: 'A test article excerpt',
                    score: 10.5,
                    highlight: ['title' => ['<em>Test</em> Article']]
                ),
            ],
            total: 1,
            page: 1,
            perPage: 20,
            tookMs: 15
        ));

    $response = $this->getJson('/api/v1/search?q=test');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'post_type', 'slug', 'title', 'excerpt', 'score', 'highlight'],
            ],
            'meta' => ['total', 'page', 'per_page', 'took_ms'],
        ])
        ->assertJsonPath('data.0.title', 'Test Article')
        ->assertJsonPath('meta.total', 1);
});

test('draft entries are not in results', function () {
    // Create draft entry
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'status' => 'draft',
        'title' => 'Draft Article',
        'slug' => 'draft-article',
    ]);

    $mockService = $this->mock(SearchServiceInterface::class);
    $mockService->shouldReceive('search')
        ->once()
        ->andReturn(SearchResult::empty(1, 20));

    $response = $this->getJson('/api/v1/search?q=draft');

    $response->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total', 0);
});

test('search results are paginated', function () {
    $mockService = $this->mock(SearchServiceInterface::class);
    $mockService->shouldReceive('search')
        ->once()
        ->andReturnUsing(function (SearchQuery $query) {
            expect($query->page())->toBe(2)
                ->and($query->perPage())->toBe(10);

            return new SearchResult(
                hits: [],
                total: 50,
                page: 2,
                perPage: 10,
                tookMs: 10
            );
        });

    $response = $this->getJson('/api/v1/search?q=test&page=2&per_page=10');

    $response->assertOk()
        ->assertJsonPath('meta.page', 2)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 50);
});

test('search returns etag header', function () {
    $mockService = $this->mock(SearchServiceInterface::class);
    $mockService->shouldReceive('search')
        ->once()
        ->andReturn(SearchResult::empty(1, 20));

    $response = $this->getJson('/api/v1/search?q=test');

    $response->assertOk();
    expect($response->headers->has('ETag'))->toBeTrue();
    expect($response->headers->get('ETag'))->toStartWith('W/"');
});

test('search returns cache control headers', function () {
    $mockService = $this->mock(SearchServiceInterface::class);
    $mockService->shouldReceive('search')
        ->once()
        ->andReturn(SearchResult::empty(1, 20));

    $response = $this->getJson('/api/v1/search?q=test');

    $response->assertOk();
    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('public')
        ->and($cacheControl)->toContain('max-age=30');
});

test('search accepts post type filter', function () {
    $mockService = $this->mock(SearchServiceInterface::class);
    $mockService->shouldReceive('search')
        ->once()
        ->andReturnUsing(function (SearchQuery $query) {
            // post_type теперь принимает ID, а не slug
            expect($query->postTypes())->toBeArray();
            return SearchResult::empty(1, 20);
        });

    $pagePostType = PostType::factory()->create(['name' => 'Page']);
    $response = $this->getJson("/api/v1/search?q=test&post_type[]={$this->postType->id}&post_type[]={$pagePostType->id}");

    $response->assertOk();
});

test('search accepts term filter', function () {
    $mockService = $this->mock(SearchServiceInterface::class);
    $mockService->shouldReceive('search')
        ->once()
        ->andReturnUsing(function (SearchQuery $query) {
            expect($query->terms())->toHaveCount(1);
            return SearchResult::empty(1, 20);
        });

    $response = $this->getJson('/api/v1/search?q=test&term[]=1:2');

    $response->assertOk();
});

test('search accepts date range filter', function () {
    $mockService = $this->mock(SearchServiceInterface::class);
    $mockService->shouldReceive('search')
        ->once()
        ->andReturnUsing(function (SearchQuery $query) {
            expect($query->from())->not->toBeNull()
                ->and($query->to())->not->toBeNull();
            return SearchResult::empty(1, 20);
        });

    $response = $this->getJson('/api/v1/search?q=test&from=2025-01-01&to=2025-12-31');

    $response->assertOk();
});

test('search validates query min length', function () {
    $response = $this->getJson('/api/v1/search?q=a');

    $response->assertStatus(422)
        ->assertJsonValidationErrors('q');
});

test('search validates query max length', function () {
    $longQuery = str_repeat('a', 201);
    $response = $this->getJson('/api/v1/search?q=' . $longQuery);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('q');
});

test('search validates date range', function () {
    $response = $this->getJson('/api/v1/search?q=test&from=2025-12-31&to=2025-01-01');

    $response->assertStatus(422)
        ->assertJsonValidationErrors('to');
});

test('search without query parameter works', function () {
    $mockService = $this->mock(SearchServiceInterface::class);
    $mockService->shouldReceive('search')
        ->once()
        ->andReturn(SearchResult::empty(1, 20));

    $response = $this->getJson('/api/v1/search');

    $response->assertOk();
});

test('search highlights matches in results', function () {
    $mockService = $this->mock(SearchServiceInterface::class);
    $mockService->shouldReceive('search')
        ->once()
        ->andReturn(new SearchResult(
            hits: [
                new SearchHit(
                    id: '01HZZTEST1111111111111111',
                    postType: $this->postType->id,
                    slug: 'headless-cms',
                    title: 'Building a Headless CMS',
                    excerpt: 'How to build a headless CMS with Laravel',
                    score: 12.5,
                    highlight: [
                        'title' => ['Building a <em>Headless</em> CMS'],
                        'excerpt' => ['How to build a <em>headless</em> CMS'],
                    ]
                ),
            ],
            total: 1,
            page: 1,
            perPage: 20,
            tookMs: 18
        ));

    $response = $this->getJson('/api/v1/search?q=headless');

    $response->assertOk()
        ->assertJsonPath('data.0.highlight.title.0', 'Building a <em>Headless</em> CMS')
        ->assertJsonPath('data.0.highlight.excerpt.0', 'How to build a <em>headless</em> CMS');
});

test('search returns score for relevance sorting', function () {
    $mockService = $this->mock(SearchServiceInterface::class);
    $mockService->shouldReceive('search')
        ->once()
        ->andReturn(new SearchResult(
            hits: [
                new SearchHit(
                    id: '01HZZTEST1111111111111111',
                    postType: $this->postType->id,
                    slug: 'test-1',
                    title: 'Test Article 1',
                    excerpt: null,
                    score: 15.8,
                    highlight: []
                ),
                new SearchHit(
                    id: '01HZZTEST2222222222222222',
                    postType: $this->postType->id,
                    slug: 'test-2',
                    title: 'Test Article 2',
                    excerpt: null,
                    score: 8.3,
                    highlight: []
                ),
            ],
            total: 2,
            page: 1,
            perPage: 20,
            tookMs: 12
        ));

    $response = $this->getJson('/api/v1/search?q=test');

    $response->assertOk()
        ->assertJsonPath('data.0.score', 15.8)
        ->assertJsonPath('data.1.score', 8.3);
});

test('search returns took ms in meta', function () {
    $mockService = $this->mock(SearchServiceInterface::class);
    $mockService->shouldReceive('search')
        ->once()
        ->andReturn(new SearchResult(
            hits: [],
            total: 0,
            page: 1,
            perPage: 20,
            tookMs: 25
        ));

    $response = $this->getJson('/api/v1/search?q=test');

    $response->assertOk()
        ->assertJsonPath('meta.took_ms', 25);
});

