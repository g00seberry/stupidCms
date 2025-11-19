<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\ReindexBlueprintEntries;
use App\Models\Path;

class PathObserver
{
    /**
     * При создании Path с data_type='blueprint' — материализовать вложенные поля.
     *
     * @param \App\Models\Path $path
     */
    public function created(Path $path): void
    {
        if ($path->isEmbeddedBlueprint() && $path->embedded_blueprint_id) {
            $path->blueprint->materializeEmbeddedBlueprint($path);
            
            // Реиндексация Entry при необходимости
            if ($path->blueprint->entries()->exists()) {
                dispatch(new ReindexBlueprintEntries($path->blueprint_id));
            }
        }
    }

    /**
     * При изменении Path — синхронизировать зависимые материализованные копии.
     *
     * @param \App\Models\Path $sourcePath
     */
    public function updated(Path $sourcePath): void
    {
        // 1) Если это Path в компоненте → синхронизировать все его материализации
        if ($sourcePath->source_component_id === null && $sourcePath->blueprint->isComponent()) {
            $this->syncMaterializedPaths($sourcePath);
        }

        // 2) Если это поле-типа blueprint, и поменялся full_path или embedded_blueprint_id
        if ($sourcePath->isEmbeddedBlueprint() && $sourcePath->wasChanged(['full_path', 'embedded_blueprint_id'])) {
            $sourcePath->blueprint->materializeEmbeddedBlueprint($sourcePath);
            dispatch(new ReindexBlueprintEntries($sourcePath->blueprint_id));
        }
    }

    /**
     * При удалении Path — удалить зависимые материализованные копии.
     *
     * @param \App\Models\Path $sourcePath
     */
    public function deleted(Path $sourcePath): void
    {
        // 1) При удалении embedded-поля удаляем и его материализацию
        if ($sourcePath->isEmbeddedBlueprint()) {
            Path::where('embedded_root_path_id', $sourcePath->id)->delete();
            $sourcePath->blueprint->invalidatePathsCache();
            
            if ($sourcePath->blueprint->entries()->exists()) {
                dispatch(new ReindexBlueprintEntries($sourcePath->blueprint_id));
            }
        }

        // 2) Если это исходный Path в компоненте → удалить все его материализации
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
            // Получить префикс из корневого Path (embedded field)
            if (!$matPath->embedded_root_path_id) {
                continue;
            }

            $rootField = Path::find($matPath->embedded_root_path_id);
            if (!$rootField) {
                continue;
            }

            $pathPrefix = $rootField->full_path;

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

