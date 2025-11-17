<?php

declare(strict_types=1);

use App\Domain\PostTypes\PostTypeOptions;

/**
 * Unit-тесты для PostTypeOptions (Value Object).
 */

test('creates options from array', function () {
    $options = PostTypeOptions::fromArray([
        'taxonomies' => [1, 2, 3],
        'custom_field' => 'value',
    ]);

    expect($options->taxonomies)->toBe([1, 2, 3])
        ->and($options->fields)->toHaveKey('custom_field')
        ->and($options->fields['custom_field'])->toBe('value');
});

test('creates empty options', function () {
    $options = PostTypeOptions::empty();

    expect($options->taxonomies)->toBe([])
        ->and($options->fields)->toBe([]);
});

test('converts options to array', function () {
    $options = PostTypeOptions::fromArray([
        'taxonomies' => [1, 2],
        'field1' => 'value1',
        'field2' => 'value2',
    ]);

    $array = $options->toArray();

    expect($array)->toBeArray()
        ->and($array)->toHaveKey('taxonomies')
        ->and($array['taxonomies'])->toBe([1, 2])
        ->and($array)->toHaveKey('field1')
        ->and($array['field1'])->toBe('value1');
});

test('normalizes string taxonomies to integers', function () {
    $options = PostTypeOptions::fromArray([
        'taxonomies' => ['1', '2', '3'],
    ]);

    expect($options->taxonomies)->toBe([1, 2, 3]);
});

test('accepts mixed integer and string taxonomies', function () {
    $options = PostTypeOptions::fromArray([
        'taxonomies' => [1, '2', 3, '4'],
    ]);

    expect($options->taxonomies)->toBe([1, 2, 3, 4]);
});

test('throws exception for invalid taxonomies', function () {
    PostTypeOptions::fromArray([
        'taxonomies' => ['invalid', 'taxonomies'],
    ]);
})->throws(InvalidArgumentException::class, 'Taxonomies must be an array of positive integers');

test('throws exception for negative taxonomy ids', function () {
    PostTypeOptions::fromArray([
        'taxonomies' => [-1, 2, 3],
    ]);
})->throws(InvalidArgumentException::class);

test('throws exception for zero taxonomy id', function () {
    PostTypeOptions::fromArray([
        'taxonomies' => [0, 1, 2],
    ]);
})->throws(InvalidArgumentException::class);

test('throws exception when taxonomies is not a list', function () {
    PostTypeOptions::fromArray([
        'taxonomies' => ['key' => 'value'],
    ]);
})->throws(InvalidArgumentException::class);

test('gets allowed taxonomies', function () {
    $options = PostTypeOptions::fromArray([
        'taxonomies' => [1, 2, 3],
    ]);

    $taxonomies = $options->getAllowedTaxonomies();

    expect($taxonomies)->toBe([1, 2, 3]);
});

test('checks if taxonomy is allowed', function () {
    $options = PostTypeOptions::fromArray([
        'taxonomies' => [1, 2, 3],
    ]);

    expect($options->isTaxonomyAllowed(1))->toBeTrue()
        ->and($options->isTaxonomyAllowed(2))->toBeTrue()
        ->and($options->isTaxonomyAllowed(4))->toBeFalse();
});

test('allows all taxonomies when list is empty', function () {
    $options = PostTypeOptions::empty();

    expect($options->isTaxonomyAllowed(1))->toBeTrue()
        ->and($options->isTaxonomyAllowed(999))->toBeTrue();
});

test('gets field value', function () {
    $options = PostTypeOptions::fromArray([
        'field1' => 'value1',
        'field2' => 'value2',
    ]);

    expect($options->getField('field1'))->toBe('value1')
        ->and($options->getField('field2'))->toBe('value2');
});

test('returns default for non existent field', function () {
    $options = PostTypeOptions::empty();

    $value = $options->getField('nonexistent', 'default');

    expect($value)->toBe('default');
});

test('checks if field exists', function () {
    $options = PostTypeOptions::fromArray([
        'existing_field' => 'value',
    ]);

    expect($options->hasField('existing_field'))->toBeTrue()
        ->and($options->hasField('nonexistent'))->toBeFalse();
});

test('is immutable value object', function () {
    $options = PostTypeOptions::fromArray([
        'taxonomies' => [1, 2, 3],
    ]);

    $reflection = new ReflectionClass($options);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->getProperty('taxonomies')->isReadOnly())->toBeTrue()
        ->and($reflection->getProperty('fields')->isReadOnly())->toBeTrue();
});

test('converts to api array with normalized structure', function () {
    $options = PostTypeOptions::fromArray([
        'taxonomies' => [1, 2, 3],
        'nested' => [],
    ]);

    $apiArray = $options->toApiArray();

    expect($apiArray)->toBeInstanceOf(stdClass::class)
        ->and($apiArray->taxonomies)->toBe([1, 2, 3]);
});

test('preserves taxonomies as array in api response', function () {
    $options = PostTypeOptions::fromArray([
        'taxonomies' => [1, 2],
    ]);

    $apiArray = $options->toApiArray();

    expect($apiArray->taxonomies)->toBeArray()
        ->and($apiArray->taxonomies)->toBe([1, 2]);
});

test('handles complex nested structures', function () {
    $options = PostTypeOptions::fromArray([
        'taxonomies' => [1],
        'config' => [
            'level1' => [
                'level2' => 'value',
            ],
        ],
    ]);

    $array = $options->toArray();

    expect($array['config']['level1']['level2'])->toBe('value');
});

