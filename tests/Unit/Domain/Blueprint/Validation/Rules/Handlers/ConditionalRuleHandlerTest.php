<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\ConditionalRule;
use App\Domain\Blueprint\Validation\Rules\Handlers\ConditionalRuleHandler;
use App\Domain\Blueprint\Validation\Rules\MinRule;

/**
 * Unit-тесты для ConditionalRuleHandler.
 */

beforeEach(function () {
    $this->handler = new ConditionalRuleHandler();
});

test('supports returns true for required_if', function () {
    expect($this->handler->supports('required_if'))->toBeTrue();
});

test('supports returns true for prohibited_unless', function () {
    expect($this->handler->supports('prohibited_unless'))->toBeTrue();
});

test('supports returns true for required_unless', function () {
    expect($this->handler->supports('required_unless'))->toBeTrue();
});

test('supports returns true for prohibited_if', function () {
    expect($this->handler->supports('prohibited_if'))->toBeTrue();
});

test('supports returns false for other types', function () {
    expect($this->handler->supports('required'))->toBeFalse();
    expect($this->handler->supports('nullable'))->toBeFalse();
    expect($this->handler->supports('min'))->toBeFalse();
    expect($this->handler->supports('max'))->toBeFalse();
    expect($this->handler->supports('pattern'))->toBeFalse();
    expect($this->handler->supports('distinct'))->toBeFalse();
});

test('handle returns correct Laravel rule for required_if', function () {
    $rule = new ConditionalRule('required_if', 'is_published', true);
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0])->toBe('required_if:is_published,true');
});

test('handle returns correct Laravel rule for prohibited_unless', function () {
    $rule = new ConditionalRule('prohibited_unless', 'is_published', true);
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0])->toBe('prohibited_unless:is_published,true');
});

test('handle returns correct Laravel rule for required_unless', function () {
    $rule = new ConditionalRule('required_unless', 'is_published', true);
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0])->toBe('required_unless:is_published,true');
});

test('handle returns correct Laravel rule for prohibited_if', function () {
    $rule = new ConditionalRule('prohibited_if', 'is_published', true);
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0])->toBe('prohibited_if:is_published,true');
});

test('handle correctly processes comparison operator', function () {
    $rule = new ConditionalRule('required_if', 'is_published', true, '==');
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    // Оператор '==' обрабатывается как стандартный формат
    expect($result[0])->toBe('required_if:is_published,true');
});

test('handle correctly formats boolean values', function () {
    $trueRule = new ConditionalRule('required_if', 'field', true);
    $falseRule = new ConditionalRule('required_if', 'field', false);

    $trueResult = $this->handler->handle($trueRule);
    $falseResult = $this->handler->handle($falseRule);

    expect($trueResult[0])->toContain('true');
    expect($falseResult[0])->toContain('false');
});

test('handle correctly formats null values', function () {
    $rule = new ConditionalRule('required_if', 'field', null);
    $result = $this->handler->handle($rule);

    expect($result[0])->toContain('null');
});

test('handle correctly formats string values', function () {
    $rule = new ConditionalRule('required_if', 'field', 'value');
    $result = $this->handler->handle($rule);

    expect($result[0])->toContain('value');
});

test('handle correctly formats numeric values', function () {
    $intRule = new ConditionalRule('required_if', 'field', 42);
    $floatRule = new ConditionalRule('required_if', 'field', 42.5);

    $intResult = $this->handler->handle($intRule);
    $floatResult = $this->handler->handle($floatRule);

    expect($intResult[0])->toContain('42');
    expect($floatResult[0])->toContain('42.5');
});

test('handle correctly formats array values', function () {
    $rule = new ConditionalRule('required_if', 'field', ['value1', 'value2']);
    $result = $this->handler->handle($rule);

    expect($result[0])->toContain('value1');
    expect($result[0])->toContain('value2');
});

test('handle throws exception for wrong rule type', function () {
    $wrongRule = new MinRule(5);

    expect(fn () => $this->handler->handle($wrongRule))
        ->toThrow(\InvalidArgumentException::class, 'Expected ConditionalRule instance');
});


