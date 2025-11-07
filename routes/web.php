<?php

use App\Http\Controllers\Admin\UtilsController;
use App\Models\Entry;
use Illuminate\Support\Facades\Route;

Route::get('/', \App\Http\Controllers\HomeController::class);

// Admin API routes
Route::prefix('api/v1/admin')->group(function () {
    Route::get('/utils/slugify', [UtilsController::class, 'slugify']);
});

// Тестовый маршрут для проверки авторизации (только для тестов)
if (app()->environment('testing')) {
    Route::get('/test/admin/entries', fn() => response()->json(['message' => 'ok']))
        ->middleware('can:viewAny,' . Entry::class);
}
