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
        $taxonomy = Taxonomy::factory()->create(['slug' => 'topics']);
        Term::factory()->count(2)->forTaxonomy($taxonomy)->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/taxonomies/topics/terms', $admin);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'taxonomy', 'name', 'slug'],
            ],
            'links',
            'meta',
        ]);
    }

    public function test_store_creates_term_with_auto_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $taxonomy = Taxonomy::factory()->create(['slug' => 'topics']);

        $response = $this->postJsonAsAdmin('/api/v1/admin/taxonomies/topics/terms', [
            'name' => 'Laravel',
        ], $admin);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Laravel');
        $slug = $response->json('data.slug');
        $this->assertNotEmpty($slug);
        $this->assertDatabaseHas('terms', ['taxonomy_id' => $taxonomy->id, 'slug' => $slug]);
    }

    public function test_store_respects_custom_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $taxonomy = Taxonomy::factory()->create(['slug' => 'topics']);

        $response = $this->postJsonAsAdmin('/api/v1/admin/taxonomies/topics/terms', [
            'name' => 'Laravel',
            'slug' => 'laravel',
        ], $admin);

        $response->assertStatus(201);
        $response->assertJsonPath('data.slug', 'laravel');
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
        $term = Term::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);

        $response = $this->putJsonAsAdmin("/api/v1/admin/terms/{$term->id}", [
            'name' => 'Laravel 11',
            'slug' => 'laravel-11',
        ], $admin);

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'laravel-11');
        $this->assertDatabaseHas('terms', ['id' => $term->id, 'slug' => 'laravel-11']);
    }

    public function test_destroy_requires_force_when_term_attached(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->withOptions(['taxonomies' => ['topics']])->create(['slug' => 'article']);
        $entry = Entry::factory()->forPostType($postType)->create();
        $taxonomy = Taxonomy::factory()->create(['slug' => 'topics']);
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
        $taxonomy = Taxonomy::factory()->create(['slug' => 'topics']);
        $term = Term::factory()->forTaxonomy($taxonomy)->create();

        $response = $this->postJsonAsAdmin('/api/v1/admin/taxonomies/topics/terms', ['name' => 'Test'], $editor);
        $response->assertStatus(403);

        $response = $this->deleteJsonAsAdmin("/api/v1/admin/terms/{$term->id}", [], $editor);
        $response->assertStatus(403);
    }
}


