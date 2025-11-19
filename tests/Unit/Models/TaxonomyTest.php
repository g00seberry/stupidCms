<?php

declare(strict_types=1);

use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

/**
 * Unit-тесты для модели Taxonomy.
 */

uses(TestCase::class);

test('casts options_json to array', function () {
    $taxonomy = new Taxonomy();

    $casts = $taxonomy->getCasts();

    expect($casts)->toHaveKey('options_json')
        ->and($casts['options_json'])->toBe('array');
});

test('casts hierarchical to boolean', function () {
    $taxonomy = new Taxonomy();

    $casts = $taxonomy->getCasts();

    expect($casts)->toHaveKey('hierarchical')
        ->and($casts['hierarchical'])->toBe('boolean');
});

test('has terms relationship', function () {
    $taxonomy = new Taxonomy();

    $relation = $taxonomy->terms();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Term::class);
});

test('label accessor returns name', function () {
    $taxonomy = new Taxonomy();
    $taxonomy->name = 'Category';

    expect($taxonomy->label)->toBe('Category');
});

test('has no guarded attributes', function () {
    $taxonomy = new Taxonomy();

    expect($taxonomy->getGuarded())->toBe([]);
});

