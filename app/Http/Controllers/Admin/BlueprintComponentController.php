<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachComponentRequest;
use App\Http\Resources\BlueprintResource;
use App\Jobs\ReindexBlueprintEntries;
use App\Models\Blueprint;
use Illuminate\Http\JsonResponse;

/**
 * API для управления компонентами Blueprint.
 *
 * @group Blueprint Components
 */
class BlueprintComponentController extends Controller
{
    /**
     * Добавить компонент к Blueprint.
     *
     * @param \App\Http\Requests\AttachComponentRequest $request
     * @param \App\Models\Blueprint $blueprint
     * @return \Illuminate\Http\JsonResponse
     */
    public function attach(AttachComponentRequest $request, Blueprint $blueprint): JsonResponse
    {
        $componentId = $request->input('component_id');
        $pathPrefix = $request->input('path_prefix');

        $component = Blueprint::findOrFail($componentId);

        // Материализация Paths
        $blueprint->materializeComponentPaths($component, $pathPrefix);

        // Attach компонента
        $blueprint->components()->attach($componentId, [
            'path_prefix' => $pathPrefix,
        ]);

        // Реиндексация существующих entries
        dispatch(new ReindexBlueprintEntries($blueprint->id));

        return response()->json([
            'message' => 'Component attached successfully',
            'blueprint' => new BlueprintResource($blueprint->fresh('components')),
        ], 200);
    }

    /**
     * Удалить компонент из Blueprint.
     *
     * @param \App\Models\Blueprint $blueprint
     * @param \App\Models\Blueprint $component
     * @return \Illuminate\Http\JsonResponse
     */
    public function detach(Blueprint $blueprint, Blueprint $component): JsonResponse
    {
        // Дематериализация Paths
        $blueprint->dematerializeComponentPaths($component);

        // Detach компонента
        $blueprint->components()->detach($component->id);

        // Реиндексация entries
        dispatch(new ReindexBlueprintEntries($blueprint->id));

        return response()->json([
            'message' => 'Component detached successfully',
        ], 200);
    }

    /**
     * Список компонентов Blueprint.
     *
     * @param \App\Models\Blueprint $blueprint
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Blueprint $blueprint)
    {
        return BlueprintResource::collection(
            $blueprint->components()->get()
        );
    }
}

