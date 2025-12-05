<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\NullableRule;

/**
 * Unit-тесты для NullableRule.
 */

test('getType returns nullable', function () {
    $rule = new NullableRule();

    expect($rule->getType())->toBe('nullable');
});

test('getParams returns empty array', function () {
    $rule = new NullableRule();

    expect($rule->getParams())->toBeArray();
    expect($rule->getParams())->toBeEmpty();
});


