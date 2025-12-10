<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\RouteNode;
use App\Services\DynamicRoutes\DynamicRouteCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Репозиторий для работы с узлами маршрутов (RouteNode).
 *
 * Предоставляет методы для загрузки дерева маршрутов с оптимизацией запросов.
 * Использует кэширование через DynamicRouteCache.
 *
 * @package App\Repositories
 */
class RouteNodeRepository
{
    /**
     * @param \App\Services\DynamicRoutes\DynamicRouteCache $cache Сервис кэширования
     */
    public function __construct(
        private DynamicRouteCache $cache,
    ) {}

    /**
     * Получить дерево маршрутов (корневые узлы с детьми).
     *
     * Загружает все корневые узлы (parent_id IS NULL) с рекурсивной загрузкой
     * всех дочерних узлов. Использует оптимизацию: загружает все узлы одним запросом
     * и собирает дерево в памяти для избежания N+1 проблем.
     * Результат кэшируется через DynamicRouteCache.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\RouteNode>
     */
    public function getTree(): Collection
    {
        return $this->cache->rememberTree(function () {
            return $this->loadTree();
        });
    }

    /**
     * Загрузить дерево маршрутов из БД (без кэша).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\RouteNode>
     */
    private function loadTree(): Collection
    {
        // Загружаем все узлы одним запросом
        $allNodes = RouteNode::query()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Собираем дерево в памяти
        $nodesById = $allNodes->keyBy('id');
        $roots = new Collection();

        foreach ($allNodes as $node) {
            if ($node->parent_id === null) {
                $roots->push($node);
            } else {
                $parent = $nodesById->get($node->parent_id);
                if ($parent) {
                    // Инициализируем коллекцию children, если её ещё нет
                    if (!$parent->relationLoaded('children')) {
                        $parent->setRelation('children', new Collection());
                    }
                    $parent->children->push($node);
                }
            }
        }

        return $roots;
    }

    /**
     * Получить дерево только включённых маршрутов.
     *
     * Аналогично getTree(), но фильтрует только узлы с enabled = true.
     * Результат кэшируется через DynamicRouteCache.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\RouteNode>
     */
    public function getEnabledTree(): Collection
    {
        return $this->cache->rememberTree(function () {
            return $this->loadEnabledTree();
        });
    }

    /**
     * Загрузить дерево включённых маршрутов из БД (без кэша).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\RouteNode>
     */
    private function loadEnabledTree(): Collection
    {
        // Загружаем все включённые узлы одним запросом
        $allNodes = RouteNode::query()
            ->where('enabled', true)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Собираем дерево в памяти
        $nodesById = $allNodes->keyBy('id');
        $roots = new Collection();

        foreach ($allNodes as $node) {
            if ($node->parent_id === null) {
                $roots->push($node);
            } else {
                $parent = $nodesById->get($node->parent_id);
                if ($parent) {
                    // Инициализируем коллекцию children, если её ещё нет
                    if (!$parent->relationLoaded('children')) {
                        $parent->setRelation('children', new Collection());
                    }
                    $parent->children->push($node);
                }
            }
        }

        return $roots;
    }

    /**
     * Получить узел с предками (родителями до корня).
     *
     * Загружает узел и всех его предков (parent, parent->parent, ...) до корня.
     *
     * @param int $id ID узла
     * @return \App\Models\RouteNode|null Узел с загруженными предками или null, если не найден
     */
    public function getNodeWithAncestors(int $id): ?RouteNode
    {
        $node = RouteNode::find($id);
        if (!$node) {
            return null;
        }

        // Загружаем всех предков одним запросом
        $ancestors = [];
        $currentId = $node->parent_id;

        while ($currentId !== null) {
            $parent = RouteNode::find($currentId);
            if (!$parent) {
                break;
            }
            $ancestors[] = $parent;
            $currentId = $parent->parent_id;
        }

        // Устанавливаем связь parent рекурсивно
        $previous = $node;
        foreach ($ancestors as $ancestor) {
            $previous->setRelation('parent', $ancestor);
            $previous = $ancestor;
        }

        return $node;
    }
}

