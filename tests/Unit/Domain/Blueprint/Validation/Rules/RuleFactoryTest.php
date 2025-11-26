<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation\Rules;

use App\Domain\Blueprint\Validation\Rules\MaxRule;
use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Domain\Blueprint\Validation\Rules\NullableRule;
use App\Domain\Blueprint\Validation\Rules\PatternRule;
use App\Domain\Blueprint\Validation\Rules\RequiredRule;
use App\Domain\Blueprint\Validation\Rules\RuleFactory;
use App\Domain\Blueprint\Validation\Rules\RuleFactoryImpl;
use Tests\TestCase;

uses(TestCase::class);

test('creates min rule correctly', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createMinRule(10, 'string');
    
    expect($rule)->toBeInstanceOf(MinRule::class)
        ->and($rule->getValue())->toBe(10)
        ->and($rule->getDataType())->toBe('string');
});

test('creates max rule correctly', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createMaxRule(500, 'string');
    
    expect($rule)->toBeInstanceOf(MaxRule::class)
        ->and($rule->getValue())->toBe(500)
        ->and($rule->getDataType())->toBe('string');
});

test('creates pattern rule correctly', function () {
    $factory = new RuleFactoryImpl();
    
    $pattern = '^\\+?[1-9]\\d{1,14}$';
    $rule = $factory->createPatternRule($pattern);
    
    expect($rule)->toBeInstanceOf(PatternRule::class)
        ->and($rule->getPattern())->toBe($pattern);
});

test('creates pattern rule with empty string returns default', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createPatternRule('');
    
    expect($rule)->toBeInstanceOf(PatternRule::class)
        ->and($rule->getPattern())->toBe('.*');
});

test('creates pattern rule with non-string returns default', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createPatternRule(null);
    
    expect($rule)->toBeInstanceOf(PatternRule::class)
        ->and($rule->getPattern())->toBe('.*');
});

test('creates required rule correctly', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createRequiredRule();
    
    expect($rule)->toBeInstanceOf(RequiredRule::class)
        ->and($rule->getType())->toBe('required');
});

test('creates nullable rule correctly', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createNullableRule();
    
    expect($rule)->toBeInstanceOf(NullableRule::class)
        ->and($rule->getType())->toBe('nullable');
});

test('creates min rule with float preserves precision', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createMinRule(0.5, 'float');
    
    expect($rule)->toBeInstanceOf(MinRule::class)
        ->and($rule->getValue())->toBe(0.5)
        ->and($rule->getDataType())->toBe('float');
});

test('creates max rule with float preserves precision', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createMaxRule(100.99, 'float');
    
    expect($rule)->toBeInstanceOf(MaxRule::class)
        ->and($rule->getValue())->toBe(100.99)
        ->and($rule->getDataType())->toBe('float');
});

test('creates array min items rule correctly', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createArrayMinItemsRule(5);
    
    expect($rule)->toBeInstanceOf(\App\Domain\Blueprint\Validation\Rules\ArrayMinItemsRule::class)
        ->and($rule->getType())->toBe('array_min_items')
        ->and($rule->getValue())->toBe(5)
        ->and($rule->getParams())->toBe(['value' => 5]);
});

test('creates array max items rule correctly', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createArrayMaxItemsRule(10);
    
    expect($rule)->toBeInstanceOf(\App\Domain\Blueprint\Validation\Rules\ArrayMaxItemsRule::class)
        ->and($rule->getType())->toBe('array_max_items')
        ->and($rule->getValue())->toBe(10)
        ->and($rule->getParams())->toBe(['value' => 10]);
});

test('creates conditional rule correctly', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createConditionalRule('required_if', 'is_published', true);
    
    expect($rule)->toBeInstanceOf(\App\Domain\Blueprint\Validation\Rules\ConditionalRule::class)
        ->and($rule->getType())->toBe('required_if')
        ->and($rule->getField())->toBe('is_published')
        ->and($rule->getValue())->toBe(true)
        ->and($rule->getOperator())->toBe('==');
});

test('creates conditional rule with operator', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createConditionalRule('required_if', 'count', 5, '>=');
    
    expect($rule)->toBeInstanceOf(\App\Domain\Blueprint\Validation\Rules\ConditionalRule::class)
        ->and($rule->getType())->toBe('required_if')
        ->and($rule->getField())->toBe('count')
        ->and($rule->getValue())->toBe(5)
        ->and($rule->getOperator())->toBe('>=');
});

test('creates unique rule correctly', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createUniqueRule('entries', 'slug');
    
    expect($rule)->toBeInstanceOf(\App\Domain\Blueprint\Validation\Rules\UniqueRule::class)
        ->and($rule->getType())->toBe('unique')
        ->and($rule->getTable())->toBe('entries')
        ->and($rule->getColumn())->toBe('slug');
});

test('creates unique rule with except', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createUniqueRule('entries', 'slug', 'id', 5);
    
    expect($rule)->toBeInstanceOf(\App\Domain\Blueprint\Validation\Rules\UniqueRule::class)
        ->and($rule->getExceptColumn())->toBe('id')
        ->and($rule->getExceptValue())->toBe(5);
});

test('creates exists rule correctly', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createExistsRule('entries', 'id');
    
    expect($rule)->toBeInstanceOf(\App\Domain\Blueprint\Validation\Rules\ExistsRule::class)
        ->and($rule->getType())->toBe('exists')
        ->and($rule->getTable())->toBe('entries')
        ->and($rule->getColumn())->toBe('id');
});

test('creates array unique rule correctly', function () {
    $factory = new RuleFactoryImpl();
    
    $rule = $factory->createArrayUniqueRule();
    
    expect($rule)->toBeInstanceOf(\App\Domain\Blueprint\Validation\Rules\ArrayUniqueRule::class)
        ->and($rule->getType())->toBe('array_unique')
        ->and($rule->getParams())->toBe([]);
});

