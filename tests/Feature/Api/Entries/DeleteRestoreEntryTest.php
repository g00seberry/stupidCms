<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Entry;
use App\Models\PostType;

/**
 * Feature-тесты для DELETE /api/v1/admin/entries/{id} и POST /api/v1/admin/entries/{id}/restore
 * 
 * Тестирует мягкое удаление и восстановление записей
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->postType = PostType::factory()->create(['name' => 'Article']);
});

// DELETE tests
test('admin can soft delete entry', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/entries/{$entry->id}");

    $response->assertNoContent();

    $this->assertSoftDeleted('entries', [
        'id' => $entry->id,
    ]);
});

test('deleted entry is not in default list', function () {
    $deleted = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);
    
    $deleted->delete();

    Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/entries');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('delete not found returns 404', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/entries/99999');

    $response->assertNotFound();
});

test('cannot delete already deleted entry', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'deleted_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/entries/{$entry->id}");

    $response->assertNotFound();
});

// RESTORE tests
test('admin can restore deleted entry', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'deleted_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/entries/{$entry->id}/restore");

    $response->assertOk()
        ->assertJsonPath('data.id', $entry->id);

    $this->assertDatabaseHas('entries', [
        'id' => $entry->id,
        'deleted_at' => null,
    ]);
});

test('restored entry appears in default list', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'deleted_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/entries/{$entry->id}/restore");

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/entries');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('restore not found returns 404', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/entries/99999/restore');

    $response->assertNotFound();
});

test('cannot restore non-deleted entry', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'deleted_at' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/entries/{$entry->id}/restore");

    $response->assertNotFound();
});

test('restored entry retains all data', function () {
    $entry = Entry::factory()->create([
        'post_type_id' => $this->postType->id,
        'author_id' => $this->user->id,
        'title' => 'Original Title',
        'data_json' => ['key' => 'value'],
        'deleted_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/entries/{$entry->id}/restore");

    $freshEntry = $entry->fresh();
    
    expect($freshEntry->title)->toBe('Original Title')
        ->and($freshEntry->data_json)->toBe(['key' => 'value'])
        ->and($freshEntry->deleted_at)->toBeNull();
});

