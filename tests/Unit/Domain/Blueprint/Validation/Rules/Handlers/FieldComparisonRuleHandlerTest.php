<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\FieldComparisonRule;
use App\Domain\Blueprint\Validation\Rules\Handlers\FieldComparisonRuleHandler;
use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Rules\FieldComparison;
use Tests\TestCase;

uses(TestCase::class);

test('supports field_comparison rule type', function () {
    $handler = new FieldComparisonRuleHandler();

    expect($handler->supports('field_comparison'))->toBeTrue()
        ->and($handler->supports('min'))->toBeFalse()
        ->and($handler->supports('max'))->toBeFalse();
});

test('handles FieldComparisonRule correctly', function () {
    $handler = new FieldComparisonRuleHandler();
    $rule = new FieldComparisonRule('>=', 'content_json.start_date');

    $result = $handler->handle($rule, 'datetime');

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0])->toBeInstanceOf(FieldComparison::class);
});

test('throws exception for wrong rule type', function () {
    $handler = new FieldComparisonRuleHandler();
    $wrongRule = new MinRule(10, 'string');

    expect(fn() => $handler->handle($wrongRule, 'string'))
        ->toThrow(InvalidArgumentException::class, 'Expected FieldComparisonRule instance');
});

test('passes correct parameters to FieldComparison', function () {
    $handler = new FieldComparisonRuleHandler();
    $rule = new FieldComparisonRule('<=', 'content_json.end_date', null);

    $result = $handler->handle($rule, 'date');

    expect($result[0])->toBeInstanceOf(FieldComparison::class);
    
    // Проверяем, что правило создано с правильными параметрами
    // через рефлексию (так как FieldComparison не имеет публичных геттеров)
    $reflection = new ReflectionClass($result[0]);
    $operatorProp = $reflection->getProperty('operator');
    $operatorProp->setAccessible(true);
    $otherFieldProp = $reflection->getProperty('otherField');
    $otherFieldProp->setAccessible(true);
    
    expect($operatorProp->getValue($result[0]))->toBe('<=')
        ->and($otherFieldProp->getValue($result[0]))->toBe('content_json.end_date');
});

test('handles constant value correctly', function () {
    $handler = new FieldComparisonRuleHandler();
    $rule = new FieldComparisonRule('>=', '', '2024-01-01');

    $result = $handler->handle($rule, 'date');

    expect($result[0])->toBeInstanceOf(FieldComparison::class);
    
    $reflection = new ReflectionClass($result[0]);
    $constantValueProp = $reflection->getProperty('constantValue');
    $constantValueProp->setAccessible(true);
    
    expect($constantValueProp->getValue($result[0]))->toBe('2024-01-01');
});

