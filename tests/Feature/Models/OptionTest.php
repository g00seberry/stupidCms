<?php

declare(strict_types=1);

use App\Models\Option;

/**
 * Feature-тесты для модели Option.
 */

test('option can be created', function () {
    $option = Option::factory()->create([
        'namespace' => 'app',
        'key' => 'site_name',
        'value_json' => 'My Site',
    ]);

    expect($option)->toBeInstanceOf(Option::class)
        ->and($option->namespace)->toBe('app')
        ->and($option->key)->toBe('site_name')
        ->and($option->value_json)->toBe('My Site')
        ->and($option->exists)->toBeTrue();

    $this->assertDatabaseHas('options', [
        'id' => $option->id,
        'namespace' => 'app',
        'key' => 'site_name',
    ]);
});

test('option value is stored as json', function () {
    $value = ['setting1' => 'value1', 'setting2' => 42];

    $option = Option::factory()->create([
        'namespace' => 'app',
        'key' => 'settings',
        'value_json' => $value,
    ]);

    $option->refresh();

    expect($option->value_json)->toBe($value);
});

test('option can be retrieved by namespace and key', function () {
    Option::factory()->create([
        'namespace' => 'app',
        'key' => 'test_key',
        'value_json' => 'test_value',
    ]);

    $option = Option::where('namespace', 'app')
        ->where('key', 'test_key')
        ->first();

    expect($option)->not->toBeNull()
        ->and($option->value_json)->toBe('test_value');
});

test('option can be soft deleted', function () {
    $option = Option::factory()->create();
    $optionId = $option->id;

    $option->delete();

    expect($option->trashed())->toBeTrue();

    $this->assertSoftDeleted('options', [
        'id' => $optionId,
    ]);
});

test('option can be restored after soft delete', function () {
    $option = Option::factory()->create();
    $option->delete();

    $option->restore();

    expect($option->trashed())->toBeFalse();

    $this->assertDatabaseHas('options', [
        'id' => $option->id,
        'deleted_at' => null,
    ]);
});

