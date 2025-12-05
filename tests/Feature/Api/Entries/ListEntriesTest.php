<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Entry;
use App\Models\PostType;

/**
 * Feature-тесты для GET /api/v1/admin/entries
 * 
 * Тестирует список записей с фильтрацией, пагинацией и поиском
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->postType = PostType::factory()->create(['slug' => 'article']);
});

test('admin can list entries', function () {
    Entry::factory()->count(3)->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/entries');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'post_type_id', 'title', 'slug', 'status'],
            ],
            'links',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonCount(3, 'data');
});

test('entries are paginated', function () {
    Entry::factory()->count(20)->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/entries?per_page=10');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 20)
        ->assertJsonCount(10, 'data');
});

test('entries can be filtered by post type', function () {
    $blogPostType = PostType::factory()->create(['slug' => 'blog']);
    
    Entry::factory()->count(2)->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);
    
    Entry::factory()->create([
        'post_type_id' => $blogPostType->id,
        'author_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/entries?post_type_id=' . $this->postType->id);

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('entries can be filtered by status', function () {
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'status' => 'draft',
    ]);
    
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/entries?status=draft');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'draft');
});

test('entries can be searched by title', function () {
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'title' => 'Laravel Testing Guide',
    ]);
    
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'title' => 'PHP Best Practices',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/entries?q=Laravel');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Laravel Testing Guide');
});

test('entries can be searched by slug', function () {
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'slug' => 'laravel-testing',
    ]);
    
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'slug' => 'php-practices',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/entries?q=testing');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('unauthenticated request returns 401', function () {
    $response = $this->getJson('/api/v1/admin/entries');

    $response->assertUnauthorized();
});

test('entries can be filtered by author', function () {
    $anotherUser = User::factory()->create();
    
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);
    
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $anotherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/entries?author_id={$this->user->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('entries can be sorted by updated_at', function () {
    $old = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'updated_at' => now()->subDays(2),
    ]);
    
    $new = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/entries?sort=updated_at.desc');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.1.id', $old->id);
});

test('entries can be sorted by title', function () {
    $b = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'title' => 'B Title',
    ]);
    
    $a = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'title' => 'A Title',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/entries?sort=title.asc');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $a->id)
        ->assertJsonPath('data.1.id', $b->id);
});

test('trashed entries are excluded by default', function () {
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);
    
    $deleted = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'deleted_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/entries');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('trashed entries can be listed with filter', function () {
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);
    
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'deleted_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/entries?status=trashed');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

