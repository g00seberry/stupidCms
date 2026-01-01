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
            'data' => ['id', 'post_type', 'title', 'status'],
        ])
        ->assertJsonPath('data.title', 'Test Article')
        ->assertJsonPath('data.post_type.id', $this->postType->id)
        ->assertJsonPath('data.post_type.name', $this->postType->name);

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

test('entry is created as draft by default', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'post_type_id' => $this->postType->id,
            'title' => 'Test Article',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft');
});

test('entry can be published immediately', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'post_type_id' => $this->postType->id,
            'title' => 'Test Article',
            'status' => 'published',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'published');
    
    $entry = Entry::latest()->first();
    expect($entry->published_at)->not->toBeNull();
});

test('entry can be created with data_json', function () {
    $content = ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Hello']]]];
    
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries', [
            'post_type_id' => $this->postType->id,
            'title' => 'Test Article',
            'data_json' => $content,
        ]);

    $response->assertCreated();
    
    $entry = Entry::latest()->first();
    expect($entry->data_json)->toBe($content);
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
