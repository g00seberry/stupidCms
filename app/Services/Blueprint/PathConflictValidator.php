<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Exceptions\Blueprint\PathConflictException;
use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\Path;
use Illuminate\Support\Collection;

/**
 * Валидатор конфликтов full_path при материализации.
 *
 * PRE-CHECK: проверяет конфликты ДО начала копирования.
 * Оптимизирован для работы с большими графами зависимостей:
 * - Одноразовая загрузка графа с eager loading
 * - Кеширование путей и embeds
 * - Конфигурируемый лимит глубины
 */
class PathConflictValidator
{
    /**
     * Кеш загруженных путей по blueprint_id.
     *
     * @var array<int, Collection<Path>>
     */
    private array $pathsCache = [];

    /**
     * Кеш загруженных embeds по blueprint_id.
     *
     * @var array<int, Collection<BlueprintEmbed>>
     */
    private array $embedsCache = [];

    /**
     * Максимальная глубина для проверки конфликтов.
     *
     * @var int|null
     */
    private ?int $maxDepth = null;

    /**
     * Переопределить максимальную глубину (для тестов).
     *
     * @var int|null
     */
    private ?int $overrideMaxDepth = null;

    /**
     * @param int|null $maxDepth Переопределить максимальную глубину (null = из конфига)
     */
    public function __construct(?int $maxDepth = null)
    {
        $this->overrideMaxDepth = $maxDepth;
    }

    /**
     * Получить максимальную глубину проверки конфликтов.
     *
     * @return int
     */
    private function getMaxDepth(): int
    {
        if ($this->maxDepth !== null) {
            return $this->maxDepth;
        }

        if ($this->overrideMaxDepth !== null) {
            $this->maxDepth = $this->overrideMaxDepth;
            return $this->maxDepth;
        }

        $this->maxDepth = (int) config('blueprint.max_conflict_check_depth', 10);
        return $this->maxDepth;
    }

    /**
     * Проверить, что материализация не создаст конфликтов full_path.
     *
     * @param Blueprint $embeddedBlueprint Кого встраиваем
     * @param Blueprint $hostBlueprint В кого встраиваем
     * @param string|null $baseParentPath Базовый путь (или null для корня)
     * @param int|null $excludeEmbedId ID embed, копии которого будут удалены (для рематериализации)
     * @return void
     * @throws PathConflictException
     */
    public function validateNoConflicts(
        Blueprint $embeddedBlueprint,
        Blueprint $hostBlueprint,
        ?string $baseParentPath,
        ?int $excludeEmbedId = null
    ): void {
        // Сбросить кеш для нового вызова
        $this->resetCache();

        // 1. Загрузить весь граф зависимостей одним запросом
        $graph = $this->loadDependencyGraph($embeddedBlueprint);

        // 2. Собрать все будущие пути (включая транзитивные) без рекурсивных запросов
        $futurePaths = $this->collectFuturePathsFromGraph($graph, $embeddedBlueprint->id, $baseParentPath);

        // 3. Проверить пересечения с существующими путями
        $query = Path::query()
            ->where('blueprint_id', $hostBlueprint->id)
            ->whereIn('full_path', $futurePaths);

        // Исключить пути, которые будут удалены (для рематериализации)
        if ($excludeEmbedId !== null) {
            $query->where(function ($q) use ($excludeEmbedId) {
                $q->whereNull('blueprint_embed_id')
                    ->orWhere('blueprint_embed_id', '!=', $excludeEmbedId);
            });
        } else {
            // При первой материализации исключаем копии (только собственные пути host blueprint)
            $query->whereNull('blueprint_embed_id');
        }

        $existingPaths = $query->pluck('full_path')->all();

        if (!empty($existingPaths)) {
            throw PathConflictException::create(
                $hostBlueprint->code,
                $embeddedBlueprint->code,
                $existingPaths
            );
        }
    }

    /**
     * Загрузить весь граф зависимостей с eager loading.
     *
     * Загружает все blueprint'ы, paths и embeds транзитивно связанные
     * с корневым blueprint одним набором запросов.
     *
     * @param Blueprint $rootBlueprint Корневой blueprint
     * @return array{blueprints: Collection<Blueprint>, paths: Collection<Path>, embeds: Collection<BlueprintEmbed>}
     */
    private function loadDependencyGraph(Blueprint $rootBlueprint): array
    {
        $visited = [];
        $blueprintIds = [$rootBlueprint->id];
        $depth = 0;

        // BFS: собрать все ID blueprint'ов в графе
        while (!empty($blueprintIds) && $depth < $this->getMaxDepth()) {
            $currentIds = $blueprintIds;
            $blueprintIds = [];

            foreach ($currentIds as $blueprintId) {
                if (isset($visited[$blueprintId])) {
                    continue;
                }
                $visited[$blueprintId] = true;

                // Загрузить embeds этого blueprint'а
                $embeds = BlueprintEmbed::query()
                    ->where('blueprint_id', $blueprintId)
                    ->with(['embeddedBlueprint', 'hostPath'])
                    ->get();

                $this->embedsCache[$blueprintId] = $embeds;

                // Собрать ID встроенных blueprint'ов для следующей итерации
                foreach ($embeds as $embed) {
                    $embeddedId = $embed->embedded_blueprint_id;
                    if (!isset($visited[$embeddedId])) {
                        $blueprintIds[] = $embeddedId;
                    }
                }
            }

            $depth++;
        }

        // Загрузить все paths для всех blueprint'ов одним запросом
        $allBlueprintIds = array_keys($visited);
        $paths = Path::query()
            ->whereIn('blueprint_id', $allBlueprintIds)
            ->whereNull('source_blueprint_id') // Только собственные пути
            ->get(['id', 'blueprint_id', 'name', 'full_path', 'parent_id']);

        // Сгруппировать paths по blueprint_id для быстрого доступа
        foreach ($paths->groupBy('blueprint_id') as $bpId => $bpPaths) {
            $this->pathsCache[$bpId] = $bpPaths;
        }

        // Загрузить все blueprint'ы одним запросом
        $blueprints = Blueprint::query()
            ->whereIn('id', $allBlueprintIds)
            ->get();

        return [
            'blueprints' => $blueprints->keyBy('id'),
            'paths' => $paths->keyBy('id'),
            'embeds' => collect($this->embedsCache)->flatten(),
        ];
    }

    /**
     * Собрать все будущие пути из загруженного графа.
     *
     * Использует закешированные данные вместо запросов к БД.
     *
     * @param array{blueprints: Collection<Blueprint>, paths: Collection<Path>, embeds: Collection<BlueprintEmbed>} $graph Загруженный граф
     * @param int $rootBlueprintId ID корневого blueprint'а
     * @param string|null $baseParentPath Базовый путь
     * @return array<string>
     */
    private function collectFuturePathsFromGraph(
        array $graph,
        int $rootBlueprintId,
        ?string $baseParentPath
    ): array {
        $paths = [];
        $visited = [];
        $queue = [[$rootBlueprintId, $baseParentPath, 0]]; // [blueprint_id, base_path, depth]

        while (!empty($queue)) {
            [$blueprintId, $basePath, $depth] = array_shift($queue);

            $basePathKey = $basePath ?? '';
            if ($depth >= $this->getMaxDepth() || isset($visited[$blueprintId][$basePathKey])) {
                continue;
            }
            $visited[$blueprintId][$basePathKey] = true;

            // Получить собственные пути этого blueprint'а из кеша
            $ownPaths = $this->pathsCache[$blueprintId] ?? collect();
            $pathNames = $ownPaths->pluck('name', 'id')->all();

            // Добавить будущие пути
            foreach ($ownPaths as $path) {
                $futureFullPath = $basePath
                    ? $basePath . '.' . $path->name
                    : $path->name;
                $paths[] = $futureFullPath;
            }

            // Обработать embeds этого blueprint'а из кеша
            $embeds = $this->embedsCache[$blueprintId] ?? collect();

            foreach ($embeds as $embed) {
                $hostPath = $embed->hostPath;

                if ($hostPath) {
                    // Embed под конкретным полем
                    $hostPathName = $pathNames[$hostPath->id] ?? $hostPath->name;
                    $newBasePath = $basePath
                        ? $basePath . '.' . $hostPathName
                        : $hostPathName;
                } else {
                    // Embed в корень
                    $newBasePath = $basePath;
                }

                // Добавить в очередь для обработки
                $queue[] = [$embed->embedded_blueprint_id, $newBasePath, $depth + 1];
            }
        }

        return array_unique($paths);
    }

    /**
     * Сбросить кеш для нового вызова.
     *
     * @return void
     */
    private function resetCache(): void
    {
        $this->pathsCache = [];
        $this->embedsCache = [];
    }
}

