<?php

declare(strict_types=1);

/**
 * Пример Unit-теста для проверки работоспособности системы тестирования.
 */

test('basic assertion example', function () {
    expect(true)->toBeTrue();
});

test('basic math works', function () {
    expect(2 + 2)->toBe(4);
});

