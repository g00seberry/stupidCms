<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBlueprintRequest;
use App\Http\Resources\BlueprintResource;
use App\Models\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * API для управления Blueprint.
 *
 * @group Blueprint Management
 */
class BlueprintController extends Controller
{
    /**
     * Список Blueprint.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $query = Blueprint::with('postType');

        if ($request->has('post_type_id')) {
            $query->where('post_type_id', $request->input('post_type_id'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $blueprints = $query->paginate(20);

        return BlueprintResource::collection($blueprints);
    }

    /**
     * Показать Blueprint.
     *
     * @param \App\Models\Blueprint $blueprint
     * @return \App\Http\Resources\BlueprintResource
     */
    public function show(Blueprint $blueprint)
    {
        $blueprint->load(['postType', 'paths']);

        return new BlueprintResource($blueprint);
    }

    /**
     * Создать Blueprint.
     *
     * @param \App\Http\Requests\StoreBlueprintRequest $request
     * @return \App\Http\Resources\BlueprintResource
     */
    public function store(StoreBlueprintRequest $request)
    {
        $blueprint = Blueprint::create($request->validated());

        return new BlueprintResource($blueprint);
    }

    /**
     * Обновить Blueprint.
     *
     * @param \App\Http\Requests\StoreBlueprintRequest $request
     * @param \App\Models\Blueprint $blueprint
     * @return \App\Http\Resources\BlueprintResource
     */
    public function update(StoreBlueprintRequest $request, Blueprint $blueprint)
    {
        $blueprint->update($request->validated());

        return new BlueprintResource($blueprint);
    }

    /**
     * Удалить Blueprint.
     *
     * @param \App\Models\Blueprint $blueprint
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Blueprint $blueprint): JsonResponse
    {
        // Проверка: нельзя удалить Blueprint с entries
        if ($blueprint->entries()->exists()) {
            return response()->json([
                'message' => 'Cannot delete Blueprint with existing entries',
                'entries_count' => $blueprint->entries()->count(),
            ], 422);
        }

        $blueprint->delete();

        return response()->json(['message' => 'Blueprint deleted'], 200);
    }
}

