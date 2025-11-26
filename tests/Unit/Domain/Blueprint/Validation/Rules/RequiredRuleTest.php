<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\RequiredRule;
use Tests\TestCase;

uses(TestCase::class);

test('returns correct type', function () {
    $rule = new RequiredRule();

    expect($rule->getType())->toBe('required');
});

test('returns empty params', function () {
    $rule = new RequiredRule();

    $params = $rule->getParams();

    expect($params)->toBeArray()
        ->and($params)->toBeEmpty();
});

