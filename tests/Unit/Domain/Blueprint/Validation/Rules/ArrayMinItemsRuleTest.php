<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation\Rules;

use App\Domain\Blueprint\Validation\Rules\ArrayMinItemsRule;
use Tests\TestCase;

uses(TestCase::class);

test('returns correct type', function () {
    $rule = new ArrayMinItemsRule(1);

    expect($rule->getType())->toBe('array_min_items');
});

test('returns correct params', function () {
    $rule = new ArrayMinItemsRule(5);

    expect($rule->getParams())->toBe(['value' => 5]);
});

test('can get value', function () {
    $rule = new ArrayMinItemsRule(10);

    expect($rule->getValue())->toBe(10);
});

test('handles zero value', function () {
    $rule = new ArrayMinItemsRule(0);

    expect($rule->getValue())->toBe(0)
        ->and($rule->getParams())->toBe(['value' => 0]);
});

