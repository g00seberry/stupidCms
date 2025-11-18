<?php

declare(strict_types=1);

use App\Domain\Search\SearchQuery;
use App\Domain\Search\ValueObjects\SearchTermFilter;
use Carbon\CarbonImmutable;

test('creates search query with all parameters', function () {
    $from = CarbonImmutable::parse('2024-01-01');
    $to = CarbonImmutable::parse('2024-12-31');
    $terms = [SearchTermFilter::fromString('1:10'), SearchTermFilter::fromString('2:20')];

    $query = new SearchQuery(
        query: 'test query',
        postTypes: ['post', 'page'],
        terms: $terms,
        from: $from,
        to: $to,
        page: 2,
        perPage: 20
    );

    expect($query->query())->toBe('test query')
        ->and($query->postTypes())->toBe(['post', 'page'])
        ->and($query->terms())->toBe($terms)
        ->and($query->from())->toBe($from)
        ->and($query->to())->toBe($to)
        ->and($query->page())->toBe(2)
        ->and($query->perPage())->toBe(20);
});

test('creates search query with minimal parameters', function () {
    $query = new SearchQuery(
        query: null,
        postTypes: [],
        terms: [],
        from: null,
        to: null,
        page: 1,
        perPage: 10
    );

    expect($query->query())->toBeNull()
        ->and($query->postTypes())->toBe([])
        ->and($query->terms())->toBe([])
        ->and($query->from())->toBeNull()
        ->and($query->to())->toBeNull()
        ->and($query->page())->toBe(1)
        ->and($query->perPage())->toBe(10);
});

test('calculates offset correctly', function () {
    $query1 = new SearchQuery(null, [], [], null, null, 1, 10);
    $query2 = new SearchQuery(null, [], [], null, null, 2, 10);
    $query3 = new SearchQuery(null, [], [], null, null, 3, 20);

    expect($query1->offset())->toBe(0)
        ->and($query2->offset())->toBe(10)
        ->and($query3->offset())->toBe(40);
});

test('is blank returns true for null query', function () {
    $query = new SearchQuery(null, [], [], null, null, 1, 10);

    expect($query->isBlank())->toBeTrue();
});

test('is blank returns true for empty string query', function () {
    $query = new SearchQuery('', [], [], null, null, 1, 10);

    expect($query->isBlank())->toBeTrue();
});

test('is blank returns true for whitespace only query', function () {
    $query = new SearchQuery('   ', [], [], null, null, 1, 10);

    expect($query->isBlank())->toBeTrue();
});

test('is blank returns false for non-empty query', function () {
    $query = new SearchQuery('test', [], [], null, null, 1, 10);

    expect($query->isBlank())->toBeFalse();
});

test('search query is immutable', function () {
    $query = new SearchQuery('test', [], [], null, null, 1, 10);

    // Все свойства readonly, поэтому нельзя изменить
    expect($query->query())->toBe('test');
});

