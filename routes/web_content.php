<?php

use App\Domain\Routing\ReservedPattern;
use App\Http\Controllers\PageController;
use App\Http\Middleware\CanonicalUrl;
use App\Http\Middleware\RejectReservedIfMatched;
use Illuminate\Support\Facades\Route;

// Taxonomies routes (пример - будет реализовано в будущих задачах)
// Route::get('/tag/{slug}', [TagController::class, 'show']);
// Route::get('/category/{slug}', [CategoryController::class, 'show']);

// Плоская маршрутизация для публичных страниц /{slug}
// Обрабатывает только плоские slug без слешей (a-z0-9-)
// Исключает зарезервированные пути через негативный lookahead в regex
// Middleware CanonicalUrl применяется на уровне группы в RouteServiceProvider
// и выполняет 301 редиректы для канонизации URL:
// - /About → /about (lowercase)
// - /about/ → /about (trailing slash)
$slugPattern = ReservedPattern::slugRegex();
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', $slugPattern)
    ->middleware(RejectReservedIfMatched::class)
    ->name('page.show');

