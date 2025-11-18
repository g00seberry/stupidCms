<?php

declare(strict_types=1);

use App\Domain\Media\MediaDeletedFilter;

test('filters only deleted', function () {
    $filter = MediaDeletedFilter::OnlyDeleted;

    expect($filter)->toBeInstanceOf(MediaDeletedFilter::class)
        ->and($filter->value)->toBe('only');
});

test('filters only not deleted', function () {
    $filter = MediaDeletedFilter::DefaultOnlyNotDeleted;

    expect($filter)->toBeInstanceOf(MediaDeletedFilter::class)
        ->and($filter->value)->toBe('default');
});

test('filters all including deleted', function () {
    $filter = MediaDeletedFilter::WithDeleted;

    expect($filter)->toBeInstanceOf(MediaDeletedFilter::class)
        ->and($filter->value)->toBe('with');
});

test('enum can be created from string value', function () {
    $filter = MediaDeletedFilter::from('default');

    expect($filter)->toBe(MediaDeletedFilter::DefaultOnlyNotDeleted);
});

test('enum can be created from string value with', function () {
    $filter = MediaDeletedFilter::from('with');

    expect($filter)->toBe(MediaDeletedFilter::WithDeleted);
});

test('enum can be created from string value only', function () {
    $filter = MediaDeletedFilter::from('only');

    expect($filter)->toBe(MediaDeletedFilter::OnlyDeleted);
});

test('enum has all expected cases', function () {
    $cases = MediaDeletedFilter::cases();

    expect($cases)->toHaveCount(3)
        ->and($cases[0])->toBe(MediaDeletedFilter::DefaultOnlyNotDeleted)
        ->and($cases[1])->toBe(MediaDeletedFilter::WithDeleted)
        ->and($cases[2])->toBe(MediaDeletedFilter::OnlyDeleted);
});

