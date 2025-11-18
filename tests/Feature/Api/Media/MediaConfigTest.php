<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Config;

/**
 * Feature-тесты для GET /api/v1/admin/media/config
 *
 * Тестирует получение конфигурации системы медиа-файлов:
 * - разрешенные MIME-типы
 * - максимальный размер загрузки
 * - варианты изображений
 */

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
});

test('admin can get media config', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media/config');

    $response->assertOk()
        ->assertJsonStructure([
            'allowed_mimes',
            'max_upload_mb',
            'image_variants',
        ]);
});

test('media config returns allowed mime types', function () {
    $expectedMimes = config('media.allowed_mimes', []);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media/config');

    $response->assertOk()
        ->assertJsonPath('allowed_mimes', $expectedMimes)
        ->assertJsonCount(count($expectedMimes), 'allowed_mimes');
});

test('media config returns max upload size', function () {
    $expectedMaxMb = config('media.max_upload_mb', 1024);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media/config');

    $response->assertOk()
        ->assertJsonPath('max_upload_mb', $expectedMaxMb);
});

test('media config returns image variants', function () {
    $expectedVariants = config('media.variants', []);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media/config');

    $response->assertOk()
        ->assertJsonStructure([
            'image_variants' => [
                '*' => ['max', 'format', 'quality'],
            ],
        ]);

    foreach ($expectedVariants as $name => $config) {
        $response->assertJsonPath("image_variants.{$name}.max", $config['max'] ?? null)
            ->assertJsonPath("image_variants.{$name}.format", $config['format'] ?? null)
            ->assertJsonPath("image_variants.{$name}.quality", $config['quality'] ?? null);
    }
});

test('media config includes all configured variants', function () {
    Config::set('media.variants', [
        'thumbnail' => ['max' => 320],
        'medium' => ['max' => 1024, 'format' => 'webp'],
        'large' => ['max' => 2048, 'quality' => 90],
    ]);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media/config');

    $response->assertOk()
        ->assertJsonCount(3, 'image_variants')
        ->assertJsonPath('image_variants.thumbnail.max', 320)
        ->assertJsonPath('image_variants.thumbnail.format', null)
        ->assertJsonPath('image_variants.thumbnail.quality', null)
        ->assertJsonPath('image_variants.medium.max', 1024)
        ->assertJsonPath('image_variants.medium.format', 'webp')
        ->assertJsonPath('image_variants.medium.quality', null)
        ->assertJsonPath('image_variants.large.max', 2048)
        ->assertJsonPath('image_variants.large.format', null)
        ->assertJsonPath('image_variants.large.quality', 90);
});

test('unauthenticated request returns 401', function () {
    $response = $this->getJson('/api/v1/admin/media/config');

    $response->assertUnauthorized();
});

test('non-admin user without viewAny permission returns 403', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->getJson('/api/v1/admin/media/config');

    $response->assertForbidden();
});

