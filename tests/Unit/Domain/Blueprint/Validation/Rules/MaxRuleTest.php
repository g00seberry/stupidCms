<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\MaxRule;

/**
 * Unit-тесты для MaxRule.
 */

test('getType returns max', function () {
    $rule = new MaxRule(100);

    expect($rule->getType())->toBe('max');
});

test('getParams returns array with value', function () {
    $rule = new MaxRule(100);

    $params = $rule->getParams();
    expect($params)->toBeArray();
    expect($params)->toHaveKey('value');
    expect($params['value'])->toBe(100);
});

test('getValue returns passed value', function () {
    $rule = new MaxRule(100);

    expect($rule->getValue())->toBe(100);
});

test('correctly stores numeric values', function () {
    $intRule = new MaxRule(100);
    $floatRule = new MaxRule(100.5);

    expect($intRule->getValue())->toBe(100);
    expect($floatRule->getValue())->toBe(100.5);
});

test('correctly stores string values', function () {
    $rule = new MaxRule('100');

    expect($rule->getValue())->toBe('100');
});


