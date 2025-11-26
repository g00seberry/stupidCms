<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\ValidationError;
use Tests\TestCase;

uses(TestCase::class);

test('can create validation error with all properties', function () {
    $error = new ValidationError(
        field: 'content_json.title',
        code: 'BLUEPRINT_MIN_LENGTH',
        params: ['min' => 1, 'max' => 500],
        message: 'The title must be at least 1 characters.',
        pathId: 123
    );

    expect($error->field)->toBe('content_json.title')
        ->and($error->code)->toBe('BLUEPRINT_MIN_LENGTH')
        ->and($error->params)->toBe(['min' => 1, 'max' => 500])
        ->and($error->message)->toBe('The title must be at least 1 characters.')
        ->and($error->pathId)->toBe(123);
});

test('can create validation error with minimal properties', function () {
    $error = new ValidationError(
        field: 'content_json.title',
        code: 'BLUEPRINT_REQUIRED'
    );

    expect($error->field)->toBe('content_json.title')
        ->and($error->code)->toBe('BLUEPRINT_REQUIRED')
        ->and($error->params)->toBeEmpty()
        ->and($error->message)->toBeNull()
        ->and($error->pathId)->toBeNull();
});

test('can get param value', function () {
    $error = new ValidationError(
        field: 'content_json.title',
        code: 'BLUEPRINT_MIN_LENGTH',
        params: ['min' => 1, 'max' => 500]
    );

    expect($error->getParam('min'))->toBe(1)
        ->and($error->getParam('max'))->toBe(500)
        ->and($error->getParam('nonexistent'))->toBeNull()
        ->and($error->getParam('nonexistent', 'default'))->toBe('default');
});

test('can check if param exists', function () {
    $error = new ValidationError(
        field: 'content_json.title',
        code: 'BLUEPRINT_MIN_LENGTH',
        params: ['min' => 1]
    );

    expect($error->hasParam('min'))->toBeTrue()
        ->and($error->hasParam('max'))->toBeFalse();
});

test('handles nested field paths', function () {
    $error = new ValidationError(
        field: 'content_json.author.name',
        code: 'BLUEPRINT_REQUIRED'
    );

    expect($error->field)->toBe('content_json.author.name');
});

