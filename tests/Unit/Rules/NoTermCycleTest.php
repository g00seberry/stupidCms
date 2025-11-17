<?php

declare(strict_types=1);

use App\Models\Taxonomy;
use App\Models\Term;
use App\Models\TermTree;
use App\Rules\NoTermCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->taxonomy = Taxonomy::factory()->create();
});

test('passes for non-cyclic hierarchy', function () {
    $parent = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    $child = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    
    // Set up hierarchy: parent -> child
    TermTree::create(['ancestor_id' => $parent->id, 'descendant_id' => $parent->id, 'depth' => 0]);
    TermTree::create(['ancestor_id' => $child->id, 'descendant_id' => $child->id, 'depth' => 0]);
    TermTree::create(['ancestor_id' => $parent->id, 'descendant_id' => $child->id, 'depth' => 1]);

    $grandchild = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    TermTree::create(['ancestor_id' => $grandchild->id, 'descendant_id' => $grandchild->id, 'depth' => 0]);

    $rule = new NoTermCycle($grandchild);
    
    $validator = Validator::make(
        ['parent_id' => $child->id],
        ['parent_id' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('fails for direct cycle', function () {
    $parent = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    $child = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    
    // Set up hierarchy: parent -> child
    TermTree::create(['ancestor_id' => $parent->id, 'descendant_id' => $parent->id, 'depth' => 0]);
    TermTree::create(['ancestor_id' => $child->id, 'descendant_id' => $child->id, 'depth' => 0]);
    TermTree::create(['ancestor_id' => $parent->id, 'descendant_id' => $child->id, 'depth' => 1]);

    // Try to set child as parent of parent (would create cycle: parent -> child -> parent)
    $rule = new NoTermCycle($parent);
    
    $validator = Validator::make(
        ['parent_id' => $child->id],
        ['parent_id' => [$rule]]
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('parent_id'))->toContain('циклическую зависимость');
});

test('fails for indirect cycle', function () {
    $root = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    $middle = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    $leaf = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    
    // Set up hierarchy: root -> middle -> leaf
    TermTree::create(['ancestor_id' => $root->id, 'descendant_id' => $root->id, 'depth' => 0]);
    TermTree::create(['ancestor_id' => $middle->id, 'descendant_id' => $middle->id, 'depth' => 0]);
    TermTree::create(['ancestor_id' => $leaf->id, 'descendant_id' => $leaf->id, 'depth' => 0]);
    
    TermTree::create(['ancestor_id' => $root->id, 'descendant_id' => $middle->id, 'depth' => 1]);
    TermTree::create(['ancestor_id' => $middle->id, 'descendant_id' => $leaf->id, 'depth' => 1]);
    TermTree::create(['ancestor_id' => $root->id, 'descendant_id' => $leaf->id, 'depth' => 2]);

    // Try to set leaf as parent of root (would create cycle: root -> middle -> leaf -> root)
    $rule = new NoTermCycle($root);
    
    $validator = Validator::make(
        ['parent_id' => $leaf->id],
        ['parent_id' => [$rule]]
    );

    expect($validator->fails())->toBeTrue();
});

test('passes when term is null (new term creation)', function () {
    $parent = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    TermTree::create(['ancestor_id' => $parent->id, 'descendant_id' => $parent->id, 'depth' => 0]);

    $rule = new NoTermCycle(null);
    
    $validator = Validator::make(
        ['parent_id' => $parent->id],
        ['parent_id' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes when parent_id is null', function () {
    $term = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    TermTree::create(['ancestor_id' => $term->id, 'descendant_id' => $term->id, 'depth' => 0]);

    $rule = new NoTermCycle($term);
    
    $validator = Validator::make(
        ['parent_id' => null],
        ['parent_id' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

test('passes for setting self as parent (though unrealistic)', function () {
    $term = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    TermTree::create(['ancestor_id' => $term->id, 'descendant_id' => $term->id, 'depth' => 0]);

    // Self-reference (depth = 0) is excluded from cycle check
    $rule = new NoTermCycle($term);
    
    $validator = Validator::make(
        ['parent_id' => $term->id],
        ['parent_id' => [$rule]]
    );

    // Should pass because depth > 0 check excludes self-reference
    expect($validator->passes())->toBeTrue();
});

test('handles non-numeric parent_id', function () {
    $term = Term::factory()->create(['taxonomy_id' => $this->taxonomy->id]);
    TermTree::create(['ancestor_id' => $term->id, 'descendant_id' => $term->id, 'depth' => 0]);

    $rule = new NoTermCycle($term);
    
    $validator = Validator::make(
        ['parent_id' => 'not-a-number'],
        ['parent_id' => [$rule]]
    );

    expect($validator->passes())->toBeTrue();
});

