<?php

declare(strict_types=1);

use App\Models\Plugin;

/**
 * Feature-тесты для модели Plugin.
 */

test('plugin can be created', function () {
    $plugin = Plugin::factory()->create([
        'name' => 'Test Plugin',
        'slug' => 'test-plugin',
    ]);

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($plugin->name)->toBe('Test Plugin')
        ->and($plugin->slug)->toBe('test-plugin')
        ->and($plugin->exists)->toBeTrue();

    $this->assertDatabaseHas('plugins', [
        'id' => $plugin->id,
        'slug' => 'test-plugin',
    ]);
});

test('plugin can be enabled', function () {
    $plugin = Plugin::factory()->create(['enabled' => true]);

    expect($plugin->enabled)->toBeTrue();

    $this->assertDatabaseHas('plugins', [
        'id' => $plugin->id,
        'enabled' => true,
    ]);
});

test('plugin can be disabled', function () {
    $plugin = Plugin::factory()->create(['enabled' => false]);

    expect($plugin->enabled)->toBeFalse();

    $this->assertDatabaseHas('plugins', [
        'id' => $plugin->id,
        'enabled' => false,
    ]);
});

test('plugin stores metadata', function () {
    $meta = [
        'version' => '1.0.0',
        'author' => 'Test Author',
        'description' => 'Test Description',
    ];

    $plugin = Plugin::factory()->create(['meta_json' => $meta]);

    $plugin->refresh();

    expect($plugin->meta_json)->toBe($meta)
        ->and($plugin->meta_json['version'])->toBe('1.0.0');
});

test('plugin tracks last sync time', function () {
    $syncTime = now();
    $plugin = Plugin::factory()->create(['last_synced_at' => $syncTime]);

    $plugin->refresh();

    expect($plugin->last_synced_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

