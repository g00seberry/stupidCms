<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Entry;
use App\Models\PostType;

/**
 * Feature-тесты для PUT /api/v1/admin/entries/{id}
 * 
 * Тестирует обновление записей
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->postType = PostType::factory()->create(['slug' => 'article']);
});

test('admin can update entry', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'title' => 'Old Title',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
            'title' => 'New Title',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.title', 'New Title');

    $this->assertDatabaseHas('entries', [
        'id' => $entry->id,
        'title' => 'New Title',
    ]);
});

test('entry data is updated correctly', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'slug' => 'old-slug',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
            'slug' => 'new-slug',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.slug', 'new-slug');
});

test('entry validation works on update', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
            'title' => '', // Empty title should fail
        ]);

    $response->assertUnprocessable();
});

test('not found returns 404', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson('/api/v1/admin/entries/99999', [
            'title' => 'New Title',
        ]);

    $response->assertNotFound();
});

test('can update content_json', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'data_json' => ['old' => 'content'],
    ]);

    $newContent = ['blocks' => [['type' => 'heading', 'data' => ['text' => 'New Heading']]]];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
            'content_json' => $newContent,
        ]);

    $response->assertOk();
    
    $freshEntry = $entry->fresh();
    expect($freshEntry->data_json)->toBe($newContent);
});

test('can update meta_json', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'seo_json' => ['old' => 'meta'],
    ]);

    $newMeta = ['title' => 'New SEO Title', 'description' => 'New description'];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
            'meta_json' => $newMeta,
        ]);

    $response->assertOk();
    
    $freshEntry = $entry->fresh();
    expect($freshEntry->seo_json)->toBe($newMeta);
});

test('can publish draft entry', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'status' => 'draft',
        'published_at' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
            'is_published' => true,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'published');
    
    $freshEntry = $entry->fresh();
    expect($freshEntry->status)->toBe('published')
        ->and($freshEntry->published_at)->not->toBeNull();
});

test('can unpublish entry', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'status' => 'published',
        'published_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
            'is_published' => false,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'draft');
    
    $freshEntry = $entry->fresh();
    expect($freshEntry->status)->toBe('draft')
        ->and($freshEntry->published_at)->toBeNull();
});

test('can update template_override', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'template_override' => 'templates.old',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
            'template_override' => 'templates.new',
        ]);

    $response->assertOk();
    
    $freshEntry = $entry->fresh();
    expect($freshEntry->template_override)->toBe('templates.new');
});

test('can update soft deleted entry', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'title' => 'Old Title',
        'deleted_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
            'title' => 'New Title',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.title', 'New Title');
});

test('updated_at changes after update', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'updated_at' => now()->subHour(),
    ]);

    $oldUpdatedAt = $entry->updated_at;

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/entries/{$entry->id}", [
            'title' => 'Updated Title',
        ]);

    $response->assertOk();
    
    $freshEntry = $entry->fresh();
    expect($freshEntry->updated_at->isAfter($oldUpdatedAt))->toBeTrue();
});

