<?php

declare(strict_types=1);

use App\Enums\RouteNodeKind;

/**
 * Unit-тесты для RouteNodeKind enum.
 */
test('RouteNodeKind::values() возвращает все значения', function () {
    $values = RouteNodeKind::values();
    
    expect($values)->toBe(['group', 'route'])
        ->and(count($values))->toBe(2);
});

test('RouteNodeKind::isGroup() корректно определяет тип', function () {
    expect(RouteNodeKind::GROUP->isGroup())->toBeTrue()
        ->and(RouteNodeKind::ROUTE->isGroup())->toBeFalse();
});

test('RouteNodeKind::isRoute() корректно определяет тип', function () {
    expect(RouteNodeKind::ROUTE->isRoute())->toBeTrue()
        ->and(RouteNodeKind::GROUP->isRoute())->toBeFalse();
});

test('RouteNodeKind можно использовать в type hints', function () {
    $fn = function (RouteNodeKind $kind): RouteNodeKind {
        return $kind;
    };
    
    expect($fn(RouteNodeKind::GROUP))->toBe(RouteNodeKind::GROUP)
        ->and($fn(RouteNodeKind::ROUTE))->toBe(RouteNodeKind::ROUTE);
});

test('RouteNodeKind имеет правильные строковые значения', function () {
    expect(RouteNodeKind::GROUP->value)->toBe('group')
        ->and(RouteNodeKind::ROUTE->value)->toBe('route');
});

