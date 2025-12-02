<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\Handlers\PatternRuleHandler;
use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Domain\Blueprint\Validation\Rules\PatternRule;

/**
 * Unit-тесты для PatternRuleHandler.
 */

beforeEach(function () {
    $this->handler = new PatternRuleHandler();
});

test('supports returns true for pattern', function () {
    expect($this->handler->supports('pattern'))->toBeTrue();
});

test('supports returns false for other types', function () {
    expect($this->handler->supports('required'))->toBeFalse();
    expect($this->handler->supports('nullable'))->toBeFalse();
    expect($this->handler->supports('min'))->toBeFalse();
    expect($this->handler->supports('max'))->toBeFalse();
    expect($this->handler->supports('distinct'))->toBeFalse();
});

test('handle returns regex:/^test$/ for PatternRule(/^test$/)', function () {
    $rule = new PatternRule('/^test$/');
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0])->toBe('regex:/^test$/');
});

test('handle correctly escapes pattern', function () {
    $rule = new PatternRule('^test/test$');
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    // Слэш должен быть экранирован
    expect($result[0])->toBe('regex:/^test\/test$/');
});

test('handle handles pattern with delimiters', function () {
    $rule = new PatternRule('/^test$/i');
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    // Паттерн с ограничителями используется как есть
    expect($result[0])->toBe('regex:/^test$/i');
});

test('handle handles pattern without delimiters', function () {
    $rule = new PatternRule('^test$');
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    // Паттерн без ограничителей должен быть обёрнут
    expect($result[0])->toBe('regex:/^test$/');
});

test('handle returns regex:/.*/ for empty pattern', function () {
    $rule = new PatternRule('');
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0])->toBe('regex:/.*/');
});

test('handle handles complex regex patterns', function () {
    $pattern = '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$';
    $rule = new PatternRule($pattern);
    $result = $this->handler->handle($rule);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect($result[0])->toStartWith('regex:/');
    expect($result[0])->toEndWith('/');
});

test('handle throws exception for wrong rule type', function () {
    $wrongRule = new MinRule(5);

    expect(fn () => $this->handler->handle($wrongRule))
        ->toThrow(\InvalidArgumentException::class, 'Expected PatternRule instance');
});


