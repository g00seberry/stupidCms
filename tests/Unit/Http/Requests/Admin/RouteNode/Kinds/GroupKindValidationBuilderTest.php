<?php

declare(strict_types=1);

use App\Http\Requests\Admin\RouteNode\Kinds\GroupKindValidationBuilder;
use App\Enums\RouteNodeKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->builder = new GroupKindValidationBuilder();
});

test('getSupportedKind() возвращает group', function () {
    expect($this->builder->getSupportedKind())->toBe(RouteNodeKind::GROUP->value);
});

test('buildRulesForStore() возвращает правила для group', function () {
    $rules = $this->builder->buildRulesForStore();

    // Проверяем, что разрешены поля для group
    expect($rules)->toHaveKey('prefix')
        ->and($rules)->toHaveKey('domain')
        ->and($rules)->toHaveKey('namespace')
        ->and($rules)->toHaveKey('middleware')
        ->and($rules)->toHaveKey('where')
        ->and($rules)->toHaveKey('children');

    // Проверяем, что запрещены поля для route
    expect($rules['uri'])->toContain('prohibited')
        ->and($rules['methods'])->toContain('prohibited')
        ->and($rules['name'])->toContain('prohibited')
        ->and($rules['action'])->toContain('prohibited')
        ->and($rules['action_type'])->toContain('prohibited')
        ->and($rules['entry_id'])->toContain('prohibited')
        ->and($rules['defaults'])->toContain('prohibited');
});

test('buildRulesForUpdate() возвращает правила для group с sometimes', function () {
    $rules = $this->builder->buildRulesForUpdate(null);

    // Проверяем, что разрешены поля для group с sometimes
    expect($rules)->toHaveKey('prefix')
        ->and($rules)->toHaveKey('domain')
        ->and($rules)->toHaveKey('namespace')
        ->and($rules)->toHaveKey('middleware')
        ->and($rules)->toHaveKey('where')
        ->and($rules)->toHaveKey('children');

    // Проверяем, что запрещены поля для route
    expect($rules['uri'])->toContain('prohibited')
        ->and($rules['methods'])->toContain('prohibited')
        ->and($rules['name'])->toContain('prohibited')
        ->and($rules['action'])->toContain('prohibited')
        ->and($rules['action_type'])->toContain('prohibited')
        ->and($rules['entry_id'])->toContain('prohibited')
        ->and($rules['defaults'])->toContain('prohibited');
});

test('buildMessages() возвращает сообщения об ошибках', function () {
    $messages = $this->builder->buildMessages();

    expect($messages)->toBeArray()
        ->and($messages)->not->toBeEmpty()
        ->and($messages)->toHaveKey('uri.prohibited')
        ->and($messages)->toHaveKey('methods.prohibited')
        ->and($messages)->toHaveKey('prefix.string');
});

