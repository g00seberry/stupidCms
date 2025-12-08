<?php

use App\Http\Controllers\Admin\V1\BlueprintController;
use App\Http\Controllers\Admin\V1\BlueprintEmbedController;
use App\Http\Controllers\Admin\V1\OptionsController;
use App\Http\Controllers\Admin\V1\PathController;
use App\Http\Controllers\Admin\V1\PathReservationController;
use App\Http\Controllers\Admin\V1\TemplateController;
use App\Http\Controllers\Admin\V1\UtilsController;
use App\Http\Controllers\Admin\V1\EntryController;
use App\Http\Controllers\Admin\V1\EntryTermsController;
use App\Http\Controllers\Admin\V1\FormConfigController;
use App\Http\Controllers\Admin\V1\MediaController;
use App\Http\Controllers\Admin\V1\PostTypeController;
use App\Http\Controllers\Admin\V1\TaxonomyController;
use App\Http\Controllers\Admin\V1\TermController;
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
    Route::get('/post-types/{id}', [PostTypeController::class, 'show'])
        ->where('id', '[0-9]+')
        ->middleware(EnsureCanManagePostTypes::class)
        ->name('admin.v1.post-types.show');
    Route::put('/post-types/{id}', [PostTypeController::class, 'update'])
        ->where('id', '[0-9]+')
        ->middleware(EnsureCanManagePostTypes::class)
        ->name('admin.v1.post-types.update');
    Route::delete('/post-types/{id}', [PostTypeController::class, 'destroy'])
        ->where('id', '[0-9]+')
        ->middleware(EnsureCanManagePostTypes::class)
        ->name('admin.v1.post-types.destroy');
    
    // Form Configs (для конфигурации формы компонентов)
    Route::get('/post-types/{post_type_id}/form-config/{blueprint}', [FormConfigController::class, 'show'])
        ->where('post_type_id', '[0-9]+')
        ->middleware(EnsureCanManagePostTypes::class)
        ->name('admin.v1.post-types.form-config.show');
    Route::put('/post-types/{post_type_id}/form-config/{blueprint}', [FormConfigController::class, 'update'])
        ->where('post_type_id', '[0-9]+')
        ->middleware(EnsureCanManagePostTypes::class)
        ->name('admin.v1.post-types.form-config.update');
    Route::delete('/post-types/{post_type_id}/form-config/{blueprint}', [FormConfigController::class, 'destroy'])
        ->where('post_type_id', '[0-9]+')
        ->middleware(EnsureCanManagePostTypes::class)
        ->name('admin.v1.post-types.form-config.destroy');
    Route::get('/post-types/{post_type_id}/form-configs', [FormConfigController::class, 'indexByPostType'])
        ->where('post_type_id', '[0-9]+')
        ->middleware(EnsureCanManagePostTypes::class)
        ->name('admin.v1.post-types.form-configs.index');
    
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

    // Blueprints (full CRUD + dependencies/embeddable)
    Route::prefix('blueprints')->group(function () {
        // CRUD Blueprint
        Route::get('/', [BlueprintController::class, 'index'])
            ->name('admin.v1.blueprints.index');
        Route::post('/', [BlueprintController::class, 'store'])
            ->name('admin.v1.blueprints.store');
        Route::get('/{blueprint}', [BlueprintController::class, 'show'])
            ->name('admin.v1.blueprints.show');
        Route::put('/{blueprint}', [BlueprintController::class, 'update'])
            ->name('admin.v1.blueprints.update');
        Route::delete('/{blueprint}', [BlueprintController::class, 'destroy'])
            ->name('admin.v1.blueprints.destroy');

        // Вспомогательные endpoints
        Route::get('/{blueprint}/can-delete', [BlueprintController::class, 'canDelete'])
            ->name('admin.v1.blueprints.can-delete');
        Route::get('/{blueprint}/dependencies', [BlueprintController::class, 'dependencies'])
            ->name('admin.v1.blueprints.dependencies');
        Route::get('/{blueprint}/embeddable', [BlueprintController::class, 'embeddable'])
            ->name('admin.v1.blueprints.embeddable');
        Route::get('/{blueprint}/schema', [BlueprintController::class, 'schema'])
            ->name('admin.v1.blueprints.schema');

        // CRUD Path
        Route::get('/{blueprint}/paths', [PathController::class, 'index'])
            ->name('admin.v1.blueprints.paths.index');
        Route::post('/{blueprint}/paths', [PathController::class, 'store'])
            ->name('admin.v1.blueprints.paths.store');

        // CRUD BlueprintEmbed
        Route::get('/{blueprint}/embeds', [BlueprintEmbedController::class, 'index'])
            ->name('admin.v1.blueprints.embeds.index');
        Route::post('/{blueprint}/embeds', [BlueprintEmbedController::class, 'store'])
            ->name('admin.v1.blueprints.embeds.store');
    });

    // Path (глобальные операции)
    Route::prefix('paths')->group(function () {
        Route::get('/{path}', [PathController::class, 'show'])
            ->name('admin.v1.paths.show');
        Route::put('/{path}', [PathController::class, 'update'])
            ->name('admin.v1.paths.update');
        Route::delete('/{path}', [PathController::class, 'destroy'])
            ->name('admin.v1.paths.destroy');
    });

    // BlueprintEmbed (глобальные операции)
    Route::prefix('embeds')->group(function () {
        Route::get('/{embed}', [BlueprintEmbedController::class, 'show'])
            ->name('admin.v1.embeds.show');
        Route::delete('/{embed}', [BlueprintEmbedController::class, 'destroy'])
            ->name('admin.v1.embeds.destroy');
    });
});

