<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation\Rules;

use App\Domain\Blueprint\Validation\Rules\ArrayMaxItemsRule;
use Tests\TestCase;

uses(TestCase::class);

test('returns correct type', function () {
    $rule = new ArrayMaxItemsRule(10);

    expect($rule->getType())->toBe('array_max_items');
});

test('returns correct params', function () {
    $rule = new ArrayMaxItemsRule(100);

    expect($rule->getParams())->toBe(['value' => 100]);
});

test('can get value', function () {
    $rule = new ArrayMaxItemsRule(50);

    expect($rule->getValue())->toBe(50);
});

test('handles large values', function () {
    $rule = new ArrayMaxItemsRule(1000);

    expect($rule->getValue())->toBe(1000)
        ->and($rule->getParams())->toBe(['value' => 1000]);
});

