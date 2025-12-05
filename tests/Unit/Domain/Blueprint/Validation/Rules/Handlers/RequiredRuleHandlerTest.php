<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\Handlers\RequiredRuleHandler;
use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Domain\Blueprint\Validation\Rules\RequiredRule;

/**
 * Unit-тесты для RequiredRuleHandler.
 */

beforeEach(function () {
    $this->handler = new RequiredRuleHandler();
});

test('supports returns true for required', function () {
    expect($this->handler->supports('required'))->toBeTrue();
});

test('supports returns false for other types', function () {
    expect($this->handler->supports('nullable'))->toBeFalse();
    expect($this->handler->supports('min'))->toBeFalse();
    expect($this->handler->supports('max'))->toBeFalse();
    expect($this->handler->supports('pattern'))->toBeFalse();
    expect($this->handler->supports('distinct'))->toBeFalse();
});

test('handle returns required for RequiredRule', function () {
    $rule = new RequiredRule();
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toContain('required');
    expect($result)->toHaveCount(1);
});

test('handle throws exception for wrong rule type', function () {
    $wrongRule = new MinRule(5);

    expect(fn () => $this->handler->handle($wrongRule))
        ->toThrow(\InvalidArgumentException::class, 'Expected RequiredRule instance');
});


