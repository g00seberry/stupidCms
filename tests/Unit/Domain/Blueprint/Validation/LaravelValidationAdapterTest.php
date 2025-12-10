<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Adapters\LaravelValidationAdapter;
use App\Domain\Blueprint\Validation\Rules\ConditionalRule;
use App\Domain\Blueprint\Validation\Rules\DistinctRule;
use App\Domain\Blueprint\Validation\Rules\Handlers\ConditionalRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\DistinctRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\FieldComparisonRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\MaxRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\MinRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\NullableRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\PatternRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\RequiredRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerRegistry;
use App\Domain\Blueprint\Validation\Rules\FieldComparisonRule;
use App\Domain\Blueprint\Validation\Rules\MaxRule;
use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Domain\Blueprint\Validation\Rules\NullableRule;
use App\Domain\Blueprint\Validation\Rules\PatternRule;
use App\Domain\Blueprint\Validation\Rules\RequiredRule;
use App\Domain\Blueprint\Validation\Rules\RuleSet;
use App\Rules\DistinctObjects;

/**
 * Unit-тесты для LaravelValidationAdapter.
 *
 * Тестирует преобразование доменных RuleSet в Laravel правила валидации.
 */

beforeEach(function () {
    // Создаём registry с зарегистрированными handlers
    $this->registry = new RuleHandlerRegistry();
    $this->registry->register('required', new RequiredRuleHandler());
    $this->registry->register('nullable', new NullableRuleHandler());
    $this->registry->register('min', new MinRuleHandler());
    $this->registry->register('max', new MaxRuleHandler());
    $this->registry->register('pattern', new PatternRuleHandler());
    $this->registry->register('distinct', new DistinctRuleHandler());
    $this->registry->register('required_if', new ConditionalRuleHandler());
    $this->registry->register('prohibited_unless', new ConditionalRuleHandler());
    $this->registry->register('required_unless', new ConditionalRuleHandler());
    $this->registry->register('prohibited_if', new ConditionalRuleHandler());
    $this->registry->register('field_comparison', new FieldComparisonRuleHandler());

    $this->adapter = new LaravelValidationAdapter($this->registry);
});

test('adapt converts RequiredRule to Laravel rule', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('data_json.title', new RequiredRule());

    $result = $this->adapter->adapt($ruleSet);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('data_json.title');
    expect($result['data_json.title'])->toContain('required');
});

test('adapt converts NullableRule to Laravel rule', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('data_json.description', new NullableRule());

    $result = $this->adapter->adapt($ruleSet);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('data_json.description');
    expect($result['data_json.description'])->toContain('nullable');
});

test('adapt converts MinRule to Laravel rule', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('data_json.price', new MinRule(10));

    $result = $this->adapter->adapt($ruleSet);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('data_json.price');
    expect($result['data_json.price'])->toContain('min:10');
});

test('adapt converts MaxRule to Laravel rule', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('data_json.title', new MaxRule(500));

    $result = $this->adapter->adapt($ruleSet);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('data_json.title');
    expect($result['data_json.title'])->toContain('max:500');
});

test('adapt converts PatternRule to Laravel regex rule', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('data_json.email', new PatternRule('^[a-z0-9]+@[a-z0-9]+\\.[a-z]+$'));

    $result = $this->adapter->adapt($ruleSet);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('data_json.email');
    expect($result['data_json.email'])->toBeArray();
    // Проверяем, что есть regex правило
    $hasRegex = false;
    foreach ($result['data_json.email'] as $rule) {
        if (is_string($rule) && str_starts_with($rule, 'regex:')) {
            $hasRegex = true;
            break;
        }
    }
    expect($hasRegex)->toBeTrue();
});

test('adapt converts DistinctRule to DistinctObjects rule', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('data_json.tags', new DistinctRule());

    $result = $this->adapter->adapt($ruleSet);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('data_json.tags');
    expect($result['data_json.tags'])->toBeArray();
    // Проверяем, что есть DistinctObjects правило
    $hasDistinctObjects = false;
    foreach ($result['data_json.tags'] as $rule) {
        if ($rule instanceof DistinctObjects) {
            $hasDistinctObjects = true;
            break;
        }
    }
    expect($hasDistinctObjects)->toBeTrue();
});

test('adapt converts ConditionalRule to Laravel rule', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('data_json.published_at', new ConditionalRule('required_if', 'is_published', true));

    $result = $this->adapter->adapt($ruleSet);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('data_json.published_at');
    expect($result['data_json.published_at'])->toBeArray();
    // Проверяем, что есть required_if правило
    $hasRequiredIf = false;
    foreach ($result['data_json.published_at'] as $rule) {
        if (is_string($rule) && str_starts_with($rule, 'required_if:')) {
            $hasRequiredIf = true;
            break;
        }
    }
    expect($hasRequiredIf)->toBeTrue();
});

test('adapt converts multiple rules for one field', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('data_json.title', new RequiredRule());
    $ruleSet->addRule('data_json.title', new MinRule(5));
    $ruleSet->addRule('data_json.title', new MaxRule(100));

    $result = $this->adapter->adapt($ruleSet);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('data_json.title');
    $rules = $result['data_json.title'];
    expect($rules)->toBeArray();
    expect($rules)->toContain('required');
    expect($rules)->toContain('min:5');
    expect($rules)->toContain('max:100');
});

test('adapt converts rules for multiple fields', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('data_json.title', new RequiredRule());
    $ruleSet->addRule('data_json.description', new NullableRule());
    $ruleSet->addRule('data_json.price', new MinRule(0));

    $result = $this->adapter->adapt($ruleSet);

    expect($result)->toBeArray();
    expect($result)->toHaveKeys(['data_json.title', 'data_json.description', 'data_json.price']);
    expect($result['data_json.title'])->toContain('required');
    expect($result['data_json.description'])->toContain('nullable');
    expect($result['data_json.price'])->toContain('min:0');
});

test('adapt returns empty array for empty RuleSet', function () {
    $ruleSet = new RuleSet();

    $result = $this->adapter->adapt($ruleSet);

    expect($result)->toBeArray();
    expect($result)->toBeEmpty();
});

test('adapt throws exception for unknown rule type', function () {
    $ruleSet = new RuleSet();
    // Создаём мок правила с неизвестным типом
    $unknownRule = Mockery::mock(\App\Domain\Blueprint\Validation\Rules\Rule::class);
    $unknownRule->shouldReceive('getType')->andReturn('unknown_rule');

    $ruleSet->addRule('data_json.field', $unknownRule);

    expect(fn () => $this->adapter->adapt($ruleSet))
        ->toThrow(\InvalidArgumentException::class, 'No handler found for rule type: unknown_rule');
});

test('adapt handles FieldComparisonRule', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('data_json.end_date', new FieldComparisonRule('>=', 'data_json.start_date'));

    $result = $this->adapter->adapt($ruleSet);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('data_json.end_date');
    expect($result['data_json.end_date'])->toBeArray();
    // FieldComparisonRule возвращает объект FieldComparison
    $hasFieldComparison = false;
    foreach ($result['data_json.end_date'] as $rule) {
        if (is_object($rule) && $rule instanceof \App\Rules\FieldComparison) {
            $hasFieldComparison = true;
            break;
        }
    }
    expect($hasFieldComparison)->toBeTrue();
});

test('adapt ignores dataTypes parameter', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('data_json.title', new RequiredRule());

    $result = $this->adapter->adapt($ruleSet, ['data_json.title' => 'string']);

    // dataTypes не влияет на результат
    expect($result)->toBeArray();
    expect($result)->toHaveKey('data_json.title');
});

test('adapt handles MinRule with non-numeric value', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('data_json.field', new MinRule('invalid'));

    $result = $this->adapter->adapt($ruleSet);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('data_json.field');
    // MinRuleHandler возвращает 'min:0' для не-числовых значений
    expect($result['data_json.field'])->toContain('min:0');
});

test('adapt handles MaxRule with non-numeric value', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('data_json.field', new MaxRule('invalid'));

    $result = $this->adapter->adapt($ruleSet);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('data_json.field');
    // MaxRuleHandler возвращает 'max:PHP_INT_MAX' для не-числовых значений
    expect($result['data_json.field'])->toBeArray();
    $hasMax = false;
    foreach ($result['data_json.field'] as $rule) {
        if (is_string($rule) && str_starts_with($rule, 'max:')) {
            $hasMax = true;
            break;
        }
    }
    expect($hasMax)->toBeTrue();
});

test('adapt handles PatternRule with empty pattern', function () {
    $ruleSet = new RuleSet();
    $ruleSet->addRule('data_json.field', new PatternRule(''));

    $result = $this->adapter->adapt($ruleSet);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('data_json.field');
    // PatternRuleHandler возвращает 'regex:/.*/' для пустого паттерна
    expect($result['data_json.field'])->toBeArray();
    $hasRegex = false;
    foreach ($result['data_json.field'] as $rule) {
        if (is_string($rule) && str_starts_with($rule, 'regex:')) {
            $hasRegex = true;
            break;
        }
    }
    expect($hasRegex)->toBeTrue();
});

afterEach(function () {
    Mockery::close();
});

