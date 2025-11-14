<?php

namespace Tests\Feature\Admin\Taxonomies;

use App\Models\Taxonomy;
use App\Models\Term;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudTaxonomiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_taxonomies(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Taxonomy::factory()->count(3)->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/taxonomies', $admin);

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'slug', 'label', 'hierarchical', 'options_json'],
            ],
            'links',
            'meta',
        ]);
    }

    public function test_store_creates_taxonomy_with_auto_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $payload = [
            'label' => 'News Categories',
            'hierarchical' => true,
            'options_json' => ['color' => 'blue'],
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/taxonomies', $payload, $admin);

        $response->assertStatus(201);
        $response->assertJsonPath('data.label', 'News Categories');
        $id = $response->json('data.id');
        $slug = $response->json('data.slug');
        $this->assertNotEmpty($id);
        $this->assertNotEmpty($slug);
        $this->assertDatabaseHas('taxonomies', ['id' => $id, 'slug' => $slug, 'name' => 'News Categories']);
    }

    public function test_store_respects_custom_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->postJsonAsAdmin('/api/v1/admin/taxonomies', [
            'label' => 'Regions',
            'slug' => 'regions',
        ], $admin);

        $response->assertStatus(201);
        $response->assertJsonPath('data.slug', 'regions');
        $id = $response->json('data.id');
        $this->assertNotEmpty($id);
        $this->assertDatabaseHas('taxonomies', ['id' => $id, 'slug' => 'regions']);
    }

    public function test_show_returns_taxonomy_details(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $taxonomy = Taxonomy::factory()->create(['slug' => 'topics', 'label' => 'Topics']);

        $response = $this->getJsonAsAdmin('/api/v1/admin/taxonomies/topics', $admin);

        $response->assertOk();
        $response->assertJsonPath('data.id', $taxonomy->id);
        $response->assertJsonPath('data.label', 'Topics');
    }

    public function test_show_returns_404_for_missing_taxonomy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->getJsonAsAdmin('/api/v1/admin/taxonomies/missing', $admin);

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertErrorResponse($response, ErrorCode::NOT_FOUND, [
            'detail' => 'Taxonomy with slug missing does not exist.',
            'meta.slug' => 'missing',
        ]);
    }

    public function test_update_modifies_taxonomy_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $taxonomy = Taxonomy::factory()->create(['slug' => 'topics', 'label' => 'Topics']);

        $response = $this->putJsonAsAdmin('/api/v1/admin/taxonomies/topics', [
            'label' => 'Updated Topics',
            'slug' => 'updated-topics',
            'hierarchical' => false,
        ], $admin);

        $response->assertOk();
        $response->assertJsonPath('data.id', $taxonomy->id);
        $response->assertJsonPath('data.slug', 'updated-topics');
        $this->assertDatabaseHas('taxonomies', ['id' => $taxonomy->id, 'slug' => 'updated-topics', 'name' => 'Updated Topics']);
    }

    public function test_update_allows_same_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $taxonomy = Taxonomy::factory()->create(['slug' => 'tags', 'label' => 'Tags']);

        $response = $this->putJsonAsAdmin('/api/v1/admin/taxonomies/tags', [
            'label' => 'Tags3',
            'slug' => 'tags',
            'hierarchical' => true,
            'options_json' => [],
        ], $admin);

        $response->assertOk();
        $response->assertJsonPath('data.id', $taxonomy->id);
        $response->assertJsonPath('data.slug', 'tags');
        $response->assertJsonPath('data.label', 'Tags3');
        $this->assertDatabaseHas('taxonomies', ['id' => $taxonomy->id, 'slug' => 'tags', 'name' => 'Tags3']);
    }

    public function test_options_json_returns_object_not_array(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $taxonomy = Taxonomy::factory()->create([
            'slug' => 'tags',
            'label' => 'Tags',
            'options_json' => [],
        ]);

        $response = $this->getJsonAsAdmin('/api/v1/admin/taxonomies/tags', $admin);

        $response->assertOk();
        $response->assertJsonPath('data.id', $taxonomy->id);
        $decoded = json_decode($response->getContent());
        $this->assertInstanceOf(\stdClass::class, $decoded->data->options_json);
        $this->assertSame([], (array) $decoded->data->options_json);
    }

    public function test_destroy_blocks_when_terms_exist_without_force(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $taxonomy = Taxonomy::factory()->create(['slug' => 'topics']);
        Term::factory()->forTaxonomy($taxonomy)->create();

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/taxonomies/topics', [], $admin);

        $response->assertStatus(409);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertErrorResponse($response, ErrorCode::CONFLICT, [
            'detail' => 'Cannot delete taxonomy while terms exist. Use force=1 to cascade delete.',
            'meta.terms_count' => 1,
        ]);
        $this->assertDatabaseHas('taxonomies', ['slug' => 'topics']);
    }

    public function test_destroy_with_force_removes_taxonomy_and_terms(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $taxonomy = Taxonomy::factory()->create(['slug' => 'topics']);
        $term = Term::factory()->forTaxonomy($taxonomy)->create();

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/taxonomies/topics?force=1', [], $admin);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('taxonomies', ['slug' => 'topics']);
        $this->assertDatabaseMissing('terms', ['id' => $term->id]);
    }

    public function test_operations_require_manage_taxonomies_permission(): void
    {
        $editor = User::factory()->create(['is_admin' => false]);
        $taxonomy = Taxonomy::factory()->create(['slug' => 'topics']);

        $response = $this->postJsonAsAdmin('/api/v1/admin/taxonomies', ['label' => 'Test'], $editor);
        $response->assertStatus(403);

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/taxonomies/topics', [], $editor);
        $response->assertStatus(403);
    }
}


