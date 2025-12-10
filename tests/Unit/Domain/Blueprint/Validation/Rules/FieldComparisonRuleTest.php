<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\FieldComparisonRule;

/**
 * Unit-тесты для FieldComparisonRule.
 */

test('getType returns field_comparison', function () {
    $rule = new FieldComparisonRule('>=', 'data_json.start_date');

    expect($rule->getType())->toBe('field_comparison');
});

test('getParams returns array with operator, other_field, constant_value', function () {
    $rule = new FieldComparisonRule('>=', 'data_json.start_date');

    $params = $rule->getParams();
    expect($params)->toBeArray();
    expect($params)->toHaveKeys(['operator', 'other_field', 'constant_value']);
    expect($params['operator'])->toBe('>=');
    expect($params['other_field'])->toBe('data_json.start_date');
    expect($params['constant_value'])->toBeNull();
});

test('getOperator returns operator', function () {
    $rule = new FieldComparisonRule('>=', 'data_json.start_date');

    expect($rule->getOperator())->toBe('>=');
});

test('getOtherField returns other field path', function () {
    $rule = new FieldComparisonRule('>=', 'data_json.start_date');

    expect($rule->getOtherField())->toBe('data_json.start_date');
});

test('getConstantValue returns constant value', function () {
    $rule = new FieldComparisonRule('>=', 'data_json.start_date', '2024-01-01');

    expect($rule->getConstantValue())->toBe('2024-01-01');
});

test('getConstantValue returns null when not set', function () {
    $rule = new FieldComparisonRule('>=', 'data_json.start_date');

    expect($rule->getConstantValue())->toBeNull();
});

test('correctly handles field comparison', function () {
    $rule = new FieldComparisonRule('>=', 'data_json.start_date');

    expect($rule->getOtherField())->toBe('data_json.start_date');
    expect($rule->getConstantValue())->toBeNull();
    expect($rule->getParams()['other_field'])->toBe('data_json.start_date');
    expect($rule->getParams()['constant_value'])->toBeNull();
});

test('correctly handles constant comparison', function () {
    $rule = new FieldComparisonRule('>=', 'data_json.start_date', '2024-01-01');

    expect($rule->getOtherField())->toBe('data_json.start_date');
    expect($rule->getConstantValue())->toBe('2024-01-01');
    expect($rule->getParams()['constant_value'])->toBe('2024-01-01');
});

test('correctly handles all comparison operators', function () {
    $operators = ['>=', '<=', '>', '<', '==', '!='];

    foreach ($operators as $operator) {
        $rule = new FieldComparisonRule($operator, 'field1', 'field2');
        expect($rule->getOperator())->toBe($operator);
        expect($rule->getParams()['operator'])->toBe($operator);
    }
});

test('correctly handles different constant value types', function () {
    $stringRule = new FieldComparisonRule('>=', 'field', '2024-01-01');
    $intRule = new FieldComparisonRule('>=', 'field', 100);
    $floatRule = new FieldComparisonRule('>=', 'field', 100.5);
    $boolRule = new FieldComparisonRule('>=', 'field', true);

    expect($stringRule->getConstantValue())->toBe('2024-01-01');
    expect($intRule->getConstantValue())->toBe(100);
    expect($floatRule->getConstantValue())->toBe(100.5);
    expect($boolRule->getConstantValue())->toBe(true);
});

test('correctly handles nested field paths', function () {
    $rule = new FieldComparisonRule('>=', 'data_json.author.birth_date', '2000-01-01');

    expect($rule->getOtherField())->toBe('data_json.author.birth_date');
    expect($rule->getParams()['other_field'])->toBe('data_json.author.birth_date');
});


