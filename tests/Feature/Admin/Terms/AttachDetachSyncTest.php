<?php

namespace Tests\Feature\Admin\Terms;

use App\Models\Entry;
use App\Models\PostType;
use App\Models\Taxonomy;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachDetachSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_attach_detach_and_sync_terms_for_entry(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $topics = Taxonomy::factory()->create(['slug' => 'topics']);
        $tags = Taxonomy::factory()->create(['slug' => 'tags']);
        $postType = PostType::factory()->withOptions(['taxonomies' => ['topics', 'tags']])->create();
        $entry = Entry::factory()->forPostType($postType)->create();
        $topicTerm = Term::factory()->forTaxonomy($topics)->create();
        $tagTerm = Term::factory()->forTaxonomy($tags)->create();

        $response = $this->postJsonAsAdmin("/api/v1/admin/entries/{$entry->id}/terms/attach", [
            'term_ids' => [$topicTerm->id, $tagTerm->id],
        ], $admin);

        $response->assertOk();
        $response->assertJsonPath('data.entry_id', $entry->id);
        $this->assertDatabaseHas('entry_term', ['entry_id' => $entry->id, 'term_id' => $topicTerm->id]);
        $this->assertDatabaseHas('entry_term', ['entry_id' => $entry->id, 'term_id' => $tagTerm->id]);

        $response = $this->postJsonAsAdmin("/api/v1/admin/entries/{$entry->id}/terms/detach", [
            'term_ids' => [$tagTerm->id],
        ], $admin);

        $response->assertOk();
        $this->assertDatabaseMissing('entry_term', ['entry_id' => $entry->id, 'term_id' => $tagTerm->id]);

        $anotherTopicTerm = Term::factory()->forTaxonomy($topics)->create();

        $response = $this->putJsonAsAdmin("/api/v1/admin/entries/{$entry->id}/terms/sync", [
            'term_ids' => [$anotherTopicTerm->id],
        ], $admin);

        $response->assertOk();
        $this->assertDatabaseMissing('entry_term', ['entry_id' => $entry->id, 'term_id' => $topicTerm->id]);
        $this->assertDatabaseHas('entry_term', ['entry_id' => $entry->id, 'term_id' => $anotherTopicTerm->id]);
    }

    public function test_attach_rejects_terms_from_forbidden_taxonomy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $allowed = Taxonomy::factory()->create(['slug' => 'topics']);
        $forbidden = Taxonomy::factory()->create(['slug' => 'regions']);
        $postType = PostType::factory()->withOptions(['taxonomies' => ['topics']])->create();
        $entry = Entry::factory()->forPostType($postType)->create();
        $allowedTerm = Term::factory()->forTaxonomy($allowed)->create();
        $forbiddenTerm = Term::factory()->forTaxonomy($forbidden)->create();

        $response = $this->postJsonAsAdmin("/api/v1/admin/entries/{$entry->id}/terms/attach", [
            'term_ids' => [$allowedTerm->id, $forbiddenTerm->id],
        ], $admin);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.term_ids.0', "Taxonomy 'regions' is not allowed for the entry post type.");
        $this->assertDatabaseMissing('entry_term', ['entry_id' => $entry->id, 'term_id' => $allowedTerm->id]);
    }
}


