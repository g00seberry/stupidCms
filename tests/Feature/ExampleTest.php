<?php

declare(strict_types=1);

/**
 * Пример Feature-теста для проверки работоспособности HTTP тестирования.
 */

test('application returns successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

