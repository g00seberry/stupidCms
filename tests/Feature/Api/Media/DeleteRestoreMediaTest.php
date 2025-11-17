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

// FORCE DELETE tests
test('admin with forceDelete permission can force delete media', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');
    
    $media = Media::factory()->create();
    
    \Illuminate\Support\Facades\Storage::fake($media->disk);
    \Illuminate\Support\Facades\Storage::disk($media->disk)->put($media->path, 'test content');

    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/media/{$media->id}/force");

    $response->assertNoContent();

    $this->assertDatabaseMissing('media', [
        'id' => $media->id,
    ]);
    
    \Illuminate\Support\Facades\Storage::disk($media->disk)->assertMissing($media->path);
});

test('force delete removes media from database permanently', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');
    
    $media = Media::factory()->create();
    
    \Illuminate\Support\Facades\Storage::fake($media->disk);
    \Illuminate\Support\Facades\Storage::disk($media->disk)->put($media->path, 'test content');

    $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/media/{$media->id}/force");

    // Проверяем, что медиа удалено окончательно (не soft delete)
    $this->assertDatabaseMissing('media', [
        'id' => $media->id,
    ]);
    
    // Проверяем, что даже с withTrashed не найдём
    expect(Media::withTrashed()->find($media->id))->toBeNull();
});

test('force delete removes physical files from storage', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');
    
    $media = Media::factory()->create();
    
    \Illuminate\Support\Facades\Storage::fake($media->disk);
    \Illuminate\Support\Facades\Storage::disk($media->disk)->put($media->path, 'test content');
    
    $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/media/{$media->id}/force");

    \Illuminate\Support\Facades\Storage::disk($media->disk)->assertMissing($media->path);
});

test('force delete removes variants and their files', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');
    
    $media = Media::factory()->create();
    $variant = \App\Models\MediaVariant::factory()->create([
        'media_id' => $media->id,
        'path' => 'variants/thumb.jpg',
    ]);
    
    \Illuminate\Support\Facades\Storage::fake($media->disk);
    \Illuminate\Support\Facades\Storage::disk($media->disk)->put($media->path, 'test content');
    \Illuminate\Support\Facades\Storage::disk($media->disk)->put($variant->path, 'variant content');

    $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/media/{$media->id}/force");

    $this->assertDatabaseMissing('media_variants', [
        'id' => $variant->id,
    ]);
    
    \Illuminate\Support\Facades\Storage::disk($media->disk)->assertMissing($variant->path);
});

test('cannot force delete without forceDelete permission', function () {
    $user = User::factory()->create(['is_admin' => false]);
    // Даём другие права, но не media.forceDelete
    $user->grantAdminPermissions('media.delete', 'media.read');
    
    $media = Media::factory()->create();

    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/media/{$media->id}/force");

    $response->assertForbidden();
    
    $this->assertDatabaseHas('media', [
        'id' => $media->id,
    ]);
});

test('cannot force delete non-existent media', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');
    
    $nonExistentId = \Illuminate\Support\Str::ulid();

    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/media/{$nonExistentId}/force");

    $response->assertNotFound();
});

test('can force delete soft-deleted media', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');
    
    $media = Media::factory()->create(['deleted_at' => now()]);
    
    \Illuminate\Support\Facades\Storage::fake($media->disk);
    \Illuminate\Support\Facades\Storage::disk($media->disk)->put($media->path, 'test content');

    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson("/api/v1/admin/media/{$media->id}/force");

    $response->assertNoContent();

    $this->assertDatabaseMissing('media', [
        'id' => $media->id,
    ]);
});

