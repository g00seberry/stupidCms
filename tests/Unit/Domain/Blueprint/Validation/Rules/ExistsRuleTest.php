<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation\Rules;

use App\Domain\Blueprint\Validation\Rules\ExistsRule;
use Tests\TestCase;

uses(TestCase::class);

test('returns correct type', function () {
    $rule = new ExistsRule('entries', 'id');

    expect($rule->getType())->toBe('exists');
});

test('returns correct params for simple rule', function () {
    $rule = new ExistsRule('entries', 'id');

    expect($rule->getParams())->toBe([
        'table' => 'entries',
        'column' => 'id',
    ]);
});

test('can get table and column', function () {
    $rule = new ExistsRule('entries', 'id');

    expect($rule->getTable())->toBe('entries')
        ->and($rule->getColumn())->toBe('id');
});

test('handles where condition', function () {
    $rule = new ExistsRule('entries', 'id', 'status', 'published');

    expect($rule->getWhereColumn())->toBe('status')
        ->and($rule->getWhereValue())->toBe('published')
        ->and($rule->getParams())->toHaveKey('where_column')
        ->and($rule->getParams())->toHaveKey('where_value');
});

test('defaults column to id', function () {
    $rule = new ExistsRule('entries');

    expect($rule->getColumn())->toBe('id');
});

