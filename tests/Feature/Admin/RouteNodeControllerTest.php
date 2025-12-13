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
                'created_at',
                'updated_at',
            ],
        ]);

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
                'created_at',
                'updated_at',
            ],
        ])
        ->assertJson([
            'data' => [
                'id' => $node->id,
                'uri' => '/test',
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
                'declarative' => [
                    '*' => [
                        'id',
                        'uri',
                        'methods',
                        'name',
                        'source',
                    ],
                ],
                'database' => [
                    '*' => [
                        'id',
                        'uri',
                        'methods',
                        'name',
                        'source',
                    ],
                ],
            ],
        ]);
    
    // Проверяем, что есть декларативные маршруты
    $response->assertJsonPath('data.declarative', fn ($value) => is_array($value));
    
    // Проверяем, что есть маршруты из БД
    $response->assertJsonPath('data.database', fn ($value) => is_array($value) && count($value) === 3);
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

