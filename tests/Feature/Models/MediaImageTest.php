<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\MediaImage;

/**
 * Feature-тесты для модели MediaImage.
 *
 * Проверяют реальное взаимодействие модели с базой данных,
 * создание, связи и хранение метаданных изображений.
 */

test('image can be created with factory', function () {
    $image = MediaImage::factory()->create();

    expect($image)->toBeInstanceOf(MediaImage::class)
        ->and($image->exists)->toBeTrue()
        ->and($image->id)->toBeString()
        ->and(strlen($image->id))->toBeGreaterThan(20); // ULID length check

    $this->assertDatabaseHas('media_images', [
        'id' => $image->id,
    ]);
});

test('image belongs to media', function () {
    $media = Media::factory()->image()->create();
    $image = MediaImage::factory()->for($media)->create();

    $image->load('media');

    expect($image->media)->toBeInstanceOf(Media::class)
        ->and($image->media->id)->toBe($media->id);
});

test('image stores dimensions correctly', function () {
    $image = MediaImage::factory()->create([
        'width' => 1920,
        'height' => 1080,
    ]);

    $image->refresh();

    expect($image->width)->toBe(1920)
        ->and($image->height)->toBe(1080)
        ->and($image->width)->toBeInt()
        ->and($image->height)->toBeInt();
});

test('image stores exif metadata', function () {
    $exifData = [
        'Make' => 'Canon',
        'Model' => 'EOS 5D',
        'DateTimeOriginal' => '2024:01:15 14:30:00',
        'GPS' => ['Latitude' => 55.7558, 'Longitude' => 37.6173],
    ];

    $image = MediaImage::factory()->create([
        'exif_json' => $exifData,
    ]);

    $image->refresh();

    expect($image->exif_json)->toBe($exifData)
        ->and($image->exif_json['Make'])->toBe('Canon')
        ->and($image->exif_json['GPS']['Latitude'])->toBe(55.7558);
});

test('image width is cast to integer', function () {
    $image = MediaImage::factory()->create([
        'width' => 1920,
    ]);

    $image->refresh();

    expect($image->width)->toBeInt()
        ->and($image->width)->toBe(1920);
});

test('image height is cast to integer', function () {
    $image = MediaImage::factory()->create([
        'height' => 1080,
    ]);

    $image->refresh();

    expect($image->height)->toBeInt()
        ->and($image->height)->toBe(1080);
});

test('image exif_json is cast to array', function () {
    $exifData = ['Make' => 'Canon', 'Model' => 'EOS 5D'];

    $image = MediaImage::factory()->create([
        'exif_json' => $exifData,
    ]);

    $image->refresh();

    expect($image->exif_json)->toBeArray()
        ->and($image->exif_json)->toBe($exifData);
});

test('image can have null exif_json', function () {
    $image = MediaImage::factory()->create([
        'exif_json' => null,
    ]);

    $image->refresh();

    expect($image->exif_json)->toBeNull();
});

test('image auto generates ULID on creation', function () {
    $media = Media::factory()->image()->create();
    
    $image = new MediaImage([
        'media_id' => $media->id,
        'width' => 1920,
        'height' => 1080,
    ]);

    $image->save();

    expect($image->id)->toBeString()
        ->and($image->id)->not->toBeNull()
        ->and(strlen($image->id))->toBeGreaterThan(20);
});

test('image has unique media_id constraint', function () {
    $media = Media::factory()->image()->create();
    
    MediaImage::factory()->for($media)->create();

    $this->expectException(\Illuminate\Database\QueryException::class);

    MediaImage::factory()->for($media)->create();
});

