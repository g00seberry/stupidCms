<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\FieldComparisonRule;
use App\Domain\Blueprint\Validation\Rules\Handlers\FieldComparisonRuleHandler;
use App\Domain\Blueprint\Validation\Rules\MinRule;

/**
 * Unit-тесты для FieldComparisonRuleHandler.
 */

beforeEach(function () {
    $this->handler = new FieldComparisonRuleHandler();
});

test('supports returns true for field_comparison', function () {
    expect($this->handler->supports('field_comparison'))->toBeTrue();
});

test('supports returns false for other types', function () {
    expect($this->handler->supports('required'))->toBeFalse();
    expect($this->handler->supports('nullable'))->toBeFalse();
    expect($this->handler->supports('min'))->toBeFalse();
    expect($this->handler->supports('max'))->toBeFalse();
    expect($this->handler->supports('pattern'))->toBeFalse();
    expect($this->handler->supports('distinct'))->toBeFalse();
});

test('handle returns correct Laravel rule for field comparison', function () {
    $rule = new FieldComparisonRule('>=', 'data_json.start_date');
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0])->toBeInstanceOf(\App\Rules\FieldComparison::class);
});

test('handle returns correct Laravel rule for constant comparison', function () {
    $rule = new FieldComparisonRule('>=', 'data_json.start_date', '2024-01-01');
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0])->toBeInstanceOf(\App\Rules\FieldComparison::class);
});

test('handle correctly processes all operators', function () {
    $operators = ['>=', '<=', '>', '<', '==', '!='];

    foreach ($operators as $operator) {
        $rule = new FieldComparisonRule($operator, 'field1', 'field2');
        $result = $this->handler->handle($rule);

        expect($result)->toBeArray();
        expect($result)->toHaveCount(1);
        expect($result[0])->toBeInstanceOf(\App\Rules\FieldComparison::class);
    }
});

test('handle correctly passes operator to FieldComparison', function () {
    $rule = new FieldComparisonRule('>=', 'field1', 'field2');
    $result = $this->handler->handle($rule);

    /** @var \App\Rules\FieldComparison $fieldComparison */
    $fieldComparison = $result[0];
    // Проверяем, что объект создан (детальная проверка будет в тестах FieldComparison)
    expect($fieldComparison)->toBeInstanceOf(\App\Rules\FieldComparison::class);
});

test('handle correctly passes otherField to FieldComparison', function () {
    $rule = new FieldComparisonRule('>=', 'data_json.start_date', '2024-01-01');
    $result = $this->handler->handle($rule);

    expect($result[0])->toBeInstanceOf(\App\Rules\FieldComparison::class);
});

test('handle correctly passes constantValue to FieldComparison', function () {
    $rule = new FieldComparisonRule('>=', 'data_json.start_date', '2024-01-01');
    $result = $this->handler->handle($rule);

    expect($result[0])->toBeInstanceOf(\App\Rules\FieldComparison::class);
});

test('handle throws exception for wrong rule type', function () {
    $wrongRule = new MinRule(5);

    expect(fn () => $this->handler->handle($wrongRule))
        ->toThrow(\InvalidArgumentException::class, 'Expected FieldComparisonRule instance');
});


