<?php

declare(strict_types=1);

use App\Models\RouteNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $this->withoutMiddleware();
});

test('POST /api/v1/admin/routes/reorder меняет parent_id и sort_order атомарно', function () {
    // Создаём несколько узлов
    $node1 = RouteNode::factory()->route()->create([
        'parent_id' => null,
        'sort_order' => 0,
    ]);

    $node2 = RouteNode::factory()->route()->create([
        'parent_id' => null,
        'sort_order' => 1,
    ]);

    $node3 = RouteNode::factory()->group()->create([
        'parent_id' => null,
        'sort_order' => 2,
    ]);

    // Переупорядочиваем: node3 становится родителем для node1 и node2
    $response = $this->postJson('/api/v1/admin/routes/reorder', [
        'nodes' => [
            ['id' => $node1->id, 'parent_id' => $node3->id, 'sort_order' => 0],
            ['id' => $node2->id, 'parent_id' => $node3->id, 'sort_order' => 1],
            ['id' => $node3->id, 'parent_id' => null, 'sort_order' => 0],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'updated' => 3,
            ],
        ]);

    // Проверяем, что изменения применены
    $node1->refresh();
    $node2->refresh();
    $node3->refresh();

    expect($node1->parent_id)->toBe($node3->id)
        ->and($node1->sort_order)->toBe(0)
        ->and($node2->parent_id)->toBe($node3->id)
        ->and($node2->sort_order)->toBe(1)
        ->and($node3->parent_id)->toBeNull()
        ->and($node3->sort_order)->toBe(0);
});

test('POST /api/v1/admin/routes/reorder с невалидными id → 422', function () {
    $response = $this->postJson('/api/v1/admin/routes/reorder', [
        'nodes' => [
            ['id' => 99999, 'parent_id' => null, 'sort_order' => 0],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['nodes.0.id']);
});

test('POST /api/v1/admin/routes/reorder с невалидным parent_id → 422', function () {
    $node = RouteNode::factory()->route()->create();

    $response = $this->postJson('/api/v1/admin/routes/reorder', [
        'nodes' => [
            ['id' => $node->id, 'parent_id' => 99999, 'sort_order' => 0],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['nodes.0.parent_id']);
});

test('POST /api/v1/admin/routes/reorder с отрицательным sort_order → 422', function () {
    $node = RouteNode::factory()->route()->create();

    $response = $this->postJson('/api/v1/admin/routes/reorder', [
        'nodes' => [
            ['id' => $node->id, 'parent_id' => null, 'sort_order' => -1],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['nodes.0.sort_order']);
});

test('POST /api/v1/admin/routes/reorder с пустым массивом → 422', function () {
    $response = $this->postJson('/api/v1/admin/routes/reorder', [
        'nodes' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['nodes']);
});

test('После reorder дерево остаётся консистентным', function () {
    // Создаём дерево: group -> route1, route2
    $group = RouteNode::factory()->group()->create([
        'parent_id' => null,
        'sort_order' => 0,
    ]);

    $route1 = RouteNode::factory()->route()->create([
        'parent_id' => $group->id,
        'sort_order' => 0,
    ]);

    $route2 = RouteNode::factory()->route()->create([
        'parent_id' => $group->id,
        'sort_order' => 1,
    ]);

    // Меняем порядок: route2 становится первым
    $response = $this->postJson('/api/v1/admin/routes/reorder', [
        'nodes' => [
            ['id' => $route1->id, 'parent_id' => $group->id, 'sort_order' => 1],
            ['id' => $route2->id, 'parent_id' => $group->id, 'sort_order' => 0],
        ],
    ]);

    $response->assertStatus(200);

    // Проверяем консистентность
    $route1->refresh();
    $route2->refresh();

    expect($route1->sort_order)->toBe(1)
        ->and($route2->sort_order)->toBe(0)
        ->and($route1->parent_id)->toBe($group->id)
        ->and($route2->parent_id)->toBe($group->id);
});

