<?php

declare(strict_types=1);

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;
use App\Models\Entry;
use App\Models\PostType;
use App\Models\RouteNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Создаём базовые данные для тестов
    $postType = PostType::factory()->create();
    $this->entry = Entry::factory()->create(['post_type_id' => $postType->id]);
});

test('RouteNode::create() сохраняет JSON-поля корректно', function () {
    $node = RouteNode::create([
        'kind' => RouteNodeKind::ROUTE,
        'action_type' => RouteNodeActionType::CONTROLLER,
        'enabled' => true,
        'methods' => ['GET', 'POST'],
        'uri' => 'test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
        'middleware' => ['web', 'auth'],
        'where' => ['id' => '[0-9]+'],
        'defaults' => ['key' => 'value'],
        'options' => ['require_published' => false],
    ]);

    expect($node->methods)->toBe(['GET', 'POST'])
        ->and($node->middleware)->toBe(['web', 'auth'])
        ->and($node->where)->toBe(['id' => '[0-9]+'])
        ->and($node->defaults)->toBe(['key' => 'value'])
        ->and($node->options)->toBe(['require_published' => false]);
});

test('$node->parent возвращает родителя или null', function () {
    $parent = RouteNode::create([
        'kind' => RouteNodeKind::GROUP,
        'action_type' => RouteNodeActionType::CONTROLLER,
        'enabled' => true,
        'prefix' => 'blog',
    ]);

    $child = RouteNode::create([
        'parent_id' => $parent->id,
        'kind' => RouteNodeKind::ROUTE,
        'action_type' => RouteNodeActionType::CONTROLLER,
        'enabled' => true,
        'methods' => ['GET'],
        'uri' => '{slug}',
        'action' => 'App\\Http\\Controllers\\BlogController@show',
    ]);

    expect($child->parent)->not->toBeNull()
        ->and($child->parent->id)->toBe($parent->id)
        ->and($parent->parent)->toBeNull();
});

test('$node->children возвращает отсортированных потомков', function () {
    $parent = RouteNode::create([
        'kind' => RouteNodeKind::GROUP,
        'action_type' => RouteNodeActionType::CONTROLLER,
        'enabled' => true,
        'prefix' => 'blog',
    ]);

    $child1 = RouteNode::create([
        'parent_id' => $parent->id,
        'sort_order' => 2,
        'kind' => RouteNodeKind::ROUTE,
        'action_type' => RouteNodeActionType::CONTROLLER,
        'enabled' => true,
        'methods' => ['GET'],
        'uri' => 'second',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $child2 = RouteNode::create([
        'parent_id' => $parent->id,
        'sort_order' => 1,
        'kind' => RouteNodeKind::ROUTE,
        'action_type' => RouteNodeActionType::CONTROLLER,
        'enabled' => true,
        'methods' => ['GET'],
        'uri' => 'first',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $children = $parent->children;

    expect($children)->toHaveCount(2)
        ->and($children->first()->id)->toBe($child2->id) // sort_order = 1
        ->and($children->last()->id)->toBe($child1->id); // sort_order = 2
});

test('$node->entry возвращает Entry или null', function () {
    $nodeWithEntry = RouteNode::create([
        'kind' => RouteNodeKind::ROUTE,
        'action_type' => RouteNodeActionType::ENTRY,
        'enabled' => true,
        'entry_id' => $this->entry->id,
        'methods' => ['GET'],
        'uri' => 'about',
    ]);

    $nodeWithoutEntry = RouteNode::create([
        'kind' => RouteNodeKind::ROUTE,
        'action_type' => RouteNodeActionType::CONTROLLER,
        'enabled' => true,
        'methods' => ['GET'],
        'uri' => 'test',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    expect($nodeWithEntry->entry)->not->toBeNull()
        ->and($nodeWithEntry->entry->id)->toBe($this->entry->id)
        ->and($nodeWithoutEntry->entry)->toBeNull();
});

test('RouteNode::enabled()->get() фильтрует только включённые', function () {
    RouteNode::create([
        'kind' => RouteNodeKind::ROUTE,
        'action_type' => RouteNodeActionType::CONTROLLER,
        'enabled' => true,
        'methods' => ['GET'],
        'uri' => 'enabled',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    RouteNode::create([
        'kind' => RouteNodeKind::ROUTE,
        'action_type' => RouteNodeActionType::CONTROLLER,
        'enabled' => false,
        'methods' => ['GET'],
        'uri' => 'disabled',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $enabled = RouteNode::enabled()->get();

    expect($enabled)->toHaveCount(1)
        ->and($enabled->first()->uri)->toBe('enabled');
});

test('RouteNode::roots()->get() возвращает только корневые узлы', function () {
    $root = RouteNode::create([
        'kind' => RouteNodeKind::GROUP,
        'action_type' => RouteNodeActionType::CONTROLLER,
        'enabled' => true,
        'prefix' => 'blog',
    ]);

    RouteNode::create([
        'parent_id' => $root->id,
        'kind' => RouteNodeKind::ROUTE,
        'action_type' => RouteNodeActionType::CONTROLLER,
        'enabled' => true,
        'methods' => ['GET'],
        'uri' => 'child',
        'action' => 'App\\Http\\Controllers\\TestController@show',
    ]);

    $roots = RouteNode::roots()->get();

    expect($roots)->toHaveCount(1)
        ->and($roots->first()->id)->toBe($root->id);
});

test('При удалении Entry, entry_id становится null (не каскадное удаление)', function () {
    $node = RouteNode::create([
        'kind' => RouteNodeKind::ROUTE,
        'action_type' => RouteNodeActionType::ENTRY,
        'enabled' => true,
        'entry_id' => $this->entry->id,
        'methods' => ['GET'],
        'uri' => 'about',
    ]);

    expect($node->entry_id)->toBe($this->entry->id);

    // При forceDelete() foreign key constraint срабатывает и устанавливает entry_id в null
    $this->entry->forceDelete();

    $node->refresh();

    expect($node->entry_id)->toBeNull()
        ->and($node->entry)->toBeNull();
});

