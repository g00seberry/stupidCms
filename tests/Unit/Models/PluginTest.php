<?php

declare(strict_types=1);

use App\Models\Plugin;

/**
 * Unit-тесты для модели Plugin.
 */

test('uses ULID as primary key', function () {
    $plugin = new Plugin();

    expect($plugin->getKeyType())->toBe('string')
        ->and($plugin->getIncrementing())->toBeFalse();
});

test('casts enabled to boolean', function () {
    $plugin = new Plugin();

    $casts = $plugin->getCasts();

    expect($casts)->toHaveKey('enabled')
        ->and($casts['enabled'])->toBe('boolean');
});

test('casts meta_json to array', function () {
    $plugin = new Plugin();

    $casts = $plugin->getCasts();

    expect($casts)->toHaveKey('meta_json')
        ->and($casts['meta_json'])->toBe('array');
});

test('casts last_synced_at to immutable_datetime', function () {
    $plugin = new Plugin();

    $casts = $plugin->getCasts();

    expect($casts)->toHaveKey('last_synced_at')
        ->and($casts['last_synced_at'])->toBe('immutable_datetime');
});

test('has no guarded attributes', function () {
    $plugin = new Plugin();

    expect($plugin->getGuarded())->toBe([]);
});

