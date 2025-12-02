<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Exceptions\InvalidValidationRuleException;
use App\Domain\Blueprint\Validation\PathValidationRulesConverter;
use App\Domain\Blueprint\Validation\Rules\DistinctRule;
use App\Domain\Blueprint\Validation\Rules\FieldComparisonRule;
use App\Domain\Blueprint\Validation\Rules\MaxRule;
use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Domain\Blueprint\Validation\Rules\NullableRule;
use App\Domain\Blueprint\Validation\Rules\PatternRule;
use App\Domain\Blueprint\Validation\Rules\RequiredRule;
use App\Domain\Blueprint\Validation\Rules\RuleFactory;
use App\Domain\Blueprint\Validation\Rules\ConditionalRule;

beforeEach(function () {
    $this->ruleFactory = Mockery::mock(RuleFactory::class);
    $this->converter = new PathValidationRulesConverter($this->ruleFactory);
});

afterEach(function () {
    Mockery::close();
});

// 3.1. Базовые правила

test('convert returns empty array for null validation_rules', function () {
    $result = $this->converter->convert(null);

    expect($result)->toBeArray()->toBeEmpty();
});

test('convert returns empty array for empty validation_rules', function () {
    $result = $this->converter->convert([]);

    expect($result)->toBeArray()->toBeEmpty();
});

test('convert creates RequiredRule for required true', function () {
    $requiredRule = new RequiredRule();
    $this->ruleFactory->shouldReceive('createRequiredRule')
        ->once()
        ->andReturn($requiredRule);

    $result = $this->converter->convert(['required' => true]);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toBeInstanceOf(RequiredRule::class);
});

test('convert creates NullableRule for required false', function () {
    $nullableRule = new NullableRule();
    $this->ruleFactory->shouldReceive('createNullableRule')
        ->once()
        ->andReturn($nullableRule);

    $result = $this->converter->convert(['required' => false]);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toBeInstanceOf(NullableRule::class);
});

test('convert automatically adds NullableRule when required key is missing', function () {
    $minRule = new MinRule(3);
    $nullableRule = new NullableRule();
    $this->ruleFactory->shouldReceive('createMinRule')
        ->once()
        ->with(3)
        ->andReturn($minRule);
    $this->ruleFactory->shouldReceive('createNullableRule')
        ->once()
        ->andReturn($nullableRule);

    $result = $this->converter->convert(['min' => 3]);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(MinRule::class)
        ->and($result[1])->toBeInstanceOf(NullableRule::class);
});

test('convert creates MinRule for min value', function () {
    $minRule = new MinRule(5);
    $nullableRule = new NullableRule();
    $this->ruleFactory->shouldReceive('createMinRule')
        ->once()
        ->with(5)
        ->andReturn($minRule);
    $this->ruleFactory->shouldReceive('createNullableRule')
        ->once()
        ->andReturn($nullableRule);

    $result = $this->converter->convert(['min' => 5]);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(MinRule::class)
        ->and($result[0]->getValue())->toBe(5)
        ->and($result[1])->toBeInstanceOf(NullableRule::class);
});

test('convert creates MaxRule for max value', function () {
    $maxRule = new MaxRule(100);
    $nullableRule = new NullableRule();
    $this->ruleFactory->shouldReceive('createMaxRule')
        ->once()
        ->with(100)
        ->andReturn($maxRule);
    $this->ruleFactory->shouldReceive('createNullableRule')
        ->once()
        ->andReturn($nullableRule);

    $result = $this->converter->convert(['max' => 100]);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(MaxRule::class)
        ->and($result[0]->getValue())->toBe(100)
        ->and($result[1])->toBeInstanceOf(NullableRule::class);
});

test('convert creates PatternRule for pattern', function () {
    $patternRule = new PatternRule('/^test$/');
    $nullableRule = new NullableRule();
    $this->ruleFactory->shouldReceive('createPatternRule')
        ->once()
        ->with('/^test$/')
        ->andReturn($patternRule);
    $this->ruleFactory->shouldReceive('createNullableRule')
        ->once()
        ->andReturn($nullableRule);

    $result = $this->converter->convert(['pattern' => '/^test$/']);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(PatternRule::class)
        ->and($result[0]->getPattern())->toBe('/^test$/')
        ->and($result[1])->toBeInstanceOf(NullableRule::class);
});

test('convert creates DistinctRule for distinct', function () {
    $distinctRule = new DistinctRule();
    $nullableRule = new NullableRule();
    $this->ruleFactory->shouldReceive('createDistinctRule')
        ->once()
        ->andReturn($distinctRule);
    $this->ruleFactory->shouldReceive('createNullableRule')
        ->once()
        ->andReturn($nullableRule);

    $result = $this->converter->convert(['distinct' => true]);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(DistinctRule::class)
        ->and($result[1])->toBeInstanceOf(NullableRule::class);
});

// 3.2. Условные правила

test('convert creates ConditionalRule for required_if with correct format', function () {
    $conditionalRule = new ConditionalRule('required_if', 'is_published', true, '==');
    $nullableRule = new NullableRule();
    $this->ruleFactory->shouldReceive('createConditionalRule')
        ->once()
        ->with('required_if', 'is_published', true, '==')
        ->andReturn($conditionalRule);
    $this->ruleFactory->shouldReceive('createNullableRule')
        ->once()
        ->andReturn($nullableRule);

    $result = $this->converter->convert([
        'required_if' => [
            'field' => 'is_published',
            'value' => true,
            'operator' => '==',
        ],
    ]);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(ConditionalRule::class)
        ->and($result[0]->getType())->toBe('required_if')
        ->and($result[0]->getField())->toBe('is_published')
        ->and($result[0]->getValue())->toBe(true)
        ->and($result[1])->toBeInstanceOf(NullableRule::class);
});

test('convert creates ConditionalRule for prohibited_unless', function () {
    $conditionalRule = new ConditionalRule('prohibited_unless', 'status', 'active', '==');
    $nullableRule = new NullableRule();
    $this->ruleFactory->shouldReceive('createConditionalRule')
        ->once()
        ->with('prohibited_unless', 'status', 'active', '==')
        ->andReturn($conditionalRule);
    $this->ruleFactory->shouldReceive('createNullableRule')
        ->once()
        ->andReturn($nullableRule);

    $result = $this->converter->convert([
        'prohibited_unless' => [
            'field' => 'status',
            'value' => 'active',
            'operator' => '==',
        ],
    ]);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(ConditionalRule::class)
        ->and($result[0]->getType())->toBe('prohibited_unless')
        ->and($result[1])->toBeInstanceOf(NullableRule::class);
});

test('convert creates ConditionalRule for required_unless', function () {
    $conditionalRule = new ConditionalRule('required_unless', 'type', 'guest', '==');
    $nullableRule = new NullableRule();
    $this->ruleFactory->shouldReceive('createConditionalRule')
        ->once()
        ->with('required_unless', 'type', 'guest', '==')
        ->andReturn($conditionalRule);
    $this->ruleFactory->shouldReceive('createNullableRule')
        ->once()
        ->andReturn($nullableRule);

    $result = $this->converter->convert([
        'required_unless' => [
            'field' => 'type',
            'value' => 'guest',
            'operator' => '==',
        ],
    ]);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(ConditionalRule::class)
        ->and($result[0]->getType())->toBe('required_unless')
        ->and($result[1])->toBeInstanceOf(NullableRule::class);
});

test('convert creates ConditionalRule for prohibited_if', function () {
    $conditionalRule = new ConditionalRule('prohibited_if', 'is_draft', true, '==');
    $nullableRule = new NullableRule();
    $this->ruleFactory->shouldReceive('createConditionalRule')
        ->once()
        ->with('prohibited_if', 'is_draft', true, '==')
        ->andReturn($conditionalRule);
    $this->ruleFactory->shouldReceive('createNullableRule')
        ->once()
        ->andReturn($nullableRule);

    $result = $this->converter->convert([
        'prohibited_if' => [
            'field' => 'is_draft',
            'value' => true,
            'operator' => '==',
        ],
    ]);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(ConditionalRule::class)
        ->and($result[0]->getType())->toBe('prohibited_if')
        ->and($result[1])->toBeInstanceOf(NullableRule::class);
});

test('convert throws exception for conditional rule without field', function () {
    expect(fn () => $this->converter->convert([
        'required_if' => [
            'value' => true,
        ],
    ]))->toThrow(InvalidValidationRuleException::class, "Условное правило 'required_if' должно содержать обязательное поле 'field'.");
});

test('convert throws exception for conditional rule with invalid format', function () {
    expect(fn () => $this->converter->convert([
        'required_if' => 'not an array',
    ]))->toThrow(InvalidValidationRuleException::class, "Условное правило 'required_if' должно быть массивом");
});

test('convert handles conditional rule with default operator', function () {
    $conditionalRule = new ConditionalRule('required_if', 'is_published', true, null);
    $nullableRule = new NullableRule();
    $this->ruleFactory->shouldReceive('createConditionalRule')
        ->once()
        ->with('required_if', 'is_published', true, null)
        ->andReturn($conditionalRule);
    $this->ruleFactory->shouldReceive('createNullableRule')
        ->once()
        ->andReturn($nullableRule);

    $result = $this->converter->convert([
        'required_if' => [
            'field' => 'is_published',
            'value' => true,
        ],
    ]);

    expect($result)->toHaveCount(2)
        ->and($result[0]->getOperator())->toBe('==')
        ->and($result[1])->toBeInstanceOf(NullableRule::class);
});

// 3.3. Правило field_comparison

test('convert creates FieldComparisonRule for field comparison', function () {
    $fieldComparisonRule = new FieldComparisonRule('>=', 'content_json.start_date', null);
    $nullableRule = new NullableRule();
    $this->ruleFactory->shouldReceive('createFieldComparisonRule')
        ->once()
        ->with('>=', 'content_json.start_date', null)
        ->andReturn($fieldComparisonRule);
    $this->ruleFactory->shouldReceive('createNullableRule')
        ->once()
        ->andReturn($nullableRule);

    $result = $this->converter->convert([
        'field_comparison' => [
            'operator' => '>=',
            'field' => 'content_json.start_date',
        ],
    ]);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(FieldComparisonRule::class)
        ->and($result[0]->getOperator())->toBe('>=')
        ->and($result[0]->getOtherField())->toBe('content_json.start_date')
        ->and($result[1])->toBeInstanceOf(NullableRule::class);
});

test('convert creates FieldComparisonRule for constant comparison', function () {
    $fieldComparisonRule = new FieldComparisonRule('>=', '', '2024-01-01');
    $nullableRule = new NullableRule();
    $this->ruleFactory->shouldReceive('createFieldComparisonRule')
        ->once()
        ->with('>=', '', '2024-01-01')
        ->andReturn($fieldComparisonRule);
    $this->ruleFactory->shouldReceive('createNullableRule')
        ->once()
        ->andReturn($nullableRule);

    $result = $this->converter->convert([
        'field_comparison' => [
            'operator' => '>=',
            'value' => '2024-01-01',
        ],
    ]);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(FieldComparisonRule::class)
        ->and($result[0]->getConstantValue())->toBe('2024-01-01')
        ->and($result[1])->toBeInstanceOf(NullableRule::class);
});

test('convert prioritizes field over constant in field_comparison', function () {
    $fieldComparisonRule = new FieldComparisonRule('>=', 'content_json.start_date', null);
    $nullableRule = new NullableRule();
    $this->ruleFactory->shouldReceive('createFieldComparisonRule')
        ->once()
        ->with('>=', 'content_json.start_date', null)
        ->andReturn($fieldComparisonRule);
    $this->ruleFactory->shouldReceive('createNullableRule')
        ->once()
        ->andReturn($nullableRule);

    $result = $this->converter->convert([
        'field_comparison' => [
            'operator' => '>=',
            'field' => 'content_json.start_date',
            'value' => '2024-01-01',
        ],
    ]);

    expect($result)->toHaveCount(2)
        ->and($result[0]->getOtherField())->toBe('content_json.start_date')
        ->and($result[0]->getConstantValue())->toBeNull()
        ->and($result[1])->toBeInstanceOf(NullableRule::class);
});

test('convert ignores invalid format for field_comparison', function () {
    $nullableRule = new NullableRule();
    $this->ruleFactory->shouldReceive('createNullableRule')
        ->once()
        ->andReturn($nullableRule);

    $result = $this->converter->convert([
        'field_comparison' => 'not an array',
    ]);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toBeInstanceOf(NullableRule::class);
});

// 3.4. Комбинации правил

test('convert handles multiple rules simultaneously', function () {
    $requiredRule = new RequiredRule();
    $minRule = new MinRule(3);
    $maxRule = new MaxRule(100);

    $this->ruleFactory->shouldReceive('createRequiredRule')
        ->once()
        ->andReturn($requiredRule);
    $this->ruleFactory->shouldReceive('createMinRule')
        ->once()
        ->with(3)
        ->andReturn($minRule);
    $this->ruleFactory->shouldReceive('createMaxRule')
        ->once()
        ->with(100)
        ->andReturn($maxRule);

    $result = $this->converter->convert([
        'required' => true,
        'min' => 3,
        'max' => 100,
    ]);

    expect($result)->toHaveCount(3)
        ->and($result[0])->toBeInstanceOf(RequiredRule::class)
        ->and($result[1])->toBeInstanceOf(MinRule::class)
        ->and($result[2])->toBeInstanceOf(MaxRule::class);
});

test('convert handles all rule types together', function () {
    $requiredRule = new RequiredRule();
    $minRule = new MinRule(5);
    $maxRule = new MaxRule(255);
    $patternRule = new PatternRule('/^test$/');
    $distinctRule = new DistinctRule();
    $conditionalRule = new ConditionalRule('required_if', 'is_published', true, '==');
    $fieldComparisonRule = new FieldComparisonRule('>=', 'content_json.start_date', null);

    $this->ruleFactory->shouldReceive('createRequiredRule')->andReturn($requiredRule);
    $this->ruleFactory->shouldReceive('createMinRule')->with(5)->andReturn($minRule);
    $this->ruleFactory->shouldReceive('createMaxRule')->with(255)->andReturn($maxRule);
    $this->ruleFactory->shouldReceive('createPatternRule')->with('/^test$/')->andReturn($patternRule);
    $this->ruleFactory->shouldReceive('createDistinctRule')->andReturn($distinctRule);
    $this->ruleFactory->shouldReceive('createConditionalRule')
        ->with('required_if', 'is_published', true, '==')
        ->andReturn($conditionalRule);
    $this->ruleFactory->shouldReceive('createFieldComparisonRule')
        ->with('>=', 'content_json.start_date', null)
        ->andReturn($fieldComparisonRule);

    $result = $this->converter->convert([
        'required' => true,
        'min' => 5,
        'max' => 255,
        'pattern' => '/^test$/',
        'distinct' => true,
        'required_if' => [
            'field' => 'is_published',
            'value' => true,
            'operator' => '==',
        ],
        'field_comparison' => [
            'operator' => '>=',
            'field' => 'content_json.start_date',
        ],
    ]);

    expect($result)->toHaveCount(7);
});

// 3.5. Ошибки

test('convert throws InvalidValidationRuleException for unknown rule', function () {
    expect(fn () => $this->converter->convert([
        'unknown_rule' => 'value',
    ]))->toThrow(InvalidValidationRuleException::class, "Неизвестное правило валидации: unknown_rule");
});

test('convert throws InvalidValidationRuleException with correct message', function () {
    expect(fn () => $this->converter->convert([
        'invalid_rule' => 'value',
    ]))->toThrow(InvalidValidationRuleException::class, "Неизвестное правило валидации: invalid_rule");
});

