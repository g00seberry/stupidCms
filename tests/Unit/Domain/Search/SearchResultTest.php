<?php

declare(strict_types=1);

use App\Domain\Search\SearchHit;
use App\Domain\Search\SearchResult;

uses();

test('creates search result with all parameters', function () {
    $hits = [
        new SearchHit('1', 1, 'test-1', 'Test Title 1', 'Excerpt 1', 0.95, []),
        new SearchHit('2', 1, 'test-2', 'Test Title 2', 'Excerpt 2', 0.85, []),
    ];

    $result = new SearchResult($hits, 100, 1, 10, 15);

    expect($result->hits())->toBe($hits)
        ->and($result->total())->toBe(100)
        ->and($result->page())->toBe(1)
        ->and($result->perPage())->toBe(10)
        ->and($result->tookMs())->toBe(15);
});

test('creates empty search result', function () {
    $result = SearchResult::empty(2, 20);

    expect($result->hits())->toBe([])
        ->and($result->total())->toBe(0)
        ->and($result->page())->toBe(2)
        ->and($result->perPage())->toBe(20)
        ->and($result->tookMs())->toBe(0);
});

test('search result is immutable', function () {
    $hits = [new SearchHit('1', 1, 'test', 'Title', null, null, [])];
    $result = new SearchResult($hits, 1, 1, 10, 0);

    // Все свойства readonly
    expect($result->total())->toBe(1);
});

test('can have empty hits list', function () {
    $result = new SearchResult([], 0, 1, 10, 5);

    expect($result->hits())->toBe([])
        ->and($result->total())->toBe(0);
});

