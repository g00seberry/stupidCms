<?php

namespace Tests\Feature\Admin\Terms;

use App\Models\Entry;
use App\Models\PostType;
use App\Models\Taxonomy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachPivotTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_can_create_term_and_attach_to_entry(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $taxonomy = Taxonomy::factory()->create(['slug' => 'topics']);
        $postType = PostType::factory()->withOptions(['taxonomies' => ['topics']])->create();
        $entry = Entry::factory()->forPostType($postType)->create();

        $response = $this->postJsonAsAdmin('/api/v1/admin/taxonomies/topics/terms', [
            'name' => 'Analytics',
            'attach_entry_id' => $entry->id,
        ], $admin);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'slug'],
            'entry_terms' => [
                'entry_id',
                'terms',
                'terms_by_taxonomy',
            ],
        ]);

        $termId = $response->json('data.id');
        $this->assertDatabaseHas('entry_term', [
            'entry_id' => $entry->id,
            'term_id' => $termId,
        ]);

        $entry->refresh();
        $this->assertCount(1, $entry->terms);
    }
}


