<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\MediaVariant;
use App\Models\MediaMetadata;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Unit-тесты для модели Media.
 *
 * Проверяют структуру модели, ULID, casts, отношения и бизнес-логику
 * без взаимодействия с БД.
 */

test('uses ULID as primary key', function () {
    $media = new Media();

    expect($media->getKeyType())->toBe('string')
        ->and($media->getIncrementing())->toBeFalse();
});

test('casts exif_json to array', function () {
    $media = new Media();

    $casts = $media->getCasts();

    expect($casts)->toHaveKey('exif_json')
        ->and($casts['exif_json'])->toBe('array');
});

test('casts deleted_at to datetime', function () {
    $media = new Media();

    $casts = $media->getCasts();

    expect($casts)->toHaveKey('deleted_at')
        ->and($casts['deleted_at'])->toBe('datetime');
});

test('casts width to integer', function () {
    $media = new Media();

    $casts = $media->getCasts();

    expect($casts)->toHaveKey('width')
        ->and($casts['width'])->toBe('integer');
});

test('casts height to integer', function () {
    $media = new Media();

    $casts = $media->getCasts();

    expect($casts)->toHaveKey('height')
        ->and($casts['height'])->toBe('integer');
});

test('casts duration_ms to integer', function () {
    $media = new Media();

    $casts = $media->getCasts();

    expect($casts)->toHaveKey('duration_ms')
        ->and($casts['duration_ms'])->toBe('integer');
});

test('casts size_bytes to integer', function () {
    $media = new Media();

    $casts = $media->getCasts();

    expect($casts)->toHaveKey('size_bytes')
        ->and($casts['size_bytes'])->toBe('integer');
});

test('has variants relationship', function () {
    $media = new Media();

    $relation = $media->variants();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(MediaVariant::class);
});

test('has metadata relationship', function () {
    $media = new Media();

    $relation = $media->metadata();

    expect($relation)->toBeInstanceOf(HasOne::class)
        ->and($relation->getRelated())->toBeInstanceOf(MediaMetadata::class);
});

test('uses soft deletes', function () {
    $media = new Media();

    $traits = class_uses_recursive($media);

    expect($traits)->toHaveKey(SoftDeletes::class);
});

test('kind returns image for image mime type', function () {
    $media = new Media();
    $media->mime = 'image/jpeg';

    expect($media->kind())->toBe('image');

    $media->mime = 'image/png';
    expect($media->kind())->toBe('image');
});

test('kind returns video for video mime type', function () {
    $media = new Media();
    $media->mime = 'video/mp4';

    expect($media->kind())->toBe('video');

    $media->mime = 'video/webm';
    expect($media->kind())->toBe('video');
});

test('kind returns audio for audio mime type', function () {
    $media = new Media();
    $media->mime = 'audio/mpeg';

    expect($media->kind())->toBe('audio');

    $media->mime = 'audio/wav';
    expect($media->kind())->toBe('audio');
});

test('kind returns document for other mime types', function () {
    $media = new Media();
    $media->mime = 'application/pdf';

    expect($media->kind())->toBe('document');

    $media->mime = 'text/plain';
    expect($media->kind())->toBe('document');

    $media->mime = 'application/zip';
    expect($media->kind())->toBe('document');
});

test('has no guarded attributes', function () {
    $media = new Media();

    $guarded = $media->getGuarded();

    expect($guarded)->toBe([]);
});

test('uses HasUlids trait', function () {
    $media = new Media();

    $traits = class_uses_recursive($media);

    expect($traits)->toHaveKey(\Illuminate\Database\Eloquent\Concerns\HasUlids::class);
});

