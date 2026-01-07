<?php

declare(strict_types=1);

use App\Http\Requests\Admin\RouteNode\Kinds\RouteKindValidationBuilder;
use App\Enums\RouteNodeKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->builder = new RouteKindValidationBuilder();
});

test('getSupportedKind() возвращает route', function () {
    expect($this->builder->getSupportedKind())->toBe(RouteNodeKind::ROUTE->value);
});

test('buildRulesForStore() возвращает правила для route', function () {
    $rules = $this->builder->buildRulesForStore();

    // Проверяем, что обязательны uri, methods, action_type
    expect($rules['uri'])->toContain('required')
        ->and($rules['methods'])->toContain('required')
        ->and($rules['action_type'])->toContain('required');

    // Проверяем, что разрешены поля для route
    expect($rules)->toHaveKey('uri')
        ->and($rules)->toHaveKey('methods')
        ->and($rules)->toHaveKey('name')
        ->and($rules)->toHaveKey('domain')
        ->and($rules)->toHaveKey('middleware')
        ->and($rules)->toHaveKey('where')
        ->and($rules)->toHaveKey('defaults')
        ->and($rules)->toHaveKey('action')
        ->and($rules)->toHaveKey('action_type')
        ->and($rules)->toHaveKey('entry_id');

    // Проверяем, что запрещены поля для group
    expect($rules['prefix'])->toContain('prohibited')
        ->and($rules['namespace'])->toContain('prohibited')
        ->and($rules['children'])->toContain('prohibited');
});

test('buildRulesForUpdate() возвращает правила для route с sometimes', function () {
    $rules = $this->builder->buildRulesForUpdate(null);

    // Проверяем, что разрешены поля для route с sometimes
    expect($rules)->toHaveKey('uri')
        ->and($rules)->toHaveKey('methods')
        ->and($rules)->toHaveKey('name')
        ->and($rules)->toHaveKey('domain')
        ->and($rules)->toHaveKey('middleware')
        ->and($rules)->toHaveKey('where')
        ->and($rules)->toHaveKey('defaults')
        ->and($rules)->toHaveKey('action')
        ->and($rules)->toHaveKey('action_type')
        ->and($rules)->toHaveKey('entry_id');

    // Проверяем, что запрещены поля для group
    expect($rules['prefix'])->toContain('prohibited')
        ->and($rules['namespace'])->toContain('prohibited')
        ->and($rules['children'])->toContain('prohibited');
});

test('buildMessages() возвращает сообщения об ошибках', function () {
    $messages = $this->builder->buildMessages();

    expect($messages)->toBeArray()
        ->and($messages)->not->toBeEmpty()
        ->and($messages)->toHaveKey('uri.required')
        ->and($messages)->toHaveKey('methods.required')
        ->and($messages)->toHaveKey('prefix.prohibited')
        ->and($messages)->toHaveKey('namespace.prohibited');
});

