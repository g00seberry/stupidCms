<?php

declare(strict_types=1);

use App\Domain\Routing\Exceptions\PathAlreadyReservedException;
use App\Support\Errors\ErrorCode;

/**
 * Unit-тесты для PathAlreadyReservedException.
 */

test('creates exception with path and owner', function () {
    $exception = new PathAlreadyReservedException('/admin', 'system');

    expect($exception->path)->toBe('/admin')
        ->and($exception->owner)->toBe('system')
        ->and($exception->getMessage())->toContain('/admin')
        ->and($exception->getMessage())->toContain('system');
});

test('creates exception with custom message', function () {
    $exception = new PathAlreadyReservedException('/api', 'plugin', 'Custom error message');

    expect($exception->getMessage())->toBe('Custom error message');
});

test('readonly properties cannot be modified', function () {
    $exception = new PathAlreadyReservedException('/test', 'owner');

    $reflection = new ReflectionClass($exception);
    $pathProperty = $reflection->getProperty('path');
    $ownerProperty = $reflection->getProperty('owner');

    expect($pathProperty->isReadOnly())->toBeTrue()
        ->and($ownerProperty->isReadOnly())->toBeTrue();
});

test('converts to error payload with conflict code', function () {
    $exception = new PathAlreadyReservedException('/admin', 'system');
    $errorsConfig = require __DIR__ . '/../../../config/errors.php';
    $kernel = \App\Support\Errors\ErrorKernel::fromConfig($errorsConfig);
    $factory = $kernel->factory();

    $error = $exception->toError($factory);

    expect($error->code)->toBe(ErrorCode::CONFLICT)
        ->and($error->meta)->toHaveKey('path')
        ->and($error->meta['path'])->toBe('/admin')
        ->and($error->meta)->toHaveKey('owner')
        ->and($error->meta['owner'])->toBe('system');
});

