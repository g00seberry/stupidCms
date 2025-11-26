<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\ValidationError;
use App\Domain\Blueprint\Validation\ValidationResult;
use Tests\TestCase;

uses(TestCase::class);

test('can add and retrieve errors', function () {
    $result = new ValidationResult();
    $error = new ValidationError('content_json.title', 'BLUEPRINT_REQUIRED');

    $result->addError('content_json.title', $error);

    expect($result->hasErrors())->toBeTrue()
        ->and($result->hasErrorsForField('content_json.title'))->toBeTrue()
        ->and($result->getErrorsForField('content_json.title'))->toHaveCount(1)
        ->and($result->getErrorsForField('content_json.title')[0])->toBe($error);
});

test('returns false when no errors', function () {
    $result = new ValidationResult();

    expect($result->hasErrors())->toBeFalse()
        ->and($result->getFieldsWithErrors())->toBeEmpty();
});

test('can add multiple errors for same field', function () {
    $result = new ValidationResult();
    $error1 = new ValidationError('content_json.title', 'BLUEPRINT_REQUIRED');
    $error2 = new ValidationError('content_json.title', 'BLUEPRINT_MIN_LENGTH', ['min' => 1]);

    $result->addError('content_json.title', $error1);
    $result->addError('content_json.title', $error2);

    expect($result->getErrorsForField('content_json.title'))->toHaveCount(2);
});

test('can add errors for different fields', function () {
    $result = new ValidationResult();
    $error1 = new ValidationError('content_json.title', 'BLUEPRINT_REQUIRED');
    $error2 = new ValidationError('content_json.phone', 'BLUEPRINT_PATTERN');

    $result->addError('content_json.title', $error1);
    $result->addError('content_json.phone', $error2);

    expect($result->getFieldsWithErrors())->toHaveCount(2)
        ->and($result->getFieldsWithErrors())->toContain('content_json.title')
        ->and($result->getFieldsWithErrors())->toContain('content_json.phone');
});

test('returns empty array for field without errors', function () {
    $result = new ValidationResult();

    expect($result->getErrorsForField('content_json.nonexistent'))->toBeArray()
        ->and($result->getErrorsForField('content_json.nonexistent'))->toBeEmpty()
        ->and($result->hasErrorsForField('content_json.nonexistent'))->toBeFalse();
});

test('can get all errors', function () {
    $result = new ValidationResult();
    $error1 = new ValidationError('content_json.title', 'BLUEPRINT_REQUIRED');
    $error2 = new ValidationError('content_json.phone', 'BLUEPRINT_PATTERN');

    $result->addError('content_json.title', $error1);
    $result->addError('content_json.phone', $error2);

    $allErrors = $result->getErrors();

    expect($allErrors)->toHaveCount(2)
        ->and($allErrors)->toHaveKey('content_json.title')
        ->and($allErrors)->toHaveKey('content_json.phone');
});

