<?php

declare(strict_types=1);

use App\Models\Term;
use App\Models\Taxonomy;
use App\Models\Entry;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tests\TestCase;

/**
 * Unit-тесты для модели Term.
 */

uses(TestCase::class);

test('casts meta_json to array', function () {
    $term = new Term();

    $casts = $term->getCasts();

    expect($casts)->toHaveKey('meta_json')
        ->and($casts['meta_json'])->toBe('array');
});

test('belongs to taxonomy', function () {
    $term = new Term();

    $relation = $term->taxonomy();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Taxonomy::class);
});

test('has entries many to many relationship', function () {
    $term = new Term();

    $relation = $term->entries();

    expect($relation)->toBeInstanceOf(BelongsToMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Entry::class)
        ->and($relation->getTable())->toBe('entry_term');
});

test('has ancestors relationship', function () {
    $term = new Term();

    $relation = $term->ancestors();

    expect($relation)->toBeInstanceOf(BelongsToMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Term::class)
        ->and($relation->getTable())->toBe('term_tree');
});

test('has descendants relationship', function () {
    $term = new Term();

    $relation = $term->descendants();

    expect($relation)->toBeInstanceOf(BelongsToMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Term::class)
        ->and($relation->getTable())->toBe('term_tree');
});

test('has parent relationship', function () {
    $term = new Term();

    $relation = $term->parent();

    expect($relation)->toBeInstanceOf(BelongsToMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Term::class);
});

test('has children relationship', function () {
    $term = new Term();

    $relation = $term->children();

    expect($relation)->toBeInstanceOf(BelongsToMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Term::class);
});

test('uses soft deletes', function () {
    $term = new Term();

    $traits = class_uses_recursive($term);

    expect($traits)->toHaveKey(SoftDeletes::class);
});

test('has no guarded attributes', function () {
    $term = new Term();

    expect($term->getGuarded())->toBe([]);
});

