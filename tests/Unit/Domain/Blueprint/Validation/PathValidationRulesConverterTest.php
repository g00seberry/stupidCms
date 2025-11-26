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
        ['min' => 1, 'max' => 500],
        'string',
        true,
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
        ['min' => 10, 'max' => 1000],
        'text',
        false,
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
        ['pattern' => '^\\+?[1-9]\\d{1,14}$'],
        'string',
        false,
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
        ['min' => 0, 'max' => 100],
        'int',
        true,
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
        ['min' => 0.5, 'max' => 5.5],
        'float',
        false,
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
        ['min' => 0, 'max' => 10],
        'float',
        true,
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
        null,
        'string',
        true,
        'one'
    );
    
    $nullableRules = $converter->convert(
        null,
        'string',
        false,
        'one'
    );

    expect($requiredRules)->toHaveCount(1)
        ->and($requiredRules[0])->toBeInstanceOf(RequiredRule::class)
        ->and($nullableRules)->toHaveCount(1)
        ->and($nullableRules[0])->toBeInstanceOf(NullableRule::class);
});

test('handles cardinality many correctly', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        ['min' => 1, 'max' => 50],
        'string',
        true, // is_required игнорируется для элементов массива
        'many'
    );

    // Для cardinality: 'many' не должно быть required/nullable
    expect($rules)->toHaveCount(2)
        ->and($rules[0])->toBeInstanceOf(MinRule::class)
        ->and($rules[1])->toBeInstanceOf(MaxRule::class);
    
    // Проверяем, что нет RequiredRule или NullableRule
    foreach ($rules as $rule) {
        expect($rule)->not->toBeInstanceOf(RequiredRule::class)
            ->and($rule)->not->toBeInstanceOf(NullableRule::class);
    }
});

test('handles empty validation rules', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        [],
        'string',
        true,
        'one'
    );

    expect($rules)->toHaveCount(1)
        ->and($rules[0])->toBeInstanceOf(RequiredRule::class);
});

test('handles null validation rules', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        null,
        'string',
        false,
        'one'
    );

    expect($rules)->toHaveCount(1)
        ->and($rules[0])->toBeInstanceOf(NullableRule::class);
});

test('validates min max relationship', function () {
    $converter = createConverter();
    
    // Если min > max, оба правила игнорируются
    $rules = $converter->convert(
        ['min' => 500, 'max' => 1],
        'string',
        true,
        'one'
    );

    // Должны остаться только required/nullable правила
    expect($rules)->toHaveCount(1)
        ->and($rules[0])->toBeInstanceOf(RequiredRule::class);
});

test('handles pattern with delimiters', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        ['pattern' => '/^test$/i'],
        'string',
        false,
        'one'
    );

    expect($rules)->toHaveCount(2)
        ->and($rules[1])->toBeInstanceOf(PatternRule::class)
        ->and($rules[1]->getPattern())->toBe('/^test$/i');
});

test('handles pattern without delimiters', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        ['pattern' => '^[A-Za-z0-9]+$'],
        'string',
        false,
        'one'
    );

    expect($rules)->toHaveCount(2)
        ->and($rules[1])->toBeInstanceOf(PatternRule::class)
        ->and($rules[1]->getPattern())->toBe('^[A-Za-z0-9]+$');
});

test('handles invalid pattern gracefully', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        ['pattern' => ''],
        'string',
        false,
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
            'min' => 1,
            'max' => 500,
            'pattern' => '^[A-Za-z0-9]+$',
        ],
        'string',
        true,
        'one'
    );

    expect($rules)->toHaveCount(4)
        ->and($rules[0])->toBeInstanceOf(RequiredRule::class)
        ->and($rules[1])->toBeInstanceOf(PatternRule::class)
        ->and($rules[2])->toBeInstanceOf(MinRule::class)
        ->and($rules[3])->toBeInstanceOf(MaxRule::class);
});

test('ignores unknown validation rule keys', function () {
    $converter = createConverter();
    
    $rules = $converter->convert(
        [
            'min' => 1,
            'max' => 500,
            'unknown_key' => 'value',
        ],
        'string',
        true,
        'one'
    );

    expect($rules)->toHaveCount(3)
        ->and($rules[0])->toBeInstanceOf(RequiredRule::class)
        ->and($rules[1])->toBeInstanceOf(MinRule::class)
        ->and($rules[2])->toBeInstanceOf(MaxRule::class);
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
