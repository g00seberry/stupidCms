<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\ReindexBlueprintEntries;
use App\Models\Path;

class PathObserver
{
    /**
     * При изменении Path в компоненте — синхронизировать материализованные копии.
     */
    public function updated(Path $sourcePath): void
    {
        // Только для исходных Paths в компонентах (не материализованных)
        if ($sourcePath->source_component_id === null && $sourcePath->blueprint->isComponent()) {
            $this->syncMaterializedPaths($sourcePath);
        }
    }

    /**
     * При удалении Path — удалить материализованные копии.
     */
    public function deleted(Path $sourcePath): void
    {
        // Только для исходных Paths в компонентах
        if ($sourcePath->source_component_id === null && $sourcePath->blueprint->isComponent()) {
            Path::where('source_path_id', $sourcePath->id)->delete();
        }
    }

    /**
     * Синхронизировать материализованные Paths.
     *
     * @param \App\Models\Path $sourcePath
     */
    private function syncMaterializedPaths(Path $sourcePath): void
    {
        $materializedPaths = Path::where('source_path_id', $sourcePath->id)->get();
        $affectedBlueprintIds = [];

        foreach ($materializedPaths as $matPath) {
            // Получить path_prefix из pivot-таблицы
            $pathPrefix = $matPath->blueprint->components()
                ->where('component_id', $sourcePath->blueprint_id)
                ->first()
                ?->pivot
                ->path_prefix;

            // Подготовить обновления
            $updates = [
                'data_type' => $sourcePath->data_type,
                'cardinality' => $sourcePath->cardinality,
                'is_indexed' => $sourcePath->is_indexed,
                'is_required' => $sourcePath->is_required,
                'ref_target_type' => $sourcePath->ref_target_type,
                'validation_rules' => $sourcePath->validation_rules,
                'ui_options' => $sourcePath->ui_options,
            ];

            // Если изменилось имя или full_path — обновить с префиксом
            if ($sourcePath->wasChanged('name') || $sourcePath->wasChanged('full_path')) {
                $updates['name'] = $sourcePath->name;
                $updates['full_path'] = $pathPrefix . '.' . $sourcePath->full_path;
            }

            $matPath->update($updates);

            // Инвалидация кеша Blueprint
            $matPath->blueprint->invalidatePathsCache();

            // Пометить Blueprint для реиндексации
            $matPath->blueprint->touch();

            // Собираем ID Blueprint'ов для реиндексации
            $affectedBlueprintIds[] = $matPath->blueprint_id;
        }

        // Диспатчим джобы для реиндексации, если нужно
        if ($this->requiresReindexing($sourcePath)) {
            $uniqueBlueprintIds = array_unique($affectedBlueprintIds);

            foreach ($uniqueBlueprintIds as $blueprintId) {
                // Постановка в очередь для асинхронной реиндексации
                dispatch(new ReindexBlueprintEntries($blueprintId));
            }
        }
    }

    /**
     * Определить, требуется ли реиндексация entries.
     *
     * Реиндексация нужна при изменении:
     * - data_type (меняется value_* поле)
     * - cardinality (меняется логика индексации)
     * - is_indexed (поле может стать индексируемым/неиндексируемым)
     * - full_path (меняется ключ поиска)
     *
     * @param \App\Models\Path $sourcePath
     * @return bool
     */
    private function requiresReindexing(Path $sourcePath): bool
    {
        return $sourcePath->wasChanged([
            'data_type',
            'cardinality',
            'is_indexed',
            'full_path',
        ]);
    }
}

