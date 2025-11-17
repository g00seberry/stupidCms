<?php

declare(strict_types=1);

use App\Domain\Routing\Exceptions\ForbiddenReservationRelease;
use App\Support\Errors\ErrorCode;

/**
 * Unit-тесты для ForbiddenReservationRelease.
 */

test('creates exception with path owner and attempted source', function () {
    $exception = new ForbiddenReservationRelease('/admin', 'system', 'plugin');

    expect($exception->path)->toBe('/admin')
        ->and($exception->owner)->toBe('system')
        ->and($exception->attemptedSource)->toBe('plugin')
        ->and($exception->getMessage())->toContain('/admin')
        ->and($exception->getMessage())->toContain('system')
        ->and($exception->getMessage())->toContain('plugin');
});

test('creates exception with custom message', function () {
    $exception = new ForbiddenReservationRelease('/api', 'core', 'plugin', 'Cannot release');

    expect($exception->getMessage())->toBe('Cannot release');
});

test('readonly properties cannot be modified', function () {
    $exception = new ForbiddenReservationRelease('/test', 'owner', 'other');

    $reflection = new ReflectionClass($exception);
    $pathProperty = $reflection->getProperty('path');
    $ownerProperty = $reflection->getProperty('owner');
    $attemptedProperty = $reflection->getProperty('attemptedSource');

    expect($pathProperty->isReadOnly())->toBeTrue()
        ->and($ownerProperty->isReadOnly())->toBeTrue()
        ->and($attemptedProperty->isReadOnly())->toBeTrue();
});

test('converts to error payload with forbidden code', function () {
    $exception = new ForbiddenReservationRelease('/admin', 'system', 'plugin');
    $factory = app(\App\Support\Errors\ErrorFactory::class);

    $error = $exception->toError($factory);

    expect($error->code)->toBe(ErrorCode::FORBIDDEN)
        ->and($error->meta)->toHaveKey('path')
        ->and($error->meta['path'])->toBe('/admin')
        ->and($error->meta)->toHaveKey('owner')
        ->and($error->meta['owner'])->toBe('system')
        ->and($error->meta)->toHaveKey('attempted_source')
        ->and($error->meta['attempted_source'])->toBe('plugin');
});

