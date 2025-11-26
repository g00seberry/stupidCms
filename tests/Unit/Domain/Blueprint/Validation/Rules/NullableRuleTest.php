<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\NullableRule;
use Tests\TestCase;

uses(TestCase::class);

test('returns correct type', function () {
    $rule = new NullableRule();

    expect($rule->getType())->toBe('nullable');
});

test('returns empty params', function () {
    $rule = new NullableRule();

    $params = $rule->getParams();

    expect($params)->toBeArray()
        ->and($params)->toBeEmpty();
});

