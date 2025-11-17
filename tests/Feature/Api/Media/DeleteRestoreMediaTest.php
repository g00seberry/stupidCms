<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Media;

/**
 * Feature-тесты для DELETE /api/v1/admin/media/{id} и POST /api/v1/admin/media/{id}/restore
 * 
 * Тестирует мягкое удаление и восстановление медиафайлов
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
});

// DELETE tests
test('admin can soft delete media', function () {
    $media = Media::factory()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/media/{$media->id}");

    $response->assertNoContent();

    $this->assertSoftDeleted('media', [
        'id' => $media->id,
    ]);
});

test('deleted media not in default list', function () {
    $deleted = Media::factory()->create();
    $deleted->delete();

    Media::factory()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('cannot delete already deleted media', function () {
    $media = Media::factory()->create(['deleted_at' => now()]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/media/{$media->id}");

    $response->assertNotFound();
});

// RESTORE tests
test('admin can restore deleted media', function () {
    $media = Media::factory()->create(['deleted_at' => now()]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/media/{$media->id}/restore");

    $response->assertOk()
        ->assertJsonPath('data.id', $media->id);

    $this->assertDatabaseHas('media', [
        'id' => $media->id,
        'deleted_at' => null,
    ]);
});

test('restored media appears in default list', function () {
    $media = Media::factory()->create(['deleted_at' => now()]);

    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/media/{$media->id}/restore");

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('cannot restore non-deleted media', function () {
    $media = Media::factory()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/media/{$media->id}/restore");

    $response->assertNotFound();
});

test('restored media retains all metadata', function () {
    $media = Media::factory()->create([
        'title' => 'Original Title',
        'alt' => 'Original Alt',
        'collection' => 'uploads',
        'deleted_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson("/api/v1/admin/media/{$media->id}/restore");

    $freshMedia = $media->fresh();
    
    expect($freshMedia->title)->toBe('Original Title')
        ->and($freshMedia->alt)->toBe('Original Alt')
        ->and($freshMedia->collection)->toBe('uploads')
        ->and($freshMedia->deleted_at)->toBeNull();
});

