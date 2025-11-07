<?php

use App\Http\Controllers\Admin\PathReservationController;
use App\Http\Controllers\Admin\UtilsController;
use App\Models\RouteReservation;
use Illuminate\Support\Facades\Route;

/**
 * Админский API роуты.
 * 
 * Загружаются с middleware('api'), что обеспечивает:
 * - Отсутствие CSRF проверки (stateless API)
 * - Throttle для защиты от злоупотреблений
 * - Правильную обработку JSON запросов
 * 
 * Безопасность:
 * - Использует guard 'admin' для явной идентификации администраторских запросов
 * - Throttle 'api' настроен в bootstrap/app.php (60 запросов в минуту)
 * - Для кросс-сайтовых запросов (SPA на другом origin) требуется:
 *   - SameSite=None; Secure для cookies
 *   - CORS с credentials: true
 *   - CSRF токены для state-changing операций (если используется cookie-based auth)
 */
Route::middleware(['auth:admin', 'throttle:api'])->group(function () {
    Route::get('/utils/slugify', [UtilsController::class, 'slugify']);
    
    // Path reservations
    Route::get('/reservations', [PathReservationController::class, 'index'])
        ->middleware('can:viewAny,' . RouteReservation::class);
    Route::post('/reservations', [PathReservationController::class, 'store'])
        ->middleware('can:create,' . RouteReservation::class);
    Route::delete('/reservations/{path}', [PathReservationController::class, 'destroy'])
        ->where('path', '.*')
        ->middleware('can:deleteAny,' . RouteReservation::class);
});

