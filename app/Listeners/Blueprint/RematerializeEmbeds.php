<?php

declare(strict_types=1);

namespace App\Listeners\Blueprint;

use App\Events\Blueprint\BlueprintStructureChanged;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Services\Blueprint\DependencyGraphService;
use App\Services\Blueprint\MaterializationService;
use Illuminate\Support\Facades\Log;

/**
 * Listener: рематериализация встраиваний при изменении структуры blueprint.
 *
 * Обрабатывает событие BlueprintStructureChanged:
 * 1. Находит всех зависимых (кто встраивает изменённый blueprint)
 * 2. Рематериализует все embeds
 * 3. Каскадно триггерит событие для зависимых
 * 4. Защита от зацикливания через processedBlueprints
 */
class RematerializeEmbeds
{
    /**
     * @param MaterializationService $materializationService
     * @param DependencyGraphService $graphService
     */
    public function __construct(
        private readonly MaterializationService $materializationService,
        private readonly DependencyGraphService $graphService
    ) {}

    /**
     * Обработать событие.
     *
     * @param BlueprintStructureChanged $event
     * @return void
     */
    public function handle(BlueprintStructureChanged $event): void
    {
        $changedBlueprint = $event->blueprint;

        // Защита от зацикливания
        if ($event->wasProcessed($changedBlueprint->id)) {
            Log::info("Blueprint {$changedBlueprint->code} уже обработан в цепочке, пропускаем");
            return;
        }

        Log::info("Обработка изменения структуры blueprint '{$changedBlueprint->code}' (ID: {$changedBlueprint->id})");

        // Пометить текущий blueprint как обработанный
        $newEvent = $event->withProcessed($changedBlueprint->id);

        // 1. Найти все blueprint'ы, которые встраивают изменённый
        $dependentIds = $this->graphService->getDirectDependents($changedBlueprint->id);

        if (empty($dependentIds)) {
            Log::info("Нет зависимых blueprint'ов для '{$changedBlueprint->code}'");
            return;
        }

        Log::info("Найдено зависимых blueprint'ов: " . count($dependentIds));

        // 2. Рематериализовать все embeds для каждого зависимого
        foreach ($dependentIds as $dependentId) {
            $this->rematerializeDependentBlueprint($dependentId, $changedBlueprint->id, $newEvent);
        }
    }

    /**
     * Рематериализовать встраивания зависимого blueprint.
     *
     * @param int $dependentId ID зависимого blueprint
     * @param int $changedId ID изменённого blueprint
     * @param BlueprintStructureChanged $event Событие с историей обработки
     * @return void
     */
    private function rematerializeDependentBlueprint(
        int $dependentId,
        int $changedId,
        BlueprintStructureChanged $event
    ): void {
        try {
            // Получить зависимый blueprint
            $dependent = Blueprint::findOrFail($dependentId);

            Log::info("Рематериализация blueprint '{$dependent->code}' (зависит от изменённого ID: {$changedId})");

            // Найти все embeds, где dependent встраивает changed
            $embeds = BlueprintEmbed::query()
                ->where('blueprint_id', $dependentId)
                ->where('embedded_blueprint_id', $changedId)
                ->with(['blueprint', 'embeddedBlueprint', 'hostPath'])
                ->get();

            foreach ($embeds as $embed) {
                Log::info("  Материализация embed ID: {$embed->id}");
                $this->materializationService->materialize($embed);
            }

            // 3. Каскадное событие для зависимого blueprint
            // (структура dependent изменилась, нужно уведомить тех, кто встраивает dependent)
            Log::info("Триггер каскадного события для '{$dependent->code}'");
            event(new BlueprintStructureChanged($dependent, $event->processedBlueprints));

        } catch (\Exception $e) {
            Log::error("Ошибка рематериализации blueprint ID {$dependentId}: {$e->getMessage()}", [
                'exception' => $e,
                'changed_blueprint_id' => $changedId,
            ]);

            // В production можно уведомить админа
            // report($e);
        }
    }
}

