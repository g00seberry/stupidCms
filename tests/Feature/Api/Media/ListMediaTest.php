<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Media;

/**
 * Feature-тесты для GET /api/v1/admin/media
 * 
 * Тестирует список медиафайлов с фильтрацией, пагинацией и поиском
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
});

test('admin can list media', function () {
    Media::factory()->count(3)->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'kind', 'name', 'ext', 'mime', 'size_bytes'],
            ],
            'links',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonCount(3, 'data');
});

test('media are paginated', function () {
    Media::factory()->count(20)->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media?per_page=10');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 20)
        ->assertJsonCount(10, 'data');
});

test('media can be filtered by mime type', function () {
    Media::factory()->create(['mime' => 'image/jpeg']);
    Media::factory()->create(['mime' => 'image/png']);
    Media::factory()->create(['mime' => 'video/mp4']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media?mime=image');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('media can be filtered by collection', function () {
    Media::factory()->create(['collection' => 'uploads']);
    Media::factory()->create(['collection' => 'uploads']);
    Media::factory()->create(['collection' => 'avatars']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media?collection=uploads');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('media can be searched by title', function () {
    Media::factory()->create(['title' => 'Hero Image']);
    Media::factory()->create(['title' => 'Background Photo']);
    Media::factory()->create(['title' => 'Logo']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media?q=Hero');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Hero Image');
});

test('media can be searched by original name', function () {
    Media::factory()->create(['original_name' => 'hero.jpg', 'title' => 'Hero']);
    Media::factory()->create(['original_name' => 'bg.png', 'title' => 'Background']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media?q=hero');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('media can be sorted by size', function () {
    $large = Media::factory()->create(['size_bytes' => 1000000]);
    $small = Media::factory()->create(['size_bytes' => 100000]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media?sort=size_bytes&order=desc');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $large->id)
        ->assertJsonPath('data.1.id', $small->id);
});

test('media can be sorted by created_at', function () {
    $old = Media::factory()->create(['created_at' => now()->subDays(2)]);
    $new = Media::factory()->create(['created_at' => now()]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media?sort=created_at&order=desc');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.1.id', $old->id);
});

test('trashed media are excluded by default', function () {
    Media::factory()->create();
    Media::factory()->create(['deleted_at' => now()]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('trashed media can be included with filter', function () {
    Media::factory()->create();
    Media::factory()->create(['deleted_at' => now()]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media?deleted=with');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('only trashed media can be shown', function () {
    Media::factory()->create();
    Media::factory()->create(['deleted_at' => now()]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media?deleted=only');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('media response includes preview and download urls', function () {
    // Создаем разные типы медиа
    $image = Media::factory()->image()->create();
    $video = Media::factory()->video()->create();
    $document = Media::factory()->document()->create();

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['download_url'],
            ],
        ]);

    // Проверяем, что изображения имеют preview_urls
    $imageData = collect($response->json('data'))->firstWhere('id', $image->id);
    expect($imageData)->toHaveKey('preview_urls')
        ->and($imageData['kind'])->toBe('image');

    // Проверяем, что видео не имеют preview_urls
    $videoData = collect($response->json('data'))->firstWhere('id', $video->id);
    expect($videoData)->not->toHaveKey('preview_urls')
        ->and($videoData['kind'])->toBe('video');

    // Проверяем, что документы не имеют preview_urls
    $documentData = collect($response->json('data'))->firstWhere('id', $document->id);
    expect($documentData)->not->toHaveKey('preview_urls')
        ->and($documentData['kind'])->toBe('document');
});

test('unauthenticated request returns 401', function () {
    $response = $this->getJson('/api/v1/admin/media');

    $response->assertUnauthorized();
});

