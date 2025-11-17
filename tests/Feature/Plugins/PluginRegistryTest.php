<?php

declare(strict_types=1);

use App\Domain\Plugins\PluginRegistry;
use App\Models\Plugin;

/**
 * Feature-тесты для PluginRegistry.
 */

test('returns enabled plugins only', function () {
    $registry = app(PluginRegistry::class);
    
    Plugin::factory()->create(['slug' => 'plugin-a', 'enabled' => true]);
    Plugin::factory()->create(['slug' => 'plugin-b', 'enabled' => false]);
    Plugin::factory()->create(['slug' => 'plugin-c', 'enabled' => true]);

    $enabled = $registry->enabled();

    expect($enabled)->toHaveCount(2)
        ->and($enabled->pluck('slug')->toArray())->toBe(['plugin-a', 'plugin-c']);
});

test('returns empty collection when no plugins enabled', function () {
    $registry = app(PluginRegistry::class);
    
    Plugin::factory()->create(['enabled' => false]);
    Plugin::factory()->create(['enabled' => false]);

    $enabled = $registry->enabled();

    expect($enabled)->toHaveCount(0)
        ->and($enabled)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

test('orders plugins by slug', function () {
    $registry = app(PluginRegistry::class);
    
    Plugin::factory()->create(['slug' => 'zebra-plugin', 'enabled' => true]);
    Plugin::factory()->create(['slug' => 'alpha-plugin', 'enabled' => true]);
    Plugin::factory()->create(['slug' => 'beta-plugin', 'enabled' => true]);

    $enabled = $registry->enabled();

    expect($enabled->pluck('slug')->toArray())->toBe([
        'alpha-plugin',
        'beta-plugin',
        'zebra-plugin',
    ]);
});

test('returns enabled providers', function () {
    $registry = app(PluginRegistry::class);
    
    Plugin::factory()->create([
        'enabled' => true,
        'provider_fqcn' => 'App\Plugins\PluginA\ServiceProvider',
    ]);
    Plugin::factory()->create([
        'enabled' => false,
        'provider_fqcn' => 'App\Plugins\PluginB\ServiceProvider',
    ]);
    Plugin::factory()->create([
        'enabled' => true,
        'provider_fqcn' => 'App\Plugins\PluginC\ServiceProvider',
    ]);

    $providers = $registry->enabledProviders();

    expect($providers)->toHaveCount(2)
        ->and($providers)->toContain('App\Plugins\PluginA\ServiceProvider')
        ->and($providers)->toContain('App\Plugins\PluginC\ServiceProvider')
        ->and($providers)->not->toContain('App\Plugins\PluginB\ServiceProvider');
});

test('filters out empty provider names', function () {
    $registry = app(PluginRegistry::class);
    
    Plugin::factory()->create([
        'enabled' => true,
        'provider_fqcn' => 'App\Plugins\Valid\ServiceProvider',
    ]);
    Plugin::factory()->create([
        'enabled' => true,
        'provider_fqcn' => '',
    ]);

    $providers = $registry->enabledProviders();

    expect($providers)->toHaveCount(1)
        ->and($providers[0])->toBe('App\Plugins\Valid\ServiceProvider');
});

test('returns empty array when no enabled plugins', function () {
    $registry = app(PluginRegistry::class);
    
    Plugin::factory()->create(['enabled' => false]);

    $providers = $registry->enabledProviders();

    expect($providers)->toBe([]);
});

test('handles mixed provider types', function () {
    $registry = app(PluginRegistry::class);
    
    Plugin::factory()->create([
        'enabled' => true,
        'provider_fqcn' => 'App\Plugins\Alpha\ServiceProvider',
    ]);
    Plugin::factory()->create([
        'enabled' => true,
        'provider_fqcn' => '',
    ]);

    $providers = $registry->enabledProviders();

    expect($providers)->toBeArray()
        ->and($providers)->toHaveCount(1);
});

