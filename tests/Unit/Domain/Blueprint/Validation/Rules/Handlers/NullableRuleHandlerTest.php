<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\Handlers\NullableRuleHandler;
use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Domain\Blueprint\Validation\Rules\NullableRule;

/**
 * Unit-тесты для NullableRuleHandler.
 */

beforeEach(function () {
    $this->handler = new NullableRuleHandler();
});

test('supports returns true for nullable', function () {
    expect($this->handler->supports('nullable'))->toBeTrue();
});

test('supports returns false for other types', function () {
    expect($this->handler->supports('required'))->toBeFalse();
    expect($this->handler->supports('min'))->toBeFalse();
    expect($this->handler->supports('max'))->toBeFalse();
    expect($this->handler->supports('pattern'))->toBeFalse();
    expect($this->handler->supports('distinct'))->toBeFalse();
});

test('handle returns nullable for NullableRule', function () {
    $rule = new NullableRule();
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toContain('nullable');
    expect($result)->toHaveCount(1);
});

test('handle throws exception for wrong rule type', function () {
    $wrongRule = new MinRule(5);

    expect(fn () => $this->handler->handle($wrongRule))
        ->toThrow(\InvalidArgumentException::class, 'Expected NullableRule instance');
});


