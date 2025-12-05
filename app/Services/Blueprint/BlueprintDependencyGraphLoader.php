<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use Illuminate\Support\Collection;

/**
 * Реализация загрузчика графа зависимостей blueprint'ов.
 *
 * Предзагружает весь граф зависимостей одним набором запросов
 * для избежания N+1 при материализации.
 */
final class BlueprintDependencyGraphLoader implements BlueprintDependencyGraphLoaderInterface
{
    /**
     * Загрузить весь граф зависимостей для корневого blueprint'а.
     *
     * Выполняет BFS-обход графа и предзагружает все paths и embeds
     * для blueprint'ов до указанной максимальной глубины.
     *
     * @param Blueprint $rootBlueprint Корневой blueprint
     * @param int $maxDepth Максимальная глубина обхода
     * @return DependencyGraph Кеш графа зависимостей
     */
    public function load(Blueprint $rootBlueprint, int $maxDepth): DependencyGraph
    {
        $visited = [];
        $blueprintIds = [$rootBlueprint->id];
        $depth = 0;
        $embedsCache = [];

        // BFS: собрать все ID blueprint'ов в графе
        while (!empty($blueprintIds) && $depth < $maxDepth) {
            $currentIds = $blueprintIds;
            $blueprintIds = [];

            // Загрузить embeds одним запросом для текущего уровня
            $embeds = BlueprintEmbed::query()
                ->whereIn('blueprint_id', $currentIds)
                ->with(['hostPath', 'embeddedBlueprint'])
                ->get();

            foreach ($currentIds as $blueprintId) {
                if (isset($visited[$blueprintId])) {
                    continue;
                }
                $visited[$blueprintId] = true;

                // Сгруппировать embeds по blueprint_id
                $blueprintEmbeds = $embeds->where('blueprint_id', $blueprintId);
                if ($blueprintEmbeds->isNotEmpty()) {
                    $embedsCache[$blueprintId] = $blueprintEmbeds;

                    // Собрать ID встроенных blueprint'ов
                    foreach ($blueprintEmbeds as $embed) {
                        $embeddedId = $embed->embedded_blueprint_id;
                        if (!isset($visited[$embeddedId])) {
                            $blueprintIds[] = $embeddedId;
                        }
                    }
                }
            }

            $depth++;
        }

        // Загрузить все paths одним запросом
        $allBlueprintIds = array_keys($visited);
        $paths = Path::query()
            ->whereIn('blueprint_id', $allBlueprintIds)
            ->whereNull('source_blueprint_id')
            ->select(['id', 'blueprint_id', 'name', 'full_path', 'parent_id', 'data_type', 'cardinality', 'is_indexed', 'sort_order', 'validation_rules'])
            ->orderByRaw('LENGTH(full_path), full_path')
            ->get()
            ->groupBy('blueprint_id');

        // Хранить как Collection для поддержки методов коллекции
        return new DependencyGraph(
            paths: $paths->map(fn($group) => $group->values())->all(),
            embeds: $embedsCache,
        );
    }
}

