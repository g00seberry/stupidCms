<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\MinRule;

/**
 * Unit-тесты для MinRule.
 */

test('getType returns min', function () {
    $rule = new MinRule(10);

    expect($rule->getType())->toBe('min');
});

test('getParams returns array with value', function () {
    $rule = new MinRule(10);

    $params = $rule->getParams();
    expect($params)->toBeArray();
    expect($params)->toHaveKey('value');
    expect($params['value'])->toBe(10);
});

test('getValue returns passed value', function () {
    $rule = new MinRule(10);

    expect($rule->getValue())->toBe(10);
});

test('correctly stores numeric values', function () {
    $intRule = new MinRule(10);
    $floatRule = new MinRule(10.5);

    expect($intRule->getValue())->toBe(10);
    expect($floatRule->getValue())->toBe(10.5);
});

test('correctly stores string values', function () {
    $rule = new MinRule('10');

    expect($rule->getValue())->toBe('10');
});

test('correctly stores zero value', function () {
    $rule = new MinRule(0);

    expect($rule->getValue())->toBe(0);
    expect($rule->getParams()['value'])->toBe(0);
});


