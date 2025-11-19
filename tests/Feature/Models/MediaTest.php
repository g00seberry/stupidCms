<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\MediaVariant;
use App\Models\MediaAvMetadata;
use App\Models\MediaImage;

/**
 * Feature-тесты для модели Media.
 *
 * Проверяют реальное взаимодействие модели с базой данных,
 * создание, связи, ULID и валидацию.
 */

test('media can be created with factory', function () {
    $media = Media::factory()->create([
        'title' => 'Test Image',
        'original_name' => 'test.jpg',
    ]);

    expect($media)->toBeInstanceOf(Media::class)
        ->and($media->title)->toBe('Test Image')
        ->and($media->original_name)->toBe('test.jpg')
        ->and($media->exists)->toBeTrue()
        ->and($media->id)->toBeString()
        ->and(strlen($media->id))->toBeGreaterThan(20); // ULID length check

    $this->assertDatabaseHas('media', [
        'id' => $media->id,
        'title' => 'Test Image',
    ]);
});

test('media has unique disk and path combination', function () {
    Media::factory()->create([
        'disk' => 'media',
        'path' => 'test/unique-path.jpg',
    ]);

    $this->expectException(\Illuminate\Database\QueryException::class);

    Media::factory()->create([
        'disk' => 'media',
        'path' => 'test/unique-path.jpg',
    ]);
});

test('media can have multiple variants', function () {
    $media = Media::factory()->create();

    $variant1 = MediaVariant::create([
        'media_id' => $media->id,
        'variant' => 'thumbnail',
        'path' => 'variants/thumb-' . uniqid() . '.jpg',
        'width' => 150,
        'height' => 150,
        'size_bytes' => 5000,
    ]);

    $variant2 = MediaVariant::create([
        'media_id' => $media->id,
        'variant' => 'medium',
        'path' => 'variants/medium-' . uniqid() . '.jpg',
        'width' => 800,
        'height' => 600,
        'size_bytes' => 50000,
    ]);

    $media->load('variants');

    expect($media->variants)->toHaveCount(2)
        ->and($media->variants->pluck('id')->toArray())->toContain($variant1->id, $variant2->id);
});

test('media can have avMetadata', function () {
    $media = Media::factory()->create();

    $metadata = MediaAvMetadata::create([
        'media_id' => $media->id,
        'duration_ms' => 120000,
        'bitrate_kbps' => 1500,
        'video_codec' => 'h264',
        'audio_codec' => 'aac',
    ]);

    $media->load('avMetadata');

    expect($media->avMetadata)->toBeInstanceOf(MediaAvMetadata::class)
        ->and($media->avMetadata->id)->toBe($metadata->id)
        ->and($media->avMetadata->duration_ms)->toBe(120000);
});

test('media can be soft deleted', function () {
    $media = Media::factory()->create();
    $mediaId = $media->id;

    $media->delete();

    expect($media->trashed())->toBeTrue();

    $this->assertSoftDeleted('media', [
        'id' => $mediaId,
    ]);
});

test('media can be restored after soft delete', function () {
    $media = Media::factory()->create();
    $media->delete();

    $media->restore();

    expect($media->trashed())->toBeFalse();

    $this->assertDatabaseHas('media', [
        'id' => $media->id,
        'deleted_at' => null,
    ]);
});

test('media can have image metadata', function () {
    $media = Media::factory()->image()->create();

    $exifData = [
        'Make' => 'Canon',
        'Model' => 'EOS 5D',
        'DateTimeOriginal' => '2024:01:15 14:30:00',
        'GPS' => ['Latitude' => 55.7558, 'Longitude' => 37.6173],
    ];

    $image = \App\Models\MediaImage::factory()->for($media)->create([
        'width' => 1920,
        'height' => 1080,
        'exif_json' => $exifData,
    ]);

    $media->load('image');
    $media->refresh();

    expect($media->image)->toBeInstanceOf(\App\Models\MediaImage::class)
        ->and($media->image->id)->toBe($image->id)
        ->and($media->image->width)->toBe(1920)
        ->and($media->image->height)->toBe(1080)
        ->and($media->image->exif_json)->toBe($exifData)
        ->and($media->image->exif_json['Make'])->toBe('Canon')
        ->and($media->image->exif_json['GPS']['Latitude'])->toBe(55.7558);
});

test('media dimensions are stored correctly via image relationship', function () {
    $media = Media::factory()->image()->withImage([
        'width' => 1920,
        'height' => 1080,
    ])->create();

    $media->load('image');

    expect($media->image->width)->toBe(1920)
        ->and($media->image->height)->toBe(1080)
        ->and($media->image->width)->toBeInt()
        ->and($media->image->height)->toBeInt();
});

test('media file size is stored in bytes', function () {
    $media = Media::factory()->create([
        'size_bytes' => 1048576, // 1MB
    ]);

    $media->refresh();

    expect($media->size_bytes)->toBe(1048576)
        ->and($media->size_bytes)->toBeInt();
});

test('media kind method works for images', function () {
    $media = Media::factory()->image()->create();

    expect($media->kind())->toBe(\App\Domain\Media\MediaKind::Image)
        ->and($media->mime)->toStartWith('image/');
});

test('media kind method works for documents', function () {
    $media = Media::factory()->document()->create();

    expect($media->kind())->toBe(\App\Domain\Media\MediaKind::Document)
        ->and($media->mime)->toBe('application/pdf');
});

test('media kind method works for video', function () {
    $media = Media::factory()->create(['mime' => 'video/mp4']);

    expect($media->kind())->toBe(\App\Domain\Media\MediaKind::Video);
});

test('media kind method works for audio', function () {
    $media = Media::factory()->create(['mime' => 'audio/mpeg']);

    expect($media->kind())->toBe(\App\Domain\Media\MediaKind::Audio);
});

test('media can have null dimensions for non-image files', function () {
    $media = Media::factory()->document()->create();

    $media->load('image');

    // Для документов нет связанной записи MediaImage
    expect($media->image)->toBeNull();
});

test('media checksum is stored correctly', function () {
    $checksum = hash('sha256', 'test-file-content');
    
    $media = Media::factory()->create([
        'checksum_sha256' => $checksum,
    ]);

    $media->refresh();

    expect($media->checksum_sha256)->toBe($checksum)
        ->and(strlen($media->checksum_sha256))->toBe(64); // SHA256 length
});


test('media duration_ms can be set for video via avMetadata relationship', function () {
    $media = Media::factory()->video()->create();

    $avMetadata = MediaAvMetadata::factory()->for($media)->create([
        'duration_ms' => 45000, // 45 seconds
    ]);

    $media->load('avMetadata');

    expect($media->avMetadata)->toBeInstanceOf(MediaAvMetadata::class)
        ->and($media->avMetadata->id)->toBe($avMetadata->id)
        ->and($media->avMetadata->duration_ms)->toBe(45000)
        ->and($media->avMetadata->duration_ms)->toBeInt();
});

