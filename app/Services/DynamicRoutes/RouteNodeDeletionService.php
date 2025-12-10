<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes;

use App\Models\RouteNode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Сервис для управления удалением узлов маршрутов с учётом иерархии.
 *
 * Реализует каскадное удаление: при удалении родительского узла
 * рекурсивно удаляются все дочерние узлы.
 *
 * @package App\Services\DynamicRoutes
 */
class RouteNodeDeletionService
{
    /**
     * Рекурсивно удалить узел и всех его потомков.
     *
     * Выполняется в транзакции для атомарности.
     * Использует soft delete для всех узлов.
     *
     * @param RouteNode $node Узел для удаления
     * @return int Количество удалённых узлов (включая сам узел)
     */
    public function deleteWithChildren(RouteNode $node): int
    {
        $deletedCount = 0;

        DB::transaction(function () use ($node, &$deletedCount) {
            $deletedCount = $this->deleteRecursive($node);
        });

        Log::info('Route node deleted with children', [
            'route_node_id' => $node->id,
            'deleted_count' => $deletedCount,
        ]);

        return $deletedCount;
    }

    /**
     * Рекурсивно удалить узел и всех его потомков (внутренний метод).
     *
     * @param RouteNode $node Узел для удаления
     * @return int Количество удалённых узлов (включая сам узел)
     */
    private function deleteRecursive(RouteNode $node): int
    {
        $count = 0;

        // Сначала удаляем всех детей
        $children = RouteNode::query()
            ->where('parent_id', $node->id)
            ->get();

        foreach ($children as $child) {
            $count += $this->deleteRecursive($child);
        }

        // Затем удаляем сам узел
        $node->delete();
        $count++;

        return $count;
    }

    /**
     * Проверить, можно ли безопасно удалить узел.
     *
     * В текущей реализации всегда возвращает true, так как используется
     * каскадное удаление. Метод оставлен для возможных будущих проверок.
     *
     * @param RouteNode $node Узел для проверки
     * @return bool true, если узел можно удалить
     */
    public function canDelete(RouteNode $node): bool
    {
        return true;
    }
}

