<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\RequiredRule;

/**
 * Unit-тесты для RequiredRule.
 */

test('getType returns required', function () {
    $rule = new RequiredRule();

    expect($rule->getType())->toBe('required');
});

test('getParams returns empty array', function () {
    $rule = new RequiredRule();

    expect($rule->getParams())->toBeArray();
    expect($rule->getParams())->toBeEmpty();
});


