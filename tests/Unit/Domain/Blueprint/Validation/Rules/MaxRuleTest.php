<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\MaxRule;
use Tests\TestCase;

uses(TestCase::class);

test('returns correct type', function () {
    $rule = new MaxRule(100, 'string');

    expect($rule->getType())->toBe('max');
});

test('returns correct params', function () {
    $rule = new MaxRule(500, 'string');

    $params = $rule->getParams();

    expect($params)->toBeArray()
        ->and($params)->toHaveKey('value')
        ->and($params)->toHaveKey('data_type')
        ->and($params['value'])->toBe(500)
        ->and($params['data_type'])->toBe('string');
});

test('can get value', function () {
    $rule = new MaxRule(100, 'int');

    expect($rule->getValue())->toBe(100);
});

test('can get data type', function () {
    $rule = new MaxRule(1000.99, 'float');

    expect($rule->getDataType())->toBe('float');
});

test('handles float values correctly', function () {
    $rule = new MaxRule(100.5, 'float');

    expect($rule->getValue())->toBe(100.5)
        ->and($rule->getDataType())->toBe('float');
});

