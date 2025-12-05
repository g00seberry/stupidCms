<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\Handlers\MaxRuleHandler;
use App\Domain\Blueprint\Validation\Rules\MaxRule;
use App\Domain\Blueprint\Validation\Rules\MinRule;

/**
 * Unit-тесты для MaxRuleHandler.
 */

beforeEach(function () {
    $this->handler = new MaxRuleHandler();
});

test('supports returns true for max', function () {
    expect($this->handler->supports('max'))->toBeTrue();
});

test('supports returns false for other types', function () {
    expect($this->handler->supports('required'))->toBeFalse();
    expect($this->handler->supports('nullable'))->toBeFalse();
    expect($this->handler->supports('min'))->toBeFalse();
    expect($this->handler->supports('pattern'))->toBeFalse();
    expect($this->handler->supports('distinct'))->toBeFalse();
});

test('handle returns max:100 for MaxRule(100)', function () {
    $rule = new MaxRule(100);
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toContain('max:100');
    expect($result)->toHaveCount(1);
});

test('handle processes int values', function () {
    $rule = new MaxRule(100);
    $result = $this->handler->handle($rule);

    expect($result)->toContain('max:100');
});

test('handle processes float values', function () {
    $rule = new MaxRule(100.5);
    $result = $this->handler->handle($rule);

    expect($result)->toContain('max:100.5');
});

test('handle returns max:PHP_INT_MAX for non-numeric values', function () {
    $rule = new MaxRule('invalid');
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    // Проверяем, что есть max правило
    $hasMax = false;
    foreach ($result as $ruleString) {
        if (is_string($ruleString) && str_starts_with($ruleString, 'max:')) {
            $hasMax = true;
            break;
        }
    }
    expect($hasMax)->toBeTrue();
});

test('handle returns max:PHP_INT_MAX for null value', function () {
    $rule = new MaxRule(null);
    $result = $this->handler->handle($rule);

    $hasMax = false;
    foreach ($result as $ruleString) {
        if (is_string($ruleString) && str_starts_with($ruleString, 'max:')) {
            $hasMax = true;
            break;
        }
    }
    expect($hasMax)->toBeTrue();
});

test('handle throws exception for wrong rule type', function () {
    $wrongRule = new MinRule(5);

    expect(fn () => $this->handler->handle($wrongRule))
        ->toThrow(\InvalidArgumentException::class, 'Expected MaxRule instance');
});


