<?php

declare(strict_types=1);

use App\Domain\Options\OptionsRepository;
use App\Events\OptionChanged;
use App\Models\Option;
use Illuminate\Support\Facades\Event;

/**
 * Feature-тесты для OptionsRepository.
 */

test('option can be created and retrieved', function () {
    $repo = app(OptionsRepository::class);

    $option = $repo->set('app', 'site_name', 'My CMS');

    expect($option)->toBeInstanceOf(Option::class)
        ->and($option->namespace)->toBe('app')
        ->and($option->key)->toBe('site_name')
        ->and($option->value_json)->toBe('My CMS');

    $retrieved = $repo->get('app', 'site_name');
    expect($retrieved)->toBe('My CMS');
});

test('option can be updated', function () {
    $repo = app(OptionsRepository::class);

    $repo->set('app', 'title', 'Old Title');
    $repo->set('app', 'title', 'New Title');

    $value = $repo->get('app', 'title');
    expect($value)->toBe('New Title');
});

test('option can be deleted', function () {
    $repo = app(OptionsRepository::class);

    $repo->set('app', 'temp_option', 'value');
    
    $deleted = $repo->delete('app', 'temp_option');
    
    expect($deleted)->toBeTrue();
    
    $value = $repo->get('app', 'temp_option', 'default');
    expect($value)->toBe('default');
});

test('options are scoped by namespace', function () {
    $repo = app(OptionsRepository::class);

    $repo->set('app', 'setting', 'app_value');
    $repo->set('plugin', 'setting', 'plugin_value');

    $appValue = $repo->get('app', 'setting');
    $pluginValue = $repo->get('plugin', 'setting');

    expect($appValue)->toBe('app_value')
        ->and($pluginValue)->toBe('plugin_value');
});

test('returns default value when option not found', function () {
    $repo = app(OptionsRepository::class);

    $value = $repo->get('nonexistent', 'key', 'default_value');

    expect($value)->toBe('default_value');
});

test('stores complex json values', function () {
    $repo = app(OptionsRepository::class);

    $complexValue = [
        'enabled' => true,
        'items' => ['a', 'b', 'c'],
        'config' => ['key' => 'value'],
    ];

    $repo->set('app', 'complex', $complexValue);
    $retrieved = $repo->get('app', 'complex');

    expect($retrieved)->toBe($complexValue);
});

test('soft deleted option returns default value', function () {
    $repo = app(OptionsRepository::class);

    $repo->set('app', 'deleted_opt', 'value');
    $repo->delete('app', 'deleted_opt');

    $value = $repo->get('app', 'deleted_opt', 'default');

    expect($value)->toBe('default');
});

test('can restore soft deleted option', function () {
    $repo = app(OptionsRepository::class);

    $repo->set('app', 'restore_test', 'original');
    $repo->delete('app', 'restore_test');
    
    $restored = $repo->restore('app', 'restore_test');

    expect($restored)->toBeInstanceOf(Option::class)
        ->and($restored->value_json)->toBe('original');

    $value = $repo->get('app', 'restore_test');
    expect($value)->toBe('original');
});

test('restore returns null for non existent option', function () {
    $repo = app(OptionsRepository::class);

    $result = $repo->restore('nonexistent', 'key');

    expect($result)->toBeNull();
});

test('delete returns false for non existent option', function () {
    $repo = app(OptionsRepository::class);

    $result = $repo->delete('nonexistent', 'key');

    expect($result)->toBeFalse();
});

test('dispatches option changed event on set', function () {
    Event::fake();
    $repo = app(OptionsRepository::class);

    $repo->set('app', 'event_test', 'new_value');

    Event::assertDispatched(OptionChanged::class, function ($event) {
        return $event->namespace === 'app'
            && $event->key === 'event_test'
            && $event->value === 'new_value';
    });
});

test('can update with description', function () {
    $repo = app(OptionsRepository::class);

    $option = $repo->set('app', 'described', 'value', 'Test description');

    expect($option->description)->toBe('Test description');

    $this->assertDatabaseHas('options', [
        'namespace' => 'app',
        'key' => 'described',
        'description' => 'Test description',
    ]);
});

test('set restores soft deleted option', function () {
    $repo = app(OptionsRepository::class);

    $repo->set('app', 'restore_via_set', 'original');
    $repo->delete('app', 'restore_via_set');
    
    // Setting again should restore it
    $option = $repo->set('app', 'restore_via_set', 'updated');

    expect($option->deleted_at)->toBeNull()
        ->and($option->value_json)->toBe('updated');
});

test('getInt returns integer value', function () {
    $repo = app(OptionsRepository::class);

    $repo->set('app', 'page_size', 25);

    $value = $repo->getInt('app', 'page_size');

    expect($value)->toBeInt()
        ->and($value)->toBe(25);
});

test('getInt returns default when option not found', function () {
    $repo = app(OptionsRepository::class);

    $value = $repo->getInt('nonexistent', 'key', 100);

    expect($value)->toBe(100);
});

test('getInt casts string to int', function () {
    $repo = app(OptionsRepository::class);

    $repo->set('app', 'string_number', '42');

    $value = $repo->getInt('app', 'string_number');

    expect($value)->toBeInt()
        ->and($value)->toBe(42);
});

