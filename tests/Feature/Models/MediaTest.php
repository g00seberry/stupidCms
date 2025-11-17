<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\MediaVariant;
use App\Models\MediaMetadata;

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

test('media can have metadata', function () {
    $media = Media::factory()->create();

    $metadata = MediaMetadata::create([
        'media_id' => $media->id,
        'duration_ms' => 120000,
        'bitrate_kbps' => 1500,
        'video_codec' => 'h264',
        'audio_codec' => 'aac',
    ]);

    $media->load('metadata');

    expect($media->metadata)->toBeInstanceOf(MediaMetadata::class)
        ->and($media->metadata->id)->toBe($metadata->id)
        ->and($media->metadata->duration_ms)->toBe(120000);
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

test('media exif json stores metadata', function () {
    $exifData = [
        'Make' => 'Canon',
        'Model' => 'EOS 5D',
        'DateTimeOriginal' => '2024:01:15 14:30:00',
        'GPS' => ['Latitude' => 55.7558, 'Longitude' => 37.6173],
    ];

    $media = Media::factory()->create([
        'exif_json' => $exifData,
    ]);

    $media->refresh();

    expect($media->exif_json)->toBe($exifData)
        ->and($media->exif_json['Make'])->toBe('Canon')
        ->and($media->exif_json['GPS']['Latitude'])->toBe(55.7558);
});

test('media dimensions are stored correctly', function () {
    $media = Media::factory()->create([
        'width' => 1920,
        'height' => 1080,
    ]);

    $media->refresh();

    expect($media->width)->toBe(1920)
        ->and($media->height)->toBe(1080)
        ->and($media->width)->toBeInt()
        ->and($media->height)->toBeInt();
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

    expect($media->kind())->toBe('image')
        ->and($media->mime)->toStartWith('image/');
});

test('media kind method works for documents', function () {
    $media = Media::factory()->document()->create();

    expect($media->kind())->toBe('document')
        ->and($media->mime)->toBe('application/pdf');
});

test('media kind method works for video', function () {
    $media = Media::factory()->create(['mime' => 'video/mp4']);

    expect($media->kind())->toBe('video');
});

test('media kind method works for audio', function () {
    $media = Media::factory()->create(['mime' => 'audio/mpeg']);

    expect($media->kind())->toBe('audio');
});

test('media can have null dimensions for non-image files', function () {
    $media = Media::factory()->document()->create([
        'width' => null,
        'height' => null,
    ]);

    $media->refresh();

    expect($media->width)->toBeNull()
        ->and($media->height)->toBeNull();
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

test('media collection can be set', function () {
    $media = Media::factory()->create([
        'collection' => 'avatars',
    ]);

    expect($media->collection)->toBe('avatars');

    $this->assertDatabaseHas('media', [
        'id' => $media->id,
        'collection' => 'avatars',
    ]);
});

test('media duration_ms can be set for video', function () {
    $media = Media::factory()->create([
        'mime' => 'video/mp4',
        'duration_ms' => 45000, // 45 seconds
    ]);

    $media->refresh();

    expect($media->duration_ms)->toBe(45000)
        ->and($media->duration_ms)->toBeInt();
});

