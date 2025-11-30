<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation;

use App\Domain\Blueprint\Validation\PathValidationRulesConverter;
use App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface;
use App\Domain\Blueprint\Validation\Rules\MaxRule;
use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Domain\Blueprint\Validation\Rules\NullableRule;
use App\Domain\Blueprint\Validation\Rules\PatternRule;
use App\Domain\Blueprint\Validation\Rules\RequiredRule;
use App\Domain\Blueprint\Validation\Rules\Rule;
use App\Domain\Blueprint\Validation\Rules\RuleFactory;
use Tests\TestCase;

uses(TestCase::class);

test('converts min max for string type', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        ['required' => true, 'min' => 1, 'max' => 500],
        'string',
        'one'
    );

    expect($rules)->toHaveCount(3)
        ->and($rules[0])->toBeInstanceOf(RequiredRule::class)
        ->and($rules[1])->toBeInstanceOf(MinRule::class)
        ->and($rules[2])->toBeInstanceOf(MaxRule::class)
        ->and($rules[1]->getValue())->toBe(1)
        ->and($rules[2]->getValue())->toBe(500);
});

test('converts min max for text type', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        ['required' => false, 'min' => 10, 'max' => 1000],
        'text',
        'one'
    );

    expect($rules)->toHaveCount(3)
        ->and($rules[0])->toBeInstanceOf(NullableRule::class)
        ->and($rules[1])->toBeInstanceOf(MinRule::class)
        ->and($rules[2])->toBeInstanceOf(MaxRule::class);
});

test('converts pattern for string type', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        ['required' => false, 'pattern' => '^\\+?[1-9]\\d{1,14}$'],
        'string',
        'one'
    );

    expect($rules)->toHaveCount(2)
        ->and($rules[0])->toBeInstanceOf(NullableRule::class)
        ->and($rules[1])->toBeInstanceOf(PatternRule::class)
        ->and($rules[1]->getPattern())->toBe('^\\+?[1-9]\\d{1,14}$');
});

test('converts min max for integer type', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        ['required' => true, 'min' => 0, 'max' => 100],
        'int',
        'one'
    );

    expect($rules)->toHaveCount(3)
        ->and($rules[0])->toBeInstanceOf(RequiredRule::class)
        ->and($rules[1])->toBeInstanceOf(MinRule::class)
        ->and($rules[2])->toBeInstanceOf(MaxRule::class)
        ->and($rules[1]->getValue())->toBe(0)
        ->and($rules[2]->getValue())->toBe(100);
});

test('converts min max for float type preserves precision', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        ['required' => false, 'min' => 0.5, 'max' => 5.5],
        'float',
        'one'
    );

    expect($rules)->toHaveCount(3)
        ->and($rules[0])->toBeInstanceOf(NullableRule::class)
        ->and($rules[1])->toBeInstanceOf(MinRule::class)
        ->and($rules[2])->toBeInstanceOf(MaxRule::class)
        ->and($rules[1]->getValue())->toBe(0.5)
        ->and($rules[2]->getValue())->toBe(5.5)
        ->and($rules[1]->getDataType())->toBe('float')
        ->and($rules[2]->getDataType())->toBe('float');
});

test('converts min max for float type with integer values', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        ['required' => true, 'min' => 0, 'max' => 10],
        'float',
        'one'
    );

    expect($rules)->toHaveCount(3)
        ->and($rules[0])->toBeInstanceOf(RequiredRule::class)
        ->and($rules[1])->toBeInstanceOf(MinRule::class)
        ->and($rules[2])->toBeInstanceOf(MaxRule::class);
});

test('handles required flag correctly', function () {
    $converter = createConverter();
    
    $requiredRules = $converter->convert(
        ['required' => true],
        'string',
        'one'
    );
    
    $nullableRules = $converter->convert(
        ['required' => false],
        'string',
        'one'
    );

    expect($requiredRules)->toHaveCount(1)
        ->and($requiredRules[0])->toBeInstanceOf(RequiredRule::class)
        ->and($nullableRules)->toHaveCount(1)
        ->and($nullableRules[0])->toBeInstanceOf(NullableRule::class);
});



test('handles empty validation rules', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        ['required' => true],
        'string',
        'one'
    );

    expect($rules)->toHaveCount(1)
        ->and($rules[0])->toBeInstanceOf(RequiredRule::class);
});



test('always adds min max rules regardless of relationship', function () {
    $converter = createConverter();
    
    // Конвертер всегда добавляет правила min/max, даже если min > max
    // Валидация соотношения min/max выполняется на HTTP-уровне
    $rules = $converter->convert(
        ['required' => true, 'min' => 500, 'max' => 1],
        'string',
        'one'
    );

    // Правила min и max должны быть добавлены (валидация на HTTP-уровне)
    expect($rules)->toHaveCount(3)
        ->and($rules[0])->toBeInstanceOf(RequiredRule::class)
        ->and($rules[1])->toBeInstanceOf(MinRule::class)
        ->and($rules[2])->toBeInstanceOf(MaxRule::class);
});

test('handles pattern with delimiters', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        ['required' => false, 'pattern' => '/^test$/i'],
        'string',
        'one'
    );

    expect($rules)->toHaveCount(2)
        ->and($rules[1])->toBeInstanceOf(PatternRule::class)
        ->and($rules[1]->getPattern())->toBe('/^test$/i');
});

test('handles pattern without delimiters', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        ['required' => false, 'pattern' => '^[A-Za-z0-9]+$'],
        'string',
        'one'
    );

    expect($rules)->toHaveCount(2)
        ->and($rules[1])->toBeInstanceOf(PatternRule::class)
        ->and($rules[1]->getPattern())->toBe('^[A-Za-z0-9]+$');
});

test('handles invalid pattern gracefully', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        ['required' => false, 'pattern' => ''],
        'string',
        'one'
    );

    expect($rules)->toHaveCount(2)
        ->and($rules[1])->toBeInstanceOf(PatternRule::class)
        ->and($rules[1]->getPattern())->toBe('.*'); // Дефолтный паттерн для пустой строки
});

test('handles all validation rules together', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        [
            'required' => true,
            'min' => 1,
            'max' => 500,
            'pattern' => '^[A-Za-z0-9]+$',
        ],
        'string',
        'one'
    );

    expect($rules)->toHaveCount(4)
        ->and($rules[0])->toBeInstanceOf(RequiredRule::class)
        ->and($rules[1])->toBeInstanceOf(MinRule::class)
        ->and($rules[2])->toBeInstanceOf(MaxRule::class)
        ->and($rules[3])->toBeInstanceOf(PatternRule::class);
});

test('ignores unknown validation rule keys', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        [
            'required' => true,
            'min' => 1,
            'max' => 500,
            'unknown_key' => 'value',
        ],
        'string',
        'one'
    );

    expect($rules)->toHaveCount(3)
        ->and($rules[0])->toBeInstanceOf(RequiredRule::class)
        ->and($rules[1])->toBeInstanceOf(MinRule::class)
        ->and($rules[2])->toBeInstanceOf(MaxRule::class);
});

test('throws exception for conditional rule with string format', function () {
    $converter = createConverter();
    
    expect(fn() => $converter->convert(
        ['required' => false, 'required_if' => 'is_published'],
        'string',
        'one'
    ))->toThrow(\InvalidArgumentException::class, "Условное правило 'required_if' должно быть массивом");
});

test('throws exception for conditional rule with old format', function () {
    $converter = createConverter();
    
    expect(fn() => $converter->convert(
        ['required' => false, 'required_if' => ['is_published' => true]],
        'string',
        'one'
    ))->toThrow(\InvalidArgumentException::class, "Условное правило 'required_if' должно содержать обязательное поле 'field'");
});

test('throws exception for conditional rule without field', function () {
    $converter = createConverter();
    
    expect(fn() => $converter->convert(
        ['required' => false, 'required_if' => ['value' => true]],
        'string',
        'one'
    ))->toThrow(\InvalidArgumentException::class, "Условное правило 'required_if' должно содержать обязательное поле 'field'");
});

test('accepts conditional rule with extended format', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        ['required' => false, 'required_if' => ['field' => 'is_published', 'value' => true, 'operator' => '==']],
        'string',
        'one'
    );

    expect($rules)->toHaveCount(2)
        ->and($rules[0])->toBeInstanceOf(NullableRule::class);
});

/**
 * Создать экземпляр конвертера для тестов.
 *
 * @return \App\Domain\Blueprint\Validation\PathValidationRulesConverterInterface
 */
function createConverter(): PathValidationRulesConverterInterface
{
    $ruleFactory = app(RuleFactory::class);
    
    return new PathValidationRulesConverter($ruleFactory);
}
