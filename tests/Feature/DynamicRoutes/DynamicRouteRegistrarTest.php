<?php

declare(strict_types=1);

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;
use App\Models\RouteNode;
use App\Repositories\RouteNodeRepository;
use App\Services\DynamicRoutes\DynamicRouteCache;
use App\Services\DynamicRoutes\DynamicRouteGuard;
use App\Services\DynamicRoutes\DynamicRouteRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->cache = new DynamicRouteCache();
    $this->repository = new RouteNodeRepository($this->cache);
    $this->guard = new DynamicRouteGuard();
    $this->registrar = new DynamicRouteRegistrar($this->repository, $this->guard);
    
    // Очищаем роуты перед каждым тестом
    Route::getRoutes()->refreshNameLookups();
    Route::getRoutes()->refreshActionLookups();
});

test('Локальная регистрация тестового дерева: группа с prefix=blog + дочерний GET /blog/{slug} → корректный ответ 200', function () {
    $group = RouteNode::factory()->group()->create([
        'prefix' => 'blog',
        'middleware' => ['web'],
    ]);

    RouteNode::factory()->route()->withParent($group)->create([
        'methods' => ['GET'],
        'uri' => '{slug}',
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'name' => 'blog.show',
    ]);

    $this->registrar->register();

    $response = $this->get('/blog/test-slug');

    $response->assertOk()
        ->assertJson(['message' => 'Test controller works']);
});

test('Проверка where: валидный параметр проходит', function () {
    $node = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test/{id}',
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'where' => ['id' => '[0-9]+'],
    ]);

    $this->registrar->register();

    $response = $this->get('/test/123');

    $response->assertOk();
});

test('Проверка where: невалидный параметр даёт 404', function () {
    $node = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test/{id}',
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'where' => ['id' => '[0-9]+'],
    ]);

    $this->registrar->register();

    $response = $this->get('/test/abc');

    $response->assertNotFound();
});

test('Узел enabled=false не создаёт маршрут', function () {
    $node = RouteNode::factory()->route()->disabled()->create([
        'methods' => ['GET'],
        'uri' => 'disabled-route',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $this->registrar->register();

    $response = $this->get('/disabled-route');

    $response->assertNotFound();
});

test('action_type=CONTROLLER с action=Controller@method регистрирует маршрут', function () {
    $node = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test-controller',
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $this->registrar->register();

    $response = $this->get('/test-controller');

    $response->assertOk()
        ->assertJson(['message' => 'Test controller works']);
});

test('action_type=CONTROLLER с action=Controller (invokable) регистрирует маршрут', function () {
    $node = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'test-invokable',
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action' => 'App\\Http\\Controllers\\TestController',
    ]);

    $this->registrar->register();

    $response = $this->get('/test-invokable');

    $response->assertOk()
        ->assertJson(['message' => 'Invokable controller works']);
});

test('action_type=CONTROLLER с action=view:pages.about возвращает Blade-шаблон', function () {
    // Создаём простой тестовый шаблон
    if (!file_exists(resource_path('views/pages'))) {
        mkdir(resource_path('views/pages'), 0755, true);
    }
    file_put_contents(resource_path('views/pages/about.blade.php'), '<h1>About Page</h1>');

    $node = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'about',
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action' => 'view:pages.about',
    ]);

    $this->registrar->register();

    $response = $this->get('/about');

    $response->assertOk()
        ->assertSee('<h1>About Page</h1>', false);

    // Очистка
    $viewPath = resource_path('views/pages/about.blade.php');
    if (file_exists($viewPath)) {
        unlink($viewPath);
    }
    $pagesDir = resource_path('views/pages');
    if (is_dir($pagesDir) && count(scandir($pagesDir)) === 2) { // Только . и ..
        rmdir($pagesDir);
    }
});

test('action_type=CONTROLLER с action=redirect:/old:301 делает редирект', function () {
    $node = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'old-page',
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action' => 'redirect:/new-page:301',
    ]);

    $this->registrar->register();

    $response = $this->get('/old-page');

    $response->assertRedirect('/new-page')
        ->assertStatus(301);
});

test('action_type=CONTROLLER с action=redirect:/old (без статуса) делает редирект 302', function () {
    $node = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'old-page-302',
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action' => 'redirect:/new-page',
    ]);

    $this->registrar->register();

    $response = $this->get('/old-page-302');

    $response->assertRedirect('/new-page')
        ->assertStatus(302);
});

test('Неразрешённый контроллер заменяется на safe action', function () {
    $node = RouteNode::factory()->route()->create([
        'methods' => ['GET'],
        'uri' => 'forbidden-route',
        'action_type' => RouteNodeActionType::CONTROLLER,
        'action' => 'App\\Forbidden\\Controller@show',
    ]);

    $this->registrar->register();

    $response = $this->get('/forbidden-route');

    $response->assertNotFound();
});

