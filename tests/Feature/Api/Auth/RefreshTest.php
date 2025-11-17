<?php

declare(strict_types=1);

/**
 * Feature-тесты для RefreshController.
 * 
 * Тестирует POST /api/v1/auth/refresh
 * 
 * Примечание: Полное интеграционное тестирование refresh-механизма
 * с реальными JWT токенами выполняется в LoginTest. Здесь тестируются
 * только базовые сценарии и обработка ошибок без реальных JWT токенов,
 * так как JwtService является final классом и не может быть замокан.
 */

test('refresh without cookie returns 401', function () {
    $response = $this->postJson('/api/v1/auth/refresh');
    
    $response->assertUnauthorized();
});

test('refresh with invalid token returns 401', function () {
    $response = $this->withUnencryptedCookie(config('jwt.cookies.refresh'), 'invalid-token')
        ->postJson('/api/v1/auth/refresh');
    
    $response->assertUnauthorized();
});

test('refresh endpoint exists and requires authentication', function () {
    // Verify the endpoint exists and returns appropriate error without valid token
    $response = $this->postJson('/api/v1/auth/refresh');
    
    expect($response->status())->toBe(401);
});

test('refresh endpoint clears cookies on error', function () {
    $response = $this->withUnencryptedCookie(config('jwt.cookies.refresh'), 'bad-token')
        ->postJson('/api/v1/auth/refresh');
    
    $response->assertUnauthorized();
    
    // Should have set cookies to clear them
    $cookies = $response->headers->getCookies();
    expect($cookies)->not->toBeEmpty();
});
