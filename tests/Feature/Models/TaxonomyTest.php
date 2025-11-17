<?php

declare(strict_types=1);

use App\Models\Taxonomy;
use App\Models\Term;

/**
 * Feature-тесты для модели Taxonomy.
 */

test('taxonomy can be created', function () {
    $taxonomy = Taxonomy::factory()->create([
        'name' => 'Category',
        'hierarchical' => true,
    ]);

    expect($taxonomy)->toBeInstanceOf(Taxonomy::class)
        ->and($taxonomy->name)->toBe('Category')
        ->and($taxonomy->hierarchical)->toBeTrue()
        ->and($taxonomy->exists)->toBeTrue();

    $this->assertDatabaseHas('taxonomies', [
        'id' => $taxonomy->id,
        'name' => 'Category',
    ]);
});

test('taxonomy can be hierarchical', function () {
    $taxonomy = Taxonomy::factory()->create(['hierarchical' => true]);

    expect($taxonomy->hierarchical)->toBeTrue();
});

test('taxonomy can be flat', function () {
    $taxonomy = Taxonomy::factory()->create(['hierarchical' => false]);

    expect($taxonomy->hierarchical)->toBeFalse();
});

test('taxonomy can have multiple terms', function () {
    $taxonomy = Taxonomy::factory()->create();

    $term1 = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);
    $term2 = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);

    $taxonomy->load('terms');

    expect($taxonomy->terms)->toHaveCount(2)
        ->and($taxonomy->terms->pluck('id')->toArray())->toContain($term1->id, $term2->id);
});

test('taxonomy label accessor works', function () {
    $taxonomy = Taxonomy::factory()->create(['name' => 'Test Taxonomy']);

    expect($taxonomy->label)->toBe('Test Taxonomy');
});

test('taxonomy options can be stored', function () {
    $options = [
        'show_in_menu' => true,
        'icon' => 'fa-folder',
    ];

    $taxonomy = Taxonomy::factory()->create(['options_json' => $options]);

    $taxonomy->refresh();

    expect($taxonomy->options_json)->toBe($options);
});

