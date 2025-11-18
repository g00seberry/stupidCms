<?php

declare(strict_types=1);

use App\Domain\Media\MediaVariantStatus;

test('has queued status', function () {
    $status = MediaVariantStatus::Queued;

    expect($status)->toBeInstanceOf(MediaVariantStatus::class)
        ->and($status->value)->toBe('queued');
});

test('has processing status', function () {
    $status = MediaVariantStatus::Processing;

    expect($status)->toBeInstanceOf(MediaVariantStatus::class)
        ->and($status->value)->toBe('processing');
});

test('has ready status', function () {
    $status = MediaVariantStatus::Ready;

    expect($status)->toBeInstanceOf(MediaVariantStatus::class)
        ->and($status->value)->toBe('ready');
});

test('has failed status', function () {
    $status = MediaVariantStatus::Failed;

    expect($status)->toBeInstanceOf(MediaVariantStatus::class)
        ->and($status->value)->toBe('failed');
});

test('can be cast to string', function () {
    $status = MediaVariantStatus::Ready;

    // Enum в PHP 8.1+ не может быть приведен к строке напрямую
    // Используем ->value для получения строкового значения
    expect($status->value)->toBe('ready');
});

test('enum can be created from string value', function () {
    expect(MediaVariantStatus::from('queued'))->toBe(MediaVariantStatus::Queued)
        ->and(MediaVariantStatus::from('processing'))->toBe(MediaVariantStatus::Processing)
        ->and(MediaVariantStatus::from('ready'))->toBe(MediaVariantStatus::Ready)
        ->and(MediaVariantStatus::from('failed'))->toBe(MediaVariantStatus::Failed);
});

test('enum has all expected cases', function () {
    $cases = MediaVariantStatus::cases();

    expect($cases)->toHaveCount(4)
        ->and($cases[0])->toBe(MediaVariantStatus::Queued)
        ->and($cases[1])->toBe(MediaVariantStatus::Processing)
        ->and($cases[2])->toBe(MediaVariantStatus::Ready)
        ->and($cases[3])->toBe(MediaVariantStatus::Failed);
});

