<?php

declare(strict_types=1);

use App\Http\Requests\Admin\RouteNode\Kinds\GroupKindValidationBuilder;
use App\Http\Requests\Admin\RouteNode\Kinds\RouteKindValidationBuilder;
use App\Http\Requests\Admin\RouteNode\Kinds\RouteNodeKindValidationBuilderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->registry = new RouteNodeKindValidationBuilderRegistry();
});

test('register() регистрирует билдер', function () {
    $builder = new GroupKindValidationBuilder();
    $this->registry->register('group', $builder);

    expect($this->registry->hasBuilder('group'))->toBeTrue()
        ->and($this->registry->getBuilder('group'))->toBe($builder);
});

test('getBuilder() возвращает зарегистрированный билдер', function () {
    $groupBuilder = new GroupKindValidationBuilder();
    $routeBuilder = new RouteKindValidationBuilder();

    $this->registry->register('group', $groupBuilder);
    $this->registry->register('route', $routeBuilder);

    expect($this->registry->getBuilder('group'))->toBe($groupBuilder)
        ->and($this->registry->getBuilder('route'))->toBe($routeBuilder);
});

test('getBuilder() возвращает null для незарегистрированного kind', function () {
    expect($this->registry->getBuilder('unknown'))->toBeNull();
});

test('hasBuilder() проверяет наличие билдера', function () {
    $builder = new GroupKindValidationBuilder();
    
    expect($this->registry->hasBuilder('group'))->toBeFalse();

    $this->registry->register('group', $builder);

    expect($this->registry->hasBuilder('group'))->toBeTrue();
});

test('getSupportedKinds() возвращает список зарегистрированных kind', function () {
    $this->registry->register('group', new GroupKindValidationBuilder());
    $this->registry->register('route', new RouteKindValidationBuilder());

    $kinds = $this->registry->getSupportedKinds();

    expect($kinds)->toBeArray()
        ->and($kinds)->toContain('group')
        ->and($kinds)->toContain('route')
        ->and($kinds)->toHaveCount(2);
});

test('getAllBuilders() возвращает все зарегистрированные билдеры', function () {
    $groupBuilder = new GroupKindValidationBuilder();
    $routeBuilder = new RouteKindValidationBuilder();

    $this->registry->register('group', $groupBuilder);
    $this->registry->register('route', $routeBuilder);

    $builders = $this->registry->getAllBuilders();

    expect($builders)->toBeArray()
        ->and($builders)->toHaveKey('group')
        ->and($builders)->toHaveKey('route')
        ->and($builders['group'])->toBe($groupBuilder)
        ->and($builders['route'])->toBe($routeBuilder);
});

test('register() перезаписывает существующий билдер', function () {
    $builder1 = new GroupKindValidationBuilder();
    $builder2 = new GroupKindValidationBuilder();

    $this->registry->register('group', $builder1);
    expect($this->registry->getBuilder('group'))->toBe($builder1);

    $this->registry->register('group', $builder2);
    expect($this->registry->getBuilder('group'))->toBe($builder2);
});

