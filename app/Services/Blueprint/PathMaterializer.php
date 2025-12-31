<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Exceptions\Blueprint\InconsistentBlueprintPathsException;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Реализация материализатора путей blueprint'а.
 *
 * Копирует структуру путей из source blueprint в host blueprint,
 * выполняя batch insert/update для оптимизации производительности.
 */
final class PathMaterializer implements PathMaterializerInterface
{
    /**
     * @param int $batchInsertSize Размер batch для вставки путей
     * @param \App\Services\Path\Constraints\PathConstraintsBuilderRegistry $constraintsBuilderRegistry Регистр билдеров constraints
     */
    public function __construct(
        private readonly int $batchInsertSize = 500,
        private readonly \App\Services\Path\Constraints\PathConstraintsBuilderRegistry $constraintsBuilderRegistry
    ) {}

    /**
     * Скопировать пути из source blueprint в host blueprint.
     *
     * Выполняет:
     * 1. Построение структуры путей с учётом baseParentPath
     * 2. Batch insert всех путей
     * 3. Batch update parent_id через CASE WHEN
     * 4. Копирование constraints для всех поддерживаемых типов полей
     * 5. Возврат карт соответствия (idMap, pathMap)
     *
     * @param Blueprint $sourceBlueprint Исходный blueprint
     * @param Blueprint $hostBlueprint Целевой blueprint
     * @param int|null $baseParentId ID родительского path в host
     * @param string|null $baseParentPath full_path родителя в host
     * @param BlueprintEmbed $rootEmbed Корневой embed (для blueprint_embed_id)
     * @param DependencyGraph|null $graphCache Кеш графа зависимостей (опционально)
     * @return array{idMap: array<int, int>, pathMap: array<int, string>}
     *         idMap: source_path_id => copy_path_id
     *         pathMap: source_path_id => copy_full_path
     */
    public function copyPaths(
        Blueprint $sourceBlueprint,
        Blueprint $hostBlueprint,
        ?int $baseParentId,
        ?string $baseParentPath,
        BlueprintEmbed $rootEmbed,
        ?DependencyGraph $graphCache = null
    ): array {
        // Получить собственные поля blueprint (без source_blueprint_id)
        $sourcePaths = $this->getSourcePaths($sourceBlueprint, $graphCache);

        // Инициализировать карты
        $idMap = [];
        $pathMap = [];

        if ($sourcePaths->isEmpty()) {
            return ['idMap' => $idMap, 'pathMap' => $pathMap];
        }

        // Построить структуру для вставки
        [$pathsToInsert, $tempPathMap, $parentIdMap] = $this->buildPathStructure(
            $sourcePaths,
            $baseParentId,
            $baseParentPath,
            $sourceBlueprint,
            $hostBlueprint,
            $rootEmbed
        );

        // Batch insert всех путей
        $insertedPaths = $this->batchInsertPaths(
            $pathsToInsert,
            $sourcePaths,
            $tempPathMap,
            $hostBlueprint,
            $rootEmbed,
            $sourceBlueprint
        );

        // Построить idMap и pathMap с реальными ID
        foreach ($sourcePaths as $source) {
            $fullPath = $tempPathMap[$source->id];
            $insertedPath = $insertedPaths->firstWhere('full_path', $fullPath);

            if ($insertedPath) {
                $idMap[$source->id] = $insertedPath->id;
                $pathMap[$source->id] = $insertedPath->full_path;
            }
        }

        // Batch update parent_id через CASE WHEN
        $this->bulkUpdateParentIds($parentIdMap, $idMap, $insertedPaths);

        // Копировать constraints для всех поддерживаемых типов полей
        $this->copyAllConstraints($sourcePaths, $idMap);

        return ['idMap' => $idMap, 'pathMap' => $pathMap];
    }

    /**
     * Получить собственные поля blueprint.
     *
     * @param Blueprint $blueprint
     * @param DependencyGraph|null $graphCache
     * @return Collection<Path>
     */
    private function getSourcePaths(Blueprint $blueprint, ?DependencyGraph $graphCache): Collection
    {
        if ($graphCache !== null) {
            $cachedPaths = $graphCache->getPaths($blueprint->id);
            if ($cachedPaths !== null) {
                // Пересортировать для гарантии порядка (родители раньше детей)
                return $cachedPaths->sortBy(function ($path) {
                    return sprintf('%05d%s', strlen($path->full_path), $path->full_path);
                })->values();
            }
        }

        // Fallback: загрузить через запрос
        $relationsToLoad = $this->getConstraintsRelationsToLoad();
        
        $query = $blueprint->paths()
            ->whereNull('source_blueprint_id')
            ->select(['id', 'name', 'full_path', 'parent_id', 'data_type', 'cardinality', 'is_indexed', 'sort_order', 'validation_rules']);
        
        if (!empty($relationsToLoad)) {
            $query->with($relationsToLoad);
        }
        
        return $query->orderByRaw('LENGTH(full_path), full_path')
            ->get();
    }

    /**
     * Построить структуру путей для вставки.
     *
     * @param Collection<Path> $sourcePaths
     * @param int|null $baseParentId
     * @param string|null $baseParentPath
     * @param Blueprint $sourceBlueprint
     * @param Blueprint $hostBlueprint
     * @param BlueprintEmbed $rootEmbed
     * @return array{0: array<array<string, mixed>>, 1: array<int, string>, 2: array<int, int>}
     */
    private function buildPathStructure(
        Collection $sourcePaths,
        ?int $baseParentId,
        ?string $baseParentPath,
        Blueprint $sourceBlueprint,
        Blueprint $hostBlueprint,
        BlueprintEmbed $rootEmbed
    ): array {
        $pathsToInsert = [];
        $tempPathMap = []; // source_id => full_path
        $parentIdMap = []; // source_id => source_parent_id
        $now = now();

        foreach ($sourcePaths as $source) {
            // Вычислить parent_path и full_path
            if ($source->parent_id === null) {
                $parentPath = $baseParentPath;
                $parentId = $baseParentId;
            } else {
                $parentPath = $tempPathMap[$source->parent_id] ?? null;
                if ($parentPath === null) {
                    throw InconsistentBlueprintPathsException::forParentNotFound(
                        $source->id,
                        $source->name,
                        $source->parent_id
                    );
                }
                $parentId = null; // Будет установлен после получения ID родителя
                $parentIdMap[$source->id] = $source->parent_id;
            }

            $fullPath = $parentPath
                ? $parentPath . '.' . $source->name
                : $source->name;

            $tempPathMap[$source->id] = $fullPath;

            // Подготовить данные для вставки
            // validation_rules уже содержит 'required', поэтому is_required не нужен
            // Сериализуем validation_rules в JSON для batch insert
            $pathsToInsert[] = [
                'blueprint_id' => $hostBlueprint->id,
                'source_blueprint_id' => $sourceBlueprint->id,
                'blueprint_embed_id' => $rootEmbed->id,
                'parent_id' => $parentId,
                'name' => $source->name,
                'full_path' => $fullPath,
                'data_type' => $source->data_type,
                'cardinality' => $source->cardinality,
                'is_indexed' => $source->is_indexed,
                'is_readonly' => true,
                'sort_order' => $source->sort_order,
                'validation_rules' => $source->validation_rules !== null ? json_encode($source->validation_rules) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return [$pathsToInsert, $tempPathMap, $parentIdMap];
    }

    /**
     * Выполнить batch insert путей.
     *
     * @param array<array<string, mixed>> $pathsToInsert
     * @param Collection<Path> $sourcePaths
     * @param array<int, string> $tempPathMap
     * @param Blueprint $hostBlueprint
     * @param BlueprintEmbed $rootEmbed
     * @param Blueprint $sourceBlueprint
     * @return Collection<Path>
     */
    private function batchInsertPaths(
        array $pathsToInsert,
        Collection $sourcePaths,
        array $tempPathMap,
        Blueprint $hostBlueprint,
        BlueprintEmbed $rootEmbed,
        Blueprint $sourceBlueprint
    ): Collection {
        if (empty($pathsToInsert)) {
            return collect();
        }

        // Получить максимальный ID перед вставкой для более надежной выборки вставленных путей
        // Это снижает риск захватить чужие записи, если кто-то ещё пишет в таблицу
        $maxIdBefore = Path::max('id') ?? 0;
        $now = now();

        if (count($pathsToInsert) > $this->batchInsertSize) {
            // Разбить на chunks для защиты от max_allowed_packet в MySQL
            $insertedPaths = collect();

            foreach (array_chunk($pathsToInsert, $this->batchInsertSize) as $chunk) {
                Path::insert($chunk);

                // Получить ID вставленных путей через индекс + maxIdBefore для надежности
                $chunkPaths = Path::query()
                    ->where('blueprint_id', $hostBlueprint->id)
                    ->where('blueprint_embed_id', $rootEmbed->id)
                    ->where('source_blueprint_id', $sourceBlueprint->id)
                    ->whereIn('full_path', array_column($chunk, 'full_path'))
                    ->where('id', '>', $maxIdBefore)
                    ->whereBetween('created_at', [
                        $now->copy()->subSecond(),
                        $now->copy()->addSecond()
                    ])
                    ->get();

                $insertedPaths = $insertedPaths->merge($chunkPaths);
                
                // Обновить maxIdBefore для следующего chunk
                $maxIdBefore = max($maxIdBefore, $insertedPaths->max('id') ?? $maxIdBefore);
            }

            return $insertedPaths;
        } else {
            // Обычная вставка для малого batch
            Path::insert($pathsToInsert);

            return Path::query()
                ->where('blueprint_id', $hostBlueprint->id)
                ->where('blueprint_embed_id', $rootEmbed->id)
                ->where('source_blueprint_id', $sourceBlueprint->id)
                ->whereIn('full_path', array_column($pathsToInsert, 'full_path'))
                ->where('id', '>', $maxIdBefore)
                ->whereBetween('created_at', [
                    $now->copy()->subSecond(),
                    $now->copy()->addSecond()
                ])
                ->get();
        }
    }

    /**
     * Выполнить batch update parent_id через CASE WHEN для снижения числа SQL-запросов.
     *
     * Обновляет parent_id для всех дочерних путей одним запросом вместо N отдельных UPDATE.
     *
     * @param array<int, int> $parentIdMap source_id => source_parent_id
     * @param array<int, int> $idMap source_id => copy_id
     * @param Collection<Path> $insertedPaths
     * @return void
     */
    private function bulkUpdateParentIds(
        array $parentIdMap,
        array $idMap,
        Collection $insertedPaths
    ): void {
        $updates = [];
        foreach ($parentIdMap as $sourceId => $sourceParentId) {
            if (isset($idMap[$sourceParentId]) && isset($idMap[$sourceId])) {
                $updates[$idMap[$sourceId]] = $idMap[$sourceParentId];
            }
        }

        if (empty($updates)) {
            return;
        }

        $cases = [];
        $bindings = [];
        $pathIds = array_keys($updates);

        foreach ($updates as $pathId => $parentId) {
            $cases[] = "WHEN ? THEN ?";
            $bindings[] = $pathId;
            $bindings[] = $parentId;
        }

        $bindings = array_merge($bindings, $pathIds);

        DB::statement(
            "UPDATE paths SET parent_id = CASE id " . implode(' ', $cases) . " END WHERE id IN (" . implode(',', array_fill(0, count($pathIds), '?')) . ")",
            $bindings
        );

        // Обновить в коллекции для последующего использования
        foreach ($updates as $pathId => $parentId) {
            $insertedPath = $insertedPaths->firstWhere('id', $pathId);
            if ($insertedPath) {
                $insertedPath->parent_id = $parentId;
            }
        }
    }

    /**
     * Получить список имён связей для eager loading constraints.
     *
     * Собирает имена связей из всех зарегистрированных билдеров constraints
     * для универсальной загрузки через with().
     *
     * @return array<string> Массив имён связей (например, ['refConstraints'])
     */
    private function getConstraintsRelationsToLoad(): array
    {
        $relationsToLoad = [];

        foreach ($this->constraintsBuilderRegistry->getAllBuilders() as $builder) {
            $relationName = $builder->getRelationName();
            if ($relationName !== '') {
                $relationsToLoad[] = $relationName;
            }
        }

        return $relationsToLoad;
    }

    /**
     * Скопировать все constraints из source paths в host paths.
     *
     * Использует регистр билдеров для копирования constraints всех поддерживаемых типов.
     * Каждый билдер самостоятельно обрабатывает копирование constraints для своего типа данных.
     *
     * @param Collection<Path> $sourcePaths Исходные paths с загруженными constraints
     * @param array<int, int> $idMap Карта соответствия source_path_id => copy_path_id
     * @return void
     */
    private function copyAllConstraints(Collection $sourcePaths, array $idMap): void
    {
        foreach ($sourcePaths as $sourcePath) {
            if (!isset($idMap[$sourcePath->id])) {
                continue;
            }

            $builder = $this->constraintsBuilderRegistry->getBuilder($sourcePath->data_type);
            if ($builder !== null) {
                $builder->copyConstraints(
                    $sourcePath,
                    $idMap[$sourcePath->id],
                    $this->batchInsertSize
                );
            }
        }
    }
}

