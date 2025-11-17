<?php

declare(strict_types=1);

use App\Domain\Auth\Exceptions\JwtAuthenticationException;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;

/**
 * Unit-тесты для JwtAuthenticationException.
 */

test('creates exception with reason and detail', function () {
    $reason = 'token_expired';
    $detail = 'Token has expired at 2025-11-17 12:00:00';

    $exception = new JwtAuthenticationException($reason, $detail);

    expect($exception->reason)->toBe($reason)
        ->and($exception->detail)->toBe($detail)
        ->and($exception->getMessage())->toContain($reason)
        ->and($exception->getMessage())->toContain($detail);
});

test('exception message includes reason and detail', function () {
    $exception = new JwtAuthenticationException('token_invalid', 'Invalid signature');

    expect($exception->getMessage())->toBe('JWT authentication failed: token_invalid (Invalid signature)');
});

test('converts to error payload with unauthorized code', function () {
    $exception = new JwtAuthenticationException('token_revoked', 'Token has been revoked');
    
    // Use app's ErrorFactory instance (resolved from container)
    $factory = app(\App\Support\Errors\ErrorFactory::class);

    $error = $exception->toError($factory);

    expect($error->code)->toBe(ErrorCode::UNAUTHORIZED)
        ->and($error->detail)->toBe('Authentication is required to access this resource.')
        ->and($error->meta)->toHaveKey('reason')
        ->and($error->meta['reason'])->toBe('token_revoked')
        ->and($error->meta)->toHaveKey('detail')
        ->and($error->meta['detail'])->toBe('Token has been revoked');
});

test('exception is instance of RuntimeException', function () {
    $exception = new JwtAuthenticationException('test', 'test detail');

    expect($exception)->toBeInstanceOf(RuntimeException::class);
});

test('reason and detail are readonly', function () {
    $exception = new JwtAuthenticationException('test', 'test detail');

    $reflection = new ReflectionClass($exception);
    $reasonProperty = $reflection->getProperty('reason');
    $detailProperty = $reflection->getProperty('detail');

    expect($reasonProperty->isReadOnly())->toBeTrue()
        ->and($detailProperty->isReadOnly())->toBeTrue();
});

