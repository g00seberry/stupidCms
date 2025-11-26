<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Blueprint\Validation\Rules;

use App\Domain\Blueprint\Validation\Rules\UniqueRule;
use Tests\TestCase;

uses(TestCase::class);

test('returns correct type', function () {
    $rule = new UniqueRule('entries', 'slug');

    expect($rule->getType())->toBe('unique');
});

test('returns correct params for simple rule', function () {
    $rule = new UniqueRule('entries', 'slug');

    expect($rule->getParams())->toBe([
        'table' => 'entries',
        'column' => 'slug',
    ]);
});

test('can get table and column', function () {
    $rule = new UniqueRule('entries', 'slug');

    expect($rule->getTable())->toBe('entries')
        ->and($rule->getColumn())->toBe('slug');
});

test('handles except column and value', function () {
    $rule = new UniqueRule('entries', 'slug', 'id', 5);

    expect($rule->getExceptColumn())->toBe('id')
        ->and($rule->getExceptValue())->toBe(5)
        ->and($rule->getParams())->toHaveKey('except_column')
        ->and($rule->getParams())->toHaveKey('except_value');
});

test('handles where condition', function () {
    $rule = new UniqueRule('entries', 'slug', null, null, 'post_type_id', 1);

    expect($rule->getWhereColumn())->toBe('post_type_id')
        ->and($rule->getWhereValue())->toBe(1)
        ->and($rule->getParams())->toHaveKey('where_column')
        ->and($rule->getParams())->toHaveKey('where_value');
});

test('handles all parameters together', function () {
    $rule = new UniqueRule('entries', 'slug', 'id', 5, 'post_type_id', 1);

    expect($rule->getTable())->toBe('entries')
        ->and($rule->getColumn())->toBe('slug')
        ->and($rule->getExceptColumn())->toBe('id')
        ->and($rule->getExceptValue())->toBe(5)
        ->and($rule->getWhereColumn())->toBe('post_type_id')
        ->and($rule->getWhereValue())->toBe(1);
});

test('defaults column to id', function () {
    $rule = new UniqueRule('entries');

    expect($rule->getColumn())->toBe('id');
});

