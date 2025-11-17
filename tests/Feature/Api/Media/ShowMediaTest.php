<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Media;

/**
 * Feature-тесты для GET /api/v1/admin/media/{id}
 * 
 * Тестирует просмотр конкретного медиафайла
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
});

test('admin can view media', function () {
    $media = Media::factory()->create(['title' => 'Test Image']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/media/{$media->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'kind', 'name', 'mime', 'size_bytes', 'title'],
        ])
        ->assertJsonPath('data.id', $media->id)
        ->assertJsonPath('data.title', 'Test Image');
});

test('media includes dimensions for images', function () {
    $media = Media::factory()->create([
        'mime' => 'image/jpeg',
        'width' => 1920,
        'height' => 1080,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/media/{$media->id}");

    $response->assertOk()
        ->assertJsonPath('data.width', 1920)
        ->assertJsonPath('data.height', 1080);
});

test('media includes duration for videos', function () {
    $media = Media::factory()->create([
        'mime' => 'video/mp4',
        'duration_ms' => 120000,
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/media/{$media->id}");

    $response->assertOk()
        ->assertJsonPath('data.duration_ms', 120000);
});

test('not found returns 404', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media/01JGAB8F0000000000000000');

    $response->assertNotFound();
});

test('can view soft deleted media', function () {
    $media = Media::factory()->create(['deleted_at' => now()]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/media/{$media->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $media->id);
});

test('media includes all metadata', function () {
    $media = Media::factory()->create([
        'title' => 'Test Title',
        'alt' => 'Test Alt',
        'collection' => 'uploads',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/media/{$media->id}");

    $response->assertOk()
        ->assertJsonPath('data.title', 'Test Title')
        ->assertJsonPath('data.alt', 'Test Alt')
        ->assertJsonPath('data.collection', 'uploads');
});

test('media includes timestamps', function () {
    $media = Media::factory()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/media/{$media->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['created_at', 'updated_at', 'deleted_at'],
        ]);
});

test('media includes preview and download urls', function () {
    $media = Media::factory()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/media/{$media->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['preview_urls', 'download_url'],
        ]);
});

