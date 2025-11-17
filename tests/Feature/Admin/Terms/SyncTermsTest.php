<?php

namespace Tests\Feature\Admin\Terms;

use App\Models\Entry;
use App\Models\PostType;
use App\Models\Taxonomy;
use App\Models\Term;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use Tests\Support\FeatureTestCase;

class SyncTermsTest extends FeatureTestCase
{
    public function test_sync_terms_for_entry(): void
    {
        $admin = $this->admin();
        $topics = Taxonomy::factory()->create();
        $tags = Taxonomy::factory()->create();
        $postType = PostType::factory()->withOptions(['taxonomies' => [$topics->id, $tags->id]])->create();
        $entry = Entry::factory()->forPostType($postType)->create();
        $topicTerm = Term::factory()->forTaxonomy($topics)->create();
        $tagTerm = Term::factory()->forTaxonomy($tags)->create();

        // Синхронизация: добавляем термы
        $response = $this->putJsonAsAdmin("/api/v1/admin/entries/{$entry->id}/terms/sync", [
            'term_ids' => [$topicTerm->id, $tagTerm->id],
        ], $admin);

        $response->assertOk();
        $response->assertJsonPath('data.entry_id', $entry->id);
        $this->assertDatabaseHas('entry_term', ['entry_id' => $entry->id, 'term_id' => $topicTerm->id]);
        $this->assertDatabaseHas('entry_term', ['entry_id' => $entry->id, 'term_id' => $tagTerm->id]);

        // Синхронизация: убираем один терм
        $response = $this->putJsonAsAdmin("/api/v1/admin/entries/{$entry->id}/terms/sync", [
            'term_ids' => [$topicTerm->id],
        ], $admin);

        $response->assertOk();
        $this->assertDatabaseMissing('entry_term', ['entry_id' => $entry->id, 'term_id' => $tagTerm->id]);
        $this->assertDatabaseHas('entry_term', ['entry_id' => $entry->id, 'term_id' => $topicTerm->id]);

        // Синхронизация: заменяем на другой терм
        $anotherTopicTerm = Term::factory()->forTaxonomy($topics)->create();

        $response = $this->putJsonAsAdmin("/api/v1/admin/entries/{$entry->id}/terms/sync", [
            'term_ids' => [$anotherTopicTerm->id],
        ], $admin);

        $response->assertOk();
        $this->assertDatabaseMissing('entry_term', ['entry_id' => $entry->id, 'term_id' => $topicTerm->id]);
        $this->assertDatabaseHas('entry_term', ['entry_id' => $entry->id, 'term_id' => $anotherTopicTerm->id]);
    }

    public function test_sync_rejects_terms_from_forbidden_taxonomy(): void
    {
        $admin = $this->admin();
        $allowed = Taxonomy::factory()->create();
        $forbidden = Taxonomy::factory()->create();
        $postType = PostType::factory()->withOptions(['taxonomies' => [$allowed->id]])->create();
        $entry = Entry::factory()->forPostType($postType)->create();
        $allowedTerm = Term::factory()->forTaxonomy($allowed)->create();
        $forbiddenTerm = Term::factory()->forTaxonomy($forbidden)->create();

        $response = $this->putJsonAsAdmin("/api/v1/admin/entries/{$entry->id}/terms/sync", [
            'term_ids' => [$allowedTerm->id, $forbiddenTerm->id],
        ], $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, [
            'term_ids' => "Taxonomy with id '{$forbidden->id}' is not allowed for the entry post type.",
        ]);
        $this->assertDatabaseMissing('entry_term', ['entry_id' => $entry->id, 'term_id' => $allowedTerm->id]);
    }
}


