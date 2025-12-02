<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\ConditionalRule;

/**
 * Unit-тесты для ConditionalRule.
 */

test('getType returns passed type', function () {
    $rule = new ConditionalRule('required_if', 'is_published', true);

    expect($rule->getType())->toBe('required_if');
});

test('getParams returns array with field, value, operator', function () {
    $rule = new ConditionalRule('required_if', 'is_published', true);

    $params = $rule->getParams();
    expect($params)->toBeArray();
    expect($params)->toHaveKeys(['field', 'value', 'operator']);
    expect($params['field'])->toBe('is_published');
    expect($params['value'])->toBe(true);
    expect($params['operator'])->toBe('=='); // По умолчанию
});

test('getField returns field path', function () {
    $rule = new ConditionalRule('required_if', 'is_published', true);

    expect($rule->getField())->toBe('is_published');
});

test('getValue returns condition value', function () {
    $rule = new ConditionalRule('required_if', 'is_published', true);

    expect($rule->getValue())->toBe(true);
});

test('getOperator returns operator default ==', function () {
    $rule = new ConditionalRule('required_if', 'is_published', true);

    expect($rule->getOperator())->toBe('==');
});

test('getOperator returns custom operator', function () {
    $rule = new ConditionalRule('required_if', 'is_published', true, '!=');

    expect($rule->getOperator())->toBe('!=');
});

test('correctly handles all conditional rule types', function () {
    $types = ['required_if', 'prohibited_unless', 'required_unless', 'prohibited_if'];

    foreach ($types as $type) {
        $rule = new ConditionalRule($type, 'field', 'value');
        expect($rule->getType())->toBe($type);
        expect($rule->getField())->toBe('field');
        expect($rule->getValue())->toBe('value');
    }
});

test('correctly handles different value types', function () {
    $boolRule = new ConditionalRule('required_if', 'field', true);
    $stringRule = new ConditionalRule('required_if', 'field', 'value');
    $intRule = new ConditionalRule('required_if', 'field', 42);
    $nullRule = new ConditionalRule('required_if', 'field', null);

    expect($boolRule->getValue())->toBe(true);
    expect($stringRule->getValue())->toBe('value');
    expect($intRule->getValue())->toBe(42);
    expect($nullRule->getValue())->toBeNull();
});

test('correctly handles all comparison operators', function () {
    $operators = ['==', '!=', '>', '<', '>=', '<='];

    foreach ($operators as $operator) {
        $rule = new ConditionalRule('required_if', 'field', 'value', $operator);
        expect($rule->getOperator())->toBe($operator);
        expect($rule->getParams()['operator'])->toBe($operator);
    }
});

test('correctly handles nested field paths', function () {
    $rule = new ConditionalRule('required_if', 'content_json.is_published', true);

    expect($rule->getField())->toBe('content_json.is_published');
    expect($rule->getParams()['field'])->toBe('content_json.is_published');
});


