<?php

namespace Tests\Feature\Admin\Entries;

use App\Models\Entry;
use App\Models\PostType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexEntriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_200_with_paginated_entries(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create(['slug' => 'page']);
        
        Entry::factory()
            ->count(3)
            ->forPostType($postType)
            ->byAuthor($admin)
            ->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries', $admin);

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'Cookie');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'post_type',
                    'title',
                    'slug',
                    'status',
                    'content_json',
                    'meta_json',
                    'is_published',
                    'published_at',
                    'created_at',
                    'updated_at',
                ],
            ],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'per_page',
                'to',
                'total',
            ],
            'links',
        ]);
        
        $response->assertJsonCount(3, 'data');
    }

    public function test_index_filters_by_post_type(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $pageType = PostType::factory()->create(['slug' => 'page']);
        $postType = PostType::factory()->create(['slug' => 'post']);
        
        Entry::factory()->forPostType($pageType)->create();
        Entry::factory()->count(2)->forPostType($postType)->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries?post_type=post', $admin);

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_status_draft(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create();
        
        Entry::factory()->forPostType($postType)->create(['status' => 'draft']);
        Entry::factory()->forPostType($postType)->published()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries?status=draft', $admin);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals('draft', $response->json('data.0.status'));
    }

    public function test_index_filters_by_status_published(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create();
        
        Entry::factory()->forPostType($postType)->create(['status' => 'draft']);
        Entry::factory()->forPostType($postType)->published()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries?status=published', $admin);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals('published', $response->json('data.0.status'));
    }

    public function test_index_filters_by_status_scheduled(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create();
        
        Entry::factory()->forPostType($postType)->published()->create();
        Entry::factory()->forPostType($postType)->scheduled()->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries?status=scheduled', $admin);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_status_trashed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create();
        
        $entry = Entry::factory()->forPostType($postType)->create();
        $entry->delete();

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries?status=trashed', $admin);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertNotNull($response->json('data.0.deleted_at'));
    }

    public function test_index_searches_by_title(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create();
        
        Entry::factory()->forPostType($postType)->create(['title' => 'About Us Page']);
        Entry::factory()->forPostType($postType)->create(['title' => 'Contact Page']);

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries?q=About', $admin);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertStringContainsString('About', $response->json('data.0.title'));
    }

    public function test_index_filters_by_author(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $author1 = User::factory()->create();
        $author2 = User::factory()->create();
        $postType = PostType::factory()->create();
        
        Entry::factory()->forPostType($postType)->byAuthor($author1)->create();
        Entry::factory()->forPostType($postType)->byAuthor($author2)->create();

        $response = $this->getJsonAsAdmin("/api/v1/admin/entries?author_id={$author1->id}", $admin);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_index_sorts_entries(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create();
        
        Entry::factory()->forPostType($postType)->create(['title' => 'Zebra']);
        Entry::factory()->forPostType($postType)->create(['title' => 'Apple']);

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries?sort=title.asc', $admin);

        $response->assertOk();
        $this->assertEquals('Apple', $response->json('data.0.title'));
        $this->assertEquals('Zebra', $response->json('data.1.title'));
    }

    public function test_index_respects_per_page_limit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $postType = PostType::factory()->create();
        
        Entry::factory()->count(25)->forPostType($postType)->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries?per_page=10', $admin);

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $this->assertEquals(10, $response->json('meta.per_page'));
    }

    public function test_index_returns_401_when_not_authenticated(): void
    {
        $response = $this->getJson('/api/v1/admin/entries');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertHeader('WWW-Authenticate', 'Bearer');
    }

    public function test_index_returns_403_for_non_admin_user(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries', $user);

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_index_allows_user_with_manage_entries_permission(): void
    {
        $editor = User::factory()->create(['is_admin' => false]);
        $editor->grantAdminPermissions('manage.entries');
        $editor->save();
        $postType = PostType::factory()->create();
        
        Entry::factory()->forPostType($postType)->create();

        $response = $this->getJsonAsAdmin('/api/v1/admin/entries', $editor);

        $response->assertOk();
    }
}

