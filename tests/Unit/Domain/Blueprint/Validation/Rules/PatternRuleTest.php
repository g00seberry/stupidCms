<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\PatternRule;

/**
 * Unit-тесты для PatternRule.
 */

test('getType returns pattern', function () {
    $rule = new PatternRule('/^test$/');

    expect($rule->getType())->toBe('pattern');
});

test('getParams returns array with pattern', function () {
    $pattern = '/^test$/';
    $rule = new PatternRule($pattern);

    $params = $rule->getParams();
    expect($params)->toBeArray();
    expect($params)->toHaveKey('pattern');
    expect($params['pattern'])->toBe($pattern);
});

test('getPattern returns passed pattern', function () {
    $pattern = '/^test$/';
    $rule = new PatternRule($pattern);

    expect($rule->getPattern())->toBe($pattern);
});

test('correctly stores regular expressions', function () {
    $patterns = [
        '/^test$/',
        '/^[a-z0-9]+$/',
        '/^\\d+$/',
        '^simple$', // Без ограничителей
    ];

    foreach ($patterns as $pattern) {
        $rule = new PatternRule($pattern);
        expect($rule->getPattern())->toBe($pattern);
    }
});

test('correctly stores complex regex patterns', function () {
    $complexPattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$/';
    $rule = new PatternRule($complexPattern);

    expect($rule->getPattern())->toBe($complexPattern);
});


