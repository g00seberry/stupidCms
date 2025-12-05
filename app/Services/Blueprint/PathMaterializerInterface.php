<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Models\Blueprint;
use App\Models\BlueprintEmbed;

/**
 * Интерфейс для материализации путей blueprint'а.
 *
 * Копирует структуру путей из source blueprint в host blueprint,
 * выполняя batch insert/update для оптимизации производительности.
 */
interface PathMaterializerInterface
{
    /**
     * Скопировать пути из source blueprint в host blueprint.
     *
     * Выполняет:
     * 1. Построение структуры путей с учётом baseParentPath
     * 2. Batch insert всех путей
     * 3. Batch update parent_id через CASE WHEN
     * 4. Возврат карт соответствия (idMap, pathMap)
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
    ): array;
}

