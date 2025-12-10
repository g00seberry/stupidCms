<?php

declare(strict_types=1);

use App\Models\RouteNode;
use App\Repositories\RouteNodeRepository;
use App\Services\DynamicRoutes\DynamicRouteCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->cache = new DynamicRouteCache();
    $this->repository = new RouteNodeRepository($this->cache);
});

test('При RouteNode::create() кэш сбрасывается', function () {
    // Создаём первый узел и кэшируем дерево
    RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test1',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $tree1 = $this->repository->getTree();
    expect($tree1)->toHaveCount(1);

    // Проверяем, что кэш заполнен
    $prefix = config('dynamic-routes.cache_key_prefix', 'dynamic_routes');
    $key = "{$prefix}:tree:v1";
    expect(Cache::has($key))->toBeTrue();

    // Создаём второй узел (должен сбросить кэш через Observer)
    RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test2',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    // Кэш должен быть очищен Observer'ом
    expect(Cache::has($key))->toBeFalse();

    // При следующем запросе должны загрузиться оба узла
    $tree2 = $this->repository->getTree();
    expect($tree2)->toHaveCount(2);
});

test('При RouteNode::update() кэш сбрасывается', function () {
    // Создаём узел и кэшируем дерево
    $node = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $tree1 = $this->repository->getTree();
    expect($tree1)->toHaveCount(1);

    // Проверяем, что кэш заполнен
    $prefix = config('dynamic-routes.cache_key_prefix', 'dynamic_routes');
    $key = "{$prefix}:tree:v1";
    expect(Cache::has($key))->toBeTrue();

    // Обновляем узел (должен сбросить кэш через Observer)
    $node->update(['uri' => 'updated-test']);

    // Кэш должен быть очищен Observer'ом
    expect(Cache::has($key))->toBeFalse();

    // При следующем запросе должен загрузиться обновлённый узел
    $tree2 = $this->repository->getTree();
    expect($tree2)->toHaveCount(1)
        ->and($tree2->first()->uri)->toBe('updated-test');
});

test('При RouteNode::delete() кэш сбрасывается', function () {
    // Создаём узел и кэшируем дерево
    $node = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $tree1 = $this->repository->getTree();
    expect($tree1)->toHaveCount(1);

    // Проверяем, что кэш заполнен
    $prefix = config('dynamic-routes.cache_key_prefix', 'dynamic_routes');
    $key = "{$prefix}:tree:v1";
    expect(Cache::has($key))->toBeTrue();

    // Удаляем узел (soft delete, должен сбросить кэш через Observer)
    $node->delete();

    // Кэш должен быть очищен Observer'ом
    expect(Cache::has($key))->toBeFalse();

    // При следующем запросе узел не должен быть в дереве (enabled tree)
    $tree2 = $this->repository->getEnabledTree();
    expect($tree2)->toHaveCount(0);
});

test('При RouteNode::restore() кэш сбрасывается', function () {
    // Создаём узел, удаляем его и кэшируем пустое дерево
    $node = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $node->delete();

    $tree1 = $this->repository->getEnabledTree();
    expect($tree1)->toHaveCount(0);

    // Заполняем кэш пустым деревом
    $this->repository->getEnabledTree();

    // Проверяем, что кэш заполнен
    $prefix = config('dynamic-routes.cache_key_prefix', 'dynamic_routes');
    $key = "{$prefix}:tree:v1";
    expect(Cache::has($key))->toBeTrue();

    // Восстанавливаем узел (должен сбросить кэш через Observer)
    $node->restore();

    // Кэш должен быть очищен Observer'ом
    expect(Cache::has($key))->toBeFalse();

    // При следующем запросе узел должен быть в дереве
    $tree2 = $this->repository->getEnabledTree();
    expect($tree2)->toHaveCount(1)
        ->and($tree2->first()->id)->toBe($node->id);
});

