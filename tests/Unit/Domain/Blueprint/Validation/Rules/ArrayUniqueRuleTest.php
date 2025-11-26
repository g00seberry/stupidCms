<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation\Rules;

use App\Domain\Blueprint\Validation\Rules\ArrayUniqueRule;
use Tests\TestCase;

uses(TestCase::class);

test('returns correct type', function () {
    $rule = new ArrayUniqueRule();

    expect($rule->getType())->toBe('array_unique');
});

test('returns empty params', function () {
    $rule = new ArrayUniqueRule();

    expect($rule->getParams())->toBe([]);
});

