<?php

namespace Tests\Feature\Admin\Entries;

use App\Models\Entry;
use App\Models\PostType;
use App\Models\ReservedRoute;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SlugPublishValidationTest extends TestCase
{
    use RefreshDatabase;

    // === SLUG VALIDATION ===

    public function test_slug_must_be_valid_format(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        PostType::factory()->create(['slug' => 'page']);

        $data = [
            'post_type' => 'page',
            'title' => 'Test',
            'slug' => 'Invalid Slug!',
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['slug' => 'The slug format is invalid. Only lowercase letters, numbers, and hyphens are allowed.']);
    }

    public function test_slug_must_be_unique_within_post_type(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'page']);
        
        Entry::factory()->forPostType($postType)->create(['slug' => 'about']);

        $data = [
            'post_type' => 'page',
            'title' => 'Another About',
            'slug' => 'about',
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['slug' => 'The slug is already taken for this post type.']);
    }

    public function test_slug_uniqueness_includes_soft_deleted_entries(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'page']);
        
        $entry = Entry::factory()->forPostType($postType)->create(['slug' => 'deleted-page']);
        $entry->delete();

        $data = [
            'post_type' => 'page',
            'title' => 'New Page',
            'slug' => 'deleted-page',
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['slug' => 'The slug is already taken for this post type.']);
    }

    public function test_slug_can_be_reused_across_different_post_types(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $pageType = PostType::factory()->create(['slug' => 'page']);
        $postType = PostType::factory()->create(['slug' => 'post']);
        
        Entry::factory()->forPostType($pageType)->create(['slug' => 'test']);

        $data = [
            'post_type' => 'post',
            'title' => 'Test Post',
            'slug' => 'test',
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(201);
    }

    public function test_update_slug_validates_uniqueness(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'page']);
        
        Entry::factory()->forPostType($postType)->create(['slug' => 'about']);
        $entry = Entry::factory()->forPostType($postType)->create(['slug' => 'contact']);

        $response = $this->putJsonAsAdmin("/api/v1/admin/entries/{$entry->id}", [
            'slug' => 'about',
        ], $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['slug' => 'The slug is already taken for this post type.']);
    }

    public function test_update_allows_keeping_same_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create();
        $entry = Entry::factory()->forPostType($postType)->create(['slug' => 'about', 'title' => 'About']);

        $response = $this->putJsonAsAdmin("/api/v1/admin/entries/{$entry->id}", [
            'title' => 'About Us',
            'slug' => 'about',
        ], $admin);

        $response->assertOk();
    }

    // === RESERVED SLUG VALIDATION ===

    public function test_slug_cannot_match_reserved_path(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        PostType::factory()->create(['slug' => 'page']);
        
        ReservedRoute::create([
            'path' => 'admin',
            'kind' => 'path',
            'source' => 'system',
        ]);

        $data = [
            'post_type' => 'page',
            'title' => 'Admin Page',
            'slug' => 'admin',
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['slug' => 'The slug conflicts with a reserved route.']);
    }

    public function test_slug_cannot_start_with_reserved_prefix(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        PostType::factory()->create(['slug' => 'page']);
        
        ReservedRoute::create([
            'path' => 'api',
            'kind' => 'prefix',
            'source' => 'system',
        ]);

        $data = [
            'post_type' => 'page',
            'title' => 'API Docs',
            'slug' => 'api/docs',
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['slug' => 'The slug conflicts with a reserved route.']);
    }

    public function test_reserved_route_check_is_case_insensitive(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        PostType::factory()->create(['slug' => 'page']);
        
        ReservedRoute::create([
            'path' => 'Admin',
            'kind' => 'path',
            'source' => 'system',
        ]);

        $data = [
            'post_type' => 'page',
            'title' => 'Admin',
            'slug' => 'admin',
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['slug' => 'The slug conflicts with a reserved route.']);
    }

    // === PUBLISH VALIDATION ===

    public function test_publishing_requires_valid_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        PostType::factory()->create(['slug' => 'page']);

        $data = [
            'post_type' => 'page',
            'title' => 'Test Page',
            'slug' => '',
            'is_published' => true,
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['slug' => 'A valid slug is required when publishing an entry.']);
    }

    public function test_publishing_with_auto_generated_slug_succeeds(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        PostType::factory()->create(['slug' => 'page']);

        $data = [
            'post_type' => 'page',
            'title' => 'Published Page',
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
        
        $this->assertNotEmpty($response->json('data.slug'));
    }

    public function test_publishing_sets_published_at_automatically(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        PostType::factory()->create(['slug' => 'page']);

        $data = [
            'post_type' => 'page',
            'title' => 'Test',
            'slug' => 'test-auto-publish',
            'is_published' => true,
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.published_at'));
    }

    public function test_publishing_respects_custom_published_at(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        PostType::factory()->create(['slug' => 'page']);

        $futureDate = now()->addDay()->toIso8601String();

        $data = [
            'post_type' => 'page',
            'title' => 'Scheduled Post',
            'slug' => 'scheduled',
            'is_published' => true,
            'published_at' => $futureDate,
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(201);
        $this->assertEquals($futureDate, $response->json('data.published_at'));
    }

    public function test_draft_entry_allows_empty_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        PostType::factory()->create(['slug' => 'page']);

        $data = [
            'post_type' => 'page',
            'title' => 'Draft Entry',
            'is_published' => false,
        ];

        $response = $this->postJsonAsAdmin('/api/v1/admin/entries', $data, $admin);

        $response->assertStatus(201);
        $response->assertJson([
            'data' => [
                'status' => 'draft',
                'is_published' => false,
            ],
        ]);
    }

    public function test_update_to_publish_validates_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create();
        $entry = Entry::factory()->forPostType($postType)->create([
            'slug' => '',
            'status' => 'draft',
        ]);

        DB::table('entries')->where('id', $entry->id)->update(['slug' => '']);
        $entry->refresh();

        $response = $this->putJsonAsAdmin("/api/v1/admin/entries/{$entry->id}", [
            'is_published' => true,
        ], $admin);

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::VALIDATION_ERROR);
        $this->assertValidationErrors($response, ['slug' => 'A valid slug is required when publishing an entry.']);
    }
}

