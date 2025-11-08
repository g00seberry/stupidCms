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
use App\Http\Resources\PluginResource;
use App\Models\Plugin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class PluginsController extends Controller
{
    use Problems;

    public function index(IndexPluginsRequest $request): ResourceCollection
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

        return PluginResource::collection($paginator);
    }

    public function enable(string $slug, PluginActivator $activator): JsonResponse
    {
        try {
            $plugin = Plugin::query()->where('slug', $slug)->firstOrFail();
        } catch (ModelNotFoundException) {
            return $this->problem(
                Response::HTTP_NOT_FOUND,
                'Plugin not found',
                sprintf('Plugin with slug "%s" was not found.', $slug),
                ['type' => 'https://stupidcms.dev/problems/plugin-not-found']
            );
        }

        Gate::authorize('toggle', $plugin);

        try {
            $plugin = $activator->enable($plugin);
        } catch (PluginAlreadyEnabledException $exception) {
            return $this->problem(
                Response::HTTP_CONFLICT,
                'Plugin already enabled',
                $exception->getMessage(),
                ['type' => 'https://stupidcms.dev/problems/plugin-already-enabled']
            );
        } catch (RoutesReloadFailed $exception) {
            return $this->problem(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to reload plugin routes',
                $exception->getMessage(),
                ['type' => 'https://stupidcms.dev/problems/routes-reload-failed']
            );
        }

        return (new PluginResource($plugin))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function disable(string $slug, PluginActivator $activator): JsonResponse
    {
        try {
            $plugin = Plugin::query()->where('slug', $slug)->firstOrFail();
        } catch (ModelNotFoundException) {
            return $this->problem(
                Response::HTTP_NOT_FOUND,
                'Plugin not found',
                sprintf('Plugin with slug "%s" was not found.', $slug),
                ['type' => 'https://stupidcms.dev/problems/plugin-not-found']
            );
        }

        Gate::authorize('toggle', $plugin);

        try {
            $plugin = $activator->disable($plugin);
        } catch (PluginAlreadyDisabledException $exception) {
            return $this->problem(
                Response::HTTP_CONFLICT,
                'Plugin already disabled',
                $exception->getMessage(),
                ['type' => 'https://stupidcms.dev/problems/plugin-already-disabled']
            );
        } catch (RoutesReloadFailed $exception) {
            return $this->problem(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to reload plugin routes',
                $exception->getMessage(),
                ['type' => 'https://stupidcms.dev/problems/routes-reload-failed']
            );
        }

        return (new PluginResource($plugin))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function sync(PluginsSynchronizer $synchronizer): JsonResponse
    {
        Gate::authorize('sync', Plugin::class);

        try {
            $summary = $synchronizer->sync();
        } catch (InvalidPluginManifest $exception) {
            return $this->problem(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Invalid plugin manifest',
                $exception->getMessage(),
                ['type' => 'https://stupidcms.dev/problems/invalid-plugin-manifest']
            );
        } catch (RoutesReloadFailed $exception) {
            return $this->problem(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to reload plugin routes',
                $exception->getMessage(),
                ['type' => 'https://stupidcms.dev/problems/routes-reload-failed']
            );
        }

        return response()->json([
            'status' => 'accepted',
            'summary' => [
                'added' => $summary['added'],
                'updated' => $summary['updated'],
                'removed' => $summary['removed'],
                'providers' => $summary['providers'],
            ],
        ], Response::HTTP_ACCEPTED);
    }
}

