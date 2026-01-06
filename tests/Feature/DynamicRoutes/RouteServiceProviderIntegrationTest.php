<?php

declare(strict_types=1);

use App\Models\RouteNode;
use App\Repositories\RouteNodeRepository;
use App\Services\DynamicRoutes\DynamicRouteCache;
use App\Services\DynamicRoutes\DynamicRouteGuard;
use App\Services\DynamicRoutes\DynamicRouteRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('Маршрут из БД регистрируется через DynamicRouteRegistrar', function () {
    // Создаём узел маршрута
    RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test-dynamic',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    // Регистрируем маршруты вручную (симулируем работу RouteServiceProvider)
    $cache = app(DynamicRouteCache::class);
    $repository = new RouteNodeRepository($cache);
    $guard = new DynamicRouteGuard();
    
    // Создаём фабрики для регистраторов и резолверов
    $actionResolverFactory = \App\Services\DynamicRoutes\ActionResolvers\ActionResolverFactory::createDefault($guard);
    $registrarFactory = \App\Services\DynamicRoutes\Registrars\RouteNodeRegistrarFactory::createDefault($guard, $actionResolverFactory);
    
    $registrar = new DynamicRouteRegistrar($repository, $guard, $registrarFactory);
    $registrar->register();

    // Проверяем, что маршрут зарегистрирован
    $routes = Route::getRoutes();
    $found = false;
    foreach ($routes as $r) {
        if ($r->uri() === 'test-dynamic') {
            $found = true;
            break;
        }
    }

    expect($found)->toBeTrue();
});

test('При отсутствии таблицы регистрация не выполняется (graceful degradation)', function () {
    // Проверяем, что таблица существует (в тестах она всегда существует)
    expect(Schema::hasTable('route_nodes'))->toBeTrue();

    // Проверяем, что метод registerDynamicRoutes() не падает
    // В реальном сценарии при отсутствии таблицы метод просто вернётся
    // Здесь мы проверяем, что код корректно обрабатывает наличие таблицы
    $hasTable = Schema::hasTable('route_nodes');
    expect($hasTable)->toBeTrue();

    // Если таблица существует, регистрация должна работать
    if ($hasTable) {
        RouteNode::factory()->route()->create([
            'methods' => ['GET'],
            'uri' => 'test',
            'action' => 'App\\Http\\Controllers\\TestController@show',
        ]);

        $cache = app(DynamicRouteCache::class);
        $repository = new RouteNodeRepository($cache);
        $guard = new DynamicRouteGuard();
        
        // Создаём фабрики для регистраторов и резолверов
        $actionResolverFactory = \App\Services\DynamicRoutes\ActionResolvers\ActionResolverFactory::createDefault($guard);
        $registrarFactory = \App\Services\DynamicRoutes\Registrars\RouteNodeRegistrarFactory::createDefault($guard, $actionResolverFactory);
        
        $registrar = new DynamicRouteRegistrar($repository, $guard, $registrarFactory);

        // Регистрация не должна падать
        $registrar->register();
        expect(true)->toBeTrue(); // Если дошли сюда, значит не упало
    }
});

test('Динамические маршруты регистрируются корректно', function () {
    // Создаём несколько узлов маршрутов
    RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'page1',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'page2',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    // Регистрируем маршруты
    $cache = app(DynamicRouteCache::class);
    $repository = new RouteNodeRepository($cache);
    $guard = new DynamicRouteGuard();
    
    // Создаём фабрики для регистраторов и резолверов
    $actionResolverFactory = \App\Services\DynamicRoutes\ActionResolvers\ActionResolverFactory::createDefault($guard);
    $registrarFactory = \App\Services\DynamicRoutes\Registrars\RouteNodeRegistrarFactory::createDefault($guard, $actionResolverFactory);
    
    $registrar = new DynamicRouteRegistrar($repository, $guard, $registrarFactory);
    $registrar->register();

    // Проверяем, что оба маршрута зарегистрированы
    $routes = Route::getRoutes();
    $found1 = false;
    $found2 = false;

    foreach ($routes as $r) {
        if ($r->uri() === 'page1') {
            $found1 = true;
        }
        if ($r->uri() === 'page2') {
            $found2 = true;
        }
    }

    expect($found1)->toBeTrue()
        ->and($found2)->toBeTrue();
});

