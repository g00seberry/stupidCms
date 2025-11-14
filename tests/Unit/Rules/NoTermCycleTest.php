<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Models\Taxonomy;
use App\Models\Term;
use App\Models\TermTree;
use App\Rules\NoTermCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class NoTermCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_when_term_is_null(): void
    {
        $rule = new NoTermCycle(null);

        $validator = Validator::make(
            ['parent_id' => 1],
            ['parent_id' => [$rule]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_passes_when_parent_id_is_null(): void
    {
        $taxonomy = Taxonomy::factory()->create(['hierarchical' => true]);
        $term = Term::factory()->forTaxonomy($taxonomy)->create();

        $rule = new NoTermCycle($term);

        $validator = Validator::make(
            ['parent_id' => null],
            ['parent_id' => [$rule]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_passes_when_parent_is_not_descendant(): void
    {
        $taxonomy = Taxonomy::factory()->create(['hierarchical' => true]);
        $term = Term::factory()->forTaxonomy($taxonomy)->create();
        $parent = Term::factory()->forTaxonomy($taxonomy)->create();

        $rule = new NoTermCycle($term);

        $validator = Validator::make(
            ['parent_id' => $parent->id],
            ['parent_id' => [$rule]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_parent_is_direct_child(): void
    {
        $taxonomy = Taxonomy::factory()->create(['hierarchical' => true]);
        $term = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Parent']);
        $child = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Child']);

        // Создаём иерархию: term -> child
        TermTree::create([
            'ancestor_id' => $term->id,
            'descendant_id' => $term->id,
            'depth' => 0,
        ]);
        TermTree::create([
            'ancestor_id' => $child->id,
            'descendant_id' => $child->id,
            'depth' => 0,
        ]);
        TermTree::create([
            'ancestor_id' => $term->id,
            'descendant_id' => $child->id,
            'depth' => 1,
        ]);

        $rule = new NoTermCycle($term);

        $validator = Validator::make(
            ['parent_id' => $child->id],
            ['parent_id' => [$rule]]
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('циклическую зависимость', $validator->errors()->first('parent_id'));
    }

    public function test_fails_when_parent_is_grandchild(): void
    {
        $taxonomy = Taxonomy::factory()->create(['hierarchical' => true]);
        $grandparent = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Grandparent']);
        $parent = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Parent']);
        $child = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Child']);

        // Создаём иерархию: grandparent -> parent -> child
        TermTree::create([
            'ancestor_id' => $grandparent->id,
            'descendant_id' => $grandparent->id,
            'depth' => 0,
        ]);
        TermTree::create([
            'ancestor_id' => $parent->id,
            'descendant_id' => $parent->id,
            'depth' => 0,
        ]);
        TermTree::create([
            'ancestor_id' => $child->id,
            'descendant_id' => $child->id,
            'depth' => 0,
        ]);
        TermTree::create([
            'ancestor_id' => $grandparent->id,
            'descendant_id' => $parent->id,
            'depth' => 1,
        ]);
        TermTree::create([
            'ancestor_id' => $grandparent->id,
            'descendant_id' => $child->id,
            'depth' => 2,
        ]);
        TermTree::create([
            'ancestor_id' => $parent->id,
            'descendant_id' => $child->id,
            'depth' => 1,
        ]);

        $rule = new NoTermCycle($grandparent);

        $validator = Validator::make(
            ['parent_id' => $child->id],
            ['parent_id' => [$rule]]
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('циклическую зависимость', $validator->errors()->first('parent_id'));
    }

    public function test_passes_when_parent_is_not_descendant_even_with_other_children(): void
    {
        $taxonomy = Taxonomy::factory()->create(['hierarchical' => true]);
        $term = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Term']);
        $child1 = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Child 1']);
        $parent = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Parent']);

        // Создаём иерархию: term -> child1
        TermTree::create([
            'ancestor_id' => $term->id,
            'descendant_id' => $term->id,
            'depth' => 0,
        ]);
        TermTree::create([
            'ancestor_id' => $child1->id,
            'descendant_id' => $child1->id,
            'depth' => 0,
        ]);
        TermTree::create([
            'ancestor_id' => $term->id,
            'descendant_id' => $child1->id,
            'depth' => 1,
        ]);

        $rule = new NoTermCycle($term);

        $validator = Validator::make(
            ['parent_id' => $parent->id],
            ['parent_id' => [$rule]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_passes_when_self_reference_exists_but_depth_is_zero(): void
    {
        $taxonomy = Taxonomy::factory()->create(['hierarchical' => true]);
        $term = Term::factory()->forTaxonomy($taxonomy)->create();

        // Создаём только само-ссылку (depth = 0)
        TermTree::create([
            'ancestor_id' => $term->id,
            'descendant_id' => $term->id,
            'depth' => 0,
        ]);

        $rule = new NoTermCycle($term);

        $validator = Validator::make(
            ['parent_id' => $term->id],
            ['parent_id' => [$rule]]
        );

        // Само-ссылка не считается циклом (depth = 0), но это должно быть отфильтровано на уровне exists правила
        // Здесь проверяем, что правило не падает на само-ссылке
        $this->assertTrue($validator->passes());
    }
}

