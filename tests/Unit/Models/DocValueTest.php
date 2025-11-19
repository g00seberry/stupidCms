<?php

declare(strict_types=1);

use App\Models\DocValue;
use App\Models\Entry;
use App\Models\Path;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

/**
 * Unit-тесты для модели DocValue.
 */

uses(TestCase::class);

test('has no guarded attributes', function () {
    $docValue = new DocValue();

    expect($docValue->getGuarded())->toBe([]);
});

test('does not use timestamps', function () {
    $docValue = new DocValue();

    expect($docValue->timestamps)->toBeFalse();
});

test('belongs to entry', function () {
    $docValue = new DocValue();
    $relation = $docValue->entry();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Entry::class);
});

test('belongs to path', function () {
    $docValue = new DocValue();
    $relation = $docValue->path();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Path::class);
});

test('get value returns string value', function () {
    $docValue = new DocValue(['value_string' => 'test']);
    $docValue->path = Path::factory()->make(['data_type' => 'string']);

    expect($docValue->getValue())->toBe('test');
});

test('get value returns int value', function () {
    $docValue = new DocValue(['value_int' => 42]);
    $docValue->path = Path::factory()->make(['data_type' => 'int']);

    expect($docValue->getValue())->toBe(42);
});

test('get value returns float value', function () {
    $docValue = new DocValue(['value_float' => 3.14]);
    $docValue->path = Path::factory()->make(['data_type' => 'float']);

    expect($docValue->getValue())->toBe(3.14);
});

test('get value returns bool value', function () {
    $docValue = new DocValue(['value_bool' => true]);
    $docValue->path = Path::factory()->make(['data_type' => 'bool']);

    expect($docValue->getValue())->toBeTrue();
});

test('get value returns text value', function () {
    $docValue = new DocValue(['value_text' => 'Long text...']);
    $docValue->path = Path::factory()->make(['data_type' => 'text']);

    expect($docValue->getValue())->toBe('Long text...');
});

test('get value returns json value', function () {
    $jsonData = ['key' => 'value'];
    $docValue = new DocValue(['value_json' => $jsonData]);
    $docValue->path = Path::factory()->make(['data_type' => 'json']);

    expect($docValue->getValue())->toBe($jsonData);
});

test('get value returns null for ref type', function () {
    $docValue = new DocValue();
    $docValue->path = Path::factory()->make(['data_type' => 'ref']);

    expect($docValue->getValue())->toBeNull();
});

test('idx defaults to 0', function () {
    $entry = Entry::factory()->create();
    $path = Path::factory()->create();
    
    $docValue = DocValue::create([
        'entry_id' => $entry->id,
        'path_id' => $path->id,
        'value_string' => 'test',
    ]);

    expect($docValue->idx)->toBe(0);
});

test('idx can be greater than 0 for many cardinality', function () {
    $entry = Entry::factory()->create();
    $path = Path::factory()->create(['cardinality' => 'many']);
    
    $docValue = DocValue::create([
        'entry_id' => $entry->id,
        'path_id' => $path->id,
        'idx' => 2,
        'value_string' => 'test',
    ]);

    expect($docValue->idx)->toBe(2);
});

test('composite primary key entry_id path_id idx', function () {
    $entry = Entry::factory()->create();
    $path = Path::factory()->create();
    
    $docValue1 = DocValue::create([
        'entry_id' => $entry->id,
        'path_id' => $path->id,
        'idx' => 0,
        'value_string' => 'value1',
    ]);
    
    $docValue2 = DocValue::create([
        'entry_id' => $entry->id,
        'path_id' => $path->id,
        'idx' => 1,
        'value_string' => 'value2',
    ]);
    
    expect($docValue1->value_string)->toBe('value1')
        ->and($docValue2->value_string)->toBe('value2');
});

