<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Media;

/**
 * Feature-тесты для PUT /api/v1/admin/media/{id}
 * 
 * Тестирует обновление метаданных медиафайла
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
});

test('admin can update media metadata', function () {
    $media = Media::factory()->create(['title' => 'Old Title']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/media/{$media->id}", [
            'title' => 'New Title',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.title', 'New Title');

    $this->assertDatabaseHas('media', [
        'id' => $media->id,
        'title' => 'New Title',
    ]);
});

test('title can be updated', function () {
    $media = Media::factory()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/media/{$media->id}", [
            'title' => 'Updated Title',
        ]);

    $response->assertOk();
    
    $freshMedia = $media->fresh();
    expect($freshMedia->title)->toBe('Updated Title');
});

test('alt text can be updated', function () {
    $media = Media::factory()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/media/{$media->id}", [
            'alt' => 'New alt text',
        ]);

    $response->assertOk();
    
    $freshMedia = $media->fresh();
    expect($freshMedia->alt)->toBe('New alt text');
});

test('collection can be updated', function () {
    $media = Media::factory()->create(['collection' => 'uploads']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/media/{$media->id}", [
            'collection' => 'avatars',
        ]);

    $response->assertOk();
    
    $freshMedia = $media->fresh();
    expect($freshMedia->collection)->toBe('avatars');
});

test('can update soft deleted media', function () {
    $media = Media::factory()->create([
        'title' => 'Old Title',
        'deleted_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/media/{$media->id}", [
            'title' => 'New Title',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.title', 'New Title');
});

test('updated_at changes after update', function () {
    $media = Media::factory()->create(['updated_at' => now()->subHour()]);
    $oldUpdatedAt = $media->updated_at;

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/media/{$media->id}", [
            'title' => 'Updated Title',
        ]);

    $response->assertOk();
    
    $freshMedia = $media->fresh();
    expect($freshMedia->updated_at->isAfter($oldUpdatedAt))->toBeTrue();
});

test('can update multiple fields at once', function () {
    $media = Media::factory()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->putJson("/api/v1/admin/media/{$media->id}", [
            'title' => 'New Title',
            'alt' => 'New Alt',
            'collection' => 'new-collection',
        ]);

    $response->assertOk();
    
    $freshMedia = $media->fresh();
    expect($freshMedia->title)->toBe('New Title')
        ->and($freshMedia->alt)->toBe('New Alt')
        ->and($freshMedia->collection)->toBe('new-collection');
});

