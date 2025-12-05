<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlueprintEmbed\StoreEmbedRequest;
use App\Http\Resources\Admin\BlueprintEmbedResource;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use App\Services\Blueprint\BlueprintStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Контроллер для управления встраиваниями Blueprint.
 *
 * Предоставляет CRUD операции для BlueprintEmbed: создание, чтение, удаление.
 * Управляет встраиванием blueprint'ов друг в друга и материализацией полей.
 *
 * @group Admin ▸ Blueprint Embeds
 * @package App\Http\Controllers\Admin\V1
 */
class BlueprintEmbedController extends Controller
{
    /**
     * @param BlueprintStructureService $structureService
     */
    public function __construct(
        private readonly BlueprintStructureService $structureService
    ) {}

    /**
     * Список встраиваний Blueprint.
     *
     * @group Admin ▸ Blueprint Embeds
     * @name List blueprint embeds
     * @authenticated
     * @urlParam blueprint integer required ID blueprint (host). Example: 1
     * @response status=200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "blueprint_id": 1,
     *       "embedded_blueprint_id": 2,
     *       "host_path_id": 5,
     *       "embedded_blueprint": {
     *         "id": 2,
     *         "code": "address",
     *         "name": "Address"
     *       },
     *       "host_path": {
     *         "id": 5,
     *         "name": "office",
     *         "full_path": "office"
     *       },
     *       "created_at": "2025-01-10T12:00:00+00:00",
     *       "updated_at": "2025-01-10T12:00:00+00:00"
     *     }
     *   ]
     * }
     *
     * @param Blueprint $blueprint
     * @return AnonymousResourceCollection
     */
    public function index(Blueprint $blueprint): AnonymousResourceCollection
    {
        $embeds = $blueprint->embeds()
            ->with(['embeddedBlueprint', 'hostPath'])
            ->get();

        return BlueprintEmbedResource::collection($embeds);
    }

    /**
     * Создать встраивание.
     *
     * @group Admin ▸ Blueprint Embeds
     * @name Create blueprint embed
     * @authenticated
     * @urlParam blueprint integer required ID blueprint (host). Example: 1
     * @bodyParam embedded_blueprint_id integer required ID встраиваемого blueprint. Example: 2
     * @bodyParam host_path_id integer ID поля-контейнера (NULL = корень). Example: 5
     * @response status=201 {
     *   "data": {
     *     "id": 1,
     *     "blueprint_id": 1,
     *     "embedded_blueprint_id": 2,
     *     "host_path_id": 5,
     *     "embedded_blueprint": {
     *       "id": 2,
     *       "code": "address",
     *       "name": "Address"
     *     },
     *     "host_path": {
     *       "id": 5,
     *       "name": "office",
     *       "full_path": "office"
     *     },
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00"
     *   }
     * }
     * @response status=422 {
     *   "message": "Циклическая зависимость: 'address' уже зависит от 'article'"
     * }
     * @response status=422 {
     *   "message": "Невозможно встроить blueprint 'address' в 'article': конфликт путей: 'email'"
     * }
     * @response status=409 {
     *   "type": "https://stupidcms.dev/problems/conflict",
     *   "title": "Conflict",
     *   "status": 409,
     *   "code": "CONFLICT",
     *   "detail": "Blueprint 'address' уже встроен в 'article' в корень.",
     *   "meta": {
     *     "host_blueprint_code": "article",
     *     "embedded_blueprint_code": "address",
     *     "host_path_full_path": null
     *   }
     * }
     *
     * @param StoreEmbedRequest $request
     * @param Blueprint $blueprint
     * @return BlueprintEmbedResource
     */
    public function store(StoreEmbedRequest $request, Blueprint $blueprint): BlueprintEmbedResource
    {
        $embedded = Blueprint::findOrFail($request->input('embedded_blueprint_id'));

        $hostPath = $request->input('host_path_id')
            ? Path::findOrFail($request->input('host_path_id'))
            : null;

        $embed = $this->structureService->createEmbed(
            $blueprint,
            $embedded,
            $hostPath
        );

        $embed->load(['embeddedBlueprint', 'hostPath']);

        return new BlueprintEmbedResource($embed);
    }

    /**
     * Просмотр встраивания.
     *
     * @group Admin ▸ Blueprint Embeds
     * @name Show blueprint embed
     * @authenticated
     * @urlParam embed integer required ID embed. Example: 1
     * @response status=200 {
     *   "data": {
     *     "id": 1,
     *     "blueprint_id": 1,
     *     "embedded_blueprint_id": 2,
     *     "host_path_id": 5,
     *     "blueprint": {
     *       "id": 1,
     *       "code": "article",
     *       "name": "Article"
     *     },
     *     "embedded_blueprint": {
     *       "id": 2,
     *       "code": "address",
     *       "name": "Address"
     *     },
     *     "host_path": {
     *       "id": 5,
     *       "name": "office",
     *       "full_path": "office"
     *     },
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00"
     *   }
     * }
     *
     * @param BlueprintEmbed $embed
     * @return BlueprintEmbedResource
     */
    public function show(BlueprintEmbed $embed): BlueprintEmbedResource
    {
        $embed->load(['blueprint', 'embeddedBlueprint', 'hostPath']);

        return new BlueprintEmbedResource($embed);
    }

    /**
     * Удалить встраивание.
     *
     * @group Admin ▸ Blueprint Embeds
     * @name Delete blueprint embed
     * @authenticated
     * @urlParam embed integer required ID embed. Example: 1
     * @response status=200 {
     *   "message": "Встраивание удалено"
     * }
     *
     * @param BlueprintEmbed $embed
     * @return JsonResponse
     */
    public function destroy(BlueprintEmbed $embed): JsonResponse
    {
        $this->structureService->deleteEmbed($embed);

        return response()->json(['message' => 'Встраивание удалено'], 200);
    }
}

