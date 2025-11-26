<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\FieldComparisonRule;
use Tests\TestCase;

uses(TestCase::class);

test('returns correct type', function () {
    $rule = new FieldComparisonRule('>=', 'content_json.start_date');

    expect($rule->getType())->toBe('field_comparison');
});

test('returns correct params', function () {
    $rule = new FieldComparisonRule('>=', 'content_json.start_date');

    $params = $rule->getParams();

    expect($params)->toBeArray()
        ->and($params)->toHaveKey('operator')
        ->and($params)->toHaveKey('other_field')
        ->and($params)->toHaveKey('constant_value')
        ->and($params['operator'])->toBe('>=')
        ->and($params['other_field'])->toBe('content_json.start_date')
        ->and($params['constant_value'])->toBeNull();
});

test('can get operator', function () {
    $rule = new FieldComparisonRule('<=', 'content_json.end_date');

    expect($rule->getOperator())->toBe('<=');
});

test('can get other field', function () {
    $rule = new FieldComparisonRule('>', 'content_json.price');

    expect($rule->getOtherField())->toBe('content_json.price');
});

test('can get constant value', function () {
    $rule = new FieldComparisonRule('>=', '', '2024-01-01');

    expect($rule->getConstantValue())->toBe('2024-01-01');
});

test('handles constant value correctly', function () {
    $rule = new FieldComparisonRule('>=', '', 100);

    expect($rule->getConstantValue())->toBe(100)
        ->and($rule->getOtherField())->toBe('');
});

test('handles field comparison without constant', function () {
    $rule = new FieldComparisonRule('==', 'content_json.status', null);

    expect($rule->getOtherField())->toBe('content_json.status')
        ->and($rule->getConstantValue())->toBeNull();
});

test('supports all operators', function () {
    $operators = ['>=', '<=', '>', '<', '==', '!='];

    foreach ($operators as $operator) {
        $rule = new FieldComparisonRule($operator, 'content_json.field');
        expect($rule->getOperator())->toBe($operator);
    }
});

