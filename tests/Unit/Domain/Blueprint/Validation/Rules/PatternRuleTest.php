<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\PatternRule;
use Tests\TestCase;

uses(TestCase::class);

test('returns correct type', function () {
    $rule = new PatternRule('^\\+?[1-9]\\d{1,14}$');

    expect($rule->getType())->toBe('pattern');
});

test('returns correct params', function () {
    $pattern = '^\\+?[1-9]\\d{1,14}$';
    $rule = new PatternRule($pattern);

    $params = $rule->getParams();

    expect($params)->toBeArray()
        ->and($params)->toHaveKey('pattern')
        ->and($params['pattern'])->toBe($pattern);
});

test('can get pattern', function () {
    $pattern = '/^test$/i';
    $rule = new PatternRule($pattern);

    expect($rule->getPattern())->toBe($pattern);
});

test('handles pattern without delimiters', function () {
    $pattern = '^[A-Za-z0-9]+$';
    $rule = new PatternRule($pattern);

    expect($rule->getPattern())->toBe($pattern);
});

test('handles pattern with delimiters and flags', function () {
    $pattern = '/^test$/i';
    $rule = new PatternRule($pattern);

    expect($rule->getPattern())->toBe($pattern);
});

