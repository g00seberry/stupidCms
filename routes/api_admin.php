<?php

use App\Http\Controllers\Admin\V1\OptionsController;
use App\Http\Controllers\Admin\V1\PathReservationController;
use App\Http\Controllers\Admin\V1\PluginsController;
use App\Http\Controllers\Admin\V1\SearchAdminController;
use App\Http\Controllers\Admin\V1\TemplateController;
use App\Http\Controllers\Admin\V1\UtilsController;
use App\Http\Controllers\Admin\V1\EntryController;
use App\Http\Controllers\Admin\V1\EntryTermsController;
use App\Http\Controllers\Admin\V1\MediaController;
use App\Http\Controllers\Admin\V1\PostTypeController;
use App\Http\Controllers\Admin\V1\TaxonomyController;
use App\Http\Controllers\Admin\V1\TermController;
use App\Http\Controllers\Admin\BlueprintController;
use App\Http\Controllers\Admin\PathController;
use App\Http\Controllers\Auth\CurrentUserController;
use App\Http\Middleware\EnsureCanManagePostTypes;
use App\Models\Entry;
use App\Models\Media;
use App\Models\Option;
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
 * - Throttle 'api' настроен в bootstrap/app.php (120 запросов в минуту)
 * - Для кросс-сайтовых запросов (SPA на другом origin) требуется:
 *   - SameSite=None; Secure для cookies
 *   - CORS с credentials: true
 *   - CSRF токены для state-changing операций (если используется cookie-based auth)
 */

Route::middleware(['jwt.auth', 'throttle:api'])->group(function () {
    Route::get('/auth/current', [CurrentUserController::class, 'show'])
        ->middleware('no-cache-auth')
        ->name('admin.v1.auth.current');
    
    Route::get('/utils/slugify', [UtilsController::class, 'slugify']);
    
    // Templates (full CRUD)
    Route::get('/templates', [TemplateController::class, 'index'])
        ->name('admin.v1.templates.index');
    Route::get('/templates/{name}', [TemplateController::class, 'show'])
        ->where('name', '.*')
        ->name('admin.v1.templates.show');
    Route::post('/templates', [TemplateController::class, 'store'])
        ->name('admin.v1.templates.store');
    Route::put('/templates/{name}', [TemplateController::class, 'update'])
        ->where('name', '.*')
        ->name('admin.v1.templates.update');
    
    Route::get('/plugins', [PluginsController::class, 'index'])
        ->middleware(['can:plugins.read', 'throttle:60,1'])
        ->name('admin.v1.plugins.index');

    Route::post('/plugins/sync', [PluginsController::class, 'sync'])
        ->middleware(['can:plugins.sync', 'throttle:10,1'])
        ->name('admin.v1.plugins.sync');

    Route::post('/plugins/{slug}/enable', [PluginsController::class, 'enable'])
        ->middleware(['can:plugins.toggle', 'throttle:10,1'])
        ->name('admin.v1.plugins.enable');

    Route::post('/plugins/{slug}/disable', [PluginsController::class, 'disable'])
        ->middleware(['can:plugins.toggle', 'throttle:10,1'])
        ->name('admin.v1.plugins.disable');

    // Path reservations
    Route::get('/reservations', [PathReservationController::class, 'index'])
        ->middleware('can:viewAny,' . ReservedRoute::class);
    Route::post('/reservations', [PathReservationController::class, 'store'])
        ->middleware('can:create,' . ReservedRoute::class);
    Route::delete('/reservations/{path}', [PathReservationController::class, 'destroy'])
        ->where('path', '.*')
        ->middleware('can:deleteAny,' . ReservedRoute::class);
    
    // Post Types (full CRUD)
    Route::post('/post-types', [PostTypeController::class, 'store'])
        ->middleware(EnsureCanManagePostTypes::class)
        ->name('admin.v1.post-types.store');
    Route::get('/post-types', [PostTypeController::class, 'index'])
        ->middleware(EnsureCanManagePostTypes::class)
        ->name('admin.v1.post-types.index');
    Route::get('/post-types/{slug}', [PostTypeController::class, 'show'])
        ->middleware(EnsureCanManagePostTypes::class)
        ->name('admin.v1.post-types.show');
    Route::put('/post-types/{slug}', [PostTypeController::class, 'update'])
        ->middleware(EnsureCanManagePostTypes::class)
        ->name('admin.v1.post-types.update');
    Route::delete('/post-types/{slug}', [PostTypeController::class, 'destroy'])
        ->middleware(EnsureCanManagePostTypes::class)
        ->name('admin.v1.post-types.destroy');
    
    // Entries (full CRUD + soft-delete/restore)
    Route::get('/entries/statuses', [EntryController::class, 'statuses'])
        ->middleware('can:viewAny,' . Entry::class)
        ->name('admin.v1.entries.statuses');
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

    Route::middleware('can:manage.taxonomies')->group(function () {
        Route::get('/taxonomies', [TaxonomyController::class, 'index'])
            ->name('admin.v1.taxonomies.index');
        Route::post('/taxonomies', [TaxonomyController::class, 'store'])
            ->name('admin.v1.taxonomies.store');
        Route::get('/taxonomies/{id}', [TaxonomyController::class, 'show'])
            ->name('admin.v1.taxonomies.show');
        Route::put('/taxonomies/{id}', [TaxonomyController::class, 'update'])
            ->name('admin.v1.taxonomies.update');
        Route::delete('/taxonomies/{id}', [TaxonomyController::class, 'destroy'])
            ->name('admin.v1.taxonomies.destroy');
    });

    Route::middleware('can:manage.terms')->group(function () {
        Route::get('/taxonomies/{taxonomy}/terms/tree', [TermController::class, 'tree'])
            ->where('taxonomy', '[0-9]+')
            ->name('admin.v1.taxonomies.terms.tree');
        Route::get('/taxonomies/{taxonomy}/terms', [TermController::class, 'indexByTaxonomy'])
            ->where('taxonomy', '[0-9]+')
            ->name('admin.v1.taxonomies.terms.index');
        Route::post('/taxonomies/{taxonomy}/terms', [TermController::class, 'store'])
            ->where('taxonomy', '[0-9]+')
            ->name('admin.v1.taxonomies.terms.store');
        Route::get('/terms/{term}', [TermController::class, 'show'])
            ->name('admin.v1.terms.show');
        Route::put('/terms/{term}', [TermController::class, 'update'])
            ->name('admin.v1.terms.update');
        Route::delete('/terms/{term}', [TermController::class, 'destroy'])
            ->name('admin.v1.terms.destroy');

        Route::get('/entries/{entry}/terms', [EntryTermsController::class, 'index'])
            ->name('admin.v1.entries.terms.index');
        Route::put('/entries/{entry}/terms/sync', [EntryTermsController::class, 'sync'])
            ->name('admin.v1.entries.terms.sync');
    });

    Route::middleware('can:viewAny,' . Media::class)->group(function () {
        Route::get('/media/config', [MediaController::class, 'config'])
            ->middleware('throttle:60,1')
            ->name('admin.v1.media.config');
        Route::get('/media', [MediaController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('admin.v1.media.index');
        Route::get('/media/{media}', [MediaController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('admin.v1.media.show');
    });

    Route::post('/media', [MediaController::class, 'store'])
        ->middleware(['can:create,' . Media::class, 'throttle:20,1'])
        ->name('admin.v1.media.store');

    Route::post('/media/bulk', [MediaController::class, 'bulkStore'])
        ->middleware(['can:create,' . Media::class, 'throttle:20,1'])
        ->name('admin.v1.media.bulkStore');

    Route::put('/media/{media}', [MediaController::class, 'update'])
        ->middleware('throttle:20,1')
        ->name('admin.v1.media.update');

    Route::delete('/media/bulk', [MediaController::class, 'bulkDestroy'])
        ->middleware('throttle:20,1')
        ->name('admin.v1.media.bulkDestroy');

    Route::post('/media/bulk/restore', [MediaController::class, 'bulkRestore'])
        ->middleware('throttle:20,1')
        ->name('admin.v1.media.bulkRestore');

    Route::delete('/media/bulk/force', [MediaController::class, 'bulkForceDestroy'])
        ->middleware('throttle:20,1')
        ->name('admin.v1.media.bulkForceDestroy');

    Route::prefix('/options')->group(function () {
        Route::get('/{namespace}', [OptionsController::class, 'index'])
            ->middleware(['can:viewAny,' . Option::class, 'can:options.read', 'throttle:120,1'])
            ->name('admin.v1.options.index');

        Route::get('/{namespace}/{key}', [OptionsController::class, 'show'])
            ->middleware(['can:options.read', 'throttle:120,1'])
            ->name('admin.v1.options.show');

        Route::put('/{namespace}/{key}', [OptionsController::class, 'put'])
            ->middleware(['can:options.write', 'throttle:30,1'])
            ->name('admin.v1.options.upsert');

        Route::delete('/{namespace}/{key}', [OptionsController::class, 'destroy'])
            ->middleware(['can:options.delete', 'throttle:30,1'])
            ->name('admin.v1.options.destroy');

        Route::post('/{namespace}/{key}/restore', [OptionsController::class, 'restore'])
            ->middleware(['can:options.restore', 'throttle:30,1'])
            ->name('admin.v1.options.restore');
    });

    Route::post('/search/reindex', [SearchAdminController::class, 'reindex'])
        ->middleware(['can:search.reindex', 'throttle:search-reindex'])
        ->name('admin.v1.search.reindex');

    // Blueprint Management
    Route::apiResource('blueprints', BlueprintController::class)
        ->names([
            'index' => 'admin.v1.blueprints.index',
            'store' => 'admin.v1.blueprints.store',
            'show' => 'admin.v1.blueprints.show',
            'update' => 'admin.v1.blueprints.update',
            'destroy' => 'admin.v1.blueprints.destroy',
        ]);

    // Paths в контексте Blueprint
    Route::prefix('blueprints/{blueprint}')->group(function () {
        Route::apiResource('paths', PathController::class)
            ->names([
                'index' => 'admin.v1.blueprints.paths.index',
                'store' => 'admin.v1.blueprints.paths.store',
                'show' => 'admin.v1.blueprints.paths.show',
                'update' => 'admin.v1.blueprints.paths.update',
                'destroy' => 'admin.v1.blueprints.paths.destroy',
            ]);
    });
});

