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

test('media includes dimensions for images via image relationship', function () {
    $media = Media::factory()->image()->withImage([
        'width' => 1920,
        'height' => 1080,
    ])->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/media/{$media->id}");

    $response->assertOk()
        ->assertJsonPath('data.kind', 'image')
        ->assertJsonPath('data.width', 1920)
        ->assertJsonPath('data.height', 1080)
        ->assertJsonStructure([
            'data' => ['preview_urls'],
        ])
        // Для изображений не должно быть duration_ms и AV-полей
        ->assertJsonMissingPath('data.duration_ms')
        ->assertJsonMissingPath('data.bitrate_kbps')
        ->assertJsonMissingPath('data.frame_rate')
        ->assertJsonMissingPath('data.video_codec')
        ->assertJsonMissingPath('data.audio_codec');
});

test('media includes duration for videos via avMetadata relationship', function () {
    $media = Media::factory()->video()->create();

    \App\Models\MediaAvMetadata::factory()->for($media)->create([
        'duration_ms' => 120000,
        'bitrate_kbps' => 3500,
        'frame_rate' => 30.0,
        'frame_count' => 3600,
        'video_codec' => 'h264',
        'audio_codec' => 'aac',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/media/{$media->id}");

    $response->assertOk()
        ->assertJsonPath('data.kind', 'video')
        ->assertJsonPath('data.duration_ms', 120000)
        ->assertJsonPath('data.bitrate_kbps', 3500)
        ->assertJsonPath('data.frame_rate', 30)
        ->assertJsonPath('data.frame_count', 3600)
        ->assertJsonPath('data.video_codec', 'h264')
        ->assertJsonPath('data.audio_codec', 'aac')
        // Для видео не должно быть width, height, preview_urls
        ->assertJsonMissingPath('data.width')
        ->assertJsonMissingPath('data.height')
        ->assertJsonMissingPath('data.preview_urls');
});

test('media includes audio metadata for audio files', function () {
    $media = Media::factory()->audio()->create();

    \App\Models\MediaAvMetadata::factory()->for($media)->create([
        'duration_ms' => 180000,
        'bitrate_kbps' => 256,
        'audio_codec' => 'mp3',
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/media/{$media->id}");

    $response->assertOk()
        ->assertJsonPath('data.kind', 'audio')
        ->assertJsonPath('data.duration_ms', 180000)
        ->assertJsonPath('data.bitrate_kbps', 256)
        ->assertJsonPath('data.audio_codec', 'mp3')
        // Для аудио не должно быть видео-специфичных полей
        ->assertJsonMissingPath('data.width')
        ->assertJsonMissingPath('data.height')
        ->assertJsonMissingPath('data.preview_urls')
        ->assertJsonMissingPath('data.frame_rate')
        ->assertJsonMissingPath('data.frame_count')
        ->assertJsonMissingPath('data.video_codec');
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
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/media/{$media->id}");

    $response->assertOk()
        ->assertJsonPath('data.title', 'Test Title')
        ->assertJsonPath('data.alt', 'Test Alt');
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
    // Для изображений должны быть preview_urls
    $image = Media::factory()->image()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/media/{$image->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['preview_urls', 'url'],
        ])
        ->assertJsonPath('data.kind', 'image');

    // Для документов preview_urls не должно быть
    $document = Media::factory()->document()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson("/api/v1/admin/media/{$document->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['url'],
        ])
        ->assertJsonMissingPath('data.preview_urls')
        ->assertJsonPath('data.kind', 'document');
});

