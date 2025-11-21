<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Exceptions\Blueprint\HostPathCopyNotFoundException;
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
 *
 * Использует:
 * - BlueprintDependencyGraphLoader для предзагрузки графа зависимостей
 * - PathMaterializer для копирования путей с batch insert/update
 *
 * Оптимизации производительности:
 * - Предзагрузка всего графа зависимостей одним набором запросов
 * - Batch insert для всех путей одного уровня (вместо N отдельных INSERT)
 * - Batch update parent_id через CASE WHEN (вместо N отдельных UPDATE)
 * - Оптимизированное получение ID после batch insert через индекс + время
 * - Chunk для больших batch insert (защита от max_allowed_packet в MySQL)
 *
 * @see docs/data-core/blueprint-materialization-optimization.md
 */
class MaterializationService
{
    /**
     * Кеш предзагруженного графа зависимостей.
     *
     * @var DependencyGraph|null
     */
    private ?DependencyGraph $graphCache = null;

    /**
     * @param PathConflictValidator $conflictValidator Валидатор конфликтов путей
     * @param BlueprintDependencyGraphLoaderInterface $graphLoader Загрузчик графа зависимостей
     * @param PathMaterializerInterface $pathMaterializer Материализатор путей
     * @param int $maxEmbedDepth Максимальная глубина вложенности (по умолчанию из конфига)
     */
    public function __construct(
        private readonly PathConflictValidator $conflictValidator,
        private readonly BlueprintDependencyGraphLoaderInterface $graphLoader,
        private readonly PathMaterializerInterface $pathMaterializer,
        private readonly int $maxEmbedDepth = 5
    ) {
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

            // 3. Предзагрузить весь граф зависимостей
            $this->graphCache = $this->graphLoader->load($embeddedBlueprint, $this->maxEmbedDepth);

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
     * @param Blueprint $blueprint Исходный blueprint
     * @param Blueprint $hostBlueprint Целевой blueprint
     * @param int|null $baseParentId ID родительского path в host
     * @param string|null $baseParentPath full_path родителя в host
     * @param BlueprintEmbed $rootEmbed Корневой embed (для blueprint_embed_id)
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
        // Защита от переполнения стека
        if ($depth >= $this->maxEmbedDepth) {
            throw MaxDepthExceededException::create($this->maxEmbedDepth);
        }

        // 1. Скопировать пути через PathMaterializer
        ['idMap' => $idMap, 'pathMap' => $pathMap] = $this->pathMaterializer->copyPaths(
            sourceBlueprint: $blueprint,
            hostBlueprint: $hostBlueprint,
            baseParentId: $baseParentId,
            baseParentPath: $baseParentPath,
            rootEmbed: $rootEmbed,
            graphCache: $this->graphCache
        );

        // 2. Рекурсивно развернуть внутренние embeds
        $innerEmbeds = $this->getInnerEmbeds($blueprint);

        foreach ($innerEmbeds as $innerEmbed) {
            /** @var BlueprintEmbed $innerEmbed */
            $innerHostPath = $innerEmbed->hostPath;

            if ($innerHostPath) {
                // Embed привязан к полю → найти копию этого поля
                $sourceHostId = $innerHostPath->id;

                if (!isset($idMap[$sourceHostId])) {
                    throw HostPathCopyNotFoundException::forEmbed($innerEmbed->id);
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
     * Получить внутренние embeds blueprint'а.
     *
     * Использует кеш графа зависимостей, если доступен.
     *
     * @param Blueprint $blueprint
     * @return Collection<BlueprintEmbed>
     */
    private function getInnerEmbeds(Blueprint $blueprint): Collection
    {
        if ($this->graphCache !== null) {
            $cachedEmbeds = $this->graphCache->getEmbeds($blueprint->id);
            if ($cachedEmbeds !== null) {
                // Убедиться, что это Collection
                return is_array($cachedEmbeds) ? collect($cachedEmbeds) : $cachedEmbeds;
            }
        }

        // Fallback: загрузить через запрос
        return $blueprint->embeds()
            ->with(['hostPath', 'embeddedBlueprint'])
            ->get();
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
