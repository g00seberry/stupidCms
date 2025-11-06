<?php

use App\Http\Controllers\Admin\UtilsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Admin API routes
Route::prefix('api/v1/admin')->group(function () {
    Route::get('/utils/slugify', [UtilsController::class, 'slugify']);
});
