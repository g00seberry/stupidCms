<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\Handlers\MinRuleHandler;
use App\Domain\Blueprint\Validation\Rules\MaxRule;
use App\Domain\Blueprint\Validation\Rules\MinRule;

/**
 * Unit-тесты для MinRuleHandler.
 */

beforeEach(function () {
    $this->handler = new MinRuleHandler();
});

test('supports returns true for min', function () {
    expect($this->handler->supports('min'))->toBeTrue();
});

test('supports returns false for other types', function () {
    expect($this->handler->supports('required'))->toBeFalse();
    expect($this->handler->supports('nullable'))->toBeFalse();
    expect($this->handler->supports('max'))->toBeFalse();
    expect($this->handler->supports('pattern'))->toBeFalse();
    expect($this->handler->supports('distinct'))->toBeFalse();
});

test('handle returns min:5 for MinRule(5)', function () {
    $rule = new MinRule(5);
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toContain('min:5');
    expect($result)->toHaveCount(1);
});

test('handle processes int values', function () {
    $rule = new MinRule(10);
    $result = $this->handler->handle($rule);

    expect($result)->toContain('min:10');
});

test('handle processes float values', function () {
    $rule = new MinRule(10.5);
    $result = $this->handler->handle($rule);

    expect($result)->toContain('min:10.5');
});

test('handle returns min:0 for non-numeric values', function () {
    $rule = new MinRule('invalid');
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toContain('min:0');
    expect($result)->toHaveCount(1);
});

test('handle returns min:0 for null value', function () {
    $rule = new MinRule(null);
    $result = $this->handler->handle($rule);

    expect($result)->toContain('min:0');
});

test('handle returns min:0 for array value', function () {
    $rule = new MinRule(['invalid']);
    $result = $this->handler->handle($rule);

    expect($result)->toContain('min:0');
});

test('handle throws exception for wrong rule type', function () {
    $wrongRule = new MaxRule(100);

    expect(fn () => $this->handler->handle($wrongRule))
        ->toThrow(\InvalidArgumentException::class, 'Expected MinRule instance');
});


