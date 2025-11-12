<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\PostTypes;

use App\Models\Entry;
use App\Models\EntrySlug;
use App\Models\Media;
use App\Models\PostType;
use App\Models\Term;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeletePostTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroy_deletes_post_type_without_entries(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'article']);

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/post-types/article', [], $admin);

        $response->assertStatus(204);
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        
        $this->assertDatabaseMissing('post_types', ['slug' => 'article']);
    }

    public function test_destroy_blocks_when_entries_exist_without_force(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'article']);
        Entry::factory()->forPostType($postType)->create();

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/post-types/article', [], $admin);

        $response->assertStatus(409);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        
        $this->assertErrorResponse($response, ErrorCode::CONFLICT, [
            'detail' => 'Cannot delete post type while entries exist. Use force=1 to cascade delete.',
            'meta.entries_count' => 1,
        ]);
        
        $this->assertDatabaseHas('post_types', ['slug' => 'article']);
    }

    public function test_destroy_with_force_deletes_post_type_and_entries(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'article']);
        $entry = Entry::factory()->forPostType($postType)->create();

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/post-types/article?force=1', [], $admin);

        $response->assertStatus(204);
        $response->assertHeader('Cache-Control', 'no-store, private');
        
        $this->assertDatabaseMissing('post_types', ['slug' => 'article']);
        $this->assertDatabaseMissing('entries', ['id' => $entry->id]);
    }

    public function test_destroy_with_force_deletes_multiple_entries(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'article']);
        $entry1 = Entry::factory()->forPostType($postType)->create();
        $entry2 = Entry::factory()->forPostType($postType)->create();
        $entry3 = Entry::factory()->forPostType($postType)->create();

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/post-types/article?force=1', [], $admin);

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('post_types', ['slug' => 'article']);
        $this->assertDatabaseMissing('entries', ['id' => $entry1->id]);
        $this->assertDatabaseMissing('entries', ['id' => $entry2->id]);
        $this->assertDatabaseMissing('entries', ['id' => $entry3->id]);
    }

    public function test_destroy_with_force_deletes_soft_deleted_entries(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'article']);
        $entry = Entry::factory()->forPostType($postType)->create();
        $entry->delete(); // Soft delete

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/post-types/article?force=1', [], $admin);

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('post_types', ['slug' => 'article']);
        $this->assertDatabaseMissing('entries', ['id' => $entry->id]);
    }

    public function test_destroy_with_force_deletes_entry_slugs(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'article']);
        $entry = Entry::factory()->forPostType($postType)->create(['slug' => 'test-entry']);
        
        // EntrySlug is created automatically by EntryObserver when Entry is created
        // Verify it exists before deletion
        $this->assertDatabaseHas('entry_slugs', [
            'entry_id' => $entry->id,
            'slug' => $entry->slug,
            'is_current' => true,
        ]);

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/post-types/article?force=1', [], $admin);

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('post_types', ['slug' => 'article']);
        $this->assertDatabaseMissing('entries', ['id' => $entry->id]);
        $this->assertDatabaseMissing('entry_slugs', ['entry_id' => $entry->id]);
    }

    public function test_destroy_with_force_deletes_entry_term_relations(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'article']);
        $entry = Entry::factory()->forPostType($postType)->create();
        $term = Term::factory()->create();
        
        // Attach term to entry
        $entry->terms()->attach($term->id);

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/post-types/article?force=1', [], $admin);

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('post_types', ['slug' => 'article']);
        $this->assertDatabaseMissing('entries', ['id' => $entry->id]);
        $this->assertDatabaseMissing('entry_term', ['entry_id' => $entry->id]);
    }

    public function test_destroy_with_force_deletes_entry_media_relations(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'article']);
        $entry = Entry::factory()->forPostType($postType)->create();
        $media = Media::factory()->create();
        
        // Attach media to entry
        $entry->media()->attach($media->id, ['field_key' => 'hero', 'order' => 0]);

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/post-types/article?force=1', [], $admin);

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('post_types', ['slug' => 'article']);
        $this->assertDatabaseMissing('entries', ['id' => $entry->id]);
        $this->assertDatabaseMissing('entry_media', ['entry_id' => $entry->id]);
    }

    public function test_destroy_returns_404_for_unknown_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/post-types/nonexistent', [], $admin);

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        
        $this->assertErrorResponse($response, ErrorCode::NOT_FOUND, [
            'detail' => 'Unknown post type slug: nonexistent',
            'meta.slug' => 'nonexistent',
        ]);
    }

    public function test_destroy_returns_401_when_not_authenticated(): void
    {
        PostType::factory()->create(['slug' => 'article']);

        $csrfToken = Str::random(40);
        $csrfCookieName = config('security.csrf.cookie_name');

        $server = $this->transformHeadersToServerVars([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
            'X-CSRF-Token' => $csrfToken,
        ]);

        $response = $this->call(
            'DELETE',
            '/api/v1/admin/post-types/article',
            [],
            [$csrfCookieName => $csrfToken],
            [],
            $server
        );

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('WWW-Authenticate', 'Bearer');
        $response->assertHeader('Cache-Control', 'no-store, private');
        
        $this->assertErrorResponse($response, ErrorCode::UNAUTHORIZED, [
            'detail' => 'Authentication is required to access this resource.',
        ]);
    }

    public function test_destroy_returns_403_for_non_admin_user(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        PostType::factory()->create(['slug' => 'article']);

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/post-types/article', [], $user);

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        
        $this->assertErrorResponse($response, ErrorCode::FORBIDDEN, [
            'detail' => 'This action is unauthorized.',
        ]);
        
        $this->assertDatabaseHas('post_types', ['slug' => 'article']);
    }

    public function test_destroy_allows_user_with_manage_posttypes_permission(): void
    {
        $editor = User::factory()->create([
            'is_admin' => false,
            'admin_permissions' => ['manage.posttypes'],
        ]);
        $postType = PostType::factory()->create(['slug' => 'article']);

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/post-types/article', [], $editor);

        $response->assertStatus(204);
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        
        $this->assertDatabaseMissing('post_types', ['slug' => 'article']);
    }

    public function test_destroy_with_force_allows_user_with_manage_posttypes_permission(): void
    {
        $editor = User::factory()->create([
            'is_admin' => false,
            'admin_permissions' => ['manage.posttypes'],
        ]);
        $postType = PostType::factory()->create(['slug' => 'article']);
        $entry = Entry::factory()->forPostType($postType)->create();

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/post-types/article?force=1', [], $editor);

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('post_types', ['slug' => 'article']);
        $this->assertDatabaseMissing('entries', ['id' => $entry->id]);
    }

    public function test_destroy_does_not_affect_other_post_types(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType1 = PostType::factory()->create(['slug' => 'article']);
        $postType2 = PostType::factory()->create(['slug' => 'page']);

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/post-types/article', [], $admin);

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('post_types', ['slug' => 'article']);
        $this->assertDatabaseHas('post_types', ['slug' => 'page']);
    }

    public function test_destroy_does_not_affect_entries_of_other_post_types(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType1 = PostType::factory()->create(['slug' => 'article']);
        $postType2 = PostType::factory()->create(['slug' => 'page']);
        $entry1 = Entry::factory()->forPostType($postType1)->create();
        $entry2 = Entry::factory()->forPostType($postType2)->create();

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/post-types/article?force=1', [], $admin);

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('post_types', ['slug' => 'article']);
        $this->assertDatabaseMissing('entries', ['id' => $entry1->id]);
        $this->assertDatabaseHas('post_types', ['slug' => 'page']);
        $this->assertDatabaseHas('entries', ['id' => $entry2->id]);
    }

    public function test_destroy_with_force_deletes_all_entry_relations(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'article']);
        $entry = Entry::factory()->forPostType($postType)->create(['slug' => 'test-entry']);
        $term = Term::factory()->create();
        $media = Media::factory()->create();
        
        // Attach all relations
        $entry->terms()->attach($term->id);
        $entry->media()->attach($media->id, ['field_key' => 'hero', 'order' => 0]);
        
        // EntrySlug is created automatically by EntryObserver when Entry is created
        // Create an additional historical slug to test deletion of all slugs
        EntrySlug::create([
            'entry_id' => $entry->id,
            'slug' => 'old-test-entry',
            'is_current' => false,
        ]);

        $response = $this->deleteJsonAsAdmin('/api/v1/admin/post-types/article?force=1', [], $admin);

        $response->assertStatus(204);
        
        $this->assertDatabaseMissing('post_types', ['slug' => 'article']);
        $this->assertDatabaseMissing('entries', ['id' => $entry->id]);
        $this->assertDatabaseMissing('entry_term', ['entry_id' => $entry->id]);
        $this->assertDatabaseMissing('entry_media', ['entry_id' => $entry->id]);
        $this->assertDatabaseMissing('entry_slugs', ['entry_id' => $entry->id]);
    }
}

