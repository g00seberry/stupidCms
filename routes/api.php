<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RefreshController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

/**
 * Public API routes.
 * 
 * Загружаются с middleware('api'), что обеспечивает:
 * - CSRF защиту для state-changing запросов (POST, PUT, PATCH, DELETE)
 *   исключая api.auth.login, api.auth.refresh, api.auth.logout (проверяется через routeIs)
 * - Throttle для защиты от злоупотреблений
 * - Правильную обработку JSON запросов
 * 
 * Безопасность:
 * - Rate limiting настроен для каждого endpoint отдельно
 * - Все публичные state-changing операции либо excluded из CSRF (auth endpoints),
 *   либо защищены JWT auth (админские endpoints в routes/api_admin.php)
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

    // Logout requires authentication - CSRF not needed with JWT guard
    Route::post('/auth/logout', [LogoutController::class, 'logout'])
        ->name('api.auth.logout')
        ->middleware(['jwt.auth', 'throttle:login', 'no-cache-auth']);

    Route::get('/search', [SearchController::class, 'index'])
        ->middleware('throttle:search-public')
        ->name('api.v1.search');

    // Public media access with signed URLs
    Route::get('/media/{id}', [PublicMediaController::class, 'show'])
        ->middleware('throttle:api')
        ->name('api.v1.media.show');
    
    // Public media variants (thumbnails, resized images)
    Route::get('/media/{id}/preview', [PublicMediaController::class, 'preview'])
        ->middleware('throttle:api')
        ->name('api.v1.media.preview');
});

