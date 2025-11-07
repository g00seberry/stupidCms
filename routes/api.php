<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/**
 * Public API routes.
 * 
 * Загружаются с middleware('api'), что обеспечивает:
 * - Отсутствие CSRF проверки (stateless API)
 * - Throttle для защиты от злоупотреблений
 * - Правильную обработку JSON запросов
 * 
 * Безопасность:
 * - Rate limiting настроен для каждого endpoint отдельно
 * - Для кросс-сайтовых запросов (SPA на другом origin) требуется:
 *   - SameSite=None; Secure для cookies
 *   - CORS с credentials: true
 */
Route::prefix('v1')->group(function () {
    // Authentication endpoints
    Route::post('/auth/login', [LoginController::class, 'login'])
        ->middleware(['throttle:login']);
});

