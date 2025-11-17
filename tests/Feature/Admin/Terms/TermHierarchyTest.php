<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Terms;

use App\Models\Taxonomy;
use App\Models\Term;
use App\Models\TermTree;
use App\Models\User;
use Tests\Support\FeatureTestCase;

class TermHierarchyTest extends FeatureTestCase
{
    public function test_store_creates_term_with_parent_id(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => true,
        ]);
        $parent = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Parent']);

        $response = $this->postJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy->id}/terms", [
            'name' => 'Child',
            'parent_id' => $parent->id,
        ], $admin);

        $response->assertStatus(201);
        $response->assertJsonPath('data.parent_id', $parent->id);
        
        $child = Term::find($response->json('data.id'));
        $this->assertNotNull($child);
        
        // Проверяем Closure Table
        $this->assertDatabaseHas('term_tree', [
            'ancestor_id' => $parent->id,
            'descendant_id' => $child->id,
            'depth' => 1,
        ]);
        
        // Проверяем само-ссылку
        $this->assertDatabaseHas('term_tree', [
            'ancestor_id' => $child->id,
            'descendant_id' => $child->id,
            'depth' => 0,
        ]);
    }

    public function test_store_creates_root_term_without_parent(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => true,
        ]);

        $response = $this->postJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy->id}/terms", [
            'name' => 'Root',
        ], $admin);

        $response->assertStatus(201);
        $response->assertJsonPath('data.parent_id', null);
        
        $term = Term::find($response->json('data.id'));
        $this->assertNotNull($term);
        
        // Проверяем только само-ссылку
        $this->assertDatabaseHas('term_tree', [
            'ancestor_id' => $term->id,
            'descendant_id' => $term->id,
            'depth' => 0,
        ]);
        
        // Не должно быть других связей
        $this->assertEquals(1, TermTree::where('descendant_id', $term->id)->count());
    }

    public function test_store_rejects_parent_from_different_taxonomy(): void
    {
        $admin = $this->admin();
        $taxonomy1 = Taxonomy::factory()->create(['hierarchical' => true]);
        $taxonomy2 = Taxonomy::factory()->create(['hierarchical' => true]);
        $parent = Term::factory()->forTaxonomy($taxonomy2)->create();

        $response = $this->postJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy1->id}/terms", [
            'name' => 'Child',
            'parent_id' => $parent->id,
        ], $admin);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('terms', ['name' => 'Child']);
    }

    public function test_store_rejects_parent_for_non_hierarchical_taxonomy(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => false,
        ]);
        $parent = Term::factory()->forTaxonomy($taxonomy)->create();

        $response = $this->postJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy->id}/terms", [
            'name' => 'Child',
            'parent_id' => $parent->id,
        ], $admin);

        // parent_id игнорируется для неиерархических таксономий
        $response->assertStatus(201);
        $term = Term::find($response->json('data.id'));
        $this->assertNull($term->parent_id);
    }

    public function test_update_changes_parent_id(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => true,
        ]);
        $oldParent = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Old Parent']);
        $newParent = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'New Parent']);
        $term = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Child']);
        
        // Устанавливаем начального родителя
        \DB::table('term_tree')->insert([
            'ancestor_id' => $oldParent->id,
            'descendant_id' => $term->id,
            'depth' => 1,
        ]);
        \DB::table('term_tree')->insert([
            'ancestor_id' => $term->id,
            'descendant_id' => $term->id,
            'depth' => 0,
        ]);

        $response = $this->putJsonAsAdmin("/api/v1/admin/terms/{$term->id}", [
            'parent_id' => $newParent->id,
        ], $admin);

        $response->assertOk();
        $response->assertJsonPath('data.parent_id', $newParent->id);
        
        // Старая связь удалена
        $this->assertDatabaseMissing('term_tree', [
            'ancestor_id' => $oldParent->id,
            'descendant_id' => $term->id,
        ]);
        
        // Новая связь создана
        $this->assertDatabaseHas('term_tree', [
            'ancestor_id' => $newParent->id,
            'descendant_id' => $term->id,
            'depth' => 1,
        ]);
    }

    public function test_update_rejects_self_as_parent(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => true,
        ]);
        $term = Term::factory()->forTaxonomy($taxonomy)->create();

        $response = $this->putJsonAsAdmin("/api/v1/admin/terms/{$term->id}", [
            'parent_id' => $term->id,
        ], $admin);

        $response->assertStatus(422);
    }

    public function test_update_rejects_cycle_creation(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => true,
        ]);
        $parent = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Parent']);
        $child = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Child']);
        
        // Устанавливаем child как потомка parent
        \DB::table('term_tree')->insert([
            'ancestor_id' => $parent->id,
            'descendant_id' => $child->id,
            'depth' => 1,
        ]);
        \DB::table('term_tree')->insert([
            'ancestor_id' => $parent->id,
            'descendant_id' => $parent->id,
            'depth' => 0,
        ]);
        \DB::table('term_tree')->insert([
            'ancestor_id' => $child->id,
            'descendant_id' => $child->id,
            'depth' => 0,
        ]);

        // Пытаемся сделать parent потомком child (цикл)
        $response = $this->putJsonAsAdmin("/api/v1/admin/terms/{$parent->id}", [
            'parent_id' => $child->id,
        ], $admin);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['parent_id']);
    }

    public function test_tree_returns_hierarchical_structure(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => true,
        ]);
        $root = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Root']);
        $child = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Child']);
        
        // Создаём иерархию в Closure Table
        \DB::table('term_tree')->insert([
            ['ancestor_id' => $root->id, 'descendant_id' => $root->id, 'depth' => 0],
            ['ancestor_id' => $child->id, 'descendant_id' => $child->id, 'depth' => 0],
            ['ancestor_id' => $root->id, 'descendant_id' => $child->id, 'depth' => 1],
        ]);

        $response = $this->getJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy->id}/terms/tree", $admin);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($root->id, $data[0]['id']);
        $this->assertNull($data[0]['parent_id']);
        $this->assertCount(1, $data[0]['children']);
        $this->assertEquals($child->id, $data[0]['children'][0]['id']);
        $this->assertEquals($root->id, $data[0]['children'][0]['parent_id']);
    }

    public function test_tree_returns_flat_list_for_non_hierarchical_taxonomy(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => false,
        ]);
        Term::factory()->count(2)->forTaxonomy($taxonomy)->create();

        $response = $this->getJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy->id}/terms/tree", $admin);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        // Для неиерархических таксономий не должно быть parent_id и children
        foreach ($data as $term) {
            $this->assertArrayNotHasKey('parent_id', $term);
            $this->assertArrayNotHasKey('children', $term);
        }
    }

    public function test_show_returns_parent_id_for_hierarchical_taxonomy(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => true,
        ]);
        $parent = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Parent']);
        $child = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Child']);
        
        // Создаём связь
        \DB::table('term_tree')->insert([
            ['ancestor_id' => $parent->id, 'descendant_id' => $parent->id, 'depth' => 0],
            ['ancestor_id' => $child->id, 'descendant_id' => $child->id, 'depth' => 0],
            ['ancestor_id' => $parent->id, 'descendant_id' => $child->id, 'depth' => 1],
        ]);

        $response = $this->getJsonAsAdmin("/api/v1/admin/terms/{$child->id}", $admin);

        $response->assertOk();
        $response->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_multilevel_hierarchy_creates_all_ancestor_links(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => true,
        ]);
        $grandparent = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Grandparent']);
        $parent = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Parent']);
        $child = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Child']);
        
        // Создаём иерархию: grandparent -> parent
        \DB::table('term_tree')->insert([
            ['ancestor_id' => $grandparent->id, 'descendant_id' => $grandparent->id, 'depth' => 0],
            ['ancestor_id' => $parent->id, 'descendant_id' => $parent->id, 'depth' => 0],
            ['ancestor_id' => $grandparent->id, 'descendant_id' => $parent->id, 'depth' => 1],
        ]);

        // Добавляем child к parent
        $response = $this->postJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy->id}/terms", [
            'name' => 'Child',
            'parent_id' => $parent->id,
        ], $admin);

        $response->assertStatus(201);
        $childId = $response->json('data.id');
        
        // Проверяем, что созданы все связи предков
        $this->assertDatabaseHas('term_tree', [
            'ancestor_id' => $parent->id,
            'descendant_id' => $childId,
            'depth' => 1,
        ]);
        
        // grandparent тоже должен быть предком child
        $this->assertDatabaseHas('term_tree', [
            'ancestor_id' => $grandparent->id,
            'descendant_id' => $childId,
            'depth' => 2,
        ]);
    }

    public function test_update_preserves_children_when_moving_term(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => true,
        ]);
        $oldParent = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Old Parent']);
        $newParent = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'New Parent']);
        $term = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Term']);
        $child = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Child']);
        
        // Создаём иерархию: oldParent -> term -> child
        \DB::table('term_tree')->insert([
            ['ancestor_id' => $oldParent->id, 'descendant_id' => $oldParent->id, 'depth' => 0],
            ['ancestor_id' => $term->id, 'descendant_id' => $term->id, 'depth' => 0],
            ['ancestor_id' => $child->id, 'descendant_id' => $child->id, 'depth' => 0],
            ['ancestor_id' => $oldParent->id, 'descendant_id' => $term->id, 'depth' => 1],
            ['ancestor_id' => $oldParent->id, 'descendant_id' => $child->id, 'depth' => 2],
            ['ancestor_id' => $term->id, 'descendant_id' => $child->id, 'depth' => 1],
        ]);

        // Переносим term под newParent
        $response = $this->putJsonAsAdmin("/api/v1/admin/terms/{$term->id}", [
            'parent_id' => $newParent->id,
        ], $admin);

        $response->assertOk();
        
        // Проверяем, что term теперь под newParent
        $this->assertDatabaseHas('term_tree', [
            'ancestor_id' => $newParent->id,
            'descendant_id' => $term->id,
            'depth' => 1,
        ]);
        
        // Проверяем, что child остался под term
        $this->assertDatabaseHas('term_tree', [
            'ancestor_id' => $term->id,
            'descendant_id' => $child->id,
            'depth' => 1,
        ]);
        
        // Проверяем, что child теперь под newParent (через term)
        $this->assertDatabaseHas('term_tree', [
            'ancestor_id' => $newParent->id,
            'descendant_id' => $child->id,
            'depth' => 2,
        ]);
        
        // Проверяем, что старые связи удалены
        $this->assertDatabaseMissing('term_tree', [
            'ancestor_id' => $oldParent->id,
            'descendant_id' => $term->id,
        ]);
        $this->assertDatabaseMissing('term_tree', [
            'ancestor_id' => $oldParent->id,
            'descendant_id' => $child->id,
        ]);
    }

    public function test_update_preserves_multilevel_children_when_moving_term(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create([
            'hierarchical' => true,
        ]);
        $oldParent = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Old Parent']);
        $newParent = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'New Parent']);
        $term = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Term']);
        $child = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Child']);
        $grandchild = Term::factory()->forTaxonomy($taxonomy)->create(['name' => 'Grandchild']);
        
        // Создаём иерархию: oldParent -> term -> child -> grandchild
        \DB::table('term_tree')->insert([
            ['ancestor_id' => $oldParent->id, 'descendant_id' => $oldParent->id, 'depth' => 0],
            ['ancestor_id' => $term->id, 'descendant_id' => $term->id, 'depth' => 0],
            ['ancestor_id' => $child->id, 'descendant_id' => $child->id, 'depth' => 0],
            ['ancestor_id' => $grandchild->id, 'descendant_id' => $grandchild->id, 'depth' => 0],
            ['ancestor_id' => $oldParent->id, 'descendant_id' => $term->id, 'depth' => 1],
            ['ancestor_id' => $oldParent->id, 'descendant_id' => $child->id, 'depth' => 2],
            ['ancestor_id' => $oldParent->id, 'descendant_id' => $grandchild->id, 'depth' => 3],
            ['ancestor_id' => $term->id, 'descendant_id' => $child->id, 'depth' => 1],
            ['ancestor_id' => $term->id, 'descendant_id' => $grandchild->id, 'depth' => 2],
            ['ancestor_id' => $child->id, 'descendant_id' => $grandchild->id, 'depth' => 1],
        ]);

        // Переносим term под newParent
        $response = $this->putJsonAsAdmin("/api/v1/admin/terms/{$term->id}", [
            'parent_id' => $newParent->id,
        ], $admin);

        $response->assertOk();
        
        // Проверяем, что term теперь под newParent
        $this->assertDatabaseHas('term_tree', [
            'ancestor_id' => $newParent->id,
            'descendant_id' => $term->id,
            'depth' => 1,
        ]);
        
        // Проверяем, что child остался под term
        $this->assertDatabaseHas('term_tree', [
            'ancestor_id' => $term->id,
            'descendant_id' => $child->id,
            'depth' => 1,
        ]);
        
        // Проверяем, что grandchild остался под child
        $this->assertDatabaseHas('term_tree', [
            'ancestor_id' => $child->id,
            'descendant_id' => $grandchild->id,
            'depth' => 1,
        ]);
        
        // Проверяем, что child теперь под newParent (через term)
        $this->assertDatabaseHas('term_tree', [
            'ancestor_id' => $newParent->id,
            'descendant_id' => $child->id,
            'depth' => 2,
        ]);
        
        // Проверяем, что grandchild теперь под newParent (через term и child)
        $this->assertDatabaseHas('term_tree', [
            'ancestor_id' => $newParent->id,
            'descendant_id' => $grandchild->id,
            'depth' => 3,
        ]);
        
        // Проверяем, что grandchild остался под term
        $this->assertDatabaseHas('term_tree', [
            'ancestor_id' => $term->id,
            'descendant_id' => $grandchild->id,
            'depth' => 2,
        ]);
        
        // Проверяем, что старые связи удалены
        $this->assertDatabaseMissing('term_tree', [
            'ancestor_id' => $oldParent->id,
            'descendant_id' => $term->id,
        ]);
        $this->assertDatabaseMissing('term_tree', [
            'ancestor_id' => $oldParent->id,
            'descendant_id' => $child->id,
        ]);
        $this->assertDatabaseMissing('term_tree', [
            'ancestor_id' => $oldParent->id,
            'descendant_id' => $grandchild->id,
        ]);
    }
}

