<?php

declare(strict_types=1);

use App\Models\Term;
use App\Models\Taxonomy;
use App\Models\Entry;
use App\Models\PostType;

/**
 * Feature-тесты для модели Term.
 */

test('term belongs to taxonomy', function () {
    $taxonomy = Taxonomy::factory()->create();
    $term = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);

    $term->load('taxonomy');

    expect($term->taxonomy)->toBeInstanceOf(Taxonomy::class)
        ->and($term->taxonomy->id)->toBe($taxonomy->id);
});

test('term can be attached to entries', function () {
    $postType = PostType::factory()->create();
    $term = Term::factory()->create();
    
    $entry = Entry::factory()->create(['post_type_id' => $postType->id]);
    $entry->terms()->attach($term->id);

    $term->load('entries');

    expect($term->entries)->toHaveCount(1)
        ->and($term->entries->first()->id)->toBe($entry->id);
});

test('term can be soft deleted', function () {
    $term = Term::factory()->create();
    $termId = $term->id;

    $term->delete();

    expect($term->trashed())->toBeTrue();

    $this->assertSoftDeleted('terms', [
        'id' => $termId,
    ]);
});

test('term meta json stores additional data', function () {
    $meta = [
        'color' => '#ff0000',
        'icon' => 'fa-tag',
    ];

    $term = Term::factory()->create(['meta_json' => $meta]);

    $term->refresh();

    expect($term->meta_json)->toBe($meta)
        ->and($term->meta_json['color'])->toBe('#ff0000');
});

test('in taxonomy scope filters by taxonomy id', function () {
    $taxonomy1 = Taxonomy::factory()->create();
    $taxonomy2 = Taxonomy::factory()->create();

    Term::factory()->count(3)->create(['taxonomy_id' => $taxonomy1->id]);
    Term::factory()->count(2)->create(['taxonomy_id' => $taxonomy2->id]);

    $terms = Term::inTaxonomy($taxonomy1->id)->get();

    expect($terms)->toHaveCount(3);
});

