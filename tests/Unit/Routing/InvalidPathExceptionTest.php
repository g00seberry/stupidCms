<?php

declare(strict_types=1);

use App\Domain\Routing\Exceptions\InvalidPathException;
use App\Support\Errors\ErrorCode;

/**
 * Unit-тесты для InvalidPathException.
 */

test('creates exception with message', function () {
    $exception = new InvalidPathException('Bad path');

    expect($exception->getMessage())->toBe('Bad path');
});

test('creates exception with default message', function () {
    $exception = new InvalidPathException();

    expect($exception->getMessage())->toBe('Invalid path');
});

test('exception is instance of Exception', function () {
    $exception = new InvalidPathException();

    expect($exception)->toBeInstanceOf(Exception::class);
});

test('converts to error payload with validation error code', function () {
    $exception = new InvalidPathException('Path is empty');
    $errorsConfig = require __DIR__ . '/../../../config/errors.php';
    $kernel = \App\Support\Errors\ErrorKernel::fromConfig($errorsConfig);
    $factory = $kernel->factory();

    $error = $exception->toError($factory);

    expect($error->code)->toBe(ErrorCode::VALIDATION_ERROR)
        ->and($error->detail)->toBe('Path is empty');
});

