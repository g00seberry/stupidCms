<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Media;

/**
 * Feature-тесты для массовых операций с медиа-файлами:
 * DELETE /api/v1/admin/media/bulk - массовое мягкое удаление
 * POST /api/v1/admin/media/bulk/restore - массовое восстановление
 * DELETE /api/v1/admin/media/bulk/force - массовое окончательное удаление
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
});

// BULK DELETE tests
test('admin can bulk soft delete media', function () {
    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();
    $media3 = Media::factory()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk', [
            'ids' => [$media1->id, $media2->id],
        ]);

    $response->assertNoContent();

    $this->assertSoftDeleted('media', [
        'id' => $media1->id,
    ]);
    $this->assertSoftDeleted('media', [
        'id' => $media2->id,
    ]);
    $this->assertDatabaseHas('media', [
        'id' => $media3->id,
        'deleted_at' => null,
    ]);
});

test('bulk deleted media not in default list', function () {
    $deleted1 = Media::factory()->create();
    $deleted2 = Media::factory()->create();
    $deleted1->delete();
    $deleted2->delete();

    $active = Media::factory()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id);
});

test('cannot bulk delete already deleted media', function () {
    $media1 = Media::factory()->create(['deleted_at' => now()]);
    $media2 = Media::factory()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk', [
            'ids' => [$media1->id, $media2->id],
        ]);

    // Должен удалить только активное медиа, уже удалённое игнорируется
    $response->assertNoContent();

    $this->assertSoftDeleted('media', [
        'id' => $media2->id,
    ]);
});

test('bulk delete requires ids array', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['ids']);
});

test('bulk delete validates ids format', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk', [
            'ids' => ['invalid-id', 'another-invalid'],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['ids.0', 'ids.1']);
});

test('bulk delete rejects duplicate ids', function () {
    $media = Media::factory()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk', [
            'ids' => [$media->id, $media->id],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['ids']);
});

test('bulk delete limits max ids count', function () {
    $ids = [];
    for ($i = 0; $i < 101; $i++) {
        $media = Media::factory()->create();
        $ids[] = $media->id;
    }

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk', [
            'ids' => $ids,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['ids']);
});

// BULK RESTORE tests
test('admin can bulk restore deleted media', function () {
    $media1 = Media::factory()->create(['deleted_at' => now()]);
    $media2 = Media::factory()->create(['deleted_at' => now()]);
    $media3 = Media::factory()->create(['deleted_at' => now()]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk/restore', [
            'ids' => [$media1->id, $media2->id],
        ]);

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $media1->id)
        ->assertJsonPath('data.1.id', $media2->id);

    $this->assertDatabaseHas('media', [
        'id' => $media1->id,
        'deleted_at' => null,
    ]);
    $this->assertDatabaseHas('media', [
        'id' => $media2->id,
        'deleted_at' => null,
    ]);
    $this->assertSoftDeleted('media', [
        'id' => $media3->id,
    ]);
});

test('bulk restored media appears in default list', function () {
    $media1 = Media::factory()->create(['deleted_at' => now()]);
    $media2 = Media::factory()->create(['deleted_at' => now()]);

    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk/restore', [
            'ids' => [$media1->id, $media2->id],
        ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('cannot bulk restore non-deleted media', function () {
    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create(['deleted_at' => now()]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk/restore', [
            'ids' => [$media1->id, $media2->id],
        ]);

    // Должен восстановить только удалённое медиа, активное игнорируется
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $media2->id);
});

test('bulk restored media retains all metadata', function () {
    $media = Media::factory()->create([
        'title' => 'Original Title',
        'alt' => 'Original Alt',
        'deleted_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk/restore', [
            'ids' => [$media->id],
        ]);

    $freshMedia = $media->fresh();

    expect($freshMedia->title)->toBe('Original Title')
        ->and($freshMedia->alt)->toBe('Original Alt')
        ->and($freshMedia->deleted_at)->toBeNull();
});

test('bulk restore requires ids array', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk/restore', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['ids']);
});

test('bulk restore validates ids format', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk/restore', [
            'ids' => ['invalid-id'],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['ids.0']);
});

test('bulk restore rejects duplicate ids', function () {
    $media = Media::factory()->create(['deleted_at' => now()]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk/restore', [
            'ids' => [$media->id, $media->id],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['ids']);
});

// BULK FORCE DELETE tests
test('admin with forceDelete permission can bulk force delete media', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');

    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();

    \Illuminate\Support\Facades\Storage::fake($media1->disk);
    \Illuminate\Support\Facades\Storage::disk($media1->disk)->put($media1->path, 'test content 1');
    \Illuminate\Support\Facades\Storage::disk($media2->disk)->put($media2->path, 'test content 2');

    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk/force', [
            'ids' => [$media1->id, $media2->id],
        ]);

    $response->assertNoContent();

    $this->assertDatabaseMissing('media', [
        'id' => $media1->id,
    ]);
    $this->assertDatabaseMissing('media', [
        'id' => $media2->id,
    ]);
});

test('bulk force delete removes media from database permanently', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');

    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();

    \Illuminate\Support\Facades\Storage::fake($media1->disk);
    \Illuminate\Support\Facades\Storage::disk($media1->disk)->put($media1->path, 'test content 1');
    \Illuminate\Support\Facades\Storage::disk($media2->disk)->put($media2->path, 'test content 2');

    $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk/force', [
            'ids' => [$media1->id, $media2->id],
        ]);

    // Проверяем, что медиа удалены окончательно (не soft delete)
    $this->assertDatabaseMissing('media', [
        'id' => $media1->id,
    ]);
    $this->assertDatabaseMissing('media', [
        'id' => $media2->id,
    ]);

    // Проверяем, что даже с withTrashed не найдём
    expect(Media::withTrashed()->find($media1->id))->toBeNull()
        ->and(Media::withTrashed()->find($media2->id))->toBeNull();
});

test('bulk force delete removes physical files from storage', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');

    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();

    \Illuminate\Support\Facades\Storage::fake($media1->disk);
    \Illuminate\Support\Facades\Storage::disk($media1->disk)->put($media1->path, 'test content 1');
    \Illuminate\Support\Facades\Storage::disk($media2->disk)->put($media2->path, 'test content 2');

    $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk/force', [
            'ids' => [$media1->id, $media2->id],
        ]);

    \Illuminate\Support\Facades\Storage::disk($media1->disk)->assertMissing($media1->path);
    \Illuminate\Support\Facades\Storage::disk($media2->disk)->assertMissing($media2->path);
});

test('bulk force delete removes variants and their files', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');

    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();

    $variant1 = \App\Models\MediaVariant::factory()->create([
        'media_id' => $media1->id,
        'path' => 'variants/thumb1.jpg',
    ]);
    $variant2 = \App\Models\MediaVariant::factory()->create([
        'media_id' => $media2->id,
        'path' => 'variants/thumb2.jpg',
    ]);

    \Illuminate\Support\Facades\Storage::fake($media1->disk);
    \Illuminate\Support\Facades\Storage::disk($media1->disk)->put($media1->path, 'test content 1');
    \Illuminate\Support\Facades\Storage::disk($media2->disk)->put($media2->path, 'test content 2');
    \Illuminate\Support\Facades\Storage::disk($media1->disk)->put($variant1->path, 'variant content 1');
    \Illuminate\Support\Facades\Storage::disk($media2->disk)->put($variant2->path, 'variant content 2');

    $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk/force', [
            'ids' => [$media1->id, $media2->id],
        ]);

    $this->assertDatabaseMissing('media_variants', [
        'id' => $variant1->id,
    ]);
    $this->assertDatabaseMissing('media_variants', [
        'id' => $variant2->id,
    ]);

    \Illuminate\Support\Facades\Storage::disk($media1->disk)->assertMissing($variant1->path);
    \Illuminate\Support\Facades\Storage::disk($media2->disk)->assertMissing($variant2->path);
});

test('bulk force delete removes media images via cascade', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');

    $media1 = Media::factory()->image()->create();
    $media2 = Media::factory()->image()->create();

    $image1 = \App\Models\MediaImage::factory()->for($media1)->create([
        'width' => 1920,
        'height' => 1080,
    ]);
    $image2 = \App\Models\MediaImage::factory()->for($media2)->create([
        'width' => 1280,
        'height' => 720,
    ]);

    \Illuminate\Support\Facades\Storage::fake($media1->disk);
    \Illuminate\Support\Facades\Storage::disk($media1->disk)->put($media1->path, 'test content 1');
    \Illuminate\Support\Facades\Storage::disk($media2->disk)->put($media2->path, 'test content 2');

    $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk/force', [
            'ids' => [$media1->id, $media2->id],
        ]);

    $this->assertDatabaseMissing('media_images', [
        'id' => $image1->id,
    ]);
    $this->assertDatabaseMissing('media_images', [
        'id' => $image2->id,
    ]);
});

test('bulk force delete removes media av metadata via cascade', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');

    $media1 = Media::factory()->video()->create();
    $media2 = Media::factory()->audio()->create();

    $avMetadata1 = \App\Models\MediaAvMetadata::factory()->for($media1)->create([
        'duration_ms' => 120000,
    ]);
    $avMetadata2 = \App\Models\MediaAvMetadata::factory()->for($media2)->create([
        'duration_ms' => 60000,
    ]);

    \Illuminate\Support\Facades\Storage::fake($media1->disk);
    \Illuminate\Support\Facades\Storage::disk($media1->disk)->put($media1->path, 'test content 1');
    \Illuminate\Support\Facades\Storage::disk($media2->disk)->put($media2->path, 'test content 2');

    $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk/force', [
            'ids' => [$media1->id, $media2->id],
        ]);

    $this->assertDatabaseMissing('media_av_metadata', [
        'id' => $avMetadata1->id,
    ]);
    $this->assertDatabaseMissing('media_av_metadata', [
        'id' => $avMetadata2->id,
    ]);
});

test('cannot bulk force delete without forceDelete permission', function () {
    $user = User::factory()->create(['is_admin' => false]);
    // Даём другие права, но не media.forceDelete
    $user->grantAdminPermissions('media.delete', 'media.read');

    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();

    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk/force', [
            'ids' => [$media1->id, $media2->id],
        ]);

    $response->assertForbidden();

    $this->assertDatabaseHas('media', [
        'id' => $media1->id,
    ]);
    $this->assertDatabaseHas('media', [
        'id' => $media2->id,
    ]);
});

test('can bulk force delete soft-deleted media', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');

    $media1 = Media::factory()->create(['deleted_at' => now()]);
    $media2 = Media::factory()->create(['deleted_at' => now()]);

    \Illuminate\Support\Facades\Storage::fake($media1->disk);
    \Illuminate\Support\Facades\Storage::disk($media1->disk)->put($media1->path, 'test content 1');
    \Illuminate\Support\Facades\Storage::disk($media2->disk)->put($media2->path, 'test content 2');

    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk/force', [
            'ids' => [$media1->id, $media2->id],
        ]);

    $response->assertNoContent();

    $this->assertDatabaseMissing('media', [
        'id' => $media1->id,
    ]);
    $this->assertDatabaseMissing('media', [
        'id' => $media2->id,
    ]);
});

test('bulk force delete requires ids array', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');

    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk/force', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['ids']);
});

test('bulk force delete validates ids format', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');

    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk/force', [
            'ids' => ['invalid-id'],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['ids.0']);
});

test('bulk force delete rejects duplicate ids', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');

    $media = Media::factory()->create();

    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk/force', [
            'ids' => [$media->id, $media->id],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['ids']);
});

test('bulk force delete handles mixed existing and non-existing ids gracefully', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $user->grantAdminPermissions('media.forceDelete');

    $media = Media::factory()->create();
    $nonExistentId = \Illuminate\Support\Str::ulid();

    \Illuminate\Support\Facades\Storage::fake($media->disk);
    \Illuminate\Support\Facades\Storage::disk($media->disk)->put($media->path, 'test content');

    // Должен удалить существующее медиа, игнорируя несуществующее
    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->deleteJson('/api/v1/admin/media/bulk/force', [
            'ids' => [$media->id, $nonExistentId],
        ]);

    $response->assertNoContent();

    $this->assertDatabaseMissing('media', [
        'id' => $media->id,
    ]);
});
