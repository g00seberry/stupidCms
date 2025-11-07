<?php

use Illuminate\Support\Facades\Route;

// Taxonomies routes (пример - будет реализовано в будущих задачах)
// Route::get('/tag/{slug}', [TagController::class, 'show']);
// Route::get('/category/{slug}', [CategoryController::class, 'show']);

// Content resolver - динамические контентные маршруты
// Catch-all для контента, но не полный fallback (fallback идёт последним)
// 
// ВАЖНО: Catch-all должен игнорировать зарезервированные префиксы!
// Используйте негативный lookahead для защиты от перехвата системных путей:
// 
// Route::get('{slug}', ContentController::class)
//     ->where('slug', '^(?!(admin|api|auth|shop)(/|$))[A-Za-z0-9][A-Za-z0-9\-\/]*$');
// 
// Это дополнительно к проверке в ReservedRouteRegistry и PathReservationService.
// Зарезервированные префиксы: admin, api, auth, shop (и другие из конфига).

// Пока что файл пустой, так как контентные маршруты будут реализованы в задаче 33

