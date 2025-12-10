<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Unit-тесты для конфигурации dynamic-routes.
 */
test('config(\'dynamic-routes.allowed_middleware\') возвращает массив', function () {
    $middleware = config('dynamic-routes.allowed_middleware');

    expect($middleware)->toBeArray()
        ->and($middleware)->toContain('web')
        ->and($middleware)->toContain('can:*')
        ->and($middleware)->toContain('throttle:*');
});

test('config(\'dynamic-routes.reserved_prefixes\') содержит системные префиксы', function () {
    $prefixes = config('dynamic-routes.reserved_prefixes');

    expect($prefixes)->toBeArray()
        ->and($prefixes)->toContain('api')
        ->and($prefixes)->toContain('admin')
        ->and($prefixes)->toContain('sanctum');
});

test('Конфиг корректно подхватывается из файла', function () {
    expect(config('dynamic-routes.allowed_controllers'))->toBeArray()
        ->and(config('dynamic-routes.cache_ttl'))->toBeInt()
        ->and(config('dynamic-routes.cache_key_prefix'))->toBeString()
        ->and(config('dynamic-routes.cache_ttl'))->toBeGreaterThan(0);
});

