<?php

declare(strict_types=1);

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;
use App\Models\RouteNode;
use App\Repositories\RouteNodeRepository;
use App\Services\DynamicRoutes\DynamicRouteCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->cache = new DynamicRouteCache();
    $this->repository = new RouteNodeRepository($this->cache);
});

test('getTree() возвращает структуру корректной вложенности', function () {
    // Создаём дерево: root -> child1 -> grandchild1
    $root = RouteNode::factory()->group()->create(['prefix' => 'root']);
    $child1 = RouteNode::factory()->route()->withParent($root)->create(['uri' => 'child1']);
    $child2 = RouteNode::factory()->route()->withParent($root)->create(['uri' => 'child2']);
    $grandchild1 = RouteNode::factory()->route()->withParent($child1)->create(['uri' => 'grandchild1']);

    $tree = $this->repository->getTree();

    expect($tree)->toHaveCount(1)
        ->and($tree->first()->id)->toBe($root->id)
        ->and($tree->first()->children)->toHaveCount(2)
        ->and($tree->first()->children->first()->children)->toHaveCount(1);
});

test('Сортировка детей идёт по sort_order, затем по id', function () {
    $root = RouteNode::factory()->group()->create(['prefix' => 'root']);

    $child3 = RouteNode::factory()->route()->withParent($root)->create([
        'uri' => 'child3',
        'sort_order' => 3,
    ]);

    $child1 = RouteNode::factory()->route()->withParent($root)->create([
        'uri' => 'child1',
        'sort_order' => 1,
    ]);

    $child2 = RouteNode::factory()->route()->withParent($root)->create([
        'uri' => 'child2',
        'sort_order' => 2,
    ]);

    $tree = $this->repository->getTree();
    $children = $tree->first()->children;

    expect($children->first()->id)->toBe($child1->id)
        ->and($children->get(1)->id)->toBe($child2->id)
        ->and($children->last()->id)->toBe($child3->id);
});

test('getEnabledTree() исключает enabled=false узлы', function () {
    $root = RouteNode::factory()->group()->create(['prefix' => 'root', 'enabled' => true]);
    $enabledChild = RouteNode::factory()->route()->withParent($root)->create([
        'uri' => 'enabled',
        'enabled' => true,
    ]);
    $disabledChild = RouteNode::factory()->route()->withParent($root)->create([
        'uri' => 'disabled',
        'enabled' => false,
    ]);

    $tree = $this->repository->getEnabledTree();

    expect($tree)->toHaveCount(1)
        ->and($tree->first()->children)->toHaveCount(1)
        ->and($tree->first()->children->first()->id)->toBe($enabledChild->id);
});

test('При одинаковом sort_order порядок стабилен', function () {
    $root = RouteNode::factory()->group()->create(['prefix' => 'root']);

    // Создаём детей с одинаковым sort_order
    $child1 = RouteNode::factory()->route()->withParent($root)->create([
        'uri' => 'child1',
        'sort_order' => 0,
    ]);

    $child2 = RouteNode::factory()->route()->withParent($root)->create([
        'uri' => 'child2',
        'sort_order' => 0,
    ]);

    $tree = $this->repository->getTree();
    $children = $tree->first()->children;

    // Порядок должен быть стабильным (по id)
    expect($children->first()->id)->toBeLessThan($children->last()->id);
});

test('getNodeWithAncestors() возвращает узел с предками', function () {
    $root = RouteNode::factory()->group()->create(['prefix' => 'root']);
    $child = RouteNode::factory()->route()->withParent($root)->create(['uri' => 'child']);
    $grandchild = RouteNode::factory()->route()->withParent($child)->create(['uri' => 'grandchild']);

    $node = $this->repository->getNodeWithAncestors($grandchild->id);

    expect($node)->not->toBeNull()
        ->and($node->id)->toBe($grandchild->id)
        ->and($node->parent)->not->toBeNull()
        ->and($node->parent->id)->toBe($child->id)
        ->and($node->parent->parent)->not->toBeNull()
        ->and($node->parent->parent->id)->toBe($root->id);
});

test('В тесте с 200-500 узлами число запросов не растёт линейно с глубиной', function () {
    DB::enableQueryLog();

    // Создаём дерево с глубиной 3 и ~50 узлами на уровень = ~200 узлов
    $root = RouteNode::factory()->group()->create(['prefix' => 'root']);
    $currentLevel = [$root];

    for ($level = 0; $level < 3; $level++) {
        $nextLevel = [];
        foreach ($currentLevel as $parent) {
            for ($i = 0; $i < 10; $i++) {
                $nextLevel[] = RouteNode::factory()->group()->withParent($parent)->create([
                    'prefix' => "level{$level}_node{$i}",
                ]);
            }
        }
        $currentLevel = $nextLevel;
    }

    // Очищаем лог запросов перед загрузкой дерева
    DB::flushQueryLog();

    $tree = $this->repository->getTree();

    $queries = DB::getQueryLog();

    // Должен быть только один запрос для загрузки всех узлов
    expect(count($queries))->toBeLessThanOrEqual(2) // Может быть 1-2 запроса (основной + возможно count)
        ->and($tree->count())->toBeGreaterThan(0);
});

