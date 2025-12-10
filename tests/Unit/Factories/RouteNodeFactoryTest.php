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

test('RouteNodeFactory::new()->group()->create() создаёт узел с kind=GROUP', function () {
    $node = RouteNode::factory()->group()->create();

    expect($node->kind)->toBe(RouteNodeKind::GROUP)
        ->and($node->methods)->toBeNull()
        ->and($node->uri)->toBeNull()
        ->and($node->action)->toBeNull()
        ->and($node->prefix)->not->toBeNull();
});

test('RouteNodeFactory::new()->withParent($parent)->create() устанавливает parent_id', function () {
    $parent = RouteNode::factory()->group()->create();

    $child = RouteNode::factory()->withParent($parent)->create();

    expect($child->parent_id)->toBe($parent->id)
        ->and($child->parent)->not->toBeNull()
        ->and($child->parent->id)->toBe($parent->id);
});

test('RouteNodeFactory::new()->withEntry($entry)->create() связывает Entry', function () {
    $postType = PostType::factory()->create();
    $entry = Entry::factory()->create(['post_type_id' => $postType->id]);

    $node = RouteNode::factory()->withEntry($entry)->create();

    expect($node->entry_id)->toBe($entry->id)
        ->and($node->action_type)->toBe(RouteNodeActionType::ENTRY)
        ->and($node->action)->toBeNull()
        ->and($node->entry)->not->toBeNull()
        ->and($node->entry->id)->toBe($entry->id);
});

test('RouteNodeFactory::new()->enabled()->create() создаёт включённый узел', function () {
    $node = RouteNode::factory()->enabled()->create();

    expect($node->enabled)->toBeTrue();
});

test('RouteNodeFactory::new()->disabled()->create() создаёт выключенный узел', function () {
    $node = RouteNode::factory()->disabled()->create();

    expect($node->enabled)->toBeFalse();
});

test('RouteNodeFactory::new()->route()->create() создаёт узел с kind=ROUTE', function () {
    $node = RouteNode::factory()->route()->create();

    expect($node->kind)->toBe(RouteNodeKind::ROUTE)
        ->and($node->methods)->not->toBeNull()
        ->and($node->uri)->not->toBeNull()
        ->and($node->action)->not->toBeNull();
});

