<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\MinRule;
use Tests\TestCase;

uses(TestCase::class);

test('returns correct type', function () {
    $rule = new MinRule(1, 'string');

    expect($rule->getType())->toBe('min');
});

test('returns correct params', function () {
    $rule = new MinRule(10, 'string');

    $params = $rule->getParams();

    expect($params)->toBeArray()
        ->and($params)->toHaveKey('value')
        ->and($params)->toHaveKey('data_type')
        ->and($params['value'])->toBe(10)
        ->and($params['data_type'])->toBe('string');
});

test('can get value', function () {
    $rule = new MinRule(5, 'int');

    expect($rule->getValue())->toBe(5);
});

test('can get data type', function () {
    $rule = new MinRule(1, 'float');

    expect($rule->getDataType())->toBe('float');
});

test('handles float values correctly', function () {
    $rule = new MinRule(0.5, 'float');

    expect($rule->getValue())->toBe(0.5)
        ->and($rule->getDataType())->toBe('float');
});

