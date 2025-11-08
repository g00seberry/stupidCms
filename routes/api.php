<?php

use App\Http\Controllers\Auth\CsrfController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RefreshController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

/**
 * Public API routes.
 * 
 * Загружаются с middleware('api'), что обеспечивает:
 * - CSRF защиту для state-changing запросов (POST, PUT, PATCH, DELETE)
 *   исключая api.auth.login и api.auth.refresh (проверяется через routeIs)
 * - Throttle для защиты от злоупотреблений
 * - Правильную обработку JSON запросов
 * 
 * Безопасность:
 * - Rate limiting настроен для каждого endpoint отдельно
 * - CSRF токен требуется для всех state-changing операций (кроме login/refresh)
 * - Для кросс-сайтовых запросов (SPA на другом origin) требуется:
 *   - SameSite=None; Secure для cookies
 *   - CORS с credentials: true
 */
Route::prefix('v1')->group(function () {
    // Authentication endpoints
    // Cache-Control: no-store prevents caching of auth responses
    Route::post('/auth/login', [LoginController::class, 'login'])
        ->name('api.auth.login')
        ->middleware(['throttle:login', 'no-cache-auth']);
    
    Route::post('/auth/refresh', [RefreshController::class, 'refresh'])
        ->name('api.auth.refresh')
        ->middleware(['throttle:refresh', 'no-cache-auth']);

    Route::post('/auth/logout', [LogoutController::class, 'logout'])
        ->middleware(['throttle:login', 'no-cache-auth']); // 5/min is sufficient

    // CSRF token endpoint
    Route::get('/auth/csrf', [CsrfController::class, 'issue'])
        ->middleware('no-cache-auth');

    Route::get('/search', [SearchController::class, 'index'])
        ->middleware('throttle:search-public')
        ->name('api.v1.search');
});

