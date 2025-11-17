<?php

declare(strict_types=1);

use App\Models\Option;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Unit-тесты для модели Option.
 */

test('uses ULID as primary key', function () {
    $option = new Option();

    expect($option->getKeyType())->toBe('string')
        ->and($option->getIncrementing())->toBeFalse();
});

test('has fillable attributes', function () {
    $option = new Option();

    $fillable = $option->getFillable();

    expect($fillable)->toContain('namespace')
        ->and($fillable)->toContain('key')
        ->and($fillable)->toContain('value_json')
        ->and($fillable)->toContain('description');
});

test('casts value_json using AsJsonValue', function () {
    $option = new Option();

    $casts = $option->getCasts();

    expect($casts)->toHaveKey('value_json')
        ->and($casts['value_json'])->toBe(\App\Casts\AsJsonValue::class);
});

test('uses soft deletes', function () {
    $option = new Option();

    $traits = class_uses_recursive($option);

    expect($traits)->toHaveKey(SoftDeletes::class);
});

test('table name is options', function () {
    $option = new Option();

    expect($option->getTable())->toBe('options');
});

