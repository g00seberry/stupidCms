<?php

declare(strict_types=1);

use App\Models\RouteNode;
use App\Repositories\RouteNodeRepository;
use App\Services\DynamicRoutes\DynamicRouteCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('php artisan routes:dynamic-cache заполняет кэш', function () {
    // Создаём узел маршрута
    RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    // Проверяем, что кэш пуст
    $prefix = config('dynamic-routes.cache_key_prefix', 'dynamic_routes');
    $key = "{$prefix}:tree:v1";
    expect(Cache::has($key))->toBeFalse();

    // Запускаем команду
    $exitCode = Artisan::call('routes:dynamic-cache');

    // Проверяем успешное выполнение
    expect($exitCode)->toBe(0);

    // Проверяем, что кэш заполнен
    expect(Cache::has($key))->toBeTrue();

    // Проверяем вывод команды
    $output = Artisan::output();
    expect($output)->toContain('Warming up dynamic routes cache')
        ->toContain('Cache warmed up successfully')
        ->toContain('route node');
});

test('php artisan routes:dynamic-cache выводит информационное сообщение', function () {
    // Создаём узел маршрута
    RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    // Запускаем команду
    Artisan::call('routes:dynamic-cache');

    // Проверяем вывод
    $output = Artisan::output();
    expect($output)->toContain('Warming up dynamic routes cache')
        ->toContain('Cache warmed up successfully');
});

test('php artisan routes:dynamic-cache работает с пустым деревом', function () {
    // Не создаём узлов

    // Запускаем команду
    $exitCode = Artisan::call('routes:dynamic-cache');

    // Проверяем успешное выполнение
    expect($exitCode)->toBe(0);

    // Проверяем вывод
    $output = Artisan::output();
    expect($output)->toContain('Cache warmed up successfully')
        ->toContain('0 route node');
});

