<?php

declare(strict_types=1);

use App\Models\MediaMetadata;
use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unit-тесты для модели MediaMetadata.
 *
 * Проверяют структуру модели, ULID, casts и отношения
 * без взаимодействия с БД.
 */

test('uses ULID as primary key', function () {
    $metadata = new MediaMetadata();

    expect($metadata->getKeyType())->toBe('string')
        ->and($metadata->getIncrementing())->toBeFalse();
});

test('casts duration_ms to integer', function () {
    $metadata = new MediaMetadata();

    $casts = $metadata->getCasts();

    expect($casts)->toHaveKey('duration_ms')
        ->and($casts['duration_ms'])->toBe('integer');
});

test('casts bitrate_kbps to integer', function () {
    $metadata = new MediaMetadata();

    $casts = $metadata->getCasts();

    expect($casts)->toHaveKey('bitrate_kbps')
        ->and($casts['bitrate_kbps'])->toBe('integer');
});

test('casts frame_rate to float', function () {
    $metadata = new MediaMetadata();

    $casts = $metadata->getCasts();

    expect($casts)->toHaveKey('frame_rate')
        ->and($casts['frame_rate'])->toBe('float');
});

test('casts frame_count to integer', function () {
    $metadata = new MediaMetadata();

    $casts = $metadata->getCasts();

    expect($casts)->toHaveKey('frame_count')
        ->and($casts['frame_count'])->toBe('integer');
});

test('belongs to media', function () {
    $metadata = new MediaMetadata();

    $relation = $metadata->media();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Media::class);
});

test('has no guarded attributes', function () {
    $metadata = new MediaMetadata();

    $guarded = $metadata->getGuarded();

    expect($guarded)->toBe([]);
});

