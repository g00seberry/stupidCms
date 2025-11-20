<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Models\BlueprintEmbed;
use Illuminate\Support\Collection;

/**
 * Сервис для работы с графом зависимостей blueprint'ов.
 *
 * Граф зависимостей: B → A означает, что B встраивает A.
 * Один blueprint может быть встроен в другой несколько раз (под разными host_path).
 */
class DependencyGraphService
{
    /**
     * Проверить, существует ли путь от fromId к targetId в графе зависимостей.
     *
     * Использует BFS (поиск в ширину) для обхода графа.
     * Граф строится по уникальным парам (blueprint_id, embedded_blueprint_id).
     *
     * @param int $fromId ID blueprint, от которого ищем путь
     * @param int $targetId ID blueprint, к которому ищем путь
     * @return bool true, если путь существует
     */
    public function hasPathTo(int $fromId, int $targetId): bool
    {
        if ($fromId === $targetId) {
            return true;
        }

        $visited = [];
        $queue = [$fromId];

        while (count($queue) > 0) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            if ($current === $targetId) {
                return true;
            }

            // Получить все blueprint'ы, которые current встраивает
            $children = $this->getDirectDependencies($current);

            foreach ($children as $childId) {
                if (!isset($visited[$childId])) {
                    $queue[] = $childId;
                }
            }
        }

        return false;
    }

    /**
     * Получить все blueprint'ы, которые прямо зависят от указанного.
     *
     * B зависит от A = B встраивает A.
     *
     * @param int $blueprintId ID blueprint
     * @return array<int> Массив ID зависимых blueprint'ов
     */
    public function getDirectDependencies(int $blueprintId): array
    {
        return BlueprintEmbed::query()
            ->where('blueprint_id', $blueprintId)
            ->pluck('embedded_blueprint_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Получить все blueprint'ы, в которые встроен указанный blueprint.
     *
     * B зависит от A = B встраивает A. Метод возвращает все B для данного A.
     *
     * @param int $blueprintId ID blueprint
     * @return array<int> Массив ID blueprint'ов, которые встраивают данный
     */
    public function getDirectDependents(int $blueprintId): array
    {
        return BlueprintEmbed::query()
            ->where('embedded_blueprint_id', $blueprintId)
            ->pluck('blueprint_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Получить все blueprint'ы, которые транзитивно зависят от указанного.
     *
     * Если A встроен в B, а B встроен в C, то C транзитивно зависит от A.
     * Метод возвращает все C для данного A.
     *
     * @param int $rootBlueprintId ID blueprint
     * @return Collection<int, int> Collection ID blueprint'ов
     */
    public function getAllDependentBlueprintIds(int $rootBlueprintId): Collection
    {
        $result = collect();
        $visited = [];
        $queue = [$rootBlueprintId];

        while (count($queue) > 0) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            // Кто встраивает текущий blueprint (прямые зависимые)
            $parents = $this->getDirectDependents($current);

            foreach ($parents as $parentId) {
                if (!isset($visited[$parentId])) {
                    $result->push($parentId);
                    $queue[] = $parentId;
                }
            }
        }

        return $result->unique()->values();
    }

    /**
     * Получить все blueprint'ы, от которых транзитивно зависит указанный.
     *
     * Если B встраивает A, а A встраивает C, то B зависит от C транзитивно.
     * Метод возвращает все C для данного B.
     *
     * @param int $blueprintId ID blueprint
     * @return Collection<int, int> Collection ID blueprint'ов
     */
    public function getAllTransitiveDependencies(int $blueprintId): Collection
    {
        $result = collect();
        $visited = [];
        $queue = [$blueprintId];

        while (count($queue) > 0) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            // Кого встраивает текущий blueprint
            $children = $this->getDirectDependencies($current);

            foreach ($children as $childId) {
                if (!isset($visited[$childId])) {
                    $result->push($childId);
                    $queue[] = $childId;
                }
            }
        }

        return $result->unique()->values();
    }
}

