<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Feature-тесты для массовой загрузки медиа-файлов:
 * POST /api/v1/admin/media/bulk - массовая загрузка медиа-файлов
 */

beforeEach(function () {
    Storage::fake('media');
    $this->user = User::factory()->create(['is_admin' => true]);
});

test('admin can bulk upload multiple images', function () {
    $file1 = UploadedFile::fake()->image('image1.jpg', 1920, 1080);
    $file2 = UploadedFile::fake()->image('image2.jpg', 1280, 720);
    $file3 = UploadedFile::fake()->image('image3.jpg', 800, 600);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk', [
            'files' => [$file1, $file2, $file3],
        ]);

    $response->assertStatus(201)
        ->assertJsonCount(3, 'data');

    expect(Media::count())->toBe(3);

    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'kind',
                'name',
                'ext',
                'mime',
                'size_bytes',
            ],
        ],
    ]);
});

test('bulk upload applies common metadata to all files', function () {
    $file1 = UploadedFile::fake()->image('image1.jpg', 1920, 1080);
    $file2 = UploadedFile::fake()->image('image2.jpg', 1280, 720);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk', [
            'files' => [$file1, $file2],
            'title' => 'Gallery images',
            'alt' => 'Gallery cover',
            'collection' => 'gallery',
        ]);

    $response->assertStatus(201)
        ->assertJsonCount(2, 'data');

    $media1 = Media::first();
    $media2 = Media::skip(1)->first();

    expect($media1->title)->toBe('Gallery images')
        ->and($media1->alt)->toBe('Gallery cover')
        ->and($media1->collection)->toBe('gallery')
        ->and($media2->title)->toBe('Gallery images')
        ->and($media2->alt)->toBe('Gallery cover')
        ->and($media2->collection)->toBe('gallery');
});

test('bulk upload requires files array', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['files']);
});

test('bulk upload validates files count minimum', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk', [
            'files' => [],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['files']);
});

test('bulk upload validates files count maximum', function () {
    $files = [];
    for ($i = 0; $i < 51; $i++) {
        $files[] = UploadedFile::fake()->image("image{$i}.jpg", 100, 100);
    }

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk', [
            'files' => $files,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['files']);
});

test('bulk upload validates file types', function () {
    $file1 = UploadedFile::fake()->image('image1.jpg', 1920, 1080);
    $file2 = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk', [
            'files' => [$file1, $file2],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['files.1']);
});

test('bulk upload validates collection format', function () {
    $file1 = UploadedFile::fake()->image('image1.jpg', 1920, 1080);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk', [
            'files' => [$file1],
            'collection' => 'invalid collection name with spaces!',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['collection']);
});

test('bulk upload normalizes collection slug', function () {
    $file1 = UploadedFile::fake()->image('image1.jpg', 1920, 1080);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk', [
            'files' => [$file1],
            'collection' => 'My Gallery Collection',
        ]);

    $response->assertStatus(201);

    $media = Media::first();
    expect($media->collection)->toBe('my-gallery-collection');
});

test('bulk upload requires authentication', function () {
    $file1 = UploadedFile::fake()->image('image1.jpg', 1920, 1080);

    $response = $this->withoutMiddleware([\App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk', [
            'files' => [$file1],
        ]);

    $response->assertUnauthorized();
});

test('bulk upload requires create permission', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $file1 = UploadedFile::fake()->image('image1.jpg', 1920, 1080);

    $response = $this->actingAs($user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk', [
            'files' => [$file1],
        ]);

    $response->assertForbidden();
});

test('bulk upload handles mixed media types', function () {
    $file1 = UploadedFile::fake()->image('image1.jpg', 1920, 1080);
    $file2 = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk', [
            'files' => [$file1, $file2],
        ]);

    $response->assertStatus(201)
        ->assertJsonCount(2, 'data');

    $media = Media::all();
    expect($media->count())->toBe(2)
        ->and($media->first()->kind())->toBe(\App\Domain\Media\MediaKind::Image)
        ->and($media->last()->kind())->toBe(\App\Domain\Media\MediaKind::Document);
});

test('bulk upload creates media with correct relationships', function () {
    $file1 = UploadedFile::fake()->image('image1.jpg', 1920, 1080);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware([\App\Http\Middleware\JwtAuth::class, \App\Http\Middleware\VerifyApiCsrf::class])
        ->postJson('/api/v1/admin/media/bulk', [
            'files' => [$file1],
        ]);

    $response->assertStatus(201);

    $media = Media::first();
    $media->load(['image', 'avMetadata']);

    expect($media->image)->not->toBeNull()
        ->and($media->image->width)->toBe(1920)
        ->and($media->image->height)->toBe(1080);
});

