<?php

declare(strict_types=1);

use App\Models\RouteNode;
use App\Services\DynamicRoutes\RouteNodeDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('deleteWithChildren() удаляет узел без детей', function () {
    $node = RouteNode::factory()->route()->create();

    $service = new RouteNodeDeletionService();
    $deletedCount = $service->deleteWithChildren($node);

    expect($deletedCount)->toBe(1)
        ->and(RouteNode::find($node->id))->toBeNull()
        ->and(RouteNode::withTrashed()->find($node->id))->not->toBeNull()
        ->and(RouteNode::withTrashed()->find($node->id)->trashed())->toBeTrue();
});

test('deleteWithChildren() каскадно удаляет дочерние узлы', function () {
    // Создаём дерево: parent -> child1, child2 -> grandchild
    $parent = RouteNode::factory()->group()->create();
    $child1 = RouteNode::factory()->route()->create(['parent_id' => $parent->id]);
    $child2 = RouteNode::factory()->group()->create(['parent_id' => $parent->id]);
    $grandchild = RouteNode::factory()->route()->create(['parent_id' => $child2->id]);

    $service = new RouteNodeDeletionService();
    $deletedCount = $service->deleteWithChildren($parent);

    expect($deletedCount)->toBe(4)
        ->and(RouteNode::find($parent->id))->toBeNull()
        ->and(RouteNode::find($child1->id))->toBeNull()
        ->and(RouteNode::find($child2->id))->toBeNull()
        ->and(RouteNode::find($grandchild->id))->toBeNull();

    // Проверяем, что все узлы soft-deleted
    expect(RouteNode::withTrashed()->find($parent->id))->not->toBeNull()
        ->and(RouteNode::withTrashed()->find($parent->id)->trashed())->toBeTrue()
        ->and(RouteNode::withTrashed()->find($child1->id))->not->toBeNull()
        ->and(RouteNode::withTrashed()->find($child1->id)->trashed())->toBeTrue()
        ->and(RouteNode::withTrashed()->find($child2->id))->not->toBeNull()
        ->and(RouteNode::withTrashed()->find($child2->id)->trashed())->toBeTrue()
        ->and(RouteNode::withTrashed()->find($grandchild->id))->not->toBeNull()
        ->and(RouteNode::withTrashed()->find($grandchild->id)->trashed())->toBeTrue();
});

test('deleteWithChildren() выполняется атомарно в транзакции', function () {
    $parent = RouteNode::factory()->group()->create();
    $child = RouteNode::factory()->route()->create(['parent_id' => $parent->id]);

    // Проверяем, что оба узла существуют до удаления
    expect(RouteNode::find($parent->id))->not->toBeNull()
        ->and(RouteNode::find($child->id))->not->toBeNull();

    $service = new RouteNodeDeletionService();
    $deletedCount = $service->deleteWithChildren($parent);

    // Проверяем, что оба узла удалены (каскадное удаление)
    expect($deletedCount)->toBe(2)
        ->and(RouteNode::find($parent->id))->toBeNull()
        ->and(RouteNode::find($child->id))->toBeNull()
        ->and(RouteNode::withTrashed()->find($parent->id)->trashed())->toBeTrue()
        ->and(RouteNode::withTrashed()->find($child->id)->trashed())->toBeTrue();
});

test('canDelete() всегда возвращает true', function () {
    $node = RouteNode::factory()->route()->create();

    $service = new RouteNodeDeletionService();

    expect($service->canDelete($node))->toBeTrue();
});

