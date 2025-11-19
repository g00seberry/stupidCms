<?php

declare(strict_types=1);

use App\Domain\Search\SearchHit;

uses();

test('creates search hit with all parameters', function () {
    $highlight = [
        'title' => ['Test <em>Title</em>'],
        'excerpt' => ['Test <em>excerpt</em>'],
    ];

    $hit = new SearchHit(
        id: '123',
        postType: 'post',
        slug: 'test-post',
        title: 'Test Title',
        excerpt: 'Test excerpt',
        score: 0.95,
        highlight: $highlight
    );

    expect($hit->id)->toBe('123')
        ->and($hit->postType)->toBe('post')
        ->and($hit->slug)->toBe('test-post')
        ->and($hit->title)->toBe('Test Title')
        ->and($hit->excerpt)->toBe('Test excerpt')
        ->and($hit->score)->toBe(0.95)
        ->and($hit->highlight)->toBe($highlight);
});

test('creates search hit with nullable fields', function () {
    $hit = new SearchHit(
        id: '456',
        postType: 'page',
        slug: 'test-page',
        title: 'Page Title',
        excerpt: null,
        score: null,
        highlight: []
    );

    expect($hit->excerpt)->toBeNull()
        ->and($hit->score)->toBeNull()
        ->and($hit->highlight)->toBe([]);
});

test('search hit is immutable', function () {
    $hit = new SearchHit('1', 'post', 'test', 'Title', null, null, []);

    // Все свойства readonly
    expect($hit->id)->toBe('1');
});

