<?php

declare(strict_types=1);

use App\Models\RouteNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Примечание: Полные тесты с JWT авторизацией будут добавлены после настройки тестовой авторизации
// Пока проверяем базовую функциональность контроллера

beforeEach(function () {
    // Создаём пользователя-администратора для обхода Policy проверок
    // (AuthServiceProvider::Gate::before() разрешает всё для is_admin=true)
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    
    $this->withoutMiddleware();
});

test('POST /api/v1/admin/routes создаёт узел с корректными данными → 201', function () {

    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'kind',
                'uri',
                'action_type',
                'action',
                'readonly',
                'created_at',
                'updated_at',
            ],
        ])
        ->assertJsonPath('data.readonly', false); // Маршруты из БД не readonly

    $this->assertDatabaseHas('route_nodes', [
        'kind' => 'route',
        'uri' => '/test',
        'action_type' => 'controller',
    ]);
});

test('GET /api/v1/admin/routes/{id} возвращает детали узла', function () {

    $node = RouteNode::factory()->route()->create([
        'uri' => '/test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $response = $this->getJson("/api/v1/admin/routes/{$node->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'kind',
                'uri',
                'action_type',
                'action',
                'readonly',
                'created_at',
                'updated_at',
            ],
        ])
        ->assertJson([
            'data' => [
                'id' => $node->id,
                'uri' => '/test',
                'readonly' => false, // Маршруты из БД не readonly
            ],
        ]);
});

test('PATCH /api/v1/admin/routes/{id} обновляет узел → 200', function () {

    $node = RouteNode::factory()->route()->create([
        'uri' => '/test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $response = $this->patchJson("/api/v1/admin/routes/{$node->id}", [
        'uri' => '/updated',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $node->id,
                'uri' => '/updated',
            ],
        ]);

    $this->assertDatabaseHas('route_nodes', [
        'id' => $node->id,
        'uri' => '/updated',
    ]);
});

test('DELETE /api/v1/admin/routes/{id} удаляет узел → 204', function () {

    $node = RouteNode::factory()->route()->create([
        'uri' => '/test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $response = $this->deleteJson("/api/v1/admin/routes/{$node->id}");

    $response->assertStatus(204);

    // Проверяем, что узел удалён (soft delete)
    $this->assertSoftDeleted('route_nodes', [
        'id' => $node->id,
    ]);
});

test('GET /api/v1/admin/routes возвращает список всех маршрутов (декларативные + из БД)', function () {

    RouteNode::factory()->count(3)->route()->create();

    $response = $this->getJson('/api/v1/admin/routes');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'uri',
                    'methods',
                    'name',
                    'source',
                    'readonly',
                ],
            ],
        ]);
    
    // Проверяем, что есть маршруты из БД
    $data = $response->json('data');
    $databaseRoutes = array_filter($data, fn($route) => $route['source'] === 'database');
    expect(count($databaseRoutes))->toBeGreaterThanOrEqual(3);
    
    // Проверяем, что есть декларативные маршруты
    $declarativeRoutes = array_filter($data, fn($route) => $route['source'] !== 'database');
    expect(count($declarativeRoutes))->toBeGreaterThan(0);
});

test('DELETE /api/v1/admin/routes/{id} каскадно удаляет дочерние узлы', function () {

    // Создаём дерево: parent -> child1, child2 -> grandchild
    $parent = RouteNode::factory()->group()->create();
    $child1 = RouteNode::factory()->route()->create(['parent_id' => $parent->id]);
    $child2 = RouteNode::factory()->group()->create(['parent_id' => $parent->id]);
    $grandchild = RouteNode::factory()->route()->create(['parent_id' => $child2->id]);

    $response = $this->deleteJson("/api/v1/admin/routes/{$parent->id}");

    $response->assertStatus(204);

    // Проверяем, что все узлы удалены
    expect(RouteNode::find($parent->id))->toBeNull()
        ->and(RouteNode::find($child1->id))->toBeNull()
        ->and(RouteNode::find($child2->id))->toBeNull()
        ->and(RouteNode::find($grandchild->id))->toBeNull();

    // Проверяем, что все узлы soft-deleted
    expect(RouteNode::withTrashed()->find($parent->id)->trashed())->toBeTrue()
        ->and(RouteNode::withTrashed()->find($child1->id)->trashed())->toBeTrue()
        ->and(RouteNode::withTrashed()->find($child2->id)->trashed())->toBeTrue()
        ->and(RouteNode::withTrashed()->find($grandchild->id)->trashed())->toBeTrue();
});

test('нельзя создать маршрут с readonly=true через API', function () {
    $response = $this->postJson('/api/v1/admin/routes', [
        'kind' => 'route',
        'action_type' => 'controller',
        'uri' => '/test',
        'methods' => ['GET'],
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'readonly' => true, // Попытка создать readonly маршрут
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['readonly']);
});

test('нельзя обновить readonly маршрут', function () {
    // Создаём readonly маршрут (симулируем декларативный)
    $node = RouteNode::factory()->route()->create([
        'uri' => '/test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'readonly' => true,
    ]);

    $response = $this->patchJson("/api/v1/admin/routes/{$node->id}", [
        'uri' => '/updated',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'code' => 'FORBIDDEN',
        ]);
});

test('нельзя удалить readonly маршрут', function () {
    // Создаём readonly маршрут (симулируем декларативный)
    $node = RouteNode::factory()->route()->create([
        'uri' => '/test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'readonly' => true,
    ]);

    $response = $this->deleteJson("/api/v1/admin/routes/{$node->id}");

    $response->assertStatus(403)
        ->assertJson([
            'code' => 'FORBIDDEN',
        ]);
});

test('декларативные маршруты имеют readonly=true', function () {
    $response = $this->getJson('/api/v1/admin/routes');

    $response->assertStatus(200);
    
    $data = $response->json('data');
    
    // Проверяем, что декларативные маршруты имеют readonly=true
    $declarativeRoutes = array_filter($data, fn($route) => $route['source'] !== 'database');
    
    foreach ($declarativeRoutes as $route) {
        expect($route['readonly'])->toBeTrue();
    }
    
    // Проверяем, что маршруты из БД имеют readonly=false
    $databaseRoutes = array_filter($data, fn($route) => $route['source'] === 'database');
    
    foreach ($databaseRoutes as $route) {
        expect($route['readonly'])->toBeFalse();
    }
});

