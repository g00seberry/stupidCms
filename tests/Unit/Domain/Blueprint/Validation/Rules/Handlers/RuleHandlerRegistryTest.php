<?php

declare(strict_types=1);

use App\Domain\Blueprint\Validation\Rules\Handlers\DistinctRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\RequiredRuleHandler;
use App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerRegistry;

/**
 * Unit-тесты для RuleHandlerRegistry.
 */

beforeEach(function () {
    $this->registry = new RuleHandlerRegistry();
});

test('register registers handler for rule type', function () {
    $handler = new RequiredRuleHandler();
    $this->registry->register('required', $handler);

    expect($this->registry->hasHandler('required'))->toBeTrue();
    expect($this->registry->getHandler('required'))->toBe($handler);
});

test('getHandler returns registered handler', function () {
    $handler = new RequiredRuleHandler();
    $this->registry->register('required', $handler);

    $retrievedHandler = $this->registry->getHandler('required');

    expect($retrievedHandler)->toBe($handler);
});

test('getHandler returns null for unregistered type', function () {
    $handler = $this->registry->getHandler('unknown_type');

    expect($handler)->toBeNull();
});

test('hasHandler returns true for registered type', function () {
    $handler = new RequiredRuleHandler();
    $this->registry->register('required', $handler);

    expect($this->registry->hasHandler('required'))->toBeTrue();
});

test('hasHandler returns false for unregistered type', function () {
    expect($this->registry->hasHandler('unknown_type'))->toBeFalse();
});

test('getRegisteredTypes returns list of all registered types', function () {
    $this->registry->register('required', new RequiredRuleHandler());
    $this->registry->register('distinct', new DistinctRuleHandler());

    $types = $this->registry->getRegisteredTypes();

    expect($types)->toBeArray();
    expect($types)->toHaveCount(2);
    expect($types)->toContain('required');
    expect($types)->toContain('distinct');
});

test('getRegisteredTypes returns empty array for empty registry', function () {
    $types = $this->registry->getRegisteredTypes();

    expect($types)->toBeArray();
    expect($types)->toBeEmpty();
});

test('overwrites handler for same rule type', function () {
    $handler1 = new RequiredRuleHandler();
    $handler2 = new RequiredRuleHandler();

    $this->registry->register('required', $handler1);
    $this->registry->register('required', $handler2);

    expect($this->registry->getHandler('required'))->toBe($handler2);
    expect($this->registry->getHandler('required'))->not->toBe($handler1);
});

test('register handles multiple different rule types', function () {
    $requiredHandler = new RequiredRuleHandler();
    $distinctHandler = new DistinctRuleHandler();

    $this->registry->register('required', $requiredHandler);
    $this->registry->register('distinct', $distinctHandler);

    expect($this->registry->getHandler('required'))->toBe($requiredHandler);
    expect($this->registry->getHandler('distinct'))->toBe($distinctHandler);
    expect($this->registry->hasHandler('required'))->toBeTrue();
    expect($this->registry->hasHandler('distinct'))->toBeTrue();
});


