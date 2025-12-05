<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Entry;
use App\Models\PostType;

/**
 * Feature-тесты для POST /api/v1/admin/entries
 * 
 * Тестирует создание записей с валидацией и auto-slug generation
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->postType = PostType::factory()->create(['name' => 'Article']);
});

test('admin can create entry', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'post_type_id' => $this->postType->id,
            'title' => 'Test Article',
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'post_type_id', 'title', 'slug', 'status'],
        ])
        ->assertJsonPath('data.title', 'Test Article')
        ->assertJsonPath('data.post_type_id', $this->postType->id);

    $this->assertDatabaseHas('entries', [
        'title' => 'Test Article',
        'post_type_id' => $this->postType->id,
    ]);
});

test('entry is created with correct author', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'post_type_id' => $this->postType->id,
            'title' => 'Test Article',
        ]);

    $response->assertCreated();
    
    $entry = Entry::latest()->first();
    expect($entry->author_id)->toBe($this->user->id);
});

test('entry slug is auto-generated from title', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'post_type_id' => $this->postType->id,
            'title' => 'Laravel Testing Guide',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'laravel-testing-guide');
});

test('entry can be created with custom slug', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'post_type_id' => $this->postType->id,
            'title' => 'Laravel Testing Guide',
            'slug' => 'custom-slug',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'custom-slug');
});

test('entry is created as draft by default', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'post_type_id' => $this->postType->id,
            'title' => 'Test Article',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.is_published', false);
});

test('entry can be published immediately', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'post_type_id' => $this->postType->id,
            'title' => 'Test Article',
            'is_published' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.is_published', true);
    
    $entry = Entry::latest()->first();
    expect($entry->published_at)->not->toBeNull();
});

test('entry can be created with content_json', function () {
    $content = ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Hello']]]];
    
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'post_type_id' => $this->postType->id,
            'title' => 'Test Article',
            'content_json' => $content,
        ]);

    $response->assertCreated();
    
    $entry = Entry::latest()->first();
    expect($entry->data_json)->toBe($content);
});

test('entry can be created with meta_json', function () {
    $meta = ['title' => 'SEO Title', 'description' => 'SEO Description'];
    
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'post_type_id' => $this->postType->id,
            'title' => 'Test Article',
            'meta_json' => $meta,
        ]);

    $response->assertCreated();
    
    $entry = Entry::latest()->first();
    expect($entry->seo_json)->toBe($meta);
});

test('entry validation fails with missing title', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'post_type_id' => $this->postType->id,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

test('entry validation fails with missing post_type', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'title' => 'Test Article',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['post_type_id']);
});

test('entry validation fails with invalid post_type', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'post_type_id' => 99999,
            'title' => 'Test Article',
        ]);

    $response->assertUnprocessable();
});


test('entry can be created with template_override', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'post_type_id' => $this->postType->id,
            'title' => 'Test Article',
            'template_override' => 'templates.custom',
        ]);

    $response->assertCreated();
    
    $entry = Entry::latest()->first();
    expect($entry->template_override)->toBe('templates.custom');
});

test('duplicate slug is made unique', function () {
    // Create first entry
    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'title' => 'Test Article',
        'slug' => 'test-article',
    ]);

    // Try to create second with same title (should auto-generate unique slug)
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'post_type_id' => $this->postType->id,
            'title' => 'Test Article',
        ]);

    $response->assertCreated();
    
    $slug = $response->json('data.slug');
    expect($slug)->not->toBe('test-article')
        ->and($slug)->toStartWith('test-article-');
});

