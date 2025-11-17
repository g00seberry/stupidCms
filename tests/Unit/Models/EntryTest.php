<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;
use App\Models\Term;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Unit-тесты для модели Entry.
 *
 * Проверяют структуру модели, casts, отношения, scopes и бизнес-логику
 * без взаимодействия с БД.
 */

test('casts data_json to array', function () {
    $entry = new Entry();

    $casts = $entry->getCasts();

    expect($casts)->toHaveKey('data_json')
        ->and($casts['data_json'])->toBe('array');
});

test('casts seo_json to array', function () {
    $entry = new Entry();

    $casts = $entry->getCasts();

    expect($casts)->toHaveKey('seo_json')
        ->and($casts['seo_json'])->toBe('array');
});

test('casts published_at to datetime', function () {
    $entry = new Entry();

    $casts = $entry->getCasts();

    expect($casts)->toHaveKey('published_at')
        ->and($casts['published_at'])->toBe('datetime');
});

test('has post type relationship', function () {
    $entry = new Entry();

    $relation = $entry->postType();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(PostType::class);
});

test('has author relationship', function () {
    $entry = new Entry();

    $relation = $entry->author();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(User::class)
        ->and($relation->getForeignKeyName())->toBe('author_id');
});

test('has terms many to many relationship', function () {
    $entry = new Entry();

    $relation = $entry->terms();

    expect($relation)->toBeInstanceOf(BelongsToMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Term::class)
        ->and($relation->getTable())->toBe('entry_term');
});

test('uses soft deletes', function () {
    $entry = new Entry();

    $traits = class_uses_recursive($entry);

    expect($traits)->toHaveKey(SoftDeletes::class);
});

test('has published scope', function () {
    $entry = new Entry();

    expect(method_exists($entry, 'scopePublished'))->toBeTrue();
});

test('has of type scope', function () {
    $entry = new Entry();

    expect(method_exists($entry, 'scopeOfType'))->toBeTrue();
});

test('has draft status constant', function () {
    expect(Entry::STATUS_DRAFT)->toBe('draft');
});

test('has published status constant', function () {
    expect(Entry::STATUS_PUBLISHED)->toBe('published');
});

test('get statuses returns all statuses', function () {
    $statuses = Entry::getStatuses();

    expect($statuses)->toBe(['draft', 'published'])
        ->and($statuses)->toHaveCount(2);
});

test('url method returns flat url for page type', function () {
    $entry = new Entry();
    $entry->slug = 'test-page';
    
    $postType = new PostType();
    $postType->slug = 'page';
    
    $entry->setRelation('postType', $postType);

    expect($entry->url())->toBe('/test-page');
});

test('url method returns hierarchical url for non-page type', function () {
    $entry = new Entry();
    $entry->slug = 'test-post';
    
    $postType = new PostType();
    $postType->slug = 'blog';
    
    $entry->setRelation('postType', $postType);

    expect($entry->url())->toBe('/blog/test-post');
});

test('has no guarded attributes', function () {
    $entry = new Entry();

    $guarded = $entry->getGuarded();

    expect($guarded)->toBe([]);
});

