<?php

use App\Http\Controllers\Admin\PathReservationController;
use App\Http\Controllers\Admin\UtilsController;
use App\Http\Controllers\Admin\V1\EntryController;
use App\Http\Controllers\Admin\V1\PostTypeController;
use App\Http\Middleware\EnsureCanManagePostTypes;
use App\Models\Entry;
use App\Models\ReservedRoute;
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
Route::middleware(['admin.auth', 'throttle:api'])->group(function () {
    Route::get('/utils/slugify', [UtilsController::class, 'slugify']);
    
    // Path reservations
    Route::get('/reservations', [PathReservationController::class, 'index'])
        ->middleware('can:viewAny,' . ReservedRoute::class);
    Route::post('/reservations', [PathReservationController::class, 'store'])
        ->middleware('can:create,' . ReservedRoute::class);
    Route::delete('/reservations/{path}', [PathReservationController::class, 'destroy'])
        ->where('path', '.*')
        ->middleware('can:deleteAny,' . ReservedRoute::class);
    
    // Post Types (only show/update, no create/delete)
    Route::get('/post-types/{slug}', [PostTypeController::class, 'show'])
        ->middleware(EnsureCanManagePostTypes::class)
        ->name('admin.v1.post-types.show');
    Route::put('/post-types/{slug}', [PostTypeController::class, 'update'])
        ->middleware(EnsureCanManagePostTypes::class)
        ->name('admin.v1.post-types.update');
    
    // Entries (full CRUD + soft-delete/restore)
    Route::get('/entries', [EntryController::class, 'index'])
        ->middleware('can:viewAny,' . Entry::class)
        ->name('admin.v1.entries.index');
    Route::post('/entries', [EntryController::class, 'store'])
        ->middleware('can:create,' . Entry::class)
        ->name('admin.v1.entries.store');
    Route::get('/entries/{id}', [EntryController::class, 'show'])
        ->name('admin.v1.entries.show');
    Route::put('/entries/{id}', [EntryController::class, 'update'])
        ->name('admin.v1.entries.update');
    Route::delete('/entries/{id}', [EntryController::class, 'destroy'])
        ->name('admin.v1.entries.destroy');
    Route::post('/entries/{id}/restore', [EntryController::class, 'restore'])
        ->name('admin.v1.entries.restore');
});

