<?php

declare(strict_types=1);

use App\Models\MediaVariant;
use App\Models\Media;
use App\Domain\Media\MediaVariantStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

/**
 * Unit-тесты для модели MediaVariant.
 *
 * Проверяют структуру модели, ULID, casts, отношения и enum.
 */

uses(TestCase::class);

test('uses ULID as primary key', function () {
    $variant = new MediaVariant();

    expect($variant->getKeyType())->toBe('string')
        ->and($variant->getIncrementing())->toBeFalse();
});

test('casts status to MediaVariantStatus enum', function () {
    $variant = new MediaVariant();

    $casts = $variant->getCasts();

    expect($casts)->toHaveKey('status')
        ->and($casts['status'])->toBe(MediaVariantStatus::class);
});

test('casts started_at to immutable_datetime', function () {
    $variant = new MediaVariant();

    $casts = $variant->getCasts();

    expect($casts)->toHaveKey('started_at')
        ->and($casts['started_at'])->toBe('immutable_datetime');
});

test('casts finished_at to immutable_datetime', function () {
    $variant = new MediaVariant();

    $casts = $variant->getCasts();

    expect($casts)->toHaveKey('finished_at')
        ->and($casts['finished_at'])->toBe('immutable_datetime');
});

test('belongs to media', function () {
    $variant = new MediaVariant();

    $relation = $variant->media();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Media::class);
});

test('has no guarded attributes', function () {
    $variant = new MediaVariant();

    $guarded = $variant->getGuarded();

    expect($guarded)->toBe([]);
});

test('uses HasUlids trait', function () {
    $variant = new MediaVariant();

    $traits = class_uses_recursive($variant);

    expect($traits)->toHaveKey(\Illuminate\Database\Eloquent\Concerns\HasUlids::class);
});

test('table name is media_variants', function () {
    $variant = new MediaVariant();

    expect($variant->getTable())->toBe('media_variants');
});

