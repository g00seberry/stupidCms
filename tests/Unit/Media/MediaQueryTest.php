<?php

declare(strict_types=1);

use App\Domain\Media\MediaQuery;
use App\Domain\Media\MediaDeletedFilter;

/**
 * Unit-тесты для MediaQuery (Value Object).
 */

test('creates media query with all parameters', function () {
    $query = new MediaQuery(
        search: 'test',
        kind: 'image',
        mimePrefix: 'image/',
        deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
        sort: 'created_at',
        order: 'desc',
        page: 1,
        perPage: 20
    );

    expect($query->search())->toBe('test')
        ->and($query->kind())->toBe('image')
        ->and($query->mimePrefix())->toBe('image/')
        ->and($query->deletedFilter())->toBe(MediaDeletedFilter::DefaultOnlyNotDeleted)
        ->and($query->sort())->toBe('created_at')
        ->and($query->order())->toBe('desc')
        ->and($query->page())->toBe(1)
        ->and($query->perPage())->toBe(20);
});

test('creates media query with minimal parameters', function () {
    $query = new MediaQuery(
        search: null,
        kind: null,
        mimePrefix: null,
        deletedFilter: MediaDeletedFilter::DefaultOnlyNotDeleted,
        sort: 'id',
        order: 'asc',
        page: 1,
        perPage: 15
    );

    expect($query->search())->toBeNull()
        ->and($query->kind())->toBeNull()
        ->and($query->mimePrefix())->toBeNull()
        ->and($query->deletedFilter())->toBe(MediaDeletedFilter::DefaultOnlyNotDeleted);
});

test('media query is immutable value object', function () {
    $query = new MediaQuery(
        search: 'original',
        kind: 'video',
        mimePrefix: 'video/',
        deletedFilter: MediaDeletedFilter::OnlyDeleted,
        sort: 'size_bytes',
        order: 'asc',
        page: 2,
        perPage: 50
    );

    // Value Object should not have setters
    $reflection = new ReflectionClass($query);
    
    expect($reflection->isFinal())->toBeTrue()
        ->and($query->search())->toBe('original')
        ->and($query->page())->toBe(2);
});

test('deleted filter has correct enum values', function () {
    expect(MediaDeletedFilter::DefaultOnlyNotDeleted)->toBeInstanceOf(MediaDeletedFilter::class)
        ->and(MediaDeletedFilter::OnlyDeleted)->toBeInstanceOf(MediaDeletedFilter::class)
        ->and(MediaDeletedFilter::WithDeleted)->toBeInstanceOf(MediaDeletedFilter::class)
        ->and(MediaDeletedFilter::DefaultOnlyNotDeleted->value)->toBe('default')
        ->and(MediaDeletedFilter::OnlyDeleted->value)->toBe('only')
        ->and(MediaDeletedFilter::WithDeleted->value)->toBe('with');
});

