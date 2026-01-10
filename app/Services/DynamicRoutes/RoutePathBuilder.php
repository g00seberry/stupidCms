<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes;

use App\Enums\RouteNodeKind;
use App\Models\RouteNode;

/**
 * Сервис для построения полного пути маршрута с учетом иерархии родительских групп.
 *
 * Собирает префиксы всех родительских групп от корня до маршрута
 * и объединяет их с URI маршрута в полный путь.
 *
 * @package App\Services\DynamicRoutes
 */
class RoutePathBuilder
{
    /**
     * Построить полный путь маршрута с учетом всех родительских групп.
     *
     * Рекурсивно собирает префиксы всех родительских групп от корня до маршрута.
     * Формат результата: `prefix1/prefix2/uri` (без ведущего слэша).
     *
     * Пример:
     * - Группа: prefix = "api/v1", parent_id = null
     * - Группа: prefix = "admin", parent_id = 1
     * - Маршрут: uri = "users", parent_id = 2
     * - Результат: "api/v1/admin/users"
     *
     * @param \App\Models\RouteNode $routeNode Узел маршрута
     * @return string Полный путь маршрута (без ведущего слэша)
     */
    public function buildFullPath(RouteNode $routeNode): string
    {
        // Собираем префиксы родительских групп (от родителя к корню)
        $prefixParts = [];
        $parent = $routeNode->parent;
        
        while ($parent !== null) {
            $prefix = trim($parent->prefix ?? '', '/');
            if ($prefix !== '') {
                $prefixParts[] = $prefix;
            }
            $parent = $parent->parent;
        }
        
        // Разворачиваем массив, чтобы получить порядок от корня к родителю
        $prefixParts = array_reverse($prefixParts);
        
        // Определяем часть пути для самого узла
        $nodePart = '';
        if ($routeNode->kind === RouteNodeKind::ROUTE) {
            $nodePart = trim($routeNode->uri ?? '', '/');
        } elseif ($routeNode->kind === RouteNodeKind::GROUP) {
            $nodePart = trim($routeNode->prefix ?? '', '/');
        }
        // Объединяем все части и фильтруем пустые
        $allParts = array_filter(
            array_merge($prefixParts, $nodePart !== '' ? [$nodePart] : []),
            fn(string $part): bool => $part !== ''
        );
        
        return implode('/', $allParts);
    }

}

