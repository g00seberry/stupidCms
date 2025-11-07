<?php

use App\Http\Controllers\Admin\UtilsController;
use App\Models\Entry;
use Illuminate\Support\Facades\Route;

Route::get('/', \App\Http\Controllers\HomeController::class);

// Admin API routes
Route::prefix('api/v1/admin')->middleware('auth')->group(function () {
    Route::get('/utils/slugify', [UtilsController::class, 'slugify']);
    
    // Path reservations
    Route::get('/reservations', [\App\Http\Controllers\Admin\PathReservationController::class, 'index'])
        ->middleware('can:viewAny,' . \App\Models\RouteReservation::class);
    Route::post('/reservations', [\App\Http\Controllers\Admin\PathReservationController::class, 'store'])
        ->middleware('can:create,' . \App\Models\RouteReservation::class);
    Route::delete('/reservations/{path}', [\App\Http\Controllers\Admin\PathReservationController::class, 'destroy'])
        ->where('path', '.*')
        ->middleware('can:deleteAny,' . \App\Models\RouteReservation::class);
});

// Тестовый маршрут для проверки авторизации (только для тестов)
if (app()->environment('testing')) {
    Route::get('/test/admin/entries', fn() => response()->json(['message' => 'ok']))
        ->middleware('can:viewAny,' . Entry::class);
}
