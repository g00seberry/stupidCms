<?php

namespace Tests\Feature\Admin\Taxonomies;

use App\Models\Taxonomy;
use App\Models\Term;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use Tests\Support\FeatureTestCase;

class CrudTaxonomiesTest extends FeatureTestCase
{
    public function test_index_returns_paginated_taxonomies(): void
    {
        $admin = $this->admin();
        Taxonomy::factory()->count(3)->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/taxonomies', $admin);

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'label', 'hierarchical', 'options_json'],
            ],
            'links',
            'meta',
        ]);
    }

    public function test_store_creates_taxonomy(): void
    {
        $admin = $this->admin();

        $payload = [
            'label' => 'News Categories',
            'hierarchical' => true,
            'options_json' => ['color' => 'blue'],
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/taxonomies', $payload, $admin);

        $response->assertStatus(201);
        $response->assertJsonPath('data.label', 'News Categories');
        $id = $response->json('data.id');
        $this->assertNotEmpty($id);
        $this->assertDatabaseHas('taxonomies', ['id' => $id, 'name' => 'News Categories']);
    }

    public function test_show_returns_taxonomy_details(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create(['label' => 'Topics']);

        $response = $this->getJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy->id}", $admin);

        $response->assertOk();
        $response->assertJsonPath('data.id', $taxonomy->id);
        $response->assertJsonPath('data.label', 'Topics');
    }

    public function test_show_returns_404_for_missing_taxonomy(): void
    {
        $admin = $this->admin();

        $response = $this->getJsonAsAdmin('/api/v1/admin/taxonomies/99999', $admin);

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertErrorResponse($response, ErrorCode::NOT_FOUND, [
            'detail' => 'Taxonomy with ID 99999 does not exist.',
            'meta.taxonomy_id' => 99999,
        ]);
    }

    public function test_update_modifies_taxonomy_fields(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create(['label' => 'Topics']);

        $response = $this->putJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy->id}", [
            'label' => 'Updated Topics',
            'hierarchical' => false,
        ], $admin);

        $response->assertOk();
        $response->assertJsonPath('data.id', $taxonomy->id);
        $response->assertJsonPath('data.label', 'Updated Topics');
        $this->assertDatabaseHas('taxonomies', ['id' => $taxonomy->id, 'name' => 'Updated Topics']);
    }

    public function test_options_json_returns_object_not_array(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create([
            'label' => 'Tags',
            'options_json' => [],
        ]);

        $response = $this->getJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy->id}", $admin);

        $response->assertOk();
        $response->assertJsonPath('data.id', $taxonomy->id);
        $decoded = json_decode($response->getContent());
        $this->assertInstanceOf(\stdClass::class, $decoded->data->options_json);
        $this->assertSame([], (array) $decoded->data->options_json);
    }

    public function test_destroy_blocks_when_terms_exist_without_force(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create();
        Term::factory()->forTaxonomy($taxonomy)->create();

        $response = $this->deleteJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy->id}", [], $admin);

        $response->assertStatus(409);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertErrorResponse($response, ErrorCode::CONFLICT, [
            'detail' => 'Cannot delete taxonomy while terms exist. Use force=1 to cascade delete.',
            'meta.terms_count' => 1,
        ]);
        $this->assertDatabaseHas('taxonomies', ['id' => $taxonomy->id]);
    }

    public function test_destroy_with_force_removes_taxonomy_and_terms(): void
    {
        $admin = $this->admin();
        $taxonomy = Taxonomy::factory()->create();
        $term = Term::factory()->forTaxonomy($taxonomy)->create();

        $response = $this->deleteJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy->id}?force=1", [], $admin);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('taxonomies', ['id' => $taxonomy->id]);
        $this->assertDatabaseMissing('terms', ['id' => $term->id]);
    }

    public function test_operations_require_manage_taxonomies_permission(): void
    {
        $editor = User::factory()->create(['is_admin' => false]);
        $taxonomy = Taxonomy::factory()->create();

        $response = $this->postJsonAsAdmin('/api/v1/admin/taxonomies', ['label' => 'Test'], $editor);
        $response->assertStatus(403);

        $response = $this->deleteJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy->id}", [], $editor);
        $response->assertStatus(403);
    }
}


