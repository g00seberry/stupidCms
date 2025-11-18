<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\MediaImage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unit-тесты для модели MediaImage.
 *
 * Проверяют структуру модели, ULID, casts и отношения
 * без взаимодействия с БД.
 */

test('uses ULID as primary key', function () {
    $image = new MediaImage();

    expect($image->getKeyType())->toBe('string')
        ->and($image->getIncrementing())->toBeFalse();
});

test('casts width to integer', function () {
    $image = new MediaImage();

    $casts = $image->getCasts();

    expect($casts)->toHaveKey('width')
        ->and($casts['width'])->toBe('integer');
});

test('casts height to integer', function () {
    $image = new MediaImage();

    $casts = $image->getCasts();

    expect($casts)->toHaveKey('height')
        ->and($casts['height'])->toBe('integer');
});

test('casts exif_json to array', function () {
    $image = new MediaImage();

    $casts = $image->getCasts();

    expect($casts)->toHaveKey('exif_json')
        ->and($casts['exif_json'])->toBe('array');
});

test('belongs to media', function () {
    $image = new MediaImage();

    $relation = $image->media();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Media::class);
});

test('has no guarded attributes', function () {
    $image = new MediaImage();

    $guarded = $image->getGuarded();

    expect($guarded)->toBe([]);
});

test('uses HasUlids trait', function () {
    $image = new MediaImage();

    $traits = class_uses_recursive($image);

    expect($traits)->toHaveKey(\Illuminate\Database\Eloquent\Concerns\HasUlids::class);
});

