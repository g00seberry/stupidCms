<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Exceptions\Blueprint\PathConflictException;
use App\Models\Blueprint;
use App\Models\Path;

/**
 * Валидатор конфликтов full_path при материализации.
 *
 * PRE-CHECK: проверяет конфликты ДО начала копирования.
 */
class PathConflictValidator
{
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
        // 1. Собрать все будущие пути (включая транзитивные)
        $futurePaths = $this->collectFuturePathsRecursive(
            $embeddedBlueprint,
            $baseParentPath
        );

        // 2. Проверить пересечения с существующими путями
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
     * Рекурсивно собрать все full_path, которые появятся при материализации.
     *
     * @param Blueprint $blueprint
     * @param string|null $baseParentPath
     * @param int $depth Текущая глубина рекурсии
     * @return array<string>
     */
    private function collectFuturePathsRecursive(
        Blueprint $blueprint,
        ?string $baseParentPath,
        int $depth = 0
    ): array {
        // Защита от слишком глубокой вложенности
        if ($depth > 10) {
            return [];
        }

        $paths = [];

        // Собрать собственные поля (без source_blueprint_id)
        $ownPaths = $blueprint->paths()
            ->whereNull('source_blueprint_id')
            ->get(['name', 'full_path', 'id']);

        // Создать map: id → name для быстрого поиска
        $pathNames = $ownPaths->pluck('name', 'id')->all();

        foreach ($ownPaths as $path) {
            $futureFullPath = $baseParentPath
                ? $baseParentPath . '.' . $path->name
                : $path->name;

            $paths[] = $futureFullPath;
        }

        // Рекурсивно обойти внутренние embeds
        $innerEmbeds = $blueprint->embeds()->with('hostPath', 'embeddedBlueprint')->get();

        foreach ($innerEmbeds as $innerEmbed) {
            $innerHostPath = $innerEmbed->hostPath;

            if ($innerHostPath) {
                // Embed под конкретным полем
                $hostPathName = $pathNames[$innerHostPath->id] ?? $innerHostPath->name;
                $newBasePath = $baseParentPath
                    ? $baseParentPath . '.' . $hostPathName
                    : $hostPathName;
            } else {
                // Embed в корень
                $newBasePath = $baseParentPath;
            }

            $childPaths = $this->collectFuturePathsRecursive(
                $innerEmbed->embeddedBlueprint,
                $newBasePath,
                $depth + 1
            );

            $paths = array_merge($paths, $childPaths);
        }

        return $paths;
    }
}

