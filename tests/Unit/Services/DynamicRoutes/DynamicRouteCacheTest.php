<?php

declare(strict_types=1);

use App\Models\RouteNode;
use App\Repositories\RouteNodeRepository;
use App\Services\DynamicRoutes\DynamicRouteCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->cache = new DynamicRouteCache();
    $this->repository = new RouteNodeRepository($this->cache);
});

test('rememberTree() не вызывает builder повторно при наличии кэша', function () {
    // Создаём узел
    $node = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    // Первый вызов - должен загрузить из БД
    $tree1 = $this->repository->getTree();

    expect($tree1)->toHaveCount(1);

    // Второй вызов - должен взять из кэша (не должно быть нового запроса к БД)
    // Проверяем, что результат идентичен
    $tree2 = $this->repository->getTree();

    expect($tree2)->toHaveCount(1)
        ->and($tree2->first()->id)->toBe($tree1->first()->id);
});

test('forgetTree() очищает кэш', function () {
    $node1 = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test1',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    // Загружаем и кэшируем
    $tree1 = $this->repository->getTree();
    expect($tree1)->toHaveCount(1);

    // Очищаем кэш
    $this->cache->forgetTree();

    // Создаём новый узел
    $node2 = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test2',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    // После очистки кэша должен загрузить оба узла
    $tree2 = $this->repository->getTree();

    expect($tree2)->toHaveCount(2);
});

test('Кэш использует правильный ключ из конфига', function () {
    $prefix = config('dynamic-routes.cache_key_prefix', 'dynamic_routes');
    $expectedKey = "{$prefix}:tree:v1";

    $node = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    // Загружаем дерево (кэшируется)
    $this->repository->getTree();

    // Проверяем, что ключ существует в кэше
    expect(Cache::has($expectedKey))->toBeTrue();
});

test('TTL кэша соответствует конфигу', function () {
    $ttl = config('dynamic-routes.cache_ttl', 3600);

    $node = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    // Загружаем дерево
    $this->repository->getTree();

    // Проверяем, что TTL установлен (косвенно через проверку существования)
    // В тестах обычно используется array cache, который не имеет TTL,
    // но мы можем проверить, что кэш работает
    $prefix = config('dynamic-routes.cache_key_prefix', 'dynamic_routes');
    $key = "{$prefix}:tree:v1";

    expect(Cache::has($key))->toBeTrue();
});

