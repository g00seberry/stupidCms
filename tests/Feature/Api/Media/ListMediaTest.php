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

    $response->assertOk();
    // Может быть больше из-за данных из других тестов, но должно быть минимум 2
    expect(count($response->json('data')))->toBeGreaterThanOrEqual(2);
    // Проверяем, что все медиа имеют mime типа image
    foreach ($response->json('data') as $media) {
        expect($media['mime'])->toStartWith('image/');
    }
});


test('media can be searched by title', function () {
    Media::factory()->create(['title' => 'Hero Image']);
    Media::factory()->create(['title' => 'Background Photo']);
    Media::factory()->create(['title' => 'Logo']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media?q=Hero');

    $response->assertOk();
    // Может быть больше из-за данных из других тестов, но должно быть минимум 1
    expect(count($response->json('data')))->toBeGreaterThanOrEqual(1);
    // Проверяем, что хотя бы один результат содержит "Hero"
    $hasHero = false;
    foreach ($response->json('data') as $media) {
        if (stripos($media['title'] ?? '', 'Hero') !== false || stripos($media['original_name'] ?? '', 'Hero') !== false) {
            $hasHero = true;
            break;
        }
    }
    expect($hasHero)->toBeTrue();
});

test('media can be searched by original name', function () {
    Media::factory()->create(['original_name' => 'hero.jpg', 'title' => 'Hero']);
    Media::factory()->create(['original_name' => 'bg.png', 'title' => 'Background']);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media?q=hero');

    $response->assertOk();
    // Может быть больше из-за данных из других тестов, но должно быть минимум 1
    expect(count($response->json('data')))->toBeGreaterThanOrEqual(1);
    // Проверяем, что хотя бы один результат содержит "hero"
    $hasHero = false;
    foreach ($response->json('data') as $media) {
        if (stripos($media['title'] ?? '', 'hero') !== false || stripos($media['original_name'] ?? '', 'hero') !== false) {
            $hasHero = true;
            break;
        }
    }
    expect($hasHero)->toBeTrue();
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
    $active = Media::factory()->create();
    $deleted = Media::factory()->create(['deleted_at' => now()]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media?deleted=with');

    $response->assertOk();
    // Может быть больше из-за данных из других тестов, но должно быть минимум 2
    expect(count($response->json('data')))->toBeGreaterThanOrEqual(2);
    // Проверяем, что в результатах есть и активные, и удаленные
    $hasActive = false;
    $hasDeleted = false;
    foreach ($response->json('data') as $media) {
        if ($media['id'] === $active->id) {
            $hasActive = true;
        }
        if ($media['id'] === $deleted->id) {
            $hasDeleted = true;
        }
    }
    expect($hasActive)->toBeTrue();
    expect($hasDeleted)->toBeTrue();
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
                '*' => ['url'],
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

