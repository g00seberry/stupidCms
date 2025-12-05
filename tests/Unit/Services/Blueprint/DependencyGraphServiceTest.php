<?php

declare(strict_types=1);

use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Services\Blueprint\DependencyGraphService;

beforeEach(function () {
    $this->service = app(DependencyGraphService::class);
});

test('hasPathTo находит прямое ребро', function () {
    $a = Blueprint::factory()->create();
    $b = Blueprint::factory()->create();

    BlueprintEmbed::create(['blueprint_id' => $a->id, 'embedded_blueprint_id' => $b->id]);

    expect($this->service->hasPathTo($a->id, $b->id))->toBeTrue();
    expect($this->service->hasPathTo($b->id, $a->id))->toBeFalse();
});

test('hasPathTo находит транзитивный путь', function () {
    $a = Blueprint::factory()->create();
    $b = Blueprint::factory()->create();
    $c = Blueprint::factory()->create();

    BlueprintEmbed::create(['blueprint_id' => $a->id, 'embedded_blueprint_id' => $b->id]);
    BlueprintEmbed::create(['blueprint_id' => $b->id, 'embedded_blueprint_id' => $c->id]);

    expect($this->service->hasPathTo($a->id, $c->id))->toBeTrue();
});

test('hasPathTo возвращает true для одинаковых ID', function () {
    $a = Blueprint::factory()->create();

    expect($this->service->hasPathTo($a->id, $a->id))->toBeTrue();
});

test('getDirectDependencies возвращает прямые зависимости', function () {
    $a = Blueprint::factory()->create();
    $b = Blueprint::factory()->create();
    $c = Blueprint::factory()->create();

    BlueprintEmbed::create(['blueprint_id' => $a->id, 'embedded_blueprint_id' => $b->id]);
    BlueprintEmbed::create(['blueprint_id' => $a->id, 'embedded_blueprint_id' => $c->id]);

    $dependencies = $this->service->getDirectDependencies($a->id);

    expect($dependencies)->toHaveCount(2)
        ->and($dependencies)->toContain($b->id, $c->id);
});

test('getDirectDependencies возвращает уникальные значения', function () {
    $a = Blueprint::factory()->create();
    $b = Blueprint::factory()->create();

    // Создаём два embed'а с одинаковым embedded_blueprint_id
    BlueprintEmbed::create(['blueprint_id' => $a->id, 'embedded_blueprint_id' => $b->id]);
    BlueprintEmbed::create(['blueprint_id' => $a->id, 'embedded_blueprint_id' => $b->id]);

    $dependencies = $this->service->getDirectDependencies($a->id);

    expect($dependencies)->toHaveCount(1)
        ->and($dependencies)->toContain($b->id);
});

test('getDirectDependents возвращает всех, кто встраивает данный blueprint', function () {
    $a = Blueprint::factory()->create();
    $b = Blueprint::factory()->create();
    $c = Blueprint::factory()->create();

    BlueprintEmbed::create(['blueprint_id' => $b->id, 'embedded_blueprint_id' => $a->id]);
    BlueprintEmbed::create(['blueprint_id' => $c->id, 'embedded_blueprint_id' => $a->id]);

    $dependents = $this->service->getDirectDependents($a->id);

    expect($dependents)->toHaveCount(2)
        ->and($dependents)->toContain($b->id, $c->id);
});

test('getAllDependentBlueprintIds возвращает всех зависимых', function () {
    $root = Blueprint::factory()->create(['code' => 'root']);
    $child1 = Blueprint::factory()->create(['code' => 'child1']);
    $child2 = Blueprint::factory()->create(['code' => 'child2']);
    $grandchild = Blueprint::factory()->create(['code' => 'grandchild']);

    // root ← child1
    BlueprintEmbed::create(['blueprint_id' => $child1->id, 'embedded_blueprint_id' => $root->id]);
    // root ← child2
    BlueprintEmbed::create(['blueprint_id' => $child2->id, 'embedded_blueprint_id' => $root->id]);
    // child1 ← grandchild
    BlueprintEmbed::create(['blueprint_id' => $grandchild->id, 'embedded_blueprint_id' => $child1->id]);

    $dependents = $this->service->getAllDependentBlueprintIds($root->id);

    expect($dependents)->toHaveCount(3)
        ->and($dependents->all())->toContain($child1->id, $child2->id, $grandchild->id);
});

test('getAllTransitiveDependencies возвращает все транзитивные зависимости', function () {
    $a = Blueprint::factory()->create();
    $b = Blueprint::factory()->create();
    $c = Blueprint::factory()->create();

    // a → b → c
    BlueprintEmbed::create(['blueprint_id' => $a->id, 'embedded_blueprint_id' => $b->id]);
    BlueprintEmbed::create(['blueprint_id' => $b->id, 'embedded_blueprint_id' => $c->id]);

    $dependencies = $this->service->getAllTransitiveDependencies($a->id);

    expect($dependencies)->toHaveCount(2)
        ->and($dependencies->all())->toContain($b->id, $c->id);
});

