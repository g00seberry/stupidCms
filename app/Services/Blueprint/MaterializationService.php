<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Exceptions\Blueprint\MaxDepthExceededException;
use App\Exceptions\Blueprint\PathConflictException;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use Illuminate\Support\Facades\DB;

/**
 * Сервис рекурсивной материализации встраиваний.
 *
 * Копирует структуру embedded blueprint в host blueprint,
 * включая все транзитивные встраивания.
 * Использует конфигурационные параметры из config/blueprint.php.
 *
 * Оптимизации производительности:
 * - Batch insert для всех путей одного уровня (вместо N отдельных INSERT)
 * - Batch update parent_id через CASE WHEN (вместо N отдельных UPDATE)
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

            // 3. Рекурсивно скопировать структуру
            $this->copyBlueprintRecursive(
                blueprint: $embeddedBlueprint,
                hostBlueprint: $hostBlueprint,
                baseParentId: $baseParentId,
                baseParentPath: $baseParentPath,
                rootEmbed: $embed,
                depth: 0
            );
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
        $sourcePaths = $blueprint->paths()
            ->whereNull('source_blueprint_id')
            ->orderByRaw('LENGTH(full_path), full_path') // родители раньше детей
            ->get();

        // 2. Карта соответствия: source path id → copy (id, full_path)
        $idMap = [];
        $pathMap = [];

        // Собрать все данные для batch insert
        $pathsToInsert = [];
        $now = now();

        // Первый проход: вычислить все full_path (используя временный pathMap)
        $tempPathMap = [];
        foreach ($sourcePaths as $source) {
            // Вычислить parent_id и full_path
            if ($source->parent_id === null) {
                // Поле верхнего уровня → привязать к baseParent
                $parentPath = $baseParentPath;
            } else {
                // Дочернее поле → найти full_path родителя
                $parentPath = $tempPathMap[$source->parent_id] ?? null;
            }

            $fullPath = $parentPath
                ? $parentPath . '.' . $source->name
                : $source->name;

            $tempPathMap[$source->id] = $fullPath;
        }

        // Второй проход: подготовить данные для вставки и построить временный pathMap
        foreach ($sourcePaths as $source) {
            $fullPath = $tempPathMap[$source->id];

            // Вычислить parent_id (будет обновлен после batch insert)
            $parentId = null;
            if ($source->parent_id !== null) {
                // parent_id будет установлен после получения ID родителя
                // Пока оставляем null, обновим после batch insert
            } else {
                $parentId = $baseParentId;
            }

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

        // Batch insert всех путей
        if (!empty($pathsToInsert)) {
            Path::insert($pathsToInsert);

            // Загрузить вставленные пути для получения ID
            $insertedPaths = Path::query()
                ->where('blueprint_id', $hostBlueprint->id)
                ->where('blueprint_embed_id', $rootEmbed->id)
                ->where('source_blueprint_id', $blueprint->id)
                ->whereIn('full_path', array_column($pathsToInsert, 'full_path'))
                ->get()
                ->keyBy('full_path');

            // Построить idMap и pathMap с реальными ID
            foreach ($sourcePaths as $source) {
                $fullPath = $tempPathMap[$source->id];
                $insertedPath = $insertedPaths->get($fullPath);

                if ($insertedPath) {
                    $idMap[$source->id] = $insertedPath->id;
                    $pathMap[$source->id] = $insertedPath->full_path;
                }
            }

            // Обновить parent_id для дочерних путей batch update через CASE WHEN
            $updates = [];
            foreach ($sourcePaths as $source) {
                if ($source->parent_id !== null && isset($idMap[$source->parent_id])) {
                    $fullPath = $tempPathMap[$source->id];
                    $insertedPath = $insertedPaths->get($fullPath);
                    
                    if ($insertedPath && $insertedPath->parent_id !== $idMap[$source->parent_id]) {
                        $updates[$insertedPath->id] = $idMap[$source->parent_id];
                    }
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
        $innerEmbeds = $blueprint->embeds()
            ->with(['hostPath', 'embeddedBlueprint'])
            ->get();

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
}

