<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\ConditionalRule;
use App\Domain\Blueprint\Validation\Rules\DistinctRule;
use App\Domain\Blueprint\Validation\Rules\FieldComparisonRule;
use App\Domain\Blueprint\Validation\Rules\MaxRule;
use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Domain\Blueprint\Validation\Rules\NullableRule;
use App\Domain\Blueprint\Validation\Rules\PatternRule;
use App\Domain\Blueprint\Validation\Rules\RequiredRule;
use App\Domain\Blueprint\Validation\Rules\RuleFactoryImpl;

/**
 * Unit-тесты для RuleFactoryImpl.
 *
 * Тестирует создание всех типов правил валидации через фабрику.
 */

beforeEach(function () {
    $this->factory = new RuleFactoryImpl();
});

// 5.1. Создание правил

test('createMinRule creates MinRule with correct value', function () {
    $rule = $this->factory->createMinRule(10);

    expect($rule)->toBeInstanceOf(MinRule::class);
    expect($rule->getValue())->toBe(10);
    expect($rule->getType())->toBe('min');
});

test('createMaxRule creates MaxRule with correct value', function () {
    $rule = $this->factory->createMaxRule(100);

    expect($rule)->toBeInstanceOf(MaxRule::class);
    expect($rule->getValue())->toBe(100);
    expect($rule->getType())->toBe('max');
});

test('createPatternRule creates PatternRule with correct pattern', function () {
    $pattern = '/^test$/';
    $rule = $this->factory->createPatternRule($pattern);

    expect($rule)->toBeInstanceOf(PatternRule::class);
    expect($rule->getPattern())->toBe($pattern);
    expect($rule->getType())->toBe('pattern');
});

test('createPatternRule handles empty string', function () {
    $rule = $this->factory->createPatternRule('');

    expect($rule)->toBeInstanceOf(PatternRule::class);
    // Пустая строка должна быть заменена на '.*'
    expect($rule->getPattern())->toBe('.*');
});

test('createPatternRule handles non-string value', function () {
    $rule = $this->factory->createPatternRule(123);

    expect($rule)->toBeInstanceOf(PatternRule::class);
    // Не-строка должна быть заменена на '.*'
    expect($rule->getPattern())->toBe('.*');
});

test('createPatternRule handles null value', function () {
    $rule = $this->factory->createPatternRule(null);

    expect($rule)->toBeInstanceOf(PatternRule::class);
    // null должна быть заменена на '.*'
    expect($rule->getPattern())->toBe('.*');
});

test('createPatternRule handles array value', function () {
    $rule = $this->factory->createPatternRule(['test']);

    expect($rule)->toBeInstanceOf(PatternRule::class);
    // Массив должна быть заменена на '.*'
    expect($rule->getPattern())->toBe('.*');
});


test('createNullableRule creates NullableRule', function () {
    $rule = $this->factory->createNullableRule();

    expect($rule)->toBeInstanceOf(NullableRule::class);
    expect($rule->getType())->toBe('nullable');
});

test('createConditionalRule creates ConditionalRule with correct parameters', function () {
    $rule = $this->factory->createConditionalRule('required_if', 'is_published', true);

    expect($rule)->toBeInstanceOf(ConditionalRule::class);
    expect($rule->getType())->toBe('required_if');
    expect($rule->getField())->toBe('is_published');
    expect($rule->getValue())->toBe(true);
    expect($rule->getOperator())->toBe('=='); // По умолчанию
});

test('createConditionalRule creates ConditionalRule with custom operator', function () {
    $rule = $this->factory->createConditionalRule('required_if', 'is_published', true, '!=');

    expect($rule)->toBeInstanceOf(ConditionalRule::class);
    expect($rule->getOperator())->toBe('!=');
});

test('createConditionalRule handles all conditional rule types', function () {
    $types = ['required_if', 'prohibited_unless', 'required_unless', 'prohibited_if'];

    foreach ($types as $type) {
        $rule = $this->factory->createConditionalRule($type, 'field', 'value');
        expect($rule->getType())->toBe($type);
    }
});

test('createDistinctRule creates DistinctRule', function () {
    $rule = $this->factory->createDistinctRule();

    expect($rule)->toBeInstanceOf(DistinctRule::class);
    expect($rule->getType())->toBe('distinct');
});

test('createFieldComparisonRule creates FieldComparisonRule with field', function () {
    $rule = $this->factory->createFieldComparisonRule('>=', 'content_json.start_date');

    expect($rule)->toBeInstanceOf(FieldComparisonRule::class);
    expect($rule->getType())->toBe('field_comparison');
    expect($rule->getOperator())->toBe('>=');
    expect($rule->getOtherField())->toBe('content_json.start_date');
    expect($rule->getConstantValue())->toBeNull();
});

test('createFieldComparisonRule creates FieldComparisonRule with constant', function () {
    $rule = $this->factory->createFieldComparisonRule('>=', 'content_json.start_date', '2024-01-01');

    expect($rule)->toBeInstanceOf(FieldComparisonRule::class);
    expect($rule->getOperator())->toBe('>=');
    expect($rule->getOtherField())->toBe('content_json.start_date');
    expect($rule->getConstantValue())->toBe('2024-01-01');
});

test('createFieldComparisonRule handles all comparison operators', function () {
    $operators = ['>=', '<=', '>', '<', '==', '!='];

    foreach ($operators as $operator) {
        $rule = $this->factory->createFieldComparisonRule($operator, 'field1', 'field2');
        expect($rule->getOperator())->toBe($operator);
    }
});

test('createMinRule handles different value types', function () {
    $intRule = $this->factory->createMinRule(10);
    $floatRule = $this->factory->createMinRule(10.5);
    $stringRule = $this->factory->createMinRule('10');

    expect($intRule->getValue())->toBe(10);
    expect($floatRule->getValue())->toBe(10.5);
    expect($stringRule->getValue())->toBe('10');
});

test('createMaxRule handles different value types', function () {
    $intRule = $this->factory->createMaxRule(100);
    $floatRule = $this->factory->createMaxRule(100.5);
    $stringRule = $this->factory->createMaxRule('100');

    expect($intRule->getValue())->toBe(100);
    expect($floatRule->getValue())->toBe(100.5);
    expect($stringRule->getValue())->toBe('100');
});


