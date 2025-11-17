<?php

declare(strict_types=1);

use App\Models\ReservedRoute;

/**
 * Feature-тесты для модели ReservedRoute.
 */

test('reserved route can be created', function () {
    $route = ReservedRoute::create([
        'path' => '/admin',
        'kind' => 'prefix',
        'source' => 'system',
    ]);

    expect($route)->toBeInstanceOf(ReservedRoute::class)
        ->and($route->exists)->toBeTrue();

    $this->assertDatabaseHas('reserved_routes', [
        'id' => $route->id,
        'path' => '/admin',
        'kind' => 'prefix',
    ]);
});

test('path type matches exact path', function () {
    $route = ReservedRoute::create([
        'path' => '/api',
        'kind' => 'path',
        'source' => 'system',
    ]);

    expect($route->kind)->toBe('path')
        ->and($route->path)->toBe('/api');
});

test('prefix type matches path prefix', function () {
    $route = ReservedRoute::create([
        'path' => '/admin',
        'kind' => 'prefix',
        'source' => 'system',
    ]);

    expect($route->kind)->toBe('prefix')
        ->and($route->path)->toBe('/admin');
});

