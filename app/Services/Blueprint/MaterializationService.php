<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Exceptions\Blueprint\MaxDepthExceededException;
use App\Exceptions\Blueprint\PathConflictException;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Сервис рекурсивной материализации встраиваний.
 *
 * Копирует структуру embedded blueprint в host blueprint,
 * включая все транзитивные встраивания.
 * Использует конфигурационные параметры из config/blueprint.php.
 *
 * Оптимизации производительности:
 * - Предзагрузка всего графа зависимостей одним набором запросов (уровень 2)
 * - Batch insert для всех путей одного уровня (вместо N отдельных INSERT)
 * - Batch update parent_id через CASE WHEN (вместо N отдельных UPDATE)
 * - Оптимизированное получение ID после batch insert через индекс + время (уровень 3)
 * - Объединение проходов по sourcePaths (уровень 4)
 * - Chunk для больших batch insert (защита от max_allowed_packet в MySQL) (уровень 6)
 *
 * @see docs/data-core/blueprint-materialization-optimization.md
 */
class MaterializationService
{
    /**
     * Максимальная глубина вложенности встраиваний.
     *
     * Читается из конфига config('blueprint.max_embed_depth').
     * По умолчанию: 5.
     *
     * @var int|null
     */
    private ?int $maxEmbedDepth = null;

    /**
     * Переопределить максимальную глубину (для тестов).
     *
     * @var int|null
     */
    private ?int $overrideMaxDepth = null;

    /**
     * Кеш предзагруженного графа зависимостей.
     *
     * @var array{paths: array<int, Collection>, embeds: array<int, Collection>}|null
     */
    private ?array $graphCache = null;

    /**
     * @param PathConflictValidator $conflictValidator
     * @param int|null $maxDepth Переопределить максимальную глубину (null = из конфига)
     */
    public function __construct(
        private readonly PathConflictValidator $conflictValidator,
        ?int $maxDepth = null
    ) {
        $this->overrideMaxDepth = $maxDepth;
    }

    /**
     * Получить максимальную глубину вложенности.
     *
     * @return int
     */
    private function getMaxEmbedDepth(): int
    {
        if ($this->maxEmbedDepth !== null) {
            return $this->maxEmbedDepth;
        }

        if ($this->overrideMaxDepth !== null) {
            $this->maxEmbedDepth = $this->overrideMaxDepth;
            return $this->maxEmbedDepth;
        }

        $this->maxEmbedDepth = (int) config('blueprint.max_embed_depth', 5);
        return $this->maxEmbedDepth;
    }

    /**
     * Материализовать встраивание со всеми транзитивными зависимостями.
     *
     * Синхронная операция в рамках DB::transaction.
     *
     * @param BlueprintEmbed $embed Встраивание для материализации
     * @return void
     * @throws PathConflictException
     * @throws MaxDepthExceededException
     */
    public function materialize(BlueprintEmbed $embed): void
    {
        // Загрузить связи для работы
        $embed->load(['blueprint', 'embeddedBlueprint', 'hostPath']);

        $hostBlueprint = $embed->blueprint;
        $embeddedBlueprint = $embed->embeddedBlueprint;
        $hostPath = $embed->hostPath;

        DB::transaction(function () use ($embed, $hostBlueprint, $embeddedBlueprint, $hostPath) {
            $baseParentId = $hostPath?->id;
            $baseParentPath = $hostPath?->full_path;

            // 1. PRE-CHECK: проверка конфликтов full_path
            // Передаём ID embed для исключения его копий при рематериализации
            $this->conflictValidator->validateNoConflicts(
                $embeddedBlueprint,
                $hostBlueprint,
                $baseParentPath,
                $embed->id
            );

            // 2. Удалить старые копии (включая транзитивные)
            Path::where('blueprint_embed_id', $embed->id)->delete();

            // 3. Предзагрузить весь граф зависимостей (оптимизация уровня 2)
            $this->graphCache = $this->preloadDependencyGraph($embeddedBlueprint);

            // 4. Рекурсивно скопировать структуру с использованием кеша
            $this->copyBlueprintRecursive(
                blueprint: $embeddedBlueprint,
                hostBlueprint: $hostBlueprint,
                baseParentId: $baseParentId,
                baseParentPath: $baseParentPath,
                rootEmbed: $embed,
                depth: 0
            );

            // Очистить кеш после завершения
            $this->graphCache = null;
        });
    }

    /**
     * Рекурсивно скопировать структуру blueprint (включая транзитивные embeds).
     *
     * Использует batch insert для оптимизации производительности:
     * все пути одного уровня вставляются одним запросом вместо N отдельных INSERT.
     *
     * @param Blueprint $blueprint Исходный blueprint (A, C, D, ...)
     * @param Blueprint $hostBlueprint Целевой blueprint (B)
     * @param int|null $baseParentId ID родительского path в B
     * @param string|null $baseParentPath full_path родителя в B
     * @param BlueprintEmbed $rootEmbed Корневой embed B→A (для blueprint_embed_id)
     * @param int $depth Текущая глубина рекурсии
     * @return void
     * @throws MaxDepthExceededException
     */
    private function copyBlueprintRecursive(
        Blueprint $blueprint,
        Blueprint $hostBlueprint,
        ?int $baseParentId,
        ?string $baseParentPath,
        BlueprintEmbed $rootEmbed,
        int $depth
    ): void {
        // Защита от переполнения стека (лимит из конфига)
        $maxDepth = $this->getMaxEmbedDepth();
        if ($depth >= $maxDepth) {
            throw MaxDepthExceededException::create($maxDepth);
        }

        // 1. Получить собственные поля blueprint (без source_blueprint_id)
        // Использовать кеш, если доступен (оптимизация уровня 2)
        if ($this->graphCache !== null && isset($this->graphCache['paths'][$blueprint->id])) {
            // Кеш уже содержит отсортированные пути, но пересортируем для гарантии
            // Используем исходный full_path для сортировки (родители раньше детей)
            $sourcePaths = $this->graphCache['paths'][$blueprint->id]
                ->sortBy(function ($path) {
                    // Сортировка по длине full_path, затем по самому full_path
                    // Это гарантирует, что родители (короткие пути) обрабатываются раньше детей
                    return sprintf('%05d%s', strlen($path->full_path), $path->full_path);
                })
                ->values();
        } else {
            // Fallback: загрузить через запрос (оптимизация уровня 9: select только нужные поля)
            $sourcePaths = $blueprint->paths()
                ->whereNull('source_blueprint_id')
                ->select(['id', 'name', 'full_path', 'parent_id', 'data_type', 'cardinality', 'is_required', 'is_indexed', 'sort_order', 'validation_rules'])
                ->orderByRaw('LENGTH(full_path), full_path') // родители раньше детей
                ->get();
        }

        // 2. Карта соответствия: source path id → copy (id, full_path)
        // Инициализировать заранее для использования в обоих ветвях (chunked и обычная вставка)
        $idMap = [];
        $pathMap = [];

        // Оптимизация уровня 4: Объединение проходов - один проход вместо двух
        $pathsToInsert = [];
        $tempPathMap = []; // id => full_path
        $parentIdMap = []; // id => source_parent_id (для обновления после batch insert)
        $now = now();

        foreach ($sourcePaths as $source) {
            // Вычислить parent_path и full_path
            if ($source->parent_id === null) {
                $parentPath = $baseParentPath;
                $parentId = $baseParentId;
            } else {
                $parentPath = $tempPathMap[$source->parent_id] ?? null;
                if ($parentPath === null) {
                    // Родитель еще не обработан - это ошибка сортировки
                    throw new \LogicException(
                        "Parent path for source path ID {$source->id} (name: {$source->name}, parent_id: {$source->parent_id}) not found in tempPathMap. " .
                        "This indicates paths are not sorted correctly (parents before children)."
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
            $pathsToInsert[] = [
                'blueprint_id' => $hostBlueprint->id,
                'source_blueprint_id' => $blueprint->id,
                'blueprint_embed_id' => $rootEmbed->id,
                'parent_id' => $parentId,
                'name' => $source->name,
                'full_path' => $fullPath,
                'data_type' => $source->data_type,
                'cardinality' => $source->cardinality,
                'is_required' => $source->is_required,
                'is_indexed' => $source->is_indexed,
                'is_readonly' => true,
                'sort_order' => $source->sort_order,
                'validation_rules' => $source->validation_rules ? json_encode($source->validation_rules) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Batch insert всех путей (оптимизация уровня 6: chunk для больших batch)
        $insertedPaths = collect(); // Инициализировать для использования в обоих ветвях
        if (!empty($pathsToInsert)) {
            $batchSize = (int) config('blueprint.batch_insert_size', 500); // Защита от max_allowed_packet в MySQL

            if (count($pathsToInsert) > $batchSize) {
                // Большой batch: разбить на chunks
                // Создать карту full_path => source для быстрого поиска
                $fullPathToSource = [];
                foreach ($sourcePaths as $source) {
                    $fullPath = $tempPathMap[$source->id] ?? null;
                    if ($fullPath) {
                        $fullPathToSource[$fullPath] = $source;
                    }
                }

                foreach (array_chunk($pathsToInsert, $batchSize) as $chunk) {
                    Path::insert($chunk);

                    // Получить ID для этого chunk через индекс (оптимизация уровня 3)
                    $chunkPaths = Path::query()
                        ->where('blueprint_id', $hostBlueprint->id)
                        ->where('blueprint_embed_id', $rootEmbed->id)
                        ->where('source_blueprint_id', $blueprint->id)
                        ->whereIn('full_path', array_column($chunk, 'full_path'))
                        ->whereBetween('created_at', [
                            $now->copy()->subSecond(),
                            $now->copy()->addSecond()
                        ])
                        ->get();

                    // Добавить в общую коллекцию insertedPaths
                    $insertedPaths = $insertedPaths->merge($chunkPaths);

                    // Добавить в общую карту только для paths из текущего chunk
                    foreach ($chunkPaths as $insertedPath) {
                        $source = $fullPathToSource[$insertedPath->full_path] ?? null;
                        if ($source) {
                            $idMap[$source->id] = $insertedPath->id;
                            $pathMap[$source->id] = $insertedPath->full_path;
                        }
                    }
                }
            } else {
                // Малый batch: обычная вставка
                Path::insert($pathsToInsert);

                // Оптимизация уровня 3: получить ID через индекс + временная метка
                $insertedPaths = Path::query()
                    ->where('blueprint_id', $hostBlueprint->id)
                    ->where('blueprint_embed_id', $rootEmbed->id)
                    ->where('source_blueprint_id', $blueprint->id)
                    ->whereIn('full_path', array_column($pathsToInsert, 'full_path'))
                    ->whereBetween('created_at', [
                        $now->copy()->subSecond(),
                        $now->copy()->addSecond()
                    ])
                    ->get();

                // Построить idMap и pathMap с реальными ID
                foreach ($sourcePaths as $source) {
                    $fullPath = $tempPathMap[$source->id];
                    $insertedPath = $insertedPaths->firstWhere('full_path', $fullPath);

                    if ($insertedPath) {
                        $idMap[$source->id] = $insertedPath->id;
                        $pathMap[$source->id] = $insertedPath->full_path;
                    }
                }
            }

            // Обновить parent_id для дочерних путей batch update через CASE WHEN
            $updates = [];
            foreach ($parentIdMap as $sourceId => $sourceParentId) {
                if (isset($idMap[$sourceParentId]) && isset($idMap[$sourceId])) {
                    $updates[$idMap[$sourceId]] = $idMap[$sourceParentId];
                }
            }

            // Batch update через CASE WHEN (один запрос вместо N)
            if (!empty($updates)) {
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
        }

        // 3. Рекурсивно развернуть внутренние embeds
        // Использовать кеш, если доступен (оптимизация уровня 2)
        if ($this->graphCache !== null && isset($this->graphCache['embeds'][$blueprint->id])) {
            $innerEmbeds = $this->graphCache['embeds'][$blueprint->id];
            // Убедиться, что это Collection
            if (is_array($innerEmbeds)) {
                $innerEmbeds = collect($innerEmbeds);
            }
        } else {
            // Fallback: загрузить через запрос
            $innerEmbeds = $blueprint->embeds()
                ->with(['hostPath', 'embeddedBlueprint'])
                ->get();
        }

        foreach ($innerEmbeds as $innerEmbed) {
            /** @var BlueprintEmbed $innerEmbed */
            $innerHostPath = $innerEmbed->hostPath;

            if ($innerHostPath) {
                // Embed привязан к полю → найти копию этого поля
                $sourceHostId = $innerHostPath->id;

                if (!isset($idMap[$sourceHostId])) {
                    // Теоретически не должно случиться
                    throw new \LogicException(
                        "Не найдена копия host_path для embed {$innerEmbed->id}"
                    );
                }

                $childBaseParentId = $idMap[$sourceHostId];
                $childBaseParentPath = $pathMap[$sourceHostId];
            } else {
                // Embed в корень → базовый путь остаётся тем же
                $childBaseParentId = $baseParentId;
                $childBaseParentPath = $baseParentPath;
            }

            $childBlueprint = $innerEmbed->embeddedBlueprint;

            // Рекурсивный вызов
            $this->copyBlueprintRecursive(
                blueprint: $childBlueprint,
                hostBlueprint: $hostBlueprint,
                baseParentId: $childBaseParentId,
                baseParentPath: $childBaseParentPath,
                rootEmbed: $rootEmbed, // НЕ меняется!
                depth: $depth + 1
            );
        }
    }

    /**
     * Рематериализовать все embeds указанного blueprint.
     *
     * Используется при изменении структуры blueprint.
     *
     * @param Blueprint $blueprint
     * @return void
     */
    public function rematerializeAllEmbeds(Blueprint $blueprint): void
    {
        // Найти все места, где blueprint встроен в другие
        $embeds = BlueprintEmbed::query()
            ->where('embedded_blueprint_id', $blueprint->id)
            ->with(['blueprint', 'embeddedBlueprint', 'hostPath'])
            ->get();

        foreach ($embeds as $embed) {
            $this->materialize($embed);
        }
    }

    /**
     * Предзагрузить весь граф зависимостей одним набором запросов (оптимизация уровня 2).
     *
     * Загружает все blueprint'ы, paths и embeds транзитивно связанные
     * с корневым blueprint одним набором запросов.
     *
     * @param Blueprint $rootBlueprint Корневой blueprint
     * @return array{paths: array<int, Collection>, embeds: array<int, Collection>}
     */
    private function preloadDependencyGraph(Blueprint $rootBlueprint): array
    {
        $visited = [];
        $blueprintIds = [$rootBlueprint->id];
        $depth = 0;
        $maxDepth = $this->getMaxEmbedDepth();
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

        // Загрузить все paths одним запросом (оптимизация уровня 9: select только нужные поля)
        $allBlueprintIds = array_keys($visited);
        $paths = Path::query()
            ->whereIn('blueprint_id', $allBlueprintIds)
            ->whereNull('source_blueprint_id')
            ->select(['id', 'blueprint_id', 'name', 'full_path', 'parent_id', 'data_type', 'cardinality', 'is_required', 'is_indexed', 'sort_order', 'validation_rules'])
            ->orderByRaw('LENGTH(full_path), full_path')
            ->get()
            ->groupBy('blueprint_id');

        // Хранить как Collection, а не массив, для поддержки методов коллекции
        return [
            'paths' => $paths->map(fn($group) => $group->values()), // Каждая группа как Collection
            'embeds' => $embedsCache,
        ];
    }
}

