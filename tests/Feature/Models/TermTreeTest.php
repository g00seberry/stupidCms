<?php

declare(strict_types=1);

use App\Models\TermTree;
use App\Models\Term;
use App\Models\Taxonomy;

/**
 * Feature-тесты для модели TermTree.
 */

test('term tree stores term relationships', function () {
    $taxonomy = Taxonomy::factory()->create();
    $parent = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);
    $child = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);

    // Создаем связь parent-child
    TermTree::create([
        'ancestor_id' => $parent->id,
        'descendant_id' => $child->id,
        'depth' => 1,
    ]);

    $this->assertDatabaseHas('term_tree', [
        'ancestor_id' => $parent->id,
        'descendant_id' => $child->id,
        'depth' => 1,
    ]);
});

test('term tree implements closure table pattern', function () {
    $taxonomy = Taxonomy::factory()->create();
    
    // Создаем иерархию: A -> B -> C
    $termA = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);
    $termB = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);
    $termC = Term::factory()->create(['taxonomy_id' => $taxonomy->id]);

    // A -> B (depth = 1)
    TermTree::create(['ancestor_id' => $termA->id, 'descendant_id' => $termB->id, 'depth' => 1]);
    
    // B -> C (depth = 1)
    TermTree::create(['ancestor_id' => $termB->id, 'descendant_id' => $termC->id, 'depth' => 1]);
    
    // A -> C (depth = 2) - транзитивная связь
    TermTree::create(['ancestor_id' => $termA->id, 'descendant_id' => $termC->id, 'depth' => 2]);

    // Проверяем, что все связи созданы
    expect(TermTree::where('ancestor_id', $termA->id)->count())->toBe(2) // A -> B, A -> C
        ->and(TermTree::where('descendant_id', $termC->id)->count())->toBe(2); // A -> C, B -> C
});

