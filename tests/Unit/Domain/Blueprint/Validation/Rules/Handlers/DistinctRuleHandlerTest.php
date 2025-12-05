<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\DistinctRule;
use App\Domain\Blueprint\Validation\Rules\Handlers\DistinctRuleHandler;
use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Rules\DistinctObjects;

/**
 * Unit-тесты для DistinctRuleHandler.
 */

beforeEach(function () {
    $this->handler = new DistinctRuleHandler();
});

test('supports returns true for distinct', function () {
    expect($this->handler->supports('distinct'))->toBeTrue();
});

test('supports returns false for other types', function () {
    expect($this->handler->supports('required'))->toBeFalse();
    expect($this->handler->supports('nullable'))->toBeFalse();
    expect($this->handler->supports('min'))->toBeFalse();
    expect($this->handler->supports('max'))->toBeFalse();
    expect($this->handler->supports('pattern'))->toBeFalse();
});

test('handle returns DistinctObjects instance for DistinctRule', function () {
    $rule = new DistinctRule();
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0])->toBeInstanceOf(DistinctObjects::class);
});

test('handle throws exception for wrong rule type', function () {
    $wrongRule = new MinRule(5);

    expect(fn () => $this->handler->handle($wrongRule))
        ->toThrow(\InvalidArgumentException::class, 'Expected DistinctRule instance');
});


