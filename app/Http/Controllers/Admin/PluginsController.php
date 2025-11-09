<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Plugins\Exceptions\InvalidPluginManifest;
use App\Domain\Plugins\Exceptions\PluginAlreadyDisabledException;
use App\Domain\Plugins\Exceptions\PluginAlreadyEnabledException;
use App\Domain\Plugins\Exceptions\RoutesReloadFailed;
use App\Domain\Plugins\PluginActivator;
use App\Domain\Plugins\Services\PluginsSynchronizer;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\Problems;
use App\Http\Requests\Admin\Plugins\IndexPluginsRequest;
use App\Http\Resources\PluginCollection;
use App\Http\Resources\PluginResource;
use App\Http\Resources\PluginSyncResource;
use App\Models\Plugin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class PluginsController extends Controller
{
    use Problems;

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
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
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
     *   "enabled": true,
     *   "routes_active": true
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/plugin-not-found",
     *   "title": "Plugin not found",
     *   "status": 404,
     *   "detail": "Plugin with slug \"seo-tools\" was not found."
     * }
     * @response status=409 {
     *   "type": "https://stupidcms.dev/problems/plugin-already-enabled",
     *   "title": "Plugin already enabled",
     *   "status": 409,
     *   "detail": "Plugin seo-tools is already enabled."
     * }
     * @response status=500 {
     *   "type": "https://stupidcms.dev/problems/routes-reload-failed",
     *   "title": "Failed to reload plugin routes",
     *   "status": 500,
     *   "detail": "Failed to reload plugin routes"
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function enable(string $slug, PluginActivator $activator): PluginResource
    {
        $plugin = $this->findPluginOrFail($slug);

        Gate::authorize('toggle', $plugin);

        try {
            $plugin = $activator->enable($plugin);
        } catch (PluginAlreadyEnabledException $exception) {
            throw new HttpResponseException(
                $this->problem(
                    Response::HTTP_CONFLICT,
                    'Plugin already enabled',
                    $exception->getMessage(),
                    ['type' => 'https://stupidcms.dev/problems/plugin-already-enabled']
                )
            );
        } catch (RoutesReloadFailed $exception) {
            throw new HttpResponseException(
                $this->problem(
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    'Failed to reload plugin routes',
                    $exception->getMessage(),
                    ['type' => 'https://stupidcms.dev/problems/routes-reload-failed']
                )
            );
        }

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
     *   "enabled": false
     * }
     * @response status=401 {
     *   "type": "https://stupidcms.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=404 {
     *   "type": "https://stupidcms.dev/problems/plugin-not-found",
     *   "title": "Plugin not found",
     *   "status": 404,
     *   "detail": "Plugin with slug \"seo-tools\" was not found."
     * }
     * @response status=409 {
     *   "type": "https://stupidcms.dev/problems/plugin-already-disabled",
     *   "title": "Plugin already disabled",
     *   "status": 409,
     *   "detail": "Plugin seo-tools is already disabled."
     * }
     * @response status=500 {
     *   "type": "https://stupidcms.dev/problems/routes-reload-failed",
     *   "title": "Failed to reload plugin routes",
     *   "status": 500,
     *   "detail": "Failed to reload plugin routes"
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function disable(string $slug, PluginActivator $activator): PluginResource
    {
        $plugin = $this->findPluginOrFail($slug);

        Gate::authorize('toggle', $plugin);

        try {
            $plugin = $activator->disable($plugin);
        } catch (PluginAlreadyDisabledException $exception) {
            throw new HttpResponseException(
                $this->problem(
                    Response::HTTP_CONFLICT,
                    'Plugin already disabled',
                    $exception->getMessage(),
                    ['type' => 'https://stupidcms.dev/problems/plugin-already-disabled']
                )
            );
        } catch (RoutesReloadFailed $exception) {
            throw new HttpResponseException(
                $this->problem(
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    'Failed to reload plugin routes',
                    $exception->getMessage(),
                    ['type' => 'https://stupidcms.dev/problems/routes-reload-failed']
                )
            );
        }

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
     *   "detail": "Authentication is required to access this resource."
     * }
     * @response status=422 {
     *   "type": "https://stupidcms.dev/problems/invalid-plugin-manifest",
     *   "title": "Invalid plugin manifest",
     *   "status": 422,
     *   "detail": "Plugin manifest is invalid."
     * }
     * @response status=500 {
     *   "type": "https://stupidcms.dev/problems/routes-reload-failed",
     *   "title": "Failed to reload plugin routes",
     *   "status": 500,
     *   "detail": "Failed to reload plugin routes"
     * }
     * @response status=429 {
     *   "message": "Too Many Attempts."
     * }
     */
    public function sync(PluginsSynchronizer $synchronizer): PluginSyncResource
    {
        Gate::authorize('sync', Plugin::class);

        try {
            $summary = $synchronizer->sync();
        } catch (InvalidPluginManifest $exception) {
            throw new HttpResponseException(
                $this->problem(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    'Invalid plugin manifest',
                    $exception->getMessage(),
                    ['type' => 'https://stupidcms.dev/problems/invalid-plugin-manifest']
                )
            );
        } catch (RoutesReloadFailed $exception) {
            throw new HttpResponseException(
                $this->problem(
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    'Failed to reload plugin routes',
                    $exception->getMessage(),
                    ['type' => 'https://stupidcms.dev/problems/routes-reload-failed']
                )
            );
        }

        return new PluginSyncResource($summary);
    }

    private function findPluginOrFail(string $slug): Plugin
    {
        try {
            return Plugin::query()->where('slug', $slug)->firstOrFail();
        } catch (ModelNotFoundException) {
            throw new HttpResponseException(
                $this->problem(
                    Response::HTTP_NOT_FOUND,
                    'Plugin not found',
                    sprintf('Plugin with slug "%s" was not found.', $slug),
                    ['type' => 'https://stupidcms.dev/problems/plugin-not-found']
                )
            );
        }
    }
}

