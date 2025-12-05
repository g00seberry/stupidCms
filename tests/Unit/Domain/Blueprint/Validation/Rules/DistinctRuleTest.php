<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\DistinctRule;

/**
 * Unit-тесты для DistinctRule.
 */

test('getType returns distinct', function () {
    $rule = new DistinctRule();

    expect($rule->getType())->toBe('distinct');
});

test('getParams returns empty array', function () {
    $rule = new DistinctRule();

    expect($rule->getParams())->toBeArray();
    expect($rule->getParams())->toBeEmpty();
});


