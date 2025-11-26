<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation\Rules;

use App\Domain\Blueprint\Validation\Rules\ConditionalRule;
use Tests\TestCase;

uses(TestCase::class);

test('returns correct type for required_if', function () {
    $rule = new ConditionalRule('required_if', 'is_published', true);

    expect($rule->getType())->toBe('required_if');
});

test('returns correct params', function () {
    $rule = new ConditionalRule('required_if', 'is_published', true);

    expect($rule->getParams())->toBe([
        'field' => 'is_published',
        'value' => true,
        'operator' => '==',
    ]);
});

test('can get field value and operator', function () {
    $rule = new ConditionalRule('prohibited_unless', 'status', 'active', '!=');

    expect($rule->getField())->toBe('status')
        ->and($rule->getValue())->toBe('active')
        ->and($rule->getOperator())->toBe('!=');
});

test('defaults operator to equals', function () {
    $rule = new ConditionalRule('required_if', 'field', 'value');

    expect($rule->getOperator())->toBe('==');
});

test('handles different conditional types', function () {
    $types = ['required_if', 'prohibited_unless', 'required_unless', 'prohibited_if'];

    foreach ($types as $type) {
        $rule = new ConditionalRule($type, 'field', 'value');
        expect($rule->getType())->toBe($type);
    }
});

test('handles boolean values', function () {
    $rule = new ConditionalRule('required_if', 'is_published', true);

    expect($rule->getValue())->toBe(true)
        ->and($rule->getParams()['value'])->toBe(true);
});

test('handles string values', function () {
    $rule = new ConditionalRule('required_if', 'status', 'published');

    expect($rule->getValue())->toBe('published');
});

test('handles numeric values', function () {
    $rule = new ConditionalRule('required_if', 'count', 5);

    expect($rule->getValue())->toBe(5);
});

