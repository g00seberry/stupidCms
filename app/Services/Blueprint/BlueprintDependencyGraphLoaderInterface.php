<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Models\Blueprint;

/**
 * Интерфейс для загрузки графа зависимостей blueprint'ов.
 *
 * Предзагружает все paths и embeds транзитивно связанных blueprint'ов
 * одним набором запросов для оптимизации производительности.
 */
interface BlueprintDependencyGraphLoaderInterface
{
    /**
     * Загрузить весь граф зависимостей для корневого blueprint'а.
     *
     * Выполняет BFS-обход графа и предзагружает все paths и embeds
     * для blueprint'ов до указанной максимальной глубины.
     *
     * @param Blueprint $rootBlueprint Корневой blueprint
     * @param int $maxDepth Максимальная глубина обхода
     * @return DependencyGraph Кеш графа зависимостей
     */
    public function load(Blueprint $rootBlueprint, int $maxDepth): DependencyGraph;
}

