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

        // Создаём терм
        $createResponse = $this->postJsonAsAdmin('/api/v1/admin/taxonomies/topics/terms', [
            'name' => 'Analytics',
        ], $admin);

        $createResponse->assertStatus(201);
        $createResponse->assertJsonStructure([
            'data' => ['id', 'name', 'slug'],
        ]);

        $termId = $createResponse->json('data.id');

        // Привязываем терм к записи через массовый метод
        $attachResponse = $this->postJsonAsAdmin("/api/v1/admin/entries/{$entry->id}/terms/attach", [
            'term_ids' => [$termId],
        ], $admin);

        $attachResponse->assertStatus(200);
        $attachResponse->assertJsonStructure([
            'data' => [
                'entry_id',
                'terms',
                'terms_by_taxonomy',
            ],
        ]);

        $this->assertDatabaseHas('entry_term', [
            'entry_id' => $entry->id,
            'term_id' => $termId,
        ]);

        $entry->refresh();
        $this->assertCount(1, $entry->terms);
    }
}


