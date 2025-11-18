<?php

declare(strict_types=1);

use App\Domain\Search\ValueObjects\SearchTermFilter;

test('creates search term filter from string', function () {
    $filter = SearchTermFilter::fromString('1:123');

    expect($filter->taxonomyId)->toBe(1)
        ->and($filter->termId)->toBe(123);
});

test('creates search term filter with different ids', function () {
    $filter = SearchTermFilter::fromString('5:456');

    expect($filter->taxonomyId)->toBe(5)
        ->and($filter->termId)->toBe(456);
});

test('throws exception for empty string', function () {
    expect(fn () => SearchTermFilter::fromString(''))
        ->toThrow(InvalidArgumentException::class, 'Term filter must be in format taxonomy_id:term_id.');
});

test('throws exception for string without colon', function () {
    expect(fn () => SearchTermFilter::fromString('123'))
        ->toThrow(InvalidArgumentException::class, 'Term filter must be in format taxonomy_id:term_id.');
});

test('throws exception for empty taxonomy id', function () {
    expect(fn () => SearchTermFilter::fromString(':123'))
        ->toThrow(InvalidArgumentException::class, 'Both taxonomy_id and term_id must be non-empty.');
});

test('throws exception for empty term id', function () {
    expect(fn () => SearchTermFilter::fromString('1:'))
        ->toThrow(InvalidArgumentException::class, 'Both taxonomy_id and term_id must be non-empty.');
});

test('throws exception for invalid taxonomy id', function () {
    expect(fn () => SearchTermFilter::fromString('abc:123'))
        ->toThrow(InvalidArgumentException::class, 'Taxonomy ID must be a valid integer.');
});

test('throws exception for invalid term id', function () {
    expect(fn () => SearchTermFilter::fromString('1:xyz'))
        ->toThrow(InvalidArgumentException::class, 'Term ID must be a valid integer.');
});

test('trims whitespace from string', function () {
    $filter = SearchTermFilter::fromString('  1  :  123  ');

    expect($filter->taxonomyId)->toBe(1)
        ->and($filter->termId)->toBe(123);
});

test('search term filter is immutable', function () {
    $filter = SearchTermFilter::fromString('1:123');

    // Все свойства readonly
    expect($filter->taxonomyId)->toBe(1)
        ->and($filter->termId)->toBe(123);
});

test('can create multiple filters', function () {
    $filter1 = SearchTermFilter::fromString('1:10');
    $filter2 = SearchTermFilter::fromString('2:20');

    expect($filter1->taxonomyId)->toBe(1)
        ->and($filter1->termId)->toBe(10)
        ->and($filter2->taxonomyId)->toBe(2)
        ->and($filter2->termId)->toBe(20);
});

