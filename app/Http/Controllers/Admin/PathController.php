<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePathRequest;
use App\Http\Resources\PathResource;
use App\Models\Blueprint;
use App\Models\Path;
use Illuminate\Http\Request;

/**
 * API для управления Path.
 *
 * @group Path Management
 */
class PathController extends Controller
{
    /**
     * Список Paths Blueprint.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Blueprint $blueprint
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request, Blueprint $blueprint)
    {
        $query = $blueprint->paths()->with('blueprint');

        if ($request->boolean('own_only')) {
            $query->whereNull('source_component_id');
        }

        return PathResource::collection($query->get());
    }

    /**
     * Показать Path.
     *
     * @param \App\Models\Blueprint $blueprint
     * @param \App\Models\Path $path
     * @return \App\Http\Resources\PathResource
     */
    public function show(Blueprint $blueprint, Path $path)
    {
        return new PathResource($path);
    }

    /**
     * Создать Path.
     *
     * @param \App\Http\Requests\StorePathRequest $request
     * @param \App\Models\Blueprint $blueprint
     * @return \App\Http\Resources\PathResource
     */
    public function store(StorePathRequest $request, Blueprint $blueprint)
    {
        $path = Path::create($request->validated());

        // Инвалидация кеша
        $blueprint->invalidatePathsCache();

        return new PathResource($path);
    }

    /**
     * Обновить Path.
     *
     * @param \App\Http\Requests\StorePathRequest $request
     * @param \App\Models\Blueprint $blueprint
     * @param \App\Models\Path $path
     * @return \App\Http\Resources\PathResource
     */
    public function update(StorePathRequest $request, Blueprint $blueprint, Path $path)
    {
        $path->update($request->validated());

        $blueprint->invalidatePathsCache();

        return new PathResource($path);
    }

    /**
     * Удалить Path.
     *
     * @param \App\Models\Blueprint $blueprint
     * @param \App\Models\Path $path
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Blueprint $blueprint, Path $path)
    {
        // PathObserver автоматически удалит материализованные копии
        $path->delete();

        $blueprint->invalidatePathsCache();

        return response()->json(['message' => 'Path deleted'], 200);
    }
}

