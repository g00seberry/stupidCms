<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Entry;
use App\Models\PostType;

/**
 * Feature-тесты для GET /api/v1/admin/entries/{id}
 * 
 * Тестирует просмотр конкретной записи с relationships
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->postType = PostType::factory()->create(['name' => 'Article']);
});

test('admin can view entry', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'title' => 'Test Article',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/entries/{$entry->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'post_type', 'title', 'status', 'author'],
        ])
        ->assertJsonPath('data.id', $entry->id)
        ->assertJsonPath('data.title', 'Test Article');
});

test('entry includes author relationship', function () {
    $author = User::factory()->create(['name' => 'John Doe']);
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $author->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/entries/{$entry->id}");

    $response->assertOk()
        ->assertJsonPath('data.author.id', $author->id)
        ->assertJsonPath('data.author.name', 'John Doe');
});

test('entry includes post type', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/entries/{$entry->id}");

    $response->assertOk()
        ->assertJsonPath('data.post_type.id', $this->postType->id)
        ->assertJsonPath('data.post_type.name', $this->postType->name);
});

test('not found returns 404', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/entries/99999');

    $response->assertNotFound();
});

test('can view soft deleted entry', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'deleted_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/entries/{$entry->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $entry->id);
});

test('entry includes data_json', function () {
    $content = ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Hello']]]];
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'data_json' => $content,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/entries/{$entry->id}");

    $response->assertOk()
        ->assertJsonPath('data.data_json', $content);
});

test('entry returns null for empty data_json', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'data_json' => [],
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/entries/{$entry->id}");

    $response->assertOk()
        ->assertJsonPath('data.data_json', null);
});

test('entry returns null for null data_json', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);
    // Устанавливаем data_json в null через прямой доступ к БД
    $entry->data_json = null;
    $entry->save();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/entries/{$entry->id}");

    $response->assertOk()
        ->assertJsonPath('data.data_json', null);
});

test('entry includes timestamps', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/entries/{$entry->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['created_at', 'updated_at', 'deleted_at'],
        ]);
});

