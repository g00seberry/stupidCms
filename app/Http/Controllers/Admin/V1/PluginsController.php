<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Domain\Plugins\Contracts\PluginActivatorInterface;
use App\Domain\Plugins\Contracts\PluginsSynchronizerInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Plugins\IndexPluginsRequest;
use App\Http\Resources\PluginCollection;
use App\Http\Resources\PluginResource;
use App\Http\Resources\PluginSyncResource;
use App\Models\Plugin;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * Контроллер для управления плагинами в админ-панели.
 *
 * Предоставляет операции для плагинов: просмотр списка, синхронизация из файловой системы,
 * включение/отключение плагинов.
 *
 * @package App\Http\Controllers\Admin\V1
 */
final class PluginsController extends Controller
{
    use ThrowsErrors;


    /**
     * Список плагинов.
     *
     * @group Admin ▸ Plugins
     * @name List plugins
     * @authenticated
     * @queryParam q string Поиск по slug/name (<=128). Example: seo
     * @queryParam enabled string Фильтр по статусу. Values: true,false,any. Default: any.
     * @queryParam sort string Поле сортировки. Values: name,slug,version,updated_at. Default: name.
     * @queryParam order string Направление сортировки. Values: asc,desc. Default: asc.
     * @queryParam per_page int Размер страницы (1-100). Default: 25.
     * @response status=200 {
     *   "data": [
     *     {
     *       "slug": "seo-tools",
     *       "name": "SEO Tools",
     *       "version": "1.2.0",
     *       "enabled": true,
     *       "provider": "Plugins\\SeoTools\\ServiceProvider",
     *       "routes_active": true,
     *       "last_synced_at": "2025-01-10T12:00:00+00:00"
     *     }
     *   ],
     *   "links": {
     *     "first": "…",
     *     "last": "…",
     *     "prev": null,
     *     "next": null
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 1,
     *     "per_page": 25,
     *     "total": 1
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "71111111-2222-3333-4444-555555555555",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-71111111222233334444555555555555-7111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "76666666-7777-8888-9999-000000000000",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-76666666777788889999000000000000-7666666677778888-01"
     * }
     */
    public function index(IndexPluginsRequest $request): PluginCollection
    {
        $validated = $request->validated();

        $query = Plugin::query();

        if (($search = Arr::get($validated, 'q')) !== null && $search !== '') {
            $term = '%' . $search . '%';

            $query->where(static function (Builder $builder) use ($term): void {
                $builder
                    ->where('slug', 'like', $term)
                    ->orWhere('name', 'like', $term);
            });
        }

        $enabled = Arr::get($validated, 'enabled', 'any');
        if ($enabled === 'true') {
            $query->where('enabled', true);
        } elseif ($enabled === 'false') {
            $query->where('enabled', false);
        }

        $sort = Arr::get($validated, 'sort', 'name');
        $order = Arr::get($validated, 'order', 'asc');

        $query->orderBy($sort, $order);

        $perPage = (int) Arr::get($validated, 'per_page', 25);

        $paginator = $query->paginate($perPage);
        $paginator->appends($request->query());

        return new PluginCollection($paginator);
    }

    /**
     * Активация плагина.
     *
     * @group Admin ▸ Plugins
     * @name Enable plugin
     * @authenticated
     * @urlParam slug string required Slug плагина. Example: seo-tools
     * @response status=200 {
     *   "slug": "seo-tools",
     *   "name": "SEO Tools",
     *   "version": "1.2.0",
     *   "enabled": true,
     *   "provider": "Plugins\\SeoTools\\ServiceProvider",
     *   "routes_active": true,
     *   "last_synced_at": "2025-01-10T12:00:00+00:00"
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "71111111-2222-3333-4444-555555555556",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-71111111222233334444555555555556-7111111122223333-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/plugin-not-found",
     *   "title": "Plugin not found",
     *   "status": 404,
     *   "code": "PLUGIN_NOT_FOUND",
     *   "detail": "Plugin with slug \"seo-tools\" was not found.",
     *   "meta": {
     *     "request_id": "71111111-2222-3333-4444-555555555557",
     *     "slug": "seo-tools"
     *   },
     *   "trace_id": "00-71111111222233334444555555555557-7111111122223333-01"
     * }
     * @response status=409 {
     *   "type": "https://stupidcms.dev/problems/plugin-already-enabled",
     *   "title": "Plugin already enabled",
     *   "status": 409,
     *   "code": "PLUGIN_ALREADY_ENABLED",
     *   "detail": "Plugin seo-tools is already enabled.",
     *   "meta": {
     *     "request_id": "71111111-2222-3333-4444-555555555558",
     *     "slug": "seo-tools"
     *   },
     *   "trace_id": "00-71111111222233334444555555555558-7111111122223333-01"
     * }
     * @response status=500 {
     *   "type": "https://stupidcms.dev/problems/routes-reload-failed",
     *   "title": "Failed to reload plugin routes",
     *   "status": 500,
     *   "code": "ROUTES_RELOAD_FAILED",
     *   "detail": "Failed to reload plugin routes.",
     *   "meta": {
     *     "request_id": "71111111-2222-3333-4444-555555555559",
     *     "slug": "seo-tools"
     *   },
     *   "trace_id": "00-71111111222233334444555555555559-7111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "76666666-7777-8888-9999-000000000001",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-76666666777788889999000000000001-7666666677778888-01"
     * }
     */
    public function enable(string $slug, PluginActivatorInterface $activator): PluginResource
    {
        $plugin = $this->findPluginOrFail($slug);

        Gate::authorize('toggle', $plugin);

        $plugin = $activator->enable($plugin);

        return new PluginResource($plugin);
    }

    /**
     * Деактивация плагина.
     *
     * @group Admin ▸ Plugins
     * @name Disable plugin
     * @authenticated
     * @urlParam slug string required Slug плагина. Example: seo-tools
     * @response status=200 {
     *   "slug": "seo-tools",
     *   "name": "SEO Tools",
     *   "version": "1.2.0",
     *   "enabled": false,
     *   "provider": "Plugins\\SeoTools\\ServiceProvider",
     *   "routes_active": false,
     *   "last_synced_at": "2025-01-10T12:00:00+00:00"
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "71111111-2222-3333-4444-555555555560",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-71111111222233334444555555555660-7111111122223333-01"
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/plugin-not-found",
     *   "title": "Plugin not found",
     *   "status": 404,
     *   "code": "PLUGIN_NOT_FOUND",
     *   "detail": "Plugin with slug \"seo-tools\" was not found.",
     *   "meta": {
     *     "request_id": "71111111-2222-3333-4444-555555555561",
     *     "slug": "seo-tools"
     *   },
     *   "trace_id": "00-71111111222233334444555555555661-7111111122223333-01"
     * }
     * @response status=409 {
     *   "type": "https://stupidcms.dev/problems/plugin-already-disabled",
     *   "title": "Plugin already disabled",
     *   "status": 409,
     *   "code": "PLUGIN_ALREADY_DISABLED",
     *   "detail": "Plugin seo-tools is already disabled.",
     *   "meta": {
     *     "request_id": "71111111-2222-3333-4444-555555555562",
     *     "slug": "seo-tools"
     *   },
     *   "trace_id": "00-71111111222233334444555555555662-7111111122223333-01"
     * }
     * @response status=500 {
     *   "type": "https://stupidcms.dev/problems/routes-reload-failed",
     *   "title": "Failed to reload plugin routes",
     *   "status": 500,
     *   "code": "ROUTES_RELOAD_FAILED",
     *   "detail": "Failed to reload plugin routes.",
     *   "meta": {
     *     "request_id": "71111111-2222-3333-4444-555555555563",
     *     "slug": "seo-tools"
     *   },
     *   "trace_id": "00-71111111222233334444555555555663-7111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "76666666-7777-8888-9999-000000000002",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-76666666777788889999000000000002-7666666677778888-01"
     * }
     */
    public function disable(string $slug, PluginActivatorInterface $activator): PluginResource
    {
        $plugin = $this->findPluginOrFail($slug);

        Gate::authorize('toggle', $plugin);

        $plugin = $activator->disable($plugin);

        return new PluginResource($plugin);
    }

    /**
     * Синхронизация метаданных плагинов.
     *
     * @group Admin ▸ Plugins
     * @name Sync plugins
     * @authenticated
     * @response status=202 {
     *   "status": "accepted",
     *   "summary": {
     *     "added": ["analytics"],
     *     "updated": ["seo-tools"],
     *     "removed": [],
     *     "providers": ["Plugins\\SeoTools\\ServiceProvider"]
     *   }
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED",
     *   "detail": "Authentication is required to access this resource.",
     *   "meta": {
     *     "request_id": "71111111-2222-3333-4444-555555555564",
     *     "reason": "missing_token"
     *   },
     *   "trace_id": "00-71111111222233334444555555555664-7111111122223333-01"
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/invalid-plugin-manifest",
     *   "title": "Invalid plugin manifest",
     *   "status": 422,
     *   "code": "INVALID_PLUGIN_MANIFEST",
     *   "detail": "Plugin manifest is invalid.",
     *   "meta": {
     *     "request_id": "71111111-2222-3333-4444-555555555565",
     *     "manifest": "plugins/seo-tools/manifest.json"
     *   },
     *   "trace_id": "00-71111111222233334444555555555665-7111111122223333-01"
     * }
     * @response status=500 {
     *   "type": "https://stupidcms.dev/problems/routes-reload-failed",
     *   "title": "Failed to reload plugin routes",
     *   "status": 500,
     *   "code": "ROUTES_RELOAD_FAILED",
     *   "detail": "Failed to reload plugin routes.",
     *   "meta": {
     *     "request_id": "71111111-2222-3333-4444-555555555566",
     *     "providers": [
     *       "Plugins\\SeoTools\\ServiceProvider"
     *     ]
     *   },
     *   "trace_id": "00-71111111222233334444555555555666-7111111122223333-01"
     * }
     * @response status=429 {
     *   "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
     *   "title": "Too Many Requests",
     *   "status": 429,
     *   "code": "RATE_LIMIT_EXCEEDED",
     *   "detail": "Too many attempts. Try again later.",
     *   "meta": {
     *     "request_id": "76666666-7777-8888-9999-000000000003",
     *     "retry_after": 60
     *   },
     *   "trace_id": "00-76666666777788889999000000000003-7666666677778888-01"
     * }
     */
    public function sync(PluginsSynchronizerInterface $synchronizer): PluginSyncResource
    {
        Gate::authorize('sync', Plugin::class);

        $summary = $synchronizer->sync();

        return new PluginSyncResource($summary);
    }

    /**
     * Найти плагин по slug или выбросить ошибку.
     *
     * @param string $slug Slug плагина
     * @return \App\Models\Plugin Плагин
     * @throws \App\Support\Errors\HttpErrorException Если плагин не найден
     */
    private function findPluginOrFail(string $slug): Plugin
    {
        $plugin = Plugin::query()->where('slug', $slug)->first();

        if (! $plugin) {
            $this->throwError(ErrorCode::PLUGIN_NOT_FOUND, sprintf('Plugin with slug "%s" was not found.', $slug), [
                'slug' => $slug,
            ]);
        }

        return $plugin;
    }
}

