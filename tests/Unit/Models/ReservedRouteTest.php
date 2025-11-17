<?php

declare(strict_types=1);

use App\Models\ReservedRoute;

/**
 * Unit-тесты для модели ReservedRoute.
 */

test('has fillable attributes', function () {
    $route = new ReservedRoute();

    $fillable = $route->getFillable();

    expect($fillable)->toContain('path')
        ->and($fillable)->toContain('kind')
        ->and($fillable)->toContain('source');
});

test('supports path type', function () {
    $route = new ReservedRoute(['kind' => 'path']);

    expect($route->kind)->toBe('path');
});

test('supports prefix type', function () {
    $route = new ReservedRoute(['kind' => 'prefix']);

    expect($route->kind)->toBe('prefix');
});

