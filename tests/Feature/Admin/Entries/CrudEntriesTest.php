<?php

namespace Tests\Feature\Admin\Entries;

use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrudEntriesTest extends TestCase
{
    use RefreshDatabase;

    // === SHOW ===

    public function test_show_returns_200_with_entry_details(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'page']);
        $entry = Entry::factory()
            ->forPostType($postType)
            ->byAuthor($admin)
            ->create(['title' => 'About Page']);

        $response = $this->getJsonAsAdmin("/api/v1/admin/entries/{$entry->id}", $admin);

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        $response->assertJson([
            'data' => [
                'id' => $entry->id,
                'post_type' => 'page',
                'title' => 'About Page',
                'slug' => $entry->slug,
            ],
        ]);
    }

    public function test_show_returns_404_for_nonexistent_entry(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries/99999', $admin);

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertErrorResponse($response, ErrorCode::NOT_FOUND, [
            'detail' => 'Entry with ID 99999 does not exist.',
            'meta.entry_id' => 99999,
            'meta.trashed' => false,
        ]);
    }

    // === STORE ===

    public function test_store_creates_entry_with_auto_generated_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'page']);

        $data = [
            'post_type' => 'page',
            'title' => 'About Us',
            'is_published' => false,
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(201);
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertJson([
            'data' => [
                'post_type' => 'page',
                'title' => 'About Us',
                'status' => 'draft',
                'is_published' => false,
            ],
        ]);

        $this->assertDatabaseHas('entries', [
            'post_type_id' => $postType->id,
            'title' => 'About Us',
            'status' => 'draft',
        ]);
    }

    public function test_store_creates_entry_with_custom_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        PostType::factory()->create(['slug' => 'page']);

        $data = [
            'post_type' => 'page',
            'title' => 'About Us',
            'slug' => 'about-custom',
            'is_published' => false,
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(201);
        $response->assertJson([
            'data' => [
                'slug' => 'about-custom',
            ],
        ]);
    }

    public function test_store_creates_published_entry_with_published_at(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        PostType::factory()->create(['slug' => 'page']);

        $data = [
            'post_type' => 'page',
            'title' => 'Published Page',
            'slug' => 'published-page',
            'is_published' => true,
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(201);
        $response->assertJson([
            'data' => [
                'status' => 'published',
                'is_published' => true,
            ],
        ]);
        
        $this->assertNotNull($response->json('data.published_at'));
    }

    public function test_store_requires_post_type(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $data = [
            'title' => 'Test',
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['post_type' => 'The post type field is required.']);
    }

    public function test_store_requires_title(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        PostType::factory()->create(['slug' => 'page']);

        $data = [
            'post_type' => 'page',
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['title' => 'The title field is required.']);
    }

    // === UPDATE ===

    public function test_update_modifies_entry_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'page']);
        $entry = Entry::factory()->forPostType($postType)->create(['title' => 'Original']);

        $data = [
            'title' => 'Updated Title',
        ];

        $response = $this->putJsonAsAdmin("/api/v1/admin/entries/{$entry->id}", $data, $admin);

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'title' => 'Updated Title',
            ],
        ]);

        $this->assertDatabaseHas('entries', [
            'id' => $entry->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_update_publishes_entry(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create();
        $entry = Entry::factory()->forPostType($postType)->create(['status' => 'draft']);

        $data = [
            'is_published' => true,
        ];

        $response = $this->putJsonAsAdmin("/api/v1/admin/entries/{$entry->id}", $data, $admin);

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'status' => 'published',
                'is_published' => true,
            ],
        ]);

        $entry->refresh();
        $this->assertEquals('published', $entry->status);
        $this->assertNotNull($entry->published_at);
    }

    public function test_update_unpublishes_entry(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create();
        $entry = Entry::factory()->forPostType($postType)->published()->create();

        $data = [
            'is_published' => false,
        ];

        $response = $this->putJsonAsAdmin("/api/v1/admin/entries/{$entry->id}", $data, $admin);

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'status' => 'draft',
                'is_published' => false,
            ],
        ]);

        $entry->refresh();
        $this->assertEquals('draft', $entry->status);
        $this->assertNull($entry->published_at);
    }

    public function test_update_returns_404_for_nonexistent_entry(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->putJsonAsAdmin('/api/v1/admin/entries/99999', ['title' => 'Test'], $admin);

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

    // === DELETE ===

    public function test_destroy_soft_deletes_entry(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create();
        $entry = Entry::factory()->forPostType($postType)->create();

        $response = $this->deleteJsonAsAdmin("/api/v1/admin/entries/{$entry->id}", [], $admin);

        $response->assertStatus(204);
        $response->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSoftDeleted('entries', ['id' => $entry->id]);
    }

    public function test_destroy_returns_404_for_nonexistent_entry(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/entries/99999', [], $admin);

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

    // === RESTORE ===

    public function test_restore_recovers_soft_deleted_entry(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create();
        $entry = Entry::factory()->forPostType($postType)->create();
        $entry->delete();

        $response = $this->postJsonAsAdmin("/api/v1/admin/entries/{$entry->id}/restore", [], $admin);

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertJson([
            'data' => [
                'id' => $entry->id,
            ],
        ]);

        $this->assertDatabaseHas('entries', [
            'id' => $entry->id,
            'deleted_at' => null,
        ]);
    }

    public function test_restore_returns_404_for_non_trashed_entry(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create();
        $entry = Entry::factory()->forPostType($postType)->create();

        $response = $this->postJsonAsAdmin("/api/v1/admin/entries/{$entry->id}/restore", [], $admin);

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

    // === AUTHORIZATION ===

    public function test_crud_returns_401_when_not_authenticated(): void
    {
        $response = $this->postJsonWithCsrf('/api/v1/admin/entries', ['post_type' => 'page', 'title' => 'Test']);
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/admin/entries/1');
        $response->assertStatus(401);

        $csrfToken = Str::random(40);
        $csrfCookieName = config('security.csrf.cookie_name');
        $server = $this->transformHeadersToServerVars([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
            'X-CSRF-Token' => $csrfToken,
        ]);

        $response = $this->call(
            'PUT',
            '/api/v1/admin/entries/1',
            ['title' => 'Test'],
            [$csrfCookieName => $csrfToken],
            [],
            $server,
            json_encode(['title' => 'Test'])
        );
        $response->assertStatus(401);

        $response = $this->deleteJsonWithCsrf('/api/v1/admin/entries/1', []);
        $response->assertStatus(401);
    }

    public function test_crud_returns_403_for_non_admin_user(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $postType = PostType::factory()->create();
        $entry = Entry::factory()->forPostType($postType)->create();

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', ['post_type' => 'page', 'title' => 'Test'], $user);
        $response->assertStatus(403);

        $response = $this->getJsonAsAdmin("/api/v1/admin/entries/{$entry->id}", $user);
        $response->assertStatus(403);

        $response = $this->putJsonAsAdmin("/api/v1/admin/entries/{$entry->id}", ['title' => 'Test'], $user);
        $response->assertStatus(403);

        $response = $this->deleteJsonAsAdmin("/api/v1/admin/entries/{$entry->id}", [], $user);
        $response->assertStatus(403);
    }

    public function test_crud_allows_user_with_manage_entries_permission(): void
    {
        $editor = User::factory()->create(['is_admin' => false]);
        $editor->grantAdminPermissions('manage.entries');
        $editor->save();
        $postType = PostType::factory()->create(['slug' => 'page']);
        $entry = Entry::factory()->forPostType($postType)->create();

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', [
            'post_type' => 'page',
            'title' => 'Test',
            'slug' => 'test-entry',
        ], $editor);
        $response->assertStatus(201);

        $response = $this->getJsonAsAdmin("/api/v1/admin/entries/{$entry->id}", $editor);
        $response->assertOk();

        $response = $this->putJsonAsAdmin("/api/v1/admin/entries/{$entry->id}", ['title' => 'Updated'], $editor);
        $response->assertOk();

        $response = $this->deleteJsonAsAdmin("/api/v1/admin/entries/{$entry->id}", [], $editor);
        $response->assertStatus(204);
    }
}

