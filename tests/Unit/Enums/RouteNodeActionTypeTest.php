<?php

declare(strict_types=1);

use App\Enums\RouteNodeActionType;

/**
 * Unit-тесты для RouteNodeActionType enum.
 */
test('RouteNodeActionType::values() возвращает все значения', function () {
    $values = RouteNodeActionType::values();
    
    expect($values)->toBe(['controller', 'entry'])
        ->and(count($values))->toBe(2);
});

test('RouteNodeActionType::requiresEntry() возвращает true для ENTRY', function () {
    expect(RouteNodeActionType::ENTRY->requiresEntry())->toBeTrue()
        ->and(RouteNodeActionType::CONTROLLER->requiresEntry())->toBeFalse();
});

test('RouteNodeActionType можно использовать в type hints', function () {
    $fn = function (RouteNodeActionType $actionType): RouteNodeActionType {
        return $actionType;
    };
    
    expect($fn(RouteNodeActionType::CONTROLLER))->toBe(RouteNodeActionType::CONTROLLER)
        ->and($fn(RouteNodeActionType::ENTRY))->toBe(RouteNodeActionType::ENTRY);
});

test('RouteNodeActionType имеет правильные строковые значения', function () {
    expect(RouteNodeActionType::CONTROLLER->value)->toBe('controller')
        ->and(RouteNodeActionType::ENTRY->value)->toBe('entry');
});

