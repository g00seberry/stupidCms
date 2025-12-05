<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Models\BlueprintEmbed;
use App\Models\Path;
use Illuminate\Support\Collection;

/**
 * DTO для кеша графа зависимостей blueprint'ов.
 *
 * Хранит предзагруженные paths и embeds для каждого blueprint'а в графе,
 * что позволяет избежать N+1 запросов при материализации.
 *
 * @param array<int, Collection<Path>> $paths Пути по blueprint_id
 * @param array<int, Collection<BlueprintEmbed>> $embeds Embeds по blueprint_id
 */
final class DependencyGraph
{
    /**
     * @param array<int, Collection<Path>> $paths
     * @param array<int, Collection<BlueprintEmbed>> $embeds
     */
    public function __construct(
        public readonly array $paths,
        public readonly array $embeds,
    ) {}

    /**
     * Получить пути для blueprint'а из кеша.
     *
     * @param int $blueprintId
     * @return Collection<Path>|null
     */
    public function getPaths(int $blueprintId): ?Collection
    {
        return $this->paths[$blueprintId] ?? null;
    }

    /**
     * Получить embeds для blueprint'а из кеша.
     *
     * @param int $blueprintId
     * @return Collection<BlueprintEmbed>|null
     */
    public function getEmbeds(int $blueprintId): ?Collection
    {
        return $this->embeds[$blueprintId] ?? null;
    }
}

