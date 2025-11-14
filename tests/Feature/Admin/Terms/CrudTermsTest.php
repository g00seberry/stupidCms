<?php

namespace Tests\Feature\Admin\Terms;

use App\Models\Entry;
use App\Models\PostType;
use App\Models\Taxonomy;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudTermsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_terms_for_taxonomy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $taxonomy = Taxonomy::factory()->create();
        Term::factory()->count(2)->forTaxonomy($taxonomy)->create();

        $response = $this->getJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy->id}/terms", $admin);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'taxonomy', 'name'],
            ],
            'links',
            'meta',
        ]);
    }

    public function test_store_creates_term(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $taxonomy = Taxonomy::factory()->create();

        $response = $this->postJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy->id}/terms", [
            'name' => 'Laravel',
        ], $admin);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Laravel');
        $this->assertDatabaseHas('terms', ['taxonomy_id' => $taxonomy->id, 'name' => 'Laravel']);
    }

    public function test_show_returns_term_details(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $term = Term::factory()->create();

        $response = $this->getJsonAsAdmin("/api/v1/admin/terms/{$term->id}", $admin);

        $response->assertOk();
        $response->assertJsonPath('data.id', $term->id);
    }

    public function test_update_changes_term_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $term = Term::factory()->create(['name' => 'Laravel']);

        $response = $this->putJsonAsAdmin("/api/v1/admin/terms/{$term->id}", [
            'name' => 'Laravel 11',
        ], $admin);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Laravel 11');
        $this->assertDatabaseHas('terms', ['id' => $term->id, 'name' => 'Laravel 11']);
    }

    public function test_destroy_requires_force_when_term_attached(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $taxonomy = Taxonomy::factory()->create();
        $postType = PostType::factory()->withOptions(['taxonomies' => [$taxonomy->id]])->create(['slug' => 'article']);
        $entry = Entry::factory()->forPostType($postType)->create();
        $term = Term::factory()->forTaxonomy($taxonomy)->create();
        $entry->terms()->attach($term->id);

        $response = $this->deleteJsonAsAdmin("/api/v1/admin/terms/{$term->id}", [], $admin);

        $response->assertStatus(409);
        $this->assertDatabaseHas('terms', ['id' => $term->id]);
    }

    public function test_destroy_with_force_detach_soft_deletes_term(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $entry = Entry::factory()->create();
        $term = Term::factory()->create();
        $entry->terms()->attach($term->id);

        $response = $this->deleteJsonAsAdmin("/api/v1/admin/terms/{$term->id}?forceDetach=1", [], $admin);

        $response->assertStatus(204);
        $this->assertSoftDeleted('terms', ['id' => $term->id]);
        $this->assertDatabaseMissing('entry_term', ['term_id' => $term->id]);
    }

    public function test_operations_require_manage_terms_permission(): void
    {
        $editor = User::factory()->create(['is_admin' => false]);
        $taxonomy = Taxonomy::factory()->create();
        $term = Term::factory()->forTaxonomy($taxonomy)->create();

        $response = $this->postJsonAsAdmin("/api/v1/admin/taxonomies/{$taxonomy->id}/terms", ['name' => 'Test'], $editor);
        $response->assertStatus(403);

        $response = $this->deleteJsonAsAdmin("/api/v1/admin/terms/{$term->id}", [], $editor);
        $response->assertStatus(403);
    }
}


