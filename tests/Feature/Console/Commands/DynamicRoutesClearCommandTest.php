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
    $this->cache = new DynamicRouteCache();
    $this->repository = new RouteNodeRepository($this->cache);
});

test('php artisan routes:dynamic-clear очищает кэш', function () {
    // Создаём узел маршрута
    RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    // Заполняем кэш
    $this->repository->getTree();

    // Проверяем, что кэш заполнен
    $prefix = config('dynamic-routes.cache_key_prefix', 'dynamic_routes');
    $key = "{$prefix}:tree:v1";
    expect(Cache::has($key))->toBeTrue();

    // Запускаем команду
    $exitCode = Artisan::call('routes:dynamic-clear');

    // Проверяем успешное выполнение
    expect($exitCode)->toBe(0);

    // Проверяем, что кэш очищен
    expect(Cache::has($key))->toBeFalse();

    // Проверяем вывод команды
    $output = Artisan::output();
    expect($output)->toContain('Clearing dynamic routes cache')
        ->toContain('Cache cleared successfully');
});

test('php artisan routes:dynamic-clear выводит информационное сообщение', function () {
    // Запускаем команду (даже если кэш пуст)
    Artisan::call('routes:dynamic-clear');

    // Проверяем вывод
    $output = Artisan::output();
    expect($output)->toContain('Clearing dynamic routes cache')
        ->toContain('Cache cleared successfully');
});

test('php artisan routes:dynamic-clear работает с пустым кэшем', function () {
    // Не заполняем кэш

    // Запускаем команду
    $exitCode = Artisan::call('routes:dynamic-clear');

    // Проверяем успешное выполнение (команда должна работать даже если кэш пуст)
    expect($exitCode)->toBe(0);

    // Проверяем вывод
    $output = Artisan::output();
    expect($output)->toContain('Cache cleared successfully');
});

